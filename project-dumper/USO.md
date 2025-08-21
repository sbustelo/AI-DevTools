# 🗂️ Project Dumper - Herramienta de Volcado de Proyectos

## Descripción
Script PHP que genera un volcado completo de la estructura de directorios y contenido de archivos de texto. Ideal para alimentar prompts de IA con el contexto completo de un proyecto.

## Características
- 📁 Genera árbol de directorios visual
- 📝 Extrae contenido de archivos de texto
- ⚡ Ignora automáticamente archivos binarios y temporales
- 🎯 Configurable con patrones personalizados
- 🔄 Ordena archivos con `_` al principio primero

## Uso Rápido
```bash
# Ejecutar desde la línea de comandos
php _project-dump.php > mi-proyecto.dump

# O ver en navegador (si está configurado)
http://tuservidor/project-dumper/_project-dump.php
```

## Configuración
Editar las variables al inicio del script:
```php
$ignoreList = [ ... ];       // Elementos a ignorar
$binaryExtensions = [ ... ]; // Extensiones binarias
```

## Ejemplos
```bash
# Volcar solo estructura de directorios
php _project-dump.php | head -20

# Volcar ignorando más archivos
php _project-dump.php | grep -v \"node_modules\" > volcado.limpio.dump
```

## Notas
- El script se ignora a sí mismo y sus derivados en el volcado
- Los archivos binarios se detectan automáticamente
- El output está optimizado para prompts de IA
- Los archivos que empiezan con `_` se ordenan primero
- Soporta patrones wildcard y regex para ignorar archivos