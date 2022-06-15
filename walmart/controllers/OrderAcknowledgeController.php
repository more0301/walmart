<?php
declare( strict_types=1 );

namespace Walmart\controllers;

use Walmart\models\OrderAcknowledgeModel;

class OrderAcknowledgeController
{
    public function start() :void
    {
        $model = new OrderAcknowledgeModel();

        $orders = $model->setOrderData();

        if ( !empty( $orders ) ) {
            foreach ( $orders as $order_id ) {

                $parameters = $model->setRequestParameters( $order_id );

                $response = $model->sendPostRequest(
                    $parameters['url'],
                    $parameters['sign'],
                    $parameters['timestamp']
                );

                //print_r( $response );
            }
        }

        $model->truncateTable();
    }
}