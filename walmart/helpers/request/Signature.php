<?php
declare( strict_types=1 );

namespace Walmart\helpers\request;

use Walmart\core\App;
use Walmart\core\Singleton;
use Walmart\helpers\Logger;

/**
 * Class Signature
 *
 * @package WalmartApi\components
 */
class Signature extends Singleton
{
    /**
     * @param array $request_data
     *
     * @return mixed
     */
    public static function exec( array $request_data )
    {
        $instance = static::getInstance();

        return $instance->getSign( $request_data );
    }

    /**
     * @param array $request_data
     *
     * @return bool|string
     */
    public function getSign( $request_data )
    {
        $auth_data = App::$consumerId . "\n";
        $auth_data .= $request_data['url'] . "\n";
        $auth_data .= $request_data['http_method'] . "\n";
        $auth_data .= $request_data['timestamp'] . "\n";

        $pem         = $this->convertPkcs8ToPem( base64_decode( App::$privateKey ) );
        $private_key = openssl_pkey_get_private( $pem );
        $hash        = defined( 'OPENSSL_ALGO_SHA256' ) ? OPENSSL_ALGO_SHA256 : 'sha256';

        if ( !openssl_sign( $auth_data, $signature, $private_key, $hash ) ) {
            Logger::log( 'Signature generation error', __METHOD__ );

            return false;
        }

        return base64_encode( $signature );
    }

    /**
     * @param $der
     *
     * @return string
     */
    public function convertPkcs8ToPem( $der )
    {
        $begin_marker = '-----BEGIN PRIVATE KEY-----';
        $end_marker   = '-----END PRIVATE KEY-----';
        $key          = base64_encode( $der );

        $pem = $begin_marker . "\n";
        $pem .= chunk_split( $key, 64, "\n" );
        $pem .= $end_marker . "\n";

        return $pem;
    }
}