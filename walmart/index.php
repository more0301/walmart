<?php

declare(strict_types=1);

use Walmart\core\App;
use Walmart\core\Router;
use Walmart\helpers\Alerts;
use Walmart\helpers\Categories;
use Walmart\helpers\Logger;

if (
    isset($argv[1])
    && $argv[1] != 'transport_create'
    && $argv[1] != 'transport_import'
    && $argv[1] != 'transport_restore'
) {
    register_shutdown_function('shutdownFunction');
}

define('WM_ACCESS', true);
define('APP_ROOT', __DIR__);

require_once __DIR__ . '/config/Db.php';
require_once __DIR__ . '/autoload.php';

App::generateOptions();

Router::setRoute();

function shutdownFunction()
{
    $last_error = error_get_last();
    if (isset($last_error['type']) && $last_error['type'] === E_ERROR) {
        App::telegramLog($last_error['message']);
    }

    $total_message = count(App::$telegramLog) > 0 ?
        implode(PHP_EOL, App::$telegramLog) . PHP_EOL : '';

    if (false === App::$appResult) {
        exit();
    } else {
        $app_result = 'Application completed successfully';
    }
    $total_message .= 'Shop id: ' . App::$shopId . ' Runtime: ' . App::$runTime;
    Logger::log($total_message, __METHOD__);

    $alert = PHP_EOL . $app_result . PHP_EOL . $total_message;

    // telegram alert
    if (
        strlen($total_message) > 0
        && false === App::$debug
        && false === App::$doNotSendTelegram
    ) {
        if (isset(App::$controller, App::$method)) {
            Alerts::sendTelegram(
                'Controller: ' . App::$controller .
                    ', method: ' . App::$method . '. ' . $alert
            );
        }
    }
}

//$categories = new Categories();
//$result     = $categories->setCategories();
