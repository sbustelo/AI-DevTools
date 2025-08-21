# 🗂️ Project Dumper - Project Dumping Tool

## Description
PHP script that generates a complete dump of directory structure and text file content. Ideal for feeding AI prompts with the full context of a project.

## Features
- 📁 Generates visual directory tree
- 📝 Extracts text file content
- ⚡ Automatically ignores binary and temporary files
- 🎯 Configurable with custom patterns
- 🔄 Sorts files with `_` at the beginning first

## Quick Start
```bash
# Execute from command line
php _project-dump.php > my-project.dump

# Or view in browser (if configured)
http://yourserver/project-dumper/_project-dump.php
```

## Configuration
Edit variables at the beginning of the script:
```php
$ignoreList = [ ... ];       // Items to ignore
$binaryExtensions = [ ... ]; // Binary extensions
```

## Examples
```bash
# Dump only directory structure
php _project-dump.php | head -20

# Dump ignoring more files
php _project-dump.php | grep -v \"node_modules\" > clean-dump.dump
```

## Notes
- The script ignores itself and its derivatives in the dump
- Binary files are automatically detected
- Output is optimized for AI prompts
- Files starting with `_` are sorted first
- Supports wildcard and regex patterns for ignoring files