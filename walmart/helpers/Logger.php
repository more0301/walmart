<?php

declare(strict_types=1);

namespace Walmart\helpers;

use Walmart\core\App;
use Walmart\core\Singleton;

/**
 * Class Logger
 *
 * @package WalmartApi\components
 */
class Logger extends Singleton
{
    /**
     * @param string      $message
     * @param string      $method
     * @param string|null $dependence
     *
     * @return void
     */
    public static function log(
        string $message,
        string $method,
        string $dependence = null
    ) {
        $instance = static::getInstance();
        $instance->writelog($message, $method, $dependence);
    }

    /**
     * @param $message
     * @param $method
     * @param $dependence
     *
     * @return bool
     */
    public function writeLog($message, $method, $dependence)
    {
        if (php_sapi_name() == 'fpm-fcgi') {
            return false;
        }

        if (App::$shopId === 0) {
            return false;
        }

        if (!isset($message) || !isset($method)) {
            return false;
        }

        // do not write logs intended for development mode
        if (isset($dependence)) {
            if ($dependence === 'dev') {
                if (false === App::$devMode) {
                    return false;
                }
            }
        }

        $method_name = Functions::methodName($method);
        $method_name = isset($method_name) ? $method_name . ' - ' : '';

        $break   = false !== stripos($message, 'Call controller') ?
            PHP_EOL . PHP_EOL : PHP_EOL;
        $message = $break . date('Y-m-d H:i:s') . ': ' . $method_name
                   . $message;

        $app_log_file = isset(App::$logFile) ? App::$logFile : App::logFile();
        $file_name    = isset($app_log_file) ? $app_log_file : 'system';

        $dir = APP_ROOT . '/logs/' . App::$shopId;
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        $file_path = $dir . '/' . $file_name . '.log';

        file_put_contents($file_path, $message, FILE_APPEND | LOCK_EX);

        return true;
    }
}
