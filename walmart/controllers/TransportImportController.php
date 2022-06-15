<?php

declare(strict_types=1);

namespace Walmart\controllers;

use Walmart\core\App;
use Walmart\models\TransportImportModel;

class TransportImportController
{
    public TransportImportModel $model;

    public function __construct()
    {
        $this->model = new TransportImportModel();
    }

    public function start()
    {
        if (App::shopIds()[array_key_first(App::shopIds())] === App::$shopId) {
            global $argv;

            // example: php index.php transport_import start orders
            if (isset($argv[1])
                && $argv[1] == 'transport_import'
                && isset($argv[3])) {
                switch ($argv[3]) {
                    case 'orders':
                        $this->importOrders();
                        break;

                    case 'order_acknowledge':
                        $this->importOrdersAcknowledge();
                        break;

                    case 'scraper':
                        $this->importScraper();
                        break;
                }
            } else {
                $this->importFull();
            }
        }
    }

    public function importFull()
    {
        if (App::shopIds()[array_key_first(App::shopIds())] === App::$shopId) {
            $import_parameters = [
                'password'          => App::$pass,
                'port'              => App::$port,
                'user'              => App::$user,
                'host'              => App::$host,
                'remote_export_dir' => '/home/' . App::$user
                                       . '/www/bezoxx/php/app/dumps/export/*'
            ];

            $this->model->cleanDumpDirImport();

            $this->model->importDump($import_parameters);
        }
    }

    public function importOrders()
    {
        if (App::shopIds()[array_key_first(App::shopIds())] === App::$shopId) {
            $import_parameters = [
                'password'          => App::$pass,
                'port'              => App::$port,
                'user'              => App::$user,
                'host'              => App::$host,
                'remote_export_dir' => '/home/' . App::$user
                                       . '/www/bezoxx/php/app/dumps/export_orders/*'
            ];

            $this->model->cleanDumpDirImportOrders();

            $this->model->importDumpOrders($import_parameters);
        }
    }

    public function importOrdersAcknowledge()
    {
        if (App::shopIds()[array_key_first(App::shopIds())] === App::$shopId) {
            $import_parameters = [
                'password'          => App::$pass,
                'port'              => App::$port,
                'user'              => App::$user,
                'host'              => App::$host,
                'remote_export_dir' => '/home/' . App::$user
                                       . '/www/bezoxx/php/app/dumps/export_order_acknowledge/*'
            ];

            $this->model->cleanDumpDirImportOrderAcknowledge();

            $this->model->importDumpOrdersAcknowledge($import_parameters);
        }
    }

    public function importScraper()
    {
        if (App::shopIds()[array_key_first(App::shopIds())] === App::$shopId) {
            $import_parameters = [
                'password'          => App::$passScraper,
                'port'              => App::$portScraper,
                'user'              => App::$userScraper,
                'host'              => App::$hostScraper,
                'remote_export_dir' => '/home/' . App::$userScraper
                                       . '/www/amazon_proxy_scan/php/dump/content/*'
            ];

            $this->model->cleanDumpDirImportScraper();

            $this->model->importDumpScraper($import_parameters);
        }
    }
}
