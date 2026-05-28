<?php
if (!function_exists('nx\test')) {
    if (!function_exists('nx\test')) {
        if (!function_exists('loadNX')) {
            function loadNX(): void{
                $srcDir = __DIR__ . '/../src';
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($srcDir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );
                foreach ($files as $file) {
                    if ($file->getExtension() === 'php') {
                        require_once $file->getPathname();
                    }
                }
            }
        }
        loadNX();
    }
}
