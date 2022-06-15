<?php
declare( strict_types=1 );

namespace Walmart\core;

use Walmart\controllers\AsinByOrderController;
use Walmart\controllers\OrdersController;
use Walmart\controllers\TransportCreateController;
use Walmart\helpers\Logger;

defined( 'WM_ACCESS' ) or App::error403();

/**
 * Class Router
 *
 * @package WalmartApi\core
 */
class Router
{
    /**
     * @return bool
     */
    public static function setRoute()
    {
        // orders list
        if ( php_sapi_name() == 'fpm-fcgi' ) {

            App::$shopId  = 10001;
            App::$logFile = 'orders-list';
            App::setConfig();

            $controller = new AsinByOrderController();
            $controller->start();
            exit();
        }

        // runtime start
        $runtime_start = microtime( true );

        $controllers = [
            'report'            => 'ReportController',
            'retire'            => 'RetireController',
            'item_preload'      => 'ItemPreloadController',
            'item_adding'       => 'ItemAddingController',
            'item_submit'       => 'ItemSubmitController',
            'price_submit'      => 'PriceSubmitController',
            'inventory_submit'  => 'InventorySubmitController',

            // update
            'shipping_update'   => 'ShippingUpdateController',

            // order acknowledge
            'order_acknowledge' => 'OrderAcknowledgeController',

            // keywords
            'keywords'          => 'KeywordsController',

            // orders
            'orders'            => 'OrdersController',

            // transport
            'transport_create'  => 'TransportCreateController',
            'transport_import'  => 'TransportImportController',
            'transport_restore' => 'TransportRestoreController',
        ];

        if ( !isset( $controllers[ App::controller() ] ) ) {
            App::$telegramLog[] = 'The controller specified does not exist';

            return false;
        }

        //if ( App::controller() === 'transport_create' ) {
        //    $controller = new TransportCreateController();
        //    $controller->start();
        //    exit();
        //}

        $controller_pref = '\\Walmart\controllers\\';
        $controller_name = $controller_pref . $controllers[ App::controller() ];

        $controller = new $controller_name();
        $method     = App::method();

        if ( !method_exists( $controller, $method ) ) {
            App::$telegramLog[] = 'The specified method does not exist in the controller ' .
                $controller_name;

            return false;
        }

        // shop ids processing
        foreach ( App::shopIds() as $key => $id ) {

            App::$shopId = $id;
            App::setConfig();

            Logger::log( 'Shop id: ' . App::$shopId . '. Call controller ' .
                $controller_name . ' and method \'' . $method . '\'', __METHOD__ );

            $result = $controller->$method();

            App::$appResult = isset( $result ) && is_bool( $result ) &&
                false !== $result;

            // runtime end
            App::$runTime = round( ( microtime( true ) - $runtime_start ) / 3600, 2 ) . ' h';

            // for the preloader, only one iteration
            if ( App::controller() == 'item_preload' ) {
                break;
            }
        }

        return true;
    }
}