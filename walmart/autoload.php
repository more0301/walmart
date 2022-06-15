<?php

declare(strict_types=1);

spl_autoload_register(
    function ($class) {
        $class_name = str_replace(
            ['Walmart', '\\'],
            [
                '',
                DIRECTORY_SEPARATOR
            ],
            $class ?? ''
        );

        $file = APP_ROOT . $class_name . '.php';

        if (file_exists($file)) {
            include_once $file;

            return true;
        }

        return false;
    }
);
