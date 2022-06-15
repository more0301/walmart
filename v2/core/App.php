<?php

declare(strict_types=1);

namespace WB\Core;

use WB\Helpers\Alert;
use WB\Helpers\Logger;

require_once APP_ROOT . '/config/Access.php';
require_once APP_ROOT . '/helpers/Logger.php';
require_once APP_ROOT . '/helpers/Alert.php';
require_once APP_ROOT . '/helpers/Db.php';
require_once APP_ROOT . '/helpers/Curl.php';
require_once APP_ROOT . '/helpers/Sanitize.php';

class App
{
    public static Db     $db;
    public static array  $options;
    public static string $clArgument;
    public static string $clArgument2;
    public static int    $shopId;
    public static array  $externalAlert;

    public function __construct()
    {
        Logger::time('App');

        $this->setClArgument();

        $this->setDb();

        $this->setAppOptions();

        $this->shopProcessing();

        $runtime = Logger::timeEnd('App');

        self::$externalAlert[] = 'Runtime: ' . gmdate('H:i:s', $runtime);
        //number_format($runtime / 3600, 3) .' h';

        Alert::sendTelegram(implode(self::$externalAlert));

        //Logger::log(
        //    implode( self::$externalAlert),
        //    __METHOD__,
        //    'info'
        //);
    }

    private function setClArgument(): void
    {
        global $argv;

        self::$clArgument = isset($argv[1]) ?
            filter_var($argv[1], FILTER_SANITIZE_STRING) : '';

        // 'dev' - view debug log (validation, sanitize)
        self::$clArgument2 = isset($argv[2]) ?
            filter_var($argv[2], FILTER_SANITIZE_STRING) : '';
    }

    private function setDb(): void
    {
        self::$db = new Db();
    }

    private function setAppOptions(): void
    {
        // default options
        $response_def = self::$db->run(
            'SELECT * FROM walmart_ca.default_options'
        )->fetch();

        $response_def['asin_reverse'] = json_decode(
            $response_def['asin_reverse'],
            true
        );

        $response_def['asin_zero_position'] = json_decode(
            $response_def['asin_zero_position'],
            true
        );

        self::$options['default'] = $response_def;

        // shop options
        $response = self::$db->run(
            'SELECT * FROM walmart_ca.options'
        )->fetchAll();

        if (isset($response) && !empty($response)) {
            array_map(
                function ($item) {
                    $item['cad_rate']  = (float)$item['cad_rate'];
                    $item['markup']    = (float)$item['markup'];
                    $item['min_price'] = (float)$item['min_price'];
                    $item['max_price'] = (float)$item['max_price'];

                    self::$options['shops'][$item['shop_id']] = $item;
                },
                $response
            );
        }
    }

    private function getModule(): string
    {
        if (empty(self::$clArgument)) {
            Logger::log(
                'Command line argument not specified',
                'App',
                'error'
            );
            exit();
        }

        $allowed_modules = [
            'preload'           => '',
            'adding'            => '',
            'item_submit'       => '',
            'price_submit'      => '',
            'inventory_submit'  => '',
            'retire'            => '',
            'report'            => '',
            'transport_create'  => '',
            'transport_import'  => '',
            'transport_restore' => ''
        ];

        if (!isset($allowed_modules[self::$clArgument])) {
            Logger::log(
                'Module does not exists',
                'App',
                'error'
            );
            exit();
        }

        $module_pref = implode(
            array_map(
                'ucfirst',
                explode('_', self::$clArgument)
            )
        );

        require_once APP_ROOT . '/modules/' . self::$clArgument . '/' .
                     $module_pref . 'Module.php';

        self::$externalAlert[] = $module_pref . PHP_EOL;

        return '\WB\Modules\\' . $module_pref . '\\' . $module_pref . 'Module';
    }

    private function shopProcessing(): void
    {
        foreach (self::$options['shops'] as $item) {
            if (isset($item['shop_id'])) {
                self::$shopId = (int)$item['shop_id'];

                Logger::log(
                           'Shop id: ' . App::$shopId,
                           __METHOD__,
                           'info',
                    alert: true
                );

                $module = $this->getModule();

                if (!empty($module)) {
                    (new $module())->run();
                }
            }
        }
    }
}
