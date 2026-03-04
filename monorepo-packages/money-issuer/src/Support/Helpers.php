<?php

if (! function_exists('documents_path')) {
    /**
     * Get the path to the documents directory.
     *
     * Resolves in the following order:
     * 1. TESTING_PACKAGE_PATH constant (for package tests)
     * 2. Published location: base_path('resources/documents')
     * 3. Fallback: money-issuer package location (for development)
     *
     * @param  string|null  $path  Optional path to append
     * @return string Absolute path to documents directory
     */
    function documents_path(?string $path = null): string
    {
        // For package tests, use the test path
        if (defined('TESTING_PACKAGE_PATH')) {
            $basePath = TESTING_PACKAGE_PATH;
        } else {
            // Published location (app resources)
            $basePath = base_path('resources/documents');

            // Fallback to package location if banks.json not found (development)
            if (! file_exists($basePath.'/banks.json')) {
                $packagePath = dirname(__DIR__, 2).'/resources/documents';
                if (file_exists($packagePath.'/banks.json')) {
                    $basePath = $packagePath;
                }
            }
        }

        return $basePath.($path ? '/'.$path : '');
    }
}
