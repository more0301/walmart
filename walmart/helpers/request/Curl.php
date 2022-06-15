<?php
declare( strict_types=1 );

namespace Walmart\helpers\request;

use Walmart\core\App;
use Walmart\core\Singleton;
use Walmart\helpers\Functions;
use Walmart\helpers\Logger;

/**
 * Class Curl
 *
 * @package Walmart\helpers\request
 */
class Curl extends Singleton
{
    /**
     * @param string $url
     * @param string $sign
     * @param int    $timestamp
     * @param string $feed
     *
     * @param bool   $shipping
     *
     * @return bool|string
     */
    public function sendPostRequest( string $url, string $sign, int $timestamp,
        string $feed, bool $shipping = false )
    {
        $content = false === $shipping ?
            'multipart/form-data' : 'application/xml';

        $ch = curl_init();

        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_TIMEOUT        => 360,
            CURLOPT_AUTOREFERER    => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,

            //            CURLOPT_VERBOSE        => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,

            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POST          => true,
            CURLOPT_POSTFIELDS    => [ 'Feed' => $feed ],

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
                'WM_CONSUMER.CHANNEL.TYPE: ' . App::$consumerChannelType,
                'Content-Type:' . $content,
                'Accept:application/xml',
                'Host:marketplace.walmartapis.com'
            ]
        ];

        curl_setopt_array( $ch, $options );

        try {
            $response = curl_exec( $ch );
        } catch ( \Throwable $e ) {
            // if curl error
            $error = curl_error( $ch );
            Logger::log( 'Curl error: ' . $error . ' System error: ' . $e->getMessage(), __METHOD__ );

            return false;
        }
        curl_close( $ch );

        return $response;
    }

    /**
     * @param string $url
     * @param int    $timestamp
     * @param string $sign
     *
     * @return bool|string
     */
    public function sendGetRequest( string $url, int $timestamp, string $sign )
    {
        $ch      = curl_init();
        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTP_VERSION   => 'CURL_HTTP_VERSION_1_1',
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_AUTOREFERER    => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_VERBOSE        => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,

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
                'Host:marketplace.walmartapis.com'
            ],
        ];
        curl_setopt_array( $ch, $options );

        try {
            $response = curl_exec( $ch );
        } catch ( \Throwable $e ) {
            // if curl error
            $error = curl_error( $ch );
            Logger::log( 'Curl error: ' . $error . ' System error: ' . $e->getMessage(), __METHOD__ );

            return false;
        }

        curl_close( $ch );

        return $response;
    }

    /**
     * @param string $url
     * @param int    $timestamp
     * @param string $sign
     *
     * @return bool|string
     */
    public function sendDeleteRequest( string $url, int $timestamp, string $sign )
    {
        $ch = curl_init();

        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_TIMEOUT        => 360,
            CURLOPT_AUTOREFERER    => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_CUSTOMREQUEST => 'DELETE',

            CURLOPT_VERBOSE        => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,

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
                'WM_CONSUMER.CHANNEL.TYPE: ' . App::$consumerChannelType,
                'Content-Type:application/xml',
                'Accept:application/xml',
                'Host:marketplace.walmartapis.com'
            ]
        ];

        curl_setopt_array( $ch, $options );

        try {
            $response = curl_exec( $ch );
        } catch ( \Throwable $e ) {
            // if curl error
            $error = curl_error( $ch );
            Logger::log( 'Curl error: ' . $error . ' System error: ' . $e->getMessage(), __METHOD__ );

            return false;
        }
        curl_close( $ch );

        return $response;
    }

    /**
     * @param string $url
     * @param string $sign
     * @param int    $timestamp
     * @param string $feed
     *
     * @param bool   $shipping
     *
     * @return mixed
     */
    public static function postRequest( string $url, string $sign, int $timestamp, string $feed, bool $shipping = false )
    {
        $curl = static::getInstance();

        return $curl->sendPostRequest( $url, $sign, $timestamp, $feed, $shipping );
    }

    /**
     * @param string $url
     * @param int    $timestamp
     * @param string $sign
     *
     * @return mixed
     */
    public static function getRequest( string $url, int $timestamp, string $sign )
    {
        $curl = static::getInstance();

        return $curl->sendGetRequest( $url, $timestamp, $sign );
    }

    /**
     * @param string $url
     * @param int    $timestamp
     * @param string $sign
     *
     * @return mixed
     */
    public static function deleteRequest( string $url, int $timestamp, string $sign )
    {
        $curl = static::getInstance();

        return $curl->sendDeleteRequest( $url, $timestamp, $sign );
    }
}
