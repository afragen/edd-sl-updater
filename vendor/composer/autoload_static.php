<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit047415cf42b6d7dd064038c532564bfd
{
    public static $prefixLengthsPsr4 = array (
        'F' => 
        array (
            'Fragen\\Translations_Updater\\' => 28,
        ),
        'E' => 
        array (
            'EDD\\Software_Licensing\\Updater\\' => 31,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Fragen\\Translations_Updater\\' => 
        array (
            0 => __DIR__ . '/..' . '/afragen/translations-updater/src/Translations_Updater',
        ),
        'EDD\\Software_Licensing\\Updater\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit047415cf42b6d7dd064038c532564bfd::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit047415cf42b6d7dd064038c532564bfd::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
