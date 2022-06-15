<?php

declare(strict_types=1);

namespace WB\Helpers;

use WB\Core\App;

class RequestParameters
{
    public static function getParameters(string $url, string $method): array
    {
        // timestamp
        $timestamp = (int)(microtime(true) * 1000);

        // sign
        $auth_data = App::$options['shops'][App::$shopId]['consumer_id'] . "\n";
        $auth_data .= $url . "\n";
        $auth_data .= $method . "\n";
        $auth_data .= $timestamp . "\n";

        $begin_marker = '-----BEGIN PRIVATE KEY-----';
        $end_marker   = '-----END PRIVATE KEY-----';
        $key          = base64_encode(
            base64_decode(App::$options['shops'][App::$shopId]['private_key'])
        );

        $pem = $begin_marker . "\n";
        $pem .= chunk_split($key, 64, "\n");
        $pem .= $end_marker . "\n";

        $private_key = openssl_pkey_get_private($pem);
        $hash        = defined('OPENSSL_ALGO_SHA256') ?
            OPENSSL_ALGO_SHA256 : 'sha256';

        if (!openssl_sign($auth_data, $signature, $private_key, $hash)) {
            Logger::log('Signature generation error', __METHOD__, 'info');

            return [];
        }

        $sign = base64_encode($signature);

        return ['timestamp' => $timestamp, 'sign' => $sign];
    }
}
