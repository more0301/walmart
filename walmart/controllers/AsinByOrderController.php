<?php
declare( strict_types=1 );

namespace Walmart\controllers;

use Walmart\core\Controller;
use Walmart\helpers\database\Db;

class AsinByOrderController extends Controller
{
    public function start()
    {
        $request_parameters = $this->model->setRequestParameters();

        if ( false === $request_parameters ) {
            echo 'Request parameters not received';

            return false;
        }

        $orders = $this->model->getOrders( $request_parameters );

        if ( false === $orders ) {
            echo 'The list of orders is empty.';

            return false;
        }

        /*        $sku_list = $this->model->getSkuList( $orders );
                if ( false === $sku_list ) {
                    echo 'The list of sku is empty.';

                    return false;
                }

                $all_data = $this->model->getAllData( $sku_list );
                if ( false === $all_data ) {
                    echo 'Data not received';

                    return false;
                }*/

        $db = new Db();

        $status_data = $this->model->parseData( $orders );
        if ( empty( $status_data ) ) {
            echo 'The list of orders is empty.';

            return false;
        }
        $additional_orders_data = $this->model
            ->getAdditionalOrdersData( $status_data, $db );
        if ( empty( $additional_orders_data ) ) {
            echo 'The list of orders is empty.';

            return false;
        }

        $this->model->tableOutput( $additional_orders_data );

        return true;
    }
}