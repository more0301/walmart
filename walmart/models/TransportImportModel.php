<?php

declare(strict_types=1);

namespace Walmart\models;

use Walmart\config\Access;

class TransportImportModel
{
    public string $dumpDirImport = APP_ROOT . '/dumps/import/';
    public string $dumpDirImportOrders
                                 = APP_ROOT . '/dumps/import_orders/';
    public string $dumpDirImportOrdersAcknowledge
                                 = APP_ROOT
                                   . '/dumps/import_order_acknowledge/';
    public string $dumpDirImportScraper
                                 = APP_ROOT . '/dumps/import_from_scraper/';

    public function __construct()
    {
        if (!file_exists($this->dumpDirImport)) {
            mkdir($this->dumpDirImport, 0777, true);
        }
        if (!file_exists($this->dumpDirImportOrders)) {
            mkdir($this->dumpDirImportOrders, 0777, true);
        }
    }

    /**
     * Import dump
     *
     * @param $import_parameters
     */
    public function importDump($import_parameters)
    {
        $dir_bezoxx  = '/home/' . Access::$user
                       . '/www/bezoxx/php/app/dumps/export/*';
        $dir_walmart = '/home/' . Access::$user
                       . '/www/walmart_robots/php/dumps/import/';

        exec('cp ' . $dir_bezoxx . ' ' . $dir_walmart);
        //exec( 'sshpass -p \'' . $import_parameters['password'] . '\' rsync -z -e \'ssh -p ' . $import_parameters['port'] . '\' ' . $import_parameters['user'] . '@' . $import_parameters['host'] . ':' . $import_parameters['remote_export_dir'] . ' ' . $this->dumpDirImport . '; chmod 0777 ' . $this->dumpDirImport . '*', $output );
    }

    public function importDumpOrders($import_parameters)
    {
        exec(
            'cp ' . $import_parameters['remote_export_dir'] . ' '
            . $this->dumpDirImportOrders
        );

        //exec(
        //    'sshpass -p \'' . $import_parameters['password']
        //    . '\' rsync -z -e \'ssh -p ' . $import_parameters['port'] . '\' '
        //    . $import_parameters['user'] . '@' . $import_parameters['host']
        //    . ':' . $import_parameters['remote_export_dir'] . ' '
        //    . $this->dumpDirImportOrders . '; chmod 0777 '
        //    . $this->dumpDirImportOrders . '*',
        //    $output
        //);
    }

    public function importDumpOrdersAcknowledge($import_parameters)
    {
        if (!file_exists($this->dumpDirImportOrdersAcknowledge)) {
            mkdir($this->dumpDirImportOrdersAcknowledge, 0777, true);
        }

        exec(
            'cp ' . $import_parameters['remote_export_dir'] . ' '
            . $this->dumpDirImportOrdersAcknowledge
        );

        //exec(
        //    'sshpass -p \'' . $import_parameters['password']
        //    . '\' rsync -z -e \'ssh -p ' . $import_parameters['port'] . '\' '
        //    . $import_parameters['user'] . '@' . $import_parameters['host']
        //    . ':' . $import_parameters['remote_export_dir'] . ' '
        //    . $this->dumpDirImportOrdersAcknowledge . '; chmod 0777 '
        //    . $this->dumpDirImportOrdersAcknowledge . '*',
        //    $output
        //);
    }

    public function importDumpScraper($import_parameters)
    {
        exec(
            'sshpass -p \'' . $import_parameters['password']
            . '\' rsync -z -e \'ssh -p ' . $import_parameters['port'] . '\' '
            . $import_parameters['user'] . '@' . $import_parameters['host']
            . ':' . $import_parameters['remote_export_dir'] . ' '
            . $this->dumpDirImportScraper . '; chmod 0777 '
            . $this->dumpDirImportScraper . '*',
            $output
        );
    }

    public function cleanDumpDirImport()
    {
        if (file_exists($this->dumpDirImport)) {
            exec('cd ' . $this->dumpDirImport . '; rm *.gz');
        }
    }

    public function cleanDumpDirImportOrders()
    {
        if (file_exists($this->dumpDirImportOrders)) {
            exec('cd ' . $this->dumpDirImportOrders . '; rm *.gz');
        }
    }

    public function cleanDumpDirImportOrderAcknowledge()
    {
        if (file_exists($this->dumpDirImportOrdersAcknowledge)) {
            exec('cd ' . $this->dumpDirImportOrdersAcknowledge . '; rm *.gz');
        }
    }

    public function cleanDumpDirImportScraper()
    {
        if (file_exists($this->dumpDirImportScraper)) {
            exec('cd ' . $this->dumpDirImportScraper . '; rm *.gz');
        }
    }
}
