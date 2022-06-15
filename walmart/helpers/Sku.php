<?php
declare( strict_types=1 );

namespace Walmart\helpers;

use Walmart\core\App;
use Walmart\helpers\database\Database;

/**
 * Class Sku
 *
 * @package Walmart\helpers
 */
class Sku
{
    /**
     * * SKU formula:
     * * {{Country from code}{Source from code}}-{{Source to code}{Source shop
     * ID}}-{asin(encoded)+2 random symbol}-{UPC}
     *
     * Example:
     * * USAM-WL000001-{encrypted asin(10) + 2 character}-{UPC(12)}
     *
     * @param array $items
     *
     * @return array
     */
    public function create( array $items )
    {
        $already_items = $this->getAlreadyItems();

        $items_with_sku = [];

        $new_sku = $old_sku = 0;

        foreach ( $items as $item ) {

            // do not generate new sku if sku is already in the report
            if ( isset( $already_items[ $item['asin'] ] ) &&
                !empty( $already_items[ $item['asin'] ] ) ) {
                $sku = $already_items[ $item['asin'] ];

                ++$old_sku;
            }
            else {
                $sku = $this->template( $item );

                ++$new_sku;
            }

            if ( false !== $sku ) {
                $item['sku']      = $sku;
                $items_with_sku[] = $item;
            }
        }

        App::telegramLog( 'Generated new sku: ' . $new_sku );
        App::telegramLog( 'Used old sku: ' . $old_sku );

        return $items_with_sku;
    }

    /**
     * Sku that are already in the report
     */
    public function getAlreadyItems()
    {
        $sql = '
        SELECT preload.asin, report.sku
        FROM walmart_ca.report report 
        
                 LEFT JOIN walmart_ca.item_preload preload
                           ON preload.upc = report.upc
        
        WHERE report.shop_id = ' . App::$shopId . '
          AND report.sku IS NOT NULL';

        $data = Database::request( $sql, __METHOD__, true );

        $result = [];
        foreach ( $data as $item ) {
            if ( isset( $item['asin'], $item['sku'] ) ) {
                $result[ $item['asin'] ] = $item['sku'];
            }
        }

        return $result;
    }

    /**
     * Sku template
     *
     * @param array $item
     *
     * @return bool|string
     */
    public function template( array $item )
    {
        $part_1 = App::$skuCountry . App::$skuSourceFrom;

        $part_2_id = str_pad( (string)App::$shopId, 6, '0', STR_PAD_LEFT );
        if ( $part_2_id === '000000' ) {
            return false;
        }
        $part_2 = App::$skuSourceTo . $part_2_id;

        $part_3 = $this->asinEncode( $item['asin'] );

        $part_4 = $item['upc'];

        $sku_36 = $part_1 . $part_2 . $part_3 . $part_4;
        $sku    = $part_1 . '-' . $part_2 . '-' . $part_3 . '-' . $part_4;

        //if ( strlen( str_ireplace( '-', '', $sku ) ) === App::$skuLength ) {
        //    return $sku;
        //}
        if ( strlen( $sku_36 ) === App::$skuLength ) {
            return $sku;
        }

        return false;
    }

    /**
     * Rearranges asin characters according to the formula
     *
     * @param string $asin
     *
     * @return string
     */
    public function asinEncode( string $asin )
    {
        $chars       = str_split( $asin );
        $sku_combine = array_combine( App::$asinReverse, $chars );

        uksort( $sku_combine, function ( $a, $b ) {
            if ( $a == $b ) {
                return 0;
            }

            return ( $a < $b ) ? -1 : 1;
        } );

        // add zero
        foreach ( App::$asinZeroPosition as $position ) {
            array_splice( $sku_combine, $position, 0, 0 );
        }

        return implode( $sku_combine );
    }
}