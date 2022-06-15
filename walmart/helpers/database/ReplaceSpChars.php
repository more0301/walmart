<?php
declare( strict_types=1 );

namespace Walmart\helpers\database;

use Walmart\core\App;

class ReplaceSpChars
{
    public static function replace()
    {
//        $sql = 'SELECT sku,product_name
//                FROM walmart_ca.item_adding
//                WHERE product_name IS NOT NULL';

                        $sql = 'SELECT sku,product_name
                                FROM walmart_ca.item_adding
                                WHERE product_name SIMILAR TO \'%&#[0-9]{1,3};%\'';

        App::$dbh = Database::setConnect();

        $items = Database::request( $sql, __METHOD__, true );

        foreach ( $items as $item ) {

            if ( isset( $item['sku'] ) ) {
//                $product_name = self::htmlAllEntities( utf8_decode( $item['product_name'] ) );
                $product_name = self::simpleReplace( $item['product_name'] );

                //                echo PHP_EOL . '=====================' . PHP_EOL;
                //                print_r( $item['asin'] );
                //                echo PHP_EOL;
                //                print_r( $item['short_description'] );
                //                echo PHP_EOL . '===description===' . PHP_EOL;
                //                print_r( $description );
                //
                //                sleep( 1 );

                $sql_update = 'UPDATE walmart_ca.item_adding
                SET product_name=' . App::$dbh->quote( $product_name ) . '
                WHERE sku=\'' . $item['sku'] . '\'';

                Database::request( $sql_update, __METHOD__ );
            }
        }
    }

    public static function htmlAllEntities( $str )
    {
        $res    = '';
        $strlen = strlen( $str );

        for ( $i = 0; $i < $strlen; $i++ ) {
            $byte = ord( $str[ $i ] );

            // 1-byte char
            if ( $byte < 128 ) {
                $res .= $str[ $i ];
            }
            // invalid utf8
            elseif ( $byte < 192 ) {
            }
            // 2-byte char
            elseif ( $byte < 224 ) {
                $res .= '&#' . ( ( 63 & $byte ) * 64 + ( 63 & ord( $str[ ++$i ] ) ) ) . ';';
            }
            // 3-byte char
            elseif ( $byte < 240 ) {
                $res .= '&#' . ( ( 15 & $byte ) * 4096 + ( 63 & ord( $str[ ++$i ] ) )
                        * 64 + ( 63 & ord( $str[ ++$i ] ) ) ) . ';';
            }
            // 4-byte char
            elseif ( $byte < 248 ) {
                $res .= '&#' . ( ( 15 & $byte ) * 262144 + ( 63 & ord( $str[ ++$i ] ) )
                        * 4096 + ( 63 & ord( $str[ ++$i ] ) )
                        * 64 + ( 63 & ord( $str[ ++$i ] ) ) ) . ';';
            }
        }

        return html_entity_decode( $res, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    }

    public static function simpleReplace( $str )
    {
        return preg_replace( '/&#[0-9]{1,3};/ui', '', $str );
    }
}