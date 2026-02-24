<?php
/**
 * _project-dump.php Version 2.11.0
 *
 * Script de modo dual (Navegador y CLI) que genera un volcado del proyecto.
 *
 * =============================================================================
 * LÓGICA DE DUMP (ARQUITECTURA V10)
 * =============================================================================
 *
 * 1. PERFIL 'default' (Inteligente):
 * - DUMPEA CONTENIDO de:
 * - 'package.json', 'composer.json' y sus *.lock (Feature 5).
 * - 'main.js', 'boot.js', '*.md', etc. (Puntos de entrada).
 * - Archivos de Interfaz (ej. 'interface-*.js') (Feature 1).
 * - Archivos de Metadata (manifest.json, styles.css, etc.)
 * - OMITE CONTENIDO de:
 * - Archivos de Implementación (ej. 'app-state.js') si existe una interfaz.
 * - ÁRBOL:
 * - Muestra el árbol completo.
 * - Añade '(se omite implementación)' en directorios con interfaces (Feature 1).
 * - Muestra un resumen ("Nivel 1 + Scopes") para 'vendor' y 'node_modules' (Feature 2).
 *
 * 2. MODO "SOLO INCLUSIÓN" (ej. ?include=vendor o ?include=app-state):
 * - DUMPEA CONTENIDO de:
 * - *Solamente* los archivos dentro de 'vendor' o 'app-state'.
 * - ÁRBOL:
 * - Muestra un ÁRBOL PARCIAL podado, mostrando *solo* la ruta a 'vendor'/'app-state'.
 * - NO MUESTRA RUIDO (archivos 'default', etc.).
 *
 * 3. MODO "COMPACTO" (Opcional: ?compact=true):
 * - Aplica optimizaciones agresivas de tokens (Feature 1 & 2):
 * - Elimina comentarios (preservando /** DocBlocks * /).
 * - Elimina 'console.log', 'debugger;', etc.
 *
 * 4. OPTIMIZACIONES (Siempre Activas):
 * - Resúmenes de Binarios: [binary file (image/png, 24KB)] (Feature 4).
 * - Separador Limpio: '---' en lugar de 10 líneas en blanco (Feature 5d).
 * - Elimina '// PATH:' duplicados del contenido (Feature 5e - AHORA CORREGIDO).
 *
 * =============================================================================
 */

// ===== CONFIGURACIÓN =====

ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');
error_reporting(E_ALL & ~E_NOTICE); // Ocultar notices

// (NUEVO V10) Patrones de archivo de interfaz.
// Si se encuentra, solo se dumpeará este archivo en el perfil 'default'.
$interfaceFilePatterns = [
    'interface-*.js',
    '*.contract.js',
    'I*.js' // Ejemplo para IEventBus.js
];

// (NUEVO V10) Directorios que se listarán parcialmente en el 'default' tree.
$partiallyListedDirs = [
    'node_modules',
    'vendor'
];

// Lista de perfiles base
$profiles = [
    'default' => 'Default (Inteligente)',
    'all' => 'DUMP COMPLETO'
];

// Lista de elementos a IGNORAR
$ignoreList = [
    '.*',
    pathinfo(__FILE__, PATHINFO_FILENAME) . '*.*', // Corregido de V10
    '*.dump',
    '*.nodump',
    '#^_project-dump.*\.#',
    '#\.(log|tmp|temp)$#i',
    '#^\d{4}-\d{2}-\d{2}_#',
    '#^_#', // Ignora archivos/directorios que empiezan con _
	'#^temp$#', // Solo ignora la carpeta "temp" exacta, no "templates"
    '.git', '.svn', '.idea', 'dist', 'build',
    '.env', '.env.example', '.gitignore', '.gitattributes',
    'logs', 'cache', 'tmp'
];

// Extensiones binarias
$binaryExtensions = [
    'png', 'jpg', 'jpeg', 'gif', 'bmp', 'tiff', 'tif', 'webp', 'svg', 'ico', 'psd', 'ai',
    'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv', 'm4v', 'mpg', 'mpeg',
    'mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a', 'wma',
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp',
    'zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz', 'tgz',
    'exe', 'dll', 'so', 'dylib', 'bin', 'app', 'apk', 'ipa',
    'db', 'sqlite', 'mdb', 'accdb', 'iso', 'img', 'toast', 'vcd',
    'ttf', 'otf', 'woff', 'woff2', 'eot',
    'applescript', 'scpt', 'webloc', 'command', 'workflow'
];


// ===== GLOBALES DE SEGUIMIENTO =====
global $omittedByIgnoreList; // Ítems omitidos por $ignoreList (ej. .git)
global $omittedImplementationDirs; // Dirs omitidos por lógica de Interfaz (ej. app-state)
$omittedByIgnoreList = [];
$omittedImplementationDirs = [];

$forceIncludeList = []; // Ítems a incluir forzosamente
$activeProfiles = [];   // Perfiles activos
$isCompactMode = false; // (NUEVO V10) Modo de ahorro de tokens


// ===== FUNCIONES HELPER (Definidas primero) =====

function customSort($a, $b) {
    $aUnderscoreCount = 0;
    for ($i = 0; $i < strlen($a); $i++) {
        if ($a[$i] === '_') $aUnderscoreCount++; else break;
    }
    $bUnderscoreCount = 0;
    for ($i = 0; $i < strlen($b); $i++) {
        if ($b[$i] === '_') $bUnderscoreCount++; else break;
    }
    if ($aUnderscoreCount > 0 && $bUnderscoreCount > 0) {
        if ($aUnderscoreCount !== $bUnderscoreCount) {
            return $bUnderscoreCount - $aUnderscoreCount;
        }
    }
    if ($aUnderscoreCount > 0 && $bUnderscoreCount === 0) return -1;
    if ($aUnderscoreCount === 0 && $bUnderscoreCount > 0) return 1;
    return strnatcasecmp($a, $b);
}

function matchesPattern($item, $pattern) {
    $delimiters = ['/', '#', '~', '!', '%', '&'];
    if (strlen($pattern) > 2 && in_array($pattern[0], $delimiters) && $pattern[0] === substr($pattern, -1)) {
        $delimiter = $pattern[0];
        $regex = substr($pattern, 1, -1);
        return preg_match($delimiter . $regex . $delimiter, $item);
    }
    $regexPattern = str_replace(['\*', '\?', '\.'], ['.*', '.', '\.'], preg_quote($pattern, '/'));
    return preg_match('/^' . $regexPattern . '$/i', $item);
}

/**
 * Comprueba si un ítem debe ser ignorado por $ignoreList.
 */
function shouldIgnore($item, $fullPath, $ignoreList, $forceIncludeList = []) {
    global $omittedByIgnoreList;
    
    if (in_array($item, $forceIncludeList)) {
        return false;
    }
    
    if (!is_array($ignoreList)) return false; 
    foreach ($ignoreList as $pattern) {
        if (matchesPattern($item, $pattern)) {
            $omittedByIgnoreList[$item] = is_dir($fullPath) ? 'dir' : 'file';
            return true;
        }
        if (is_dir($fullPath) && strpos($pattern, '*') === false && !in_array($pattern[0], ['/', '#', '~', '!', '%', '&'])) {
            if (strpos($item, $pattern) !== false) {
                $omittedByIgnoreList[$item] = 'dir';
                return true;
            }
        }
    }
    return false;
}

/**
 * (NUEVO V10.4) Comprueba si un path es relevante para la recursión/dibujado.
 * Esta es la corrección del bug V9.
 */
function isPathRelevant($normalizedPath, $activeProfiles, $forceIncludeList) {
    // 1. Si 'default' o 'all' están activos, todo es relevante (sin poda).
    if (in_array('default', $activeProfiles) || in_array('all', $activeProfiles)) {
        return true;
    }

    // 2. Construir lista de objetivos (de perfiles y de inclusiones)
    $allTargets = [];
    
    foreach ($forceIncludeList as $item) {
        $allTargets[] = $item; // ej. 'vendor', 'app-state'
    }

    if (empty($allTargets)) {
        return false; // Sin perfiles, sin inclusiones = no mostrar nada.
    }

    // 3. Comprobar relevancia
    foreach ($allTargets as $target) {
        // './engine/vendor' contiene 'vendor' -> TRUE
        if (strpos($normalizedPath, $target) !== false) {
            return true;
        }
        // 'vendor' está contenido en './engine' (si el path es un dir) -> TRUE
        if (is_dir($normalizedPath) && $normalizedPath !== './') {
            return true;
        }
    }
    
    // Si estamos en raíz, siempre permitir (para que entre a ./app y ./engine)
    if ($normalizedPath === './') return true;

    return false;
}


/**
 * (NUEVO V10) Comprueba si un path está forzado a incluirse.
 */
function isPathForced($normalizedPath, $forceIncludeList = []) {
    foreach ($forceIncludeList as $item) {
        // ej. ./engine/vendor contiene 'vendor'
        if (strpos($normalizedPath, $item) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Generador de árbol recursivo con lógica V10.3
 */
function generateTree($dir, $prefix, $ignoreList, $activeProfiles, $forceIncludeList, $partiallyListedDirs, $interfaceFilePatterns, $prune) {
    global $omittedImplementationDirs;
    
    $items = @scandir($dir);
    if ($items === false) {
        echo $prefix . "└── [Error: No se puede leer el directorio]\n";
        return;
    }

    // 1. Filtrar por $ignoreList
    $filteredItems = array_diff($items, ['.', '..']);
    $filteredItems = array_filter($filteredItems, function($item) use ($dir, $ignoreList, $forceIncludeList) {
        $fullPath = $dir . DIRECTORY_SEPARATOR . $item;
        return !shouldIgnore($item, $fullPath, $ignoreList, $forceIncludeList);
    });
    
    // 2. (NUEVO V10) Lógica de Poda (Pruning) para dumps parciales
    if ($prune) {
        $filteredItems = array_filter($filteredItems, function($item) use ($dir, $activeProfiles, $forceIncludeList) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            $normalizedPath = str_replace(DIRECTORY_SEPARATOR, '/', $path);
            return isPathRelevant($normalizedPath, $activeProfiles, $forceIncludeList);
        });
    }

    // 3. (REESCRITO V10.3) Lógica de Interfaz y Listado Parcial (Solo para 'default')
    $isDefaultProfile = in_array('default', $activeProfiles);
    $implementationFiles = [];
    $interfaceFiles = [];
    $otherFiles = []; // Archivos de metadata (manifest, css, etc.)
    $dirs = [];
    $hasInterfaceInDir = false; // Flag para saber si este directorio tiene una interfaz

    if ($isDefaultProfile) {
        // 3a. Primera pasada: Detectar si hay *alguna* interfaz
        foreach ($filteredItems as $item) {
            if (is_file($dir . DIRECTORY_SEPARATOR . $item)) {
                foreach ($interfaceFilePatterns as $pattern) {
                    if (matchesPattern($item, $pattern)) {
                        $hasInterfaceInDir = true;
                        break 2;
                    }
                }
            }
        }
        
        // 3b. Segunda pasada: Clasificar archivos
        foreach ($filteredItems as $item) {
            $itemPath = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($itemPath)) {
                $dirs[] = $item; // Guardar directorios
                continue;
            }
            
            // Es un archivo
            $isInterface = false;
            foreach ($interfaceFilePatterns as $pattern) {
                if (matchesPattern($item, $pattern)) {
                    $isInterface = true;
                    break;
                }
            }
            
            // (NUEVO V10.2) Lista de archivos que NUNCA son "implementación"
            $isMetadata = in_array(basename($item), ['manifest.json', 'package.json', 'composer.json', 'README.md', 'package-lock.json', 'composer.lock']) ||
                          in_array(pathinfo($item, PATHINFO_EXTENSION), ['md', 'css', 'html', 'json', 'php', 'htm']);
            
            if ($isInterface) {
                $interfaceFiles[] = $item;
            } elseif ($isMetadata) {
                $otherFiles[] = $item;
            } else {
                $implementationFiles[] = $item; // Solo archivos (ej: .js) que no son interfaz ni metadata
            }
        }
        
        // Si encontramos interfaces, *solo* mostramos interfaces y metadata
        if ($hasInterfaceInDir) {
            $dirBasename = basename($dir);
            $omittedImplementationDirs[$dirBasename] = 'dir'; // Añadir al modal
            // Reconstruir $filteredItems: directorios + interfaces + metadata
            $filteredItems = array_merge($dirs, $interfaceFiles, $otherFiles);
        }
        // Si $hasInterfaceInDir es falso, $filteredItems no se toca (se muestra todo)
    }
    
    usort($filteredItems, 'customSort');
    $count = count($filteredItems);
    $i = 0;

    // 4. Dibujar Árbol
    foreach ($filteredItems as $item) {
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        // (CORREGIDO V10.2) Comprobar si es el último *visual*
        $isLast = (++$i === $count) && (empty($implementationFiles) || !$isDefaultProfile || !$hasInterfaceInDir);
        
        echo $prefix . ($isLast ? '└── ' : '├── ') . $item . "\n";
        
        if (is_dir($path)) {
            // (NUEVO V10) Comprobar si es un dir de listado parcial
            $isPartialDir = $isDefaultProfile && in_array($item, $partiallyListedDirs);
            
            if ($isPartialDir) {
                // Lógica "Nivel 1 + Scopes" (Opción 3)
                $subItems = @scandir($path);
                if ($subItems) {
                    $subItems = array_diff($subItems, ['.', '..']);
                    usort($subItems, 'customSort');
                    $subPrefix = $prefix . ($isLast ? '    ' : '│   ');
                    
                    foreach ($subItems as $subItem) {
                        if (strpos($subItem, '@') === 0) { // Es un Scope (ej. @babel)
                            echo $subPrefix . '├── ' . $subItem . "/\n";
                            $scopeItems = @scandir($path . DIRECTORY_SEPARATOR . $subItem);
                            if ($scopeItems) {
                                $scopeItems = array_diff($scopeItems, ['.', '..']);
                                usort($scopeItems, 'customSort');
                                $scopePrefix = $subPrefix . '│   ';
                                foreach ($scopeItems as $scopeItem) {
                                    echo $scopePrefix . '├── ' . $scopeItem . "/\n";
                                }
                            }
                        } else {
                            echo $subPrefix . '├── ' . $subItem . "/\n";
                        }
                    }
                    echo $subPrefix . "└── (se omite el resto del contenido)\n";
                }
            } else {
                // Recursión normal
                generateTree($path, $prefix . ($isLast ? '    ' : '│   '), $ignoreList, $activeProfiles, $forceIncludeList, $partiallyListedDirs, $interfaceFilePatterns, $prune);
            }
        }
    }
    

    if ($isDefaultProfile && $hasInterfaceInDir && !empty($implementationFiles)) {
        $omittedNames = implode(', ', $implementationFiles);
        echo $prefix . "    └── (se omite implementación: " . $omittedNames . ")\n";
    }
}


/**
 * (REESCRITO V10.5 - CORRIGE BUG 'vendor')
 * Procesa el contenido de los archivos con la nueva lógica.
 */
function processFiles($dir, $ignoreList, $binaryExtensions, $activeProfiles, $forceIncludeList, $isCompactMode) {
    $items = @scandir($dir);
    if ($items === false) return;
    
    $filteredItems = array_diff($items, ['.', '..']);
    
    // 1. Filtrar por $ignoreList
    $filteredItems = array_filter($filteredItems, function($item) use ($dir, $ignoreList, $forceIncludeList) {
        $fullPath = $dir . DIRECTORY_SEPARATOR . $item;
        return !shouldIgnore($item, $fullPath, $ignoreList, $forceIncludeList);
    });
        
    usort($filteredItems, 'customSort');
    
    foreach ($filteredItems as $item) {
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        $normalizedPath = str_replace(DIRECTORY_SEPARATOR, '/', $path);
        
        // (NUEVO V10.4) Lógica de PODA (Pruning)
        if (!isPathRelevant($normalizedPath, $activeProfiles, $forceIncludeList)) {
            continue;
        }

        if (is_dir($path)) {
            // ===== INICIO FIX V10.5 (BUG VENDOR) =====
            $isDefaultProfile = in_array('default', $activeProfiles);
            // Usar GLOBALS['partiallyListedDirs'] para acceder a la config
            $isPartialDir = $isDefaultProfile && in_array($item, $GLOBALS['partiallyListedDirs']);

            if ($isPartialDir) {
                continue; // ¡NO RECURSAR EN VENDOR/NODE_MODULES EN MODO DEFAULT!
            }
            // ===== FIN FIX V10.5 =====

            processFiles($path, $ignoreList, $binaryExtensions, $activeProfiles, $forceIncludeList, $isCompactMode);
        } else {
            displayFileContent($path, $binaryExtensions, $activeProfiles, $forceIncludeList, $isCompactMode);
        }
    }
}

/**
 * Comprueba si un path cumple con la lógica de UN perfil específico.
 */
function isContentAllowedByProfile($filepath, $dumpProfile, $interfaceFilePatterns) {
    if (!is_string($dumpProfile)) return false; 
    $filepath = str_replace(DIRECTORY_SEPARATOR, '/', $filepath);
    $filename = basename($filepath);
    $dirname = dirname($filepath);

    switch ($dumpProfile) {
        case 'all':
            return true;
        
        case 'default':
        default:
            // Lógica V10: ¿Es un archivo de interfaz?
            foreach ($interfaceFilePatterns as $pattern) {
                if (matchesPattern($filename, $pattern)) return true;
            }
            
            // (NUEVO V10.2) ¿Es metadata, un punto de entrada o un archivo de dependencia?
            $isMetadata = in_array(basename($filename), ['manifest.json', 'package.json', 'composer.json', 'README.md', 'package-lock.json', 'composer.lock']) ||
                          in_array(pathinfo($filename, PATHINFO_EXTENSION), ['md', 'css', 'html', 'json', 'php', 'htm']);
            
            if ($isMetadata) return true;

            // ¿Es un punto de entrada? (Redundante con lo de arriba, pero seguro)
            if ($filepath === './app/main/main.js') return true;
            if ($filepath === './engine/kernel/boot.js') return true;
            if ($filepath === './workspace/app-config.json') return true;

            // (NUEVO V10) ¿Está en un dir *sin* interfaces?
            $hasInterface = false;
            $files = @glob($dirname . DIRECTORY_SEPARATOR . '*'); // Usar glob
            if ($files) {
                foreach ($files as $file) {
                    foreach ($interfaceFilePatterns as $pattern) {
                        if (matchesPattern(basename($file), $pattern)) {
                            $hasInterface = true;
                            break 2; // Salir de ambos bucles
                        }
                    }
                }
            }
            if (!$hasInterface) return true; // Si no hay interfaces, dumpear todo (ej. app/modules)
            
            return false; // Es un dir con interfaz, y este *no* es la interfaz (es implementación)
    }
}

/**
 * (REESCRITO V10)
 * Muestra el contenido del archivo con lógica V10 y modo compacto.
 */
function displayFileContent($filepath, $binaryExtensions, $activeProfiles, $forceIncludeList, $isCompactMode) {
    
    $normalizedPath = str_replace(DIRECTORY_SEPARATOR, '/', $filepath);

    // 1. Comprobar si este path está forzado a incluirse
    $isForced = isPathForced($normalizedPath, $forceIncludeList);
    
    // 2. Comprobar si este path está permitido por CUALQUIERA de los perfiles activos
    $isAllowedByProfile = false;
    foreach ($activeProfiles as $profile) {
        if (isContentAllowedByProfile($filepath, $profile, $GLOBALS['interfaceFilePatterns'])) {
            $isAllowedByProfile = true;
            break;
        }
    }

    // 3. Decidir si mostrar el archivo (Lógica de PODA V10)
    $isSoloInclusion = empty($activeProfiles) && !empty($forceIncludeList);
    $isDefaultActive = in_array('default', $activeProfiles);

    if ($isSoloInclusion && !$isForced) {
        return; // MODO "SOLO INCLUSIÓN": Podar si no está forzado
    }
    
    if (!$isDefaultActive && !$isSoloInclusion && !$isAllowedByProfile && !$isForced) {
        return; // Podar ruido (ej. no mostrar .md en modo 'all' si no lo pido)
    }

    // 4. Decidir si mostrar el *contenido*
    $showContent = false;
    $skipReason = "";
    
    if ($isSoloInclusion) {
        $showContent = true; // Si llegamos aquí, es $isForced
    } else {
        // MODO "PERFIL"
        if ($isAllowedByProfile || $isForced) {
            $showContent = true;
        } else {
            // Si llegamos aquí, es porque 'default' está activo y es un archivo de implementación
            $showContent = false;
            $skipReason = "// [Content skipped by 'default' profile (implementation file)]\n";
        }
    }
    
    // --- Imprimir Salida ---
    
    echo "// PATH: " . $filepath . "\n\n";
    $binaryCheck = isTextFile($filepath, $binaryExtensions);
    
    if ($binaryCheck !== true) { // (MODIFICADO V10)
        echo $binaryCheck . "\n"; // Imprime el resumen binario
    } else {
        if ($showContent) {
            $content = @file_get_contents($filepath);
            if ($content !== false) {
                // (NUEVO V10) Aplicar optimizaciones
                $content = applyOptimizations($content, $isCompactMode);
                echo $content;
            } else {
                echo "// Error: No se pudo leer el archivo.\n";
            }
        } else {
            echo $skipReason;
        }
    }
    
    // (NUEVO V10) Separador limpio (Feature 5d)
    echo "\n\n\n---\n\n\n";
}

/**
 * (NUEVO V10 / CORREGIDO V10.5 - CORRIGE BUGS COMPACT Y DE CONTENIDO)
 * Aplica optimizaciones de tokens al contenido del archivo.
 */
function applyOptimizations($content, $isCompactMode) {
    
    // (Feature 5c) Colapsar líneas en blanco excesivas
    $content = preg_replace("/\n{4,}/", "\n\n\n", $content);

    if ($isCompactMode) {
        // (Feature 5e) Eliminar // PATH: o /* PATH: */ duplicados del *contenido*
        // (V10.5) Movido aquí. Solo se ejecuta en modo compacto para no borrar
        // archivos (como color-list.js) que empiezan con ese comentario.
        $content = preg_replace('/^(\/\*|\/\/)\s*PATH:.*\s*(\*\/)?\s*(\r\n|\r|\n)/m', '', $content);

        // (CORREGIDO V10.4) Separar las dos regex de eliminación de comentarios.
        
        // 1. Eliminar /* ... */ (bloques no-docblock)
        // Se necesita 's' (dotall) y 'U' (non-greedy)
        $content = preg_replace_callback(
            '#(\/\*(?!\*).*?\*\/)#sU', // <-- Delimitador '#'
            function($matches) {
                return ''; // Siempre eliminar, ya que (?!\*) previene DocBlocks
            },
            $content
        );

        // 2. Eliminar // ... (comentarios de una línea)
        // Se necesita 'm' (multiline) pero NO 's' (dotall)
        $content = preg_replace_callback(
            '#(^\s*\/\/.*?$)#m', // <-- Delimitador '#'
            function($matches) {
                return '';
            },
            $content
        );

        // (Feature 2) Eliminar código de depuración
        $content = preg_replace('/^\s*(console\.(log|warn|error|info|debug)|debugger;).*;?\s*$/m', '', $content);
    }
    
    return trim($content);
}

/**
 * (MODIFICADO V10)
 * Devuelve 'true' o un string de resumen binario (Feature 4).
 */
function isTextFile($filename, $binaryExtensions) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (in_array($extension, $binaryExtensions)) {
        return getBinarySummary($filename);
    }
    
    if (PHP_OS_FAMILY === 'Darwin' && is_executable('/usr/bin/file')) {
        $output = []; $returnCode = 0;
        @exec("/usr/bin/file -b --mime-type " . escapeshellarg($filename), $output, $returnCode);
        if ($returnCode === 0 && !empty($output)) {
            $mime = trim($output[0]);
            $textMimes = ['text/', 'application/json', 'application/xml', 'application/x-httpd-php'];
            foreach ($textMimes as $textMime) {
                if (strpos($mime, $textMime) === 0) return true;
            }
            // Si 'file' dice que es binario, es binario
            return getBinarySummary($filename); 
        }
    }
    
    $isText = isFileContentTextual($filename);
    if (!$isText) {
        return getBinarySummary($filename); // Es binario por contenido
    }
    
    return true; // Es de texto
}

/**
 * (NUEVO V10) Genera el resumen de binarios (Feature 4)
 */
function getBinarySummary($filename) {
    $filesize = @filesize($filename);
    $mime = @mime_content_type($filename);
    
    $sizeStr = '';
    if ($filesize !== false) {
        if ($filesize > 1024 * 1024) {
            $sizeStr = round($filesize / 1024 / 1024, 1) . 'MB';
        } elseif ($filesize > 1024) {
            $sizeStr = round($filesize / 1024) . 'KB';
        } else {
            $sizeStr = $filesize . 'B';
        }
    }
    
    $mimeStr = ($mime && $mime !== 'application/octet-stream' && $mime !== 'inode/x-empty') ? "$mime, " : '';
    return "[binary file (" . $mimeStr . $sizeStr . ")]";
}

function isFileContentTextual($filename) {
    if (!file_exists($filename) || !is_readable($filename)) return false;
    $fileSize = filesize($filename);
    if ($fileSize === 0) return true;
    $file = @fopen($filename, 'rb');
    if (!$file) return false;
    $sampleSize = min($fileSize, 8192);
    $sample = fread($file, $sampleSize);
    fclose($file);
    $boms = ["\xEF\xBB\xBF", "\xFF\xFE", "\xFE\xFF", "\xFF\xFE\x00\x00", "\x00\x00\xFE\xFF"];
    foreach ($boms as $bom) {
        if (strpos($sample, $bom) === 0) return true;
    }
    return !preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $sample);
}

// ===== FIN FUNCIONES HELPER =====


// ===== LÓGICA PRINCIPAL (MODO DUAL) =====

// 1. Detectar modo (CLI o Web)
$isCli = (php_sapi_name() === 'cli');

// 2. Determinar perfiles, inclusiones y modo compacto
if ($isCli) {
    $cliArgs = $argv ?? [];
    foreach ($cliArgs as $arg) {
        if (strpos($arg, '--profiles=') === 0) {
            $profilesStr = substr($arg, 11);
            $activeProfiles = array_filter(explode(',', $profilesStr));
        }
        if (strpos($arg, '--include=') === 0) {
            $includeStr = substr($arg, 10);
            $forceIncludeList = array_filter(explode(',', $includeStr));
        }
        if ($arg === '--compact') { // (NUEVO V10)
            $isCompactMode = true;
        }
    }
} else {
    $profilesStr = $_GET['profiles'] ?? 'default'; // 'default' si no se especifica nada
    $activeProfiles = array_filter(explode(',', $profilesStr));
    $includeStr = $_GET['include'] ?? '';
    $forceIncludeList = array_filter(explode(',', $includeStr));
    if (isset($_GET['compact']) && $_GET['compact'] === 'true') { // (NUEVO V10)
        $isCompactMode = true;
    }
}

// 3. Validar perfiles (ahora solo 'default' y 'all')
$activeProfiles = array_intersect($activeProfiles, array_keys($profiles));
if (empty($activeProfiles) && !empty($_GET['profiles'])) {
     $activeProfiles = [];
} elseif (empty($activeProfiles) && empty($forceIncludeList) && !$isCli) {
    $activeProfiles = ['default'];
} elseif (empty($activeProfiles) && isset($_GET['profiles']) && $_GET['profiles'] === '') {
    $activeProfiles = [];
}

// 4. Establecer Header
if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
}

// 5. Iniciar captura de buffer
ob_start();

// ===== INICIO DUMP DE TEXTO PLANO =====
echo "// ********************\n";
echo "// PROJECT DUMP START \n";

// Mostrar resumen de lo que se está dumpeando
if (empty($activeProfiles) && !empty($forceIncludeList)) {
    echo "// MODO: Solo Inclusión (Items: " . htmlspecialchars(implode(', ', $forceIncludeList)) . ")\n";
} else {
    if (!empty($activeProfiles)) {
        echo "// Perfiles: " . htmlspecialchars(implode(', ', $activeProfiles)) . "\n";
    }
    if (!empty($forceIncludeList)) {
        echo "// Incluyendo además: " . htmlspecialchars(implode(', ', $forceIncludeList)) . "\n";
    }
}
if ($isCompactMode) {
    echo "// MODO COMPACTO ACTIVADO (Comentarios y logs eliminados)\n";
}
echo "// ********************\n\n\n";

// 1. (REESCRITO V10) Generar árbol de directorios
$isPartialMode = empty($activeProfiles) && !empty($forceIncludeList);
$isDefaultOrAll = in_array('default', $activeProfiles) || in_array('all', $activeProfiles);

if ($isDefaultOrAll) {
    echo "// ========== DIRECTORY TREE ==========\n\n";
    generateTree('.', '', $GLOBALS['ignoreList'], $activeProfiles, $forceIncludeList, $GLOBALS['partiallyListedDirs'], $GLOBALS['interfaceFilePatterns'], false); // prune = false
    echo "\n\n";
} elseif ($isPartialMode) {
    echo "// ========== PARTIAL DIRECTORY TREE ==========\n";
    echo "// Mostrando solo la estructura de los ítems seleccionados.\n\n";
    generateTree('.', '', $GLOBALS['ignoreList'], $activeProfiles, $forceIncludeList, $GLOBALS['partiallyListedDirs'], $GLOBALS['interfaceFilePatterns'], true); // prune = true
    echo "\n\n";
}

// 2. (REESCRITO V10) Añadir la "Nota para la IA"
if (in_array('default', $activeProfiles)) {
    echo "// =========================================================================\n";
    echo "// NOTA PARA LA IA:\n";
    echo "// Este dump (perfil 'default') omite intencionalmente el contenido\n";
    echo "// de las implementaciones (ej. 'app-state.js') cuando se encuentra\n";
    echo "// un archivo de Interfaz (ej. 'interface-app-state.js').\n";
    echo "//\n";
    echo "// El árbol de directorios SÍ está completo (con resúmenes para 'vendor').\n";
    echo "//\n";
    echo "// Si necesitas el contenido de un archivo de implementación omitido,\n";
    echo "// pídemelo usando el nombre del directorio (ej. 'app-state').\n";
    echo "//\n";
    echo "// ¡IMPORTANTE! Si necesitas un archivo y no lo tienes, PÍDEMELO.\n";
    echo "// Jamás adivines, supongas o inventes contenido.\n";
    echo "// =========================================================================\n\n";
}


// 3. Mostrar contenido de archivos
echo "// ========== FILE CONTENTS ==========\n\n";
processFiles('.', $GLOBALS['ignoreList'], $GLOBALS['binaryExtensions'], $activeProfiles, $forceIncludeList, $isCompactMode);

// Cabecera final
echo "\n\n// ********************\n";
echo "// PROJECT DUMP END\n";
echo "// ********************\n";
// ===== FIN DUMP DE TEXTO PLANO =====

// 6. Obtener el contenido del buffer
$dumpContent = ob_get_clean();

// (NUEVO V10) Combinar todas las listas de omisiones para el modal
$finalOmittedItems = array_merge($omittedByIgnoreList, $omittedImplementationDirs, array_flip($partiallyListedDirs));
ksort($finalOmittedItems);

// 7. Mostrar la salida final
if ($isCli) {
    // MODO CLI
    echo $dumpContent;
    if (!empty($finalOmittedItems)) {
        echo "\n\n// --- Items Omitidos / Parciales ---\n";
        echo "// (Use --include=" . implode(',', array_keys($finalOmittedItems)) . " para incluirlos)\n";
    }
} else {
    // MODO WEB
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Project Dump</title>
    <style>
        body { background: #1e1e1e; color: #d4d4d4; font-family: monospace; margin: 0; }
        .dump-ui { background: #2a2a2a; padding: 10px 20px; border-bottom: 1px solid #444; position: sticky; top: 0; z-index: 10; display: flex; flex-wrap: wrap; align-items: center; }
        .dump-ui label { font-weight: bold; margin-right: 15px; font-family: sans-serif; font-size: 14px; }
        .dump-ui a { font-family: sans-serif; text-decoration: none; color: #d4d4d4; background: #3c3c3c; padding: 8px 12px; border-radius: 4px; margin-right: 10px; margin-bottom: 5px; font-size: 14px; border: 1px solid #3c3c3c; }
        .dump-ui a:hover { background: #555; }
        .dump-ui a.active { background: #007acc; color: white; font-weight: bold; border-color: #007acc; }
        #copy-button { font-family: sans-serif; text-decoration: none; color: #d4d4d4; background: #3c3c3c; padding: 8px 12px; border-radius: 4px; margin-left: auto; margin-bottom: 5px; font-size: 14px; border: 1px solid #555; cursor: pointer; transition: background-color 0.2s, color 0.2s; }
        #copy-button:hover { background: #555; }
        #copy-button.success { background: #28a745; color: white; border-color: #28a745; }
        #copy-button.error { background: #dc3545; color: white; border-color: #dc3545; }
        pre#dump-content { padding: 20px; margin: 0; white-space: pre-wrap; word-wrap: break-word; user-select: text; }
        .omitted-ui { background: #252526; padding: 8px 20px; font-family: sans-serif; font-size: 13px; border-bottom: 1px solid #444; position: sticky; top: 59px; z-index: 9; color: #aaa; }
        .omitted-ui button#omitted-button { background: #3c3c3c; border: 1px solid #555; color: #d4d4d4; padding: 4px 8px; border-radius: 3px; cursor: pointer; font-size: 12px; margin-right: 10px; }
        .omitted-ui button#omitted-button:hover { background: #555; }
        .omitted-ui span { font-style: italic; }
        #omitted-modal { display: none; position: fixed; z-index: 100; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); font-family: sans-serif; }
        #omitted-modal .modal-content { background-color: #2a2a2a; color: #d4d4d4; margin: 10% auto; padding: 20px; border: 1px solid #444; width: 80%; max-width: 600px; border-radius: 5px; box-shadow: 0 4px 10px rgba(0,0,0,0.4); }
        #omitted-modal h3 { margin-top: 0; }
        #omitted-modal h4 { margin-top: 15px; margin-bottom: 5px; color: #aaa; border-bottom: 1px solid #444; padding-bottom: 5px; font-weight: normal; }
        #omitted-modal #modal-close { color: #aaa; float: right; font-size: 28px; font-weight: bold; line-height: 1; cursor: pointer; }
        #omitted-modal .checkbox-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; max-height: 150px; overflow-y: auto; background: #1e1e1e; padding: 10px; border-radius: 3px; border: 1px solid #3c3c3c; margin-bottom: 20px; }
        #omitted-modal .checkbox-grid label { display: block; font-size: 14px; cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        #omitted-modal .checkbox-grid input { margin-right: 8px; vertical-align: middle; }
        #omitted-modal button#modal-submit-button { background: #007acc; color: white; padding: 10px 15px; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; width: 100%; }
        #omitted-modal button#modal-submit-button:hover { background: #005a9e; }
        #compact-mode-toggle { margin-top: 15px; font-size: 14px; }
    </style>
</head>
<body>

<div class="dump-ui">
    <label>Perfiles Base:</label>
    <?php
    foreach ($profiles as $key => $label) {
        $class = in_array($key, $activeProfiles) ? 'active' : '';
        $scriptName = basename(__FILE__);
        echo "<a href=\"{$scriptName}?profiles={$key}\" class=\"{$class}\">{$label}</a>\n";
    }
    ?>
    <div style="margin-left: auto; display: flex; gap: 10px;">
        <button id="copy-button" title="Copiar al portapapeles">Copiar Dump 📋</button>
        <button id="download-button" style="font-family: sans-serif; color: #d4d4d4; background: #3c3c3c; padding: 8px 12px; border-radius: 4px; font-size: 14px; border: 1px solid #555; cursor: pointer;">Descargar .txt ⬇️</button>
    </div>
</div>

<?php if (!empty($finalOmittedItems)) : ?>
<div class="omitted-ui">
    <button id="omitted-button">[ <?php echo count($finalOmittedItems); ?> items omitidos / parciales ]</button>
    <span><?php echo htmlspecialchars(implode(', ', array_keys($finalOmittedItems))); ?></span>
</div>
<?php endif; ?>

<div id="omitted-modal">
    <div class="modal-content">
        <span id="modal-close">&times;</span>
        <h3>Construir Dump Personalizado</h3>
        <p>Selecciona perfiles y/o ítems. Sin perfiles = "Modo Solo Inclusión".</p>
        
        <form id="include-form">
            
            <h4>Perfiles Base</h4>
            <div id="profile-checkboxes" class="checkbox-grid">
                </div>

            <?php if (!empty($finalOmittedItems)) : ?>
            <h4>Items Omitidos / Parciales</h4>
            <div id="omitted-checkboxes" class="checkbox-grid">
                </div>
            <?php endif; ?>
            
            <label id="compact-mode-toggle">
                <input type="checkbox" id="compact-checkbox" <?php echo $isCompactMode ? 'checked' : ''; ?>>
                Modo Compacto (Ahorrar tokens: quitar comentarios y logs)
            </label>

            <button type="submit" id="modal-submit-button">Generar Dump</button>
        </form>
    </div>
</div>
<pre id="dump-content">
<?php
// Escapamos TODO el dump para que el HTML se muestre como texto
echo htmlspecialchars($dumpContent);
?>
</pre>

<script>
    // --- Lógica del botón de Copiar (Existente) ---
    document.getElementById('copy-button').addEventListener('click', function() {
        const content = document.getElementById('dump-content').textContent;
        const button = this;
        navigator.clipboard.writeText(content).then(function() {
            const originalText = button.textContent;
            button.textContent = '¡Copiado! 👍';
            button.classList.add('success');
            setTimeout(function() {
                button.textContent = originalText;
                button.classList.remove('success');
            }, 2000);
        }, function(err) {
            const originalText = button.textContent;
            button.textContent = 'Error al copiar';
            button.classList.add('error');
            console.error('Error al copiar al portapapeles: ', err);
            setTimeout(function() {
                button.textContent = originalText;
                button.classList.remove('error');
            }, 3000);
        });
    });

    // --- (REESCRITO V10) Lógica del Modal de Omisiones ---
    
    // 1. Obtener los datos de PHP
    const allProfiles = <?php echo json_encode($profiles); ?>;
    const omittedItems = <?php echo json_encode($finalOmittedItems); ?>;
    const currentlyActiveProfiles = <?php echo json_encode($activeProfiles); ?>;
    const currentlyIncluded = <?php echo json_encode($forceIncludeList); ?>;
    const isCompact = <?php echo json_encode($isCompactMode); ?>;
    const scriptName = '<?php echo basename(__FILE__); ?>';

    // 2. Referencias a elementos del DOM
    const modal = document.getElementById('omitted-modal');
    const openBtn = document.getElementById('omitted-button');
    const closeBtn = document.getElementById('modal-close');
    const profileCheckboxContainer = document.getElementById('profile-checkboxes');
    const omittedCheckboxContainer = document.getElementById('omitted-checkboxes');
    const compactCheckbox = document.getElementById('compact-checkbox');
    const form = document.getElementById('include-form');

    // 3. Poblar checkboxes de Perfiles (V10)
    if (profileCheckboxContainer) {
        for (const [key, label] of Object.entries(allProfiles)) {
            const isChecked = currentlyActiveProfiles.includes(key);
            const labelEl = document.createElement('label');
            labelEl.title = label;
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.name = 'profiles';
            checkbox.value = key;
            checkbox.checked = isChecked;
            labelEl.appendChild(checkbox);
            labelEl.appendChild(document.createTextNode(` ${label}`));
            profileCheckboxContainer.appendChild(labelEl);
        }
    }
    
    // 4. Poblar checkboxes de Omitidos (V10)
    if (omittedCheckboxContainer) {
        const sortedKeys = Object.keys(omittedItems).sort();
        sortedKeys.forEach(item => {
            let type = omittedItems[item] || 'item';
            const isChecked = currentlyIncluded.includes(item);
            
            const labelEl = document.createElement('label');
            labelEl.title = type;
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.name = 'include';
            checkbox.value = item;
            checkbox.checked = isChecked;
            labelEl.appendChild(checkbox);
            labelEl.appendChild(document.createTextNode(` ${item}`));
            omittedCheckboxContainer.appendChild(labelEl);
        });
    }

    // 5. Event Listeners para abrir/cerrar modal
    if (openBtn) {
        openBtn.onclick = function() {
            modal.style.display = 'block';
        }
    }
    if (closeBtn) {
        closeBtn.onclick = function() {
            modal.style.display = 'none';
        }
    }
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }

    // 6. (REESCRITO V10) Event Listener para el envío del formulario
    if (form) {
        form.onsubmit = function(e) {
            e.preventDefault();
            
            // Recoger perfiles
            const checkedProfiles = profileCheckboxContainer.querySelectorAll('input[name="profiles"]:checked');
            const selectedProfiles = Array.from(checkedProfiles).map(cb => cb.value);
            
            // Recoger inclusiones
            let selectedIncludes = [];
            if (omittedCheckboxContainer) {
                const checkedIncludes = omittedCheckboxContainer.querySelectorAll('input[name="include"]:checked');
                selectedIncludes = Array.from(checkedIncludes).map(cb => cb.value);
            }
            
            // Recoger modo compacto
            const isCompactChecked = compactCheckbox.checked;
            
            // Construir la nueva URL
            let params = [];
            if (selectedProfiles.length > 0) {
                params.push('profiles=' + encodeURIComponent(selectedProfiles.join(',')));
            } else {
                params.push('profiles='); // Activar modo "Solo Inclusión"
            }
            
            if (selectedIncludes.length > 0) {
                params.push('include=' + encodeURIComponent(selectedIncludes.join(',')));
            }
            
            if (isCompactChecked) {
                params.push('compact=true');
            }
            
            const newUrl = `${scriptName}?${params.join('&')}`;
            window.location.href = newUrl;
        }
    }


// --- Lógica del botón de Descargar (V3.500) ---
    document.getElementById('download-button').addEventListener('click', function() {
        const content = document.getElementById('dump-content').textContent;
        
        // 1. Obtener el nombre del directorio actual (procedente de la URL o el path del script)
        // Usamos una técnica simple para obtener el nombre de la carpeta "padre"
        const dirName = window.location.pathname.split('/').slice(-2, -1)[0] || 'project';
        
        // 2. Generar fecha local: YYYY-MM-DD--HH-mm-ss
        const now = new Date();
        const pad = (n) => n.toString().padStart(2, '0');
        const timestamp = now.getFullYear() + '-' + 
                          pad(now.getMonth() + 1) + '-' + 
                          pad(now.getDate()) + '--' + 
                          pad(now.getHours()) + '-' + 
                          pad(now.getMinutes()) + '-' + 
                          pad(now.getSeconds());

        // 3. Construir nombre: _[DIR]-[FECHA].dump
        const fileName = `_${dirName}-${timestamp}.dump`;

        const blob = new Blob([content], { type: 'text/plain' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        
        a.href = url;
        a.download = fileName;
        document.body.appendChild(a);
        a.click();
        
        setTimeout(() => {
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        }, 100);
    });


</script>

</body>
</html>
<?php
} // Fin del bloque else (isCli)

?>