<?php
declare( strict_types=1 );

namespace Walmart\helpers\xml;

use Walmart\helpers\Logger;
use Walmart\core\App;

class XmlRead
{
    public static function exec( string $data, string $ns = null )
    {
        try {
            $xml = simplexml_load_string( $data );
        }
        catch ( \Throwable $e ) {
            Logger::log( 'Error reading response. Original data: ' .
                $data, __METHOD__ );

            return false;
        }

        if ( isset( $ns ) && false !== stripos( $data, $ns ) ) {
            $namespaces = $xml->getNameSpaces( true );

            try {
                $current_ns = $namespaces[ $ns ];
            }
            catch ( \Throwable $e ) {
                Logger::log( $e->getMessage(), __METHOD__ );

                return App::jsonToArray( json_encode( $data ) );
            }

            return App::jsonToArray( json_encode( $xml->children( $current_ns ) ) );
        }

        return App::jsonToArray( json_encode( $xml ) );
    }
}