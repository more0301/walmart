<?php
declare( strict_types=1 );

namespace Walmart\models;

use Walmart\core\App;
use Walmart\helpers\database\Database;
use Walmart\helpers\Logger;

/**
 * Class PreloadCompilePrice
 *
 * @package Walmart\models\preload\compile
 */
class PriceCompileModel
{
    /**
     * @return array|bool|mixed
     */
    public function importData()
    {
        // in price_preload add entries from external.usa_product
        // is in item_success
        // not in price_black
        // price_amazon or price_prime > 0

        // price_amazon != price_adding.price
        // or price_prime != price_adding.price

        $sql = '
        INSERT INTO walmart_ca.price_adding
        
        SELECT ' . App::$shopId . ' shop_id,
               product.asin,
               success.sku,
               product.price_amazon,
               product.price_prime
        
        FROM external_usa_bezoxx.usa_product product
        
                 LEFT JOIN walmart_ca.item_preload item_preload
                           ON item_preload.asin = product.asin
        
                 LEFT JOIN walmart_ca.item_success success
                           ON success.upc = item_preload.upc
                               AND success.shop_id = ' . App::$shopId . '
        
                 LEFT JOIN walmart_ca.price_adding adding
                           ON adding.sku = success.sku
                               AND adding.shop_id = ' . App::$shopId . '
        
                 LEFT JOIN walmart_ca.price_black black
                           ON black.sku = success.sku
                               AND black.shop_id = ' . App::$shopId . '
        
        WHERE success.shop_id = ' . App::$shopId . '  
          AND success.sku IS NOT NULL
          AND black.sku IS NULL
          AND ((product.price_amazon::DECIMAL > 0 AND
                (product.price_amazon <> adding.price_amazon OR
                 adding.price_amazon IS NULL))
            OR (product.price_prime::DECIMAL > 0 AND
                (product.price_prime <> adding.price_prime OR
                 adding.price_prime IS NULL)))
        
        ON CONFLICT (sku)
            DO UPDATE SET price_amazon = excluded.price_amazon,
                          price_prime  = excluded.price_prime,
                          date_create  = excluded.date_create
        RETURNING sku';

        $result = Database::request( $sql, __METHOD__, true );

        $count = count( $result );
        if ( $count > 0 ) {
            Logger::log( 'Imported ' . $count . ' records from the ' .
                App::$pricePreloadT . ' table into the ' .
                App::$externalUsaProductT . ' table', __METHOD__ );

            return $count;
        }

        Logger::log( 'Nothing is imported into table ' .
            App::$pricePreloadT, __METHOD__ );

        return false;
    }
}
