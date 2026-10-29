<?php

declare(strict_types=1);

/*
 * The unit tests are Craft-free. With a dev install (`composer install`) Composer autoloads
 * everything; without one (PHPUnit from another install, as constructor-cms's CI runs it) this
 * maps the plugin's own namespace.
 */
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
}

spl_autoload_register(static function(string $class): void {
    $prefixes = [
        'wmd\\sectionandproducttype\\tests\\' => dirname(__DIR__) . '/tests/',
        'wmd\\sectionandproducttype\\' => dirname(__DIR__) . '/src/',
    ];

    foreach ($prefixes as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }

            return;
        }
    }
});
