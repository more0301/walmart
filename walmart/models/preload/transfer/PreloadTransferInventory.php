<?php
declare( strict_types=1 );

namespace Walmart\models\preload\transfer;

use Walmart\core\App;
use Walmart\helpers\database\Database;
use Walmart\helpers\Logger;

/**
 * Class PreloadTransferInventory
 *
 * @package Walmart\models\preload\transfer
 */
class PreloadTransferInventory
{
    /**
     * @inheritDoc
     */
    public function transferToAdding()
    {
        // inventory_preload.amount <> inventory_adding.amount
        // inventory_preload.fulfillment <> inventory_adding.fulfillment
        // OR inventory_adding.sku IS NULL

        // нет в inventory_black

        $sql = '
        INSERT INTO walmart_ca.inventory_adding
        
        SELECT ' . App::$shopId . ' AS shop_id,
               preload.sku,
               preload.unit,
               preload.amount,
               preload.fulfillment
        FROM walmart_ca.inventory_preload preload
        
                 LEFT JOIN walmart_ca.inventory_adding adding
                           ON adding.sku = preload.sku
                               AND adding.shop_id = ' . App::$shopId . '
        
                 LEFT JOIN walmart_ca.inventory_black black
                           ON black.sku = preload.sku
                               AND black.shop_id = ' . App::$shopId . '
        
        WHERE preload.shop_id = ' . App::$shopId . '
          AND (adding.sku IS NULL OR adding.amount <> preload.amount)
          AND black.sku IS NULL
        
        ON CONFLICT (sku)
            DO UPDATE SET amount      = excluded.amount,
                          fulfillment = excluded.fulfillment,
                          date_create = excluded.date_create
        RETURNING sku';

        $result = Database::request( $sql, __METHOD__, true );

        $count = count( $result );
        if ( false === $result || $count <= 0 ) {
            Logger::log( 'Error: Items not copied to ' .
                App::$inventoryAddingT . ' table', __METHOD__ );

            return false;
        }

        return true;
    }
}