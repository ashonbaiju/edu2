<?php
/**
 * Migration Script - Project Wide Path & DB Update
 * Run this once to fix all hardcoded /project/ paths and DB includes.
 */

$root = __DIR__;
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

foreach ($files as $file) {
    if ($file->isDir() || $file->getExtension() !== 'php') continue;
    
    $path = $file->getRealPath();
    if (strpos($path, 'migration.php') !== false) continue;
    if (strpos($path, '.git') !== false) continue;
    if (strpos($path, 'vendor') !== false) continue;

    $content = file_get_contents($path);
    $original = $content;

    // 1. Update Database include
    // Replace 'includes/db.php' with 'config/db.php'
    $content = str_replace("includes/db.php", "config/db.php", $content);
    
    // 2. Update Hardcoded /project/ paths
    // We replace "/project/" with "<?= BASE_URL ? >" (without spaces)
    // Be careful with common strings, usually they are in href or src
    $content = str_replace('"/project/', '"<?= BASE_URL ?>', $content);
    $content = str_replace("'/project/", "'<?= BASE_URL ?>", $content);
    $content = str_replace("(/project/", "(<?= BASE_URL ?>", $content);

    if ($content !== $original) {
        file_put_contents($path, $content);
        echo "Updated: $path\n";
    }
}

echo "Migration complete!\n";
?>
