<?php
/**
 * _project-dump.php
 * 
 * Script que genera un volcado completo del árbol de directorios y el contenido
 * de todos los archivos de texto en el directorio actual y sus subdirectorios.
 */

/*
Uso recomendado desde la línea de comandos:
php _project-dump.php > _project-dumped.txt

El archivo txt contendrá toda la información lista para copiar y pegar en tu prompt de IA.

Si el nombre del archivo generado empieza igual que el del PHP, será correctamente ignorado dentro del listado de archivos del dump. Si se prefiere usar otro nombre, ponerle la extensión ".dump" o ".nodump" para que sea ignorado:

php _project-dump.php > proyecto.dump


Este PHP también puede ser visto en el navegador.
Funcionará correctamente para la mayoría de proyectos de tamaño medio.
En general, es preferible el uso desde la línea de comandos.


Para modificar los archivos a ignorar, editar $ignoreList.
Admite el wildcard * , y también expresiones regulares.

PATTERN MATCHING SYNTAX:
- Wildcard * 
  '*.log'       - todos los archivos .log
  'test*.*'     - archivos que empiezan con 'test'
  '.*'          - todos los archivos ocultos

- Regex entre / /:
  '/^test-\d+\.log$/'     - test-1.log, test-42.log
  '/\.tmp$/i'             - cualquier archivo .tmp (case-insensitive)
  '/^backup-202\d-/'      - backups del año 2020-2029
  '/^[a-z0-9]{8}\./'      - archivos con 8 chars alfanuméricos

- Coincidencia parcial para directorios:
  'node_modules'          - ignora cualquier directorio que contenga 'node_modules'
*/

// Configuración
ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');
header('Content-Type: text/plain; charset=utf-8');

// ===== CABECERA INICIAL =====
echo "// ********************\n";
echo "// PROJECT DUMP START\n";
echo "// ********************\n\n\n";
// ===========================

// Lista de elementos a IGNORAR (con soporte para wildcards y regex)
$ignoreList = [
    // Wildcards simples
    '.*', // Todos los archivos ocultos
    pathinfo(__FILE__, PATHINFO_FILENAME) . '*.*', // Script y derivados
    
    // Ejemplos de regex (pueden personalizarse) - CORREGIDOS
    '#^_project-dump.*\.#', // regex equivalente al anterior
    '#\.(log|tmp|temp)$#i', // archivos temporales y logs
    '#^\d{4}-\d{2}-\d{2}_#', // archivos que empiezan con fecha YYYY-MM-DD_
    
    // Directorios y patrones comunes
    '.git', '.svn', '.idea', 'node_modules', 'vendor', 'dist', 'build',
    '.env', '.env.example', '.gitignore', '.gitattributes',
    'composer.lock', 'package-lock.json', 'yarn.lock',
    'logs', 'cache', 'tmp', 'temp', '.dump'
];

// Extensiones de archivos binarios conocidos (NO leer contenido)
$binaryExtensions = [
    // Imágenes
    'png', 'jpg', 'jpeg', 'gif', 'bmp', 'tiff', 'tif', 'webp', 'svg', 'ico', 'psd', 'ai',
    // Videos
    'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv', 'm4v', 'mpg', 'mpeg',
    // Audio
    'mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a', 'wma',
    // Documentos
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp',
    // Archivos comprimidos
    'zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz', 'tgz',
    // Ejecutables y librerías
    'exe', 'dll', 'so', 'dylib', 'bin', 'app', 'apk', 'ipa',
    // Bases de datos y binarios varios
    'db', 'sqlite', 'mdb', 'accdb', 'iso', 'img', 'toast', 'vcd',
    // Fuentes
    'ttf', 'otf', 'woff', 'woff2', 'eot',
    // macOS específicos
    'applescript', 'scpt', 'webloc', 'command', 'workflow'
];

// 1. Generar árbol de directorios
echo "// ========== DIRECTORY TREE ==========\n\n";
generateTree('.', '', $ignoreList);
echo "\n\n";

// 2. Mostrar contenido de archivos de texto
echo "// ========== FILE CONTENTS ==========\n\n";
processFiles('.', $ignoreList, $binaryExtensions);

// ========== FUNCIONES ==========

function customSort($a, $b) {
    // Contar guiones bajos al inicio de cada string
    $aUnderscoreCount = 0;
    $bUnderscoreCount = 0;
    
    // Contar guiones bajos consecutivos al inicio de $a
    for ($i = 0; $i < strlen($a); $i++) {
        if ($a[$i] === '_') {
            $aUnderscoreCount++;
        } else {
            break;
        }
    }
    
    // Contar guiones bajos consecutivos al inicio de $b
    for ($i = 0; $i < strlen($b); $i++) {
        if ($b[$i] === '_') {
            $bUnderscoreCount++;
        } else {
            break;
        }
    }
    
    // Si ambos empiezan con guiones bajos, el que tiene más guiones va primero
    if ($aUnderscoreCount > 0 && $bUnderscoreCount > 0) {
        if ($aUnderscoreCount !== $bUnderscoreCount) {
            return $bUnderscoreCount - $aUnderscoreCount; // Más guiones = primero
        }
    }
    
    // Si solo uno empieza con guiones bajos, va primero
    if ($aUnderscoreCount > 0 && $bUnderscoreCount === 0) {
        return -1; // a va primero
    }
    if ($aUnderscoreCount === 0 && $bUnderscoreCount > 0) {
        return 1; // b va primero
    }
    
    // Ambos tienen la misma cantidad de guiones bajos (o ninguno)
    // Ordenar alfabéticamente, case-insensitive
    return strnatcasecmp($a, $b);
}

// Función para verificar si un item coincide con un patrón (wildcard o regex)
function matchesPattern($item, $pattern) {
    // Si es un regex (empieza y termina con el mismo carácter delimitador)
    $delimiters = ['/', '#', '~', '!', '%', '&'];
    if (strlen($pattern) > 2 && in_array($pattern[0], $delimiters) && $pattern[0] === substr($pattern, -1)) {
        $delimiter = $pattern[0];
        $regex = substr($pattern, 1, -1);
        return preg_match($delimiter . $regex . $delimiter, $item);
    }
    
    // Si es un wildcard simple
    $regexPattern = str_replace(
        ['\*', '\?', '\.'], 
        ['.*', '.', '\.'], 
        preg_quote($pattern, '/')
    );
    
    return preg_match('/^' . $regexPattern . '$/i', $item);
}

function shouldIgnore($item, $fullPath, $ignoreList) {
    foreach ($ignoreList as $pattern) {
        if (matchesPattern($item, $pattern)) {
            return true;
        }
        
        // Para directorios, también verificar coincidencias parciales (backward compatibility)
        if (is_dir($fullPath) && strpos($pattern, '*') === false && $pattern[0] !== '/') {
            if (strpos($item, $pattern) === 0) {
                return true;
            }
        }
    }
    
    return false;
}

function generateTree($dir, $prefix = '', $ignoreList = []) {
    $items = scandir($dir);
    $filteredItems = array_diff($items, ['.', '..']);
    $filteredItems = array_filter($filteredItems, function($item) use ($dir, $ignoreList) {
        return !shouldIgnore($item, $dir . DIRECTORY_SEPARATOR . $item, $ignoreList);
    });
    
    usort($filteredItems, 'customSort');
    
    $count = count($filteredItems);
    $i = 0;

    foreach ($filteredItems as $item) {
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        $isLast = (++$i === $count);
        echo $prefix . ($isLast ? '└── ' : '├── ') . $item . "\n";
        if (is_dir($path)) {
            generateTree($path, $prefix . ($isLast ? '    ' : '│   '), $ignoreList);
        }
    }
}

function processFiles($dir, $ignoreList, $binaryExtensions) {
    $items = scandir($dir);
    $filteredItems = array_diff($items, ['.', '..']);
    $filteredItems = array_filter($filteredItems, function($item) use ($dir, $ignoreList) {
        return !shouldIgnore($item, $dir . DIRECTORY_SEPARATOR . $item, $ignoreList);
    });
    
    usort($filteredItems, 'customSort');
    
    foreach ($filteredItems as $item) {
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            processFiles($path, $ignoreList, $binaryExtensions);
        } else {
            displayFileContent($path, $binaryExtensions);
        }
    }
}

function isTextFile($filename, $binaryExtensions) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    // 1. Si es extensión binaria conocida → salir rápido
    if (in_array($extension, $binaryExtensions)) {
        return false;
    }
    
    // 2. Usar comando file de macOS si está disponible
    if (PHP_OS_FAMILY === 'Darwin' && is_executable('/usr/bin/file')) {
        $output = [];
        $returnCode = 0;
        @exec("/usr/bin/file -b --mime-type " . escapeshellarg($filename), $output, $returnCode);
        
        if ($returnCode === 0 && !empty($output)) {
            $mime = trim($output[0]);
            // MIME types que consideramos texto
            $textMimes = ['text/', 'application/json', 'application/xml', 'application/x-httpd-php'];
            foreach ($textMimes as $textMime) {
                if (strpos($mime, $textMime) === 0) {
                    return true;
                }
            }
            return false;
        }
    }
    
    // 3. Fallback: análisis de contenido para casos dudosos
    return isFileContentTextual($filename);
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
    
    // Detectar BOMs de texto
    $boms = [
        "\xEF\xBB\xBF", "\xFF\xFE", "\xFE\xFF", 
        "\xFF\xFE\x00\x00", "\x00\x00\xFE\xFF"
    ];
    foreach ($boms as $bom) {
        if (strpos($sample, $bom) === 0) return true;
    }
    
    // Buscar caracteres de control no permitidos
    return !preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $sample);
}

function displayFileContent($filepath, $binaryExtensions) {
    echo "// PATH: " . $filepath . "\n\n";

    if (isTextFile($filepath, $binaryExtensions)) {
        $content = @file_get_contents($filepath);
        echo $content !== false ? $content : "// Error: No se pudo leer el archivo.\n";
    } else {
        echo "[binary file]\n";
    }

    echo "\n// END of file: " . $filepath . "\n\n\n\n\n";
}

// Información del sistema y archivos ignorados
echo "// ========== SYSTEM INFO ==========\n";
echo "// OS: " . PHP_OS_FAMILY . " (" . php_uname('s') . " " . php_uname('r') . ")\n";
echo "// PHP Version: " . PHP_VERSION . "\n";
echo "// Script: " . basename(__FILE__) . "\n";
echo "// Generated: " . date('Y-m-d H:i:s') . "\n";
echo "// ========== IGNORED ITEMS ==========\n";
foreach ($ignoreList as $ignored) {
    echo "// - " . $ignored . "\n";
}
echo "// ===================================\n";

// ===== CABECERA FINAL =====
echo "\n\n// ********************\n";
echo "// PROJECT DUMP END\n";
echo "// ********************\n";
// ==========================
?>