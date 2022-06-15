<?php
declare( strict_types=1 );

namespace Walmart\controllers;

use Walmart\core\App;
use Walmart\models\TransportCreateModel;

class TransportCreateController
{
    public TransportCreateModel $model;

    public function __construct()
    {
        $this->model = new TransportCreateModel();
    }

    public function start()
    {
        global $argv;

        // example: php index.php transport_create start orders
        if ( isset( $argv[1] )
            && $argv[1] == 'transport_create'
            && isset( $argv[3] ) ) {

            switch ( $argv[3] ) {
                case 'orders':
                    $this->createDumpOrders();
                    break;
            }
        }
        // full dump
        // php index.php transport_create start
        else {

            // truncate only first iteration
            if ( App::shopIds()[ array_key_first( App::shopIds() ) ] === App::$shopId ) {
                $this->model->cleanDumpDirExport();
            }

            $this->createDumpReport();
            $this->createCurrentBrands();
            $this->createAvailableBrands();
            $this->createRuleBrands();
            $this->createOthers();
            $this->createCatKeywords();
        }
    }

    private function createDumpOrders()
    {
        $order_controller = new OrdersController();
        $order_controller->start();

        // truncate only first iteration
        if ( App::shopIds()[ array_key_first( App::shopIds() ) ] === App::$shopId ) {
            $this->model->cleanDumpDirExportOrders();
        }

        // last iteration
        if ( App::shopIds()[ array_key_last( App::shopIds() ) ] === App::$shopId ) {
            $this->model->makeFileOrders();
        }
    }

    private function createDumpReport()
    {
        // first iteration
        if ( App::shopIds()[ array_key_first( App::shopIds() ) ] === App::$shopId ) {
            $this->model->makeFileReport();
        }
    }

    /**
     * All available brands, no filters
     */
    private function createCurrentBrands()
    {
        // first iteration
        if ( App::shopIds()[ array_key_first( App::shopIds() ) ] === App::$shopId ) {
            $this->model->makeFileCurrentBrands();
        }
    }

    /**
     * Available brands after all filters
     */
    private function createAvailableBrands()
    {
        // first iteration
        if ( App::shopIds()[ array_key_first( App::shopIds() ) ] === App::$shopId ) {
            $this->model->makeFileAvailableBrands();
        }
    }

    /**
     * List of brands from the rules table
     */
    private function createRuleBrands()
    {
        // first iteration
        if ( App::shopIds()[ array_key_first( App::shopIds() ) ] === App::$shopId ) {
            $this->model->makeFileRuleBrands();
        }
    }

    private function createOthers()
    {
        // first iteration
        if ( App::shopIds()[ array_key_first( App::shopIds() ) ] === App::$shopId ) {
            $this->model->makeFileOthers();
        }
    }

    private function createCatKeywords()
    {
        // first iteration
        if ( App::shopIds()[ array_key_first( App::shopIds() ) ] === App::$shopId ) {
            $this->model->makeFileCatKeywords();
        }
    }
}