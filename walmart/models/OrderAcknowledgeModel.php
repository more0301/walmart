<?php
declare( strict_types=1 );

namespace Walmart\models;

use Walmart\core\App;
use Walmart\helpers\database\Db;
use Walmart\helpers\Functions;
use Walmart\helpers\Logger;
use Walmart\helpers\request\RequestParameters;

class OrderAcknowledgeModel
{
    public Db $db;

    public function __construct()
    {
        $this->db = new Db();
    }

    public function setOrderData() :array
    {
        $sql = 'SELECT order_id
                FROM walmart_ca.order_acknowledge_walmart_ca';

        $orders = $this->db->run( $sql )->fetchAll();

        if ( !isset( $orders ) || empty( $orders ) ) {
            return [];
        }

        return array_column( $orders, 'order_id' );
    }

    public function setRequestParameters( $order_id )
    {
        $parameters = RequestParameters::exec(
            'acknowledge_order',
            [ 'purchaseOrderId' => $order_id ] );

        if ( false === $parameters ) {
            Logger::log( 'Error creating request data', __METHOD__ );

            return false;
        }

        return $parameters;
    }

    public function sendPostRequest( string $url, string $sign, int $timestamp )
    {
        $ch = curl_init();

        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_AUTOREFERER    => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,

            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POST          => true,

            CURLOPT_FRESH_CONNECT     => true,
            CURLOPT_DNS_CACHE_TIMEOUT => 0,

            CURLOPT_HTTPHEADER => [
                'WM_SVC.NAME:Walmart Marketplace',
                'WM_TENANT_ID:WALMART.CA',
                'WM_LOCALE_ID:en_CA',
                'WM_QOS.CORRELATION_ID:' . Functions::getGuid(),
                'WM_SEC.TIMESTAMP:' . $timestamp,
                'WM_SEC.AUTH_SIGNATURE:' . $sign,
                'WM_CONSUMER.ID:' . App::$consumerId,
                'WM_CONSUMER.CHANNEL.TYPE:' . App::$consumerChannelType,
                'Content-Type:application/xml',
                'Accept:application/xml',
                'Host:marketplace.walmartapis.com',
                'Content-length:' . strlen( $url )
            ]
        ];

        curl_setopt_array( $ch, $options );

        try {
            $response = curl_exec( $ch );
        }
        catch ( \Throwable $e ) {
            // if curl error
            $error = curl_error( $ch );
            Logger::log( 'Curl error: ' . $error .
                ' System error: ' . $e->getMessage(), __METHOD__ );

            return false;
        }

        curl_close( $ch );

        return $response;
    }

    public function truncateTable()
    {
        $sql = 'TRUNCATE TABLE walmart_ca.order_acknowledge_walmart_ca 
                RESTART IDENTITY';

        $this->db->run( $sql );
    }
}