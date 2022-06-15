<?php
declare( strict_types=1 );

namespace Walmart\controllers;

use Walmart\models\ShippingUpdateModel;

class ShippingUpdateController
{
    public function start()
    {
        $model = new ShippingUpdateModel();

        //$request_parameters = $model->setRequestParameters( 'Y18899554' );
        //$model->sendData( $request_parameters );
        //exit();

        $data = $model->setOrderData();

        if ( empty( $data ) ) {
            return false;
        }

        foreach ( $data as $item ) {
            $request_parameters = $model
                ->setRequestParameters( $item['order_id'] );

            $xml_feed           = $model->createXmlFeed( $item );

            $model->sendData( $request_parameters, $xml_feed );
        }
    }
}