<?php

declare(strict_types=1);

namespace WB\Helpers;

use Throwable;

class Curl
{
    public function postRequest(array $data): string
    {
        $content = isset($data['shipping']) && false === $data['shipping'] ?
            'multipart/form-data' : 'application/xml';

        $ch = curl_init();

        $options = [
            CURLOPT_URL            => $data['url'],
            CURLOPT_TIMEOUT        => 360,
            CURLOPT_AUTOREFERER    => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,

            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POST          => true,
            CURLOPT_POSTFIELDS    => ['Feed' => $data['feed']],

            CURLOPT_FRESH_CONNECT     => true,
            CURLOPT_DNS_CACHE_TIMEOUT => 0,

            CURLOPT_HTTPHEADER => [
                'WM_SVC.NAME:Walmart Marketplace',
                'WM_TENANT_ID:WALMART.CA',
                'WM_LOCALE_ID:en_CA',
                'WM_QOS.CORRELATION_ID:' . $data['guid'],
                'WM_SEC.TIMESTAMP:' . $data['timestamp'],
                'WM_SEC.AUTH_SIGNATURE:' . $data['sign'],
                'WM_CONSUMER.ID:' . $data['consumer_id'],
                'WM_CONSUMER.CHANNEL.TYPE: ' . $data['consumer_channel_type'],
                'Content-Type:' . $content,
                'Accept:application/xml',
                'Host:marketplace.walmartapis.com'
            ]
        ];

        curl_setopt_array($ch, $options);

        try {
            $response = curl_exec($ch);
        } catch (Throwable $e) {
            $error = curl_error($ch);

            Logger::log(
                'Curl error: ' . $error . ' System error: ' . $e->getMessage(),
                __METHOD__,
                'error'
            );

            return '';
        }
        curl_close($ch);

        return (string)$response;
    }

    public function getRequest(array $data): string
    {
        $ch = curl_init();

        $options = [
            CURLOPT_URL            => $data['url'],
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
                'WM_QOS.CORRELATION_ID:' . $data['guid'],
                'WM_SEC.TIMESTAMP:' . $data['timestamp'],
                'WM_SEC.AUTH_SIGNATURE:' . $data['sign'],
                'WM_CONSUMER.ID:' . $data['consumer_id'],
                'WM_CONSUMER.CHANNEL.TYPE:' . $data['consumer_channel_type'],
                'Content-Type:application/xml',
                'Accept:application/xml',
                'Host:marketplace.walmartapis.com'
            ],
        ];

        curl_setopt_array($ch, $options);

        try {
            $response = curl_exec($ch);
        } catch (Throwable $e) {
            $error = curl_error($ch);

            Logger::log(
                'Curl error: ' . $error . ' System error: ' . $e->getMessage(),
                __METHOD__,
                'error'
            );

            return '';
        }

        curl_close($ch);

        return (string)$response;
    }

    public function deleteRequest(array $data): string
    {
        $ch = curl_init();

        $options = [
            CURLOPT_URL            => $data['url'],
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
                'WM_QOS.CORRELATION_ID:' . $data['guid'],
                'WM_SEC.TIMESTAMP:' . $data['timestamp'],
                'WM_SEC.AUTH_SIGNATURE:' . $data['sign'],
                'WM_CONSUMER.ID:' . $data['consumer_id'],
                'WM_CONSUMER.CHANNEL.TYPE: ' . $data['consumer_channel_type'],
                'Content-Type:application/xml',
                'Accept:application/xml',
                'Host:marketplace.walmartapis.com'
            ]
        ];

        curl_setopt_array($ch, $options);

        try {
            $response = curl_exec($ch);
        } catch (Throwable $e) {
            $error = curl_error($ch);

            Logger::log(
                'Curl error: ' . $error . ' System error: ' . $e->getMessage(),
                __METHOD__,
                'error'
            );

            return '';
        }

        curl_close($ch);

        return (string)$response;
    }
}
