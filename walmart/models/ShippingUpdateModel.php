<?php
declare( strict_types=1 );

namespace Walmart\models;

use Walmart\core\App;
use Walmart\helpers\database\Db;
use Walmart\helpers\Functions;
use Walmart\helpers\Logger;
use Walmart\helpers\request\Curl;
use Walmart\helpers\request\RequestParameters;
use Walmart\helpers\xml\templates\ShippingTemplate;
use Walmart\helpers\xml\XmlValid;

class ShippingUpdateModel
{
    public Db $db;

    public function __construct()
    {
        $this->db = new Db();
    }

    public function setOrderData()
    {
        //        $sql = 'SELECT *
        //FROM walmart_ca.orders_walmart_ca
        //WHERE carrier IS NOT NULL AND tracking_number IS NOT NULL';
        //
        //        $data = $this->db->run( $sql )->fetchAll();
        //
        //        if ( !isset( $data ) || empty( $data ) ) {
        //            return [];
        //        }
        //
        //        $result = [];
        //        foreach ( $data as $key => $item ) {
        //            if ( !isset( $item['order_id'] ) ) {
        //                continue;
        //            }
        //            $result[ $key ]['order_id']        = $item['order_id'];
        //            $result[ $key ]['status']          = $item['status'];
        //            $result[ $key ]['ship_date_time']  = date( 'Y-m-d\TH:i:s.000\Z', strtotime( $item['order_date'] ) );
        //            $result[ $key ]['carrier']         = $item['carrier'];
        //            $result[ $key ]['tracking_number'] = $item['tracking_number'];
        //
        //            $result[ $key ]['tracking_url'] = 'http://';
        //            if ( $item['carrier'] == 'fedex' ) {
        //                $result[ $key ]['tracking_url'] .= 'fedex.com';
        //            }
        //            elseif ( $item['carrier'] == 'dhl' ) {
        //                $result[ $key ]['tracking_url'] .= 'dhl.com';
        //            }
        //        }

        //return $result;

        return [
            0 => [
                'order_id'        => 'Y21212575',
                'status'          => 'Shipped',
                'ship_date_time'  => '2020-12-7T01:23:15.000Z',
                'carrier'         => 'FedEx',
                'tracking_number' => 'MAY0810US64211450501',
                'tracking_url'    => 'http://www.fedex.com'
            ]
        ];
    }

    public function setRequestParameters( $order_id )
    {
        $parameters = RequestParameters::exec( 'shipping_update',
            [ 'purchaseOrderId' => $order_id ] );

        if ( false === $parameters ) {
            Logger::log( 'Error creating request data', __METHOD__ );

            return false;
        }

        return $parameters;
    }

    public function createXmlFeed( $data )
    {
        $xml_feed = ShippingTemplate::getFeed( $data );

        if ( false === XmlValid::exec( $xml_feed ) ) {
            Logger::log( 'Xml feed failed validation', __METHOD__ );

            return false;
        }

        return $xml_feed;
    }

    public function sendData( array $request_parameters, string $xml_feed = '' )
    {
        //$new = iconv( 'ASCII', 'UTF-8', $xml_feed );
        //$new = mb_convert_encoding( $xml_feed, 'UTF-8', 'ASCII' );
        //$new = mb_convert_encoding( $xml_feed, 'UTF-8', 'auto' );
        $new = utf8_encode( $xml_feed );
        //
        //print_r( 'iconv' );
        //echo PHP_EOL;
        //print_r( mb_detect_encoding( $f, 'UTF-8', true ) );
        //echo PHP_EOL;
        //
        //print_r( 'mb_convert_encoding' );
        //echo PHP_EOL;
        //print_r( mb_detect_encoding( $f2, 'UTF-8', true ) );
        //echo PHP_EOL;
        //
        //print_r( 'mb_convert_encoding auto' );
        //echo PHP_EOL;
        //print_r( mb_detect_encoding( $f3, 'UTF-8', true ) );
        //echo PHP_EOL;
        //
        //print_r( 'utf8_encode auto' );
        //echo PHP_EOL;
        //print_r( mb_detect_encoding( $f4, 'UTF-8', true ) );
        //echo PHP_EOL;
        //
        //exit();

        $response = $this->sendPostRequest(
            $request_parameters['url'],
            $request_parameters['sign'],
            $request_parameters['timestamp'],
            $new
        );

        print_r( $response );

        //        $feed = XmlRead::exec( $response, 'ns3' );
        //
        //        if ( isset( $feed['feedId'] ) && is_string( $feed['feedId'] ) ) {
        //            $feed_id = filter_var( trim( $feed['feedId'] ), FILTER_SANITIZE_STRING );
        //        }
        //
        //        if ( !isset( $feed_id ) || false === $feed_id || empty( $feed_id ) ) {
        //            Logger::log( 'Error validating feed id', __METHOD__ );
        //
        //            return false;
        //        }
        //
        //        return is_string( $feed_id ) ? $feed_id : false;
    }

    public function sendPostRequest( string $url, string $sign, int $timestamp,
        string $feed )
    {
        $ch = curl_init();

        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_TIMEOUT        => 360,
            CURLOPT_AUTOREFERER    => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,

            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POST          => true,
            CURLOPT_POSTFIELDS    => empty( $feed ) ? '' : [ 'Feed' => $feed ],

            CURLOPT_FRESH_CONNECT     => true,
            CURLOPT_DNS_CACHE_TIMEOUT => 0,

            CURLOPT_ENCODING => 'UTF-8',

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
                'Content-length:' . ( strlen( $url ) + strlen( $feed ) )
            ]
        ];

        print_r($options);

        curl_setopt_array( $ch, $options );

        try {
            $response = curl_exec( $ch );
        }
        catch ( \Throwable $e ) {
            // if curl error
            $error = curl_error( $ch );
            Logger::log( 'Curl error: ' . $error . ' System error: ' .
                $e->getMessage(), __METHOD__ );

            return false;
        }
        curl_close( $ch );

        return $response;
    }
}