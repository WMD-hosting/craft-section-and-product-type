<?php

declare(strict_types=1);

/*
 * The unit tests run without a Craft application or a database. With a dev install
 * (`composer install`) Composer autoloads everything; without one (PHPUnit from another install,
 * as constructor-cms's CI runs it) this maps the plugin's own namespace.
 *
 * The field tests build the field classes, so Craft's classes must be loadable from whichever
 * install runs PHPUnit. No application is created: `Craft::$app` stays null, which is also how
 * the tests get an install where Commerce is not loaded.
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

// Composer autoloads neither `Yii` nor `Craft`; Craft's own bootstrap requires them the same way.
if (!class_exists('Yii', false)) {
    require Composer\InstalledVersions::getInstallPath('yiisoft/yii2') . '/Yii.php';
    require Composer\InstalledVersions::getInstallPath('craftcms/cms') . '/src/Craft.php';
}
