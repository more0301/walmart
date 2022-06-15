<?php
declare( strict_types=1 );

namespace Walmart\models\preload\compile;

use Walmart\core\App;
use Walmart\helpers\database\Database;
use Walmart\helpers\Logger;

/**
 * Class PreloadCompileInventory
 *
 * @package Walmart\models\preload\compile
 */
class PreloadCompileInventory
{
    /**
     * @return bool|int
     */
    public function importData()
    {
        // not in inventory_preload
        // not in inventory_success
        // not in inventory_black

        $sql = '
        INSERT INTO walmart_ca.inventory_preload

        SELECT ' . App::$shopId . ' AS shop_id, price_success.sku
        FROM walmart_ca.price_success price_success
        
                 LEFT JOIN walmart_ca.inventory_preload inventory_preload
                           ON price_success.sku = inventory_preload.sku
                               AND inventory_preload.shop_id = ' . App::$shopId . '
        
                 LEFT JOIN walmart_ca.inventory_success inventory_success
                           ON price_success.sku = inventory_success.sku
                               AND inventory_success.shop_id = ' . App::$shopId . '
        
                 LEFT JOIN walmart_ca.inventory_black inventory_black
                           ON price_success.sku = inventory_black.sku
                               AND inventory_black.shop_id = ' . App::$shopId . '
        
        WHERE (price_success.sku IS NOT NULL AND
               price_success.shop_id = ' . App::$shopId . ')
          AND inventory_preload.sku IS NULL
          AND inventory_success.sku IS NULL
          AND inventory_black.sku IS NULL
        
        ON CONFLICT DO NOTHING
        RETURNING sku';

        $result = Database::request( $sql, __METHOD__, true );

        $count = count( $result );
        if ( $count > 0 ) {
            Logger::log( 'Imported ' . $count . ' records from the ' .
                App::$inventoryPreloadT . ' table into the ' .
                App::$priceSuccessT . ' table', __METHOD__ );

            return true;
        }

        Logger::log( 'Nothing is imported into table ' .
            App::$inventoryPreloadT, __METHOD__ );

        return false;
    }
}