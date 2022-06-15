<?php
declare( strict_types=1 );

namespace Walmart\helpers\xml\templates;

class InventoryTemplate
{
    /**
     * @param array $data
     *
     * @return string
     */
    public static function getFeed( array $data )
    {
        $items = '';
        foreach ( $data as $key => $item ) {
            $items .= self::inventoryItem( $item );
        }

        if ( empty( $items ) ) {
            return false;
        }

        return self::inventoryFeed( $items );
    }

    /**
     * @param string $items
     *
     * @return string
     */
    private static function inventoryFeed( string $items )
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
                <InventoryFeed xmlns="http://walmart.com/">
                  <InventoryHeader>
                    <version>1.4</version>
                  </InventoryHeader>
                  ' . $items . '
                </InventoryFeed>';
    }

    /**
     * @param array $item
     *
     * @return string
     */
    private static function inventoryItem( array $item )
    {
        return '<inventory>
                    <sku>' . $item['sku'] . '</sku>
                    <quantity>
                      <unit>' . $item['inventory_unit'] . '</unit>
                      <amount>' . $item['inventory_amount'] . '</amount>
                    </quantity>
                    <fulfillmentLagTime>' . $item['inventory_fulfillment'] . '</fulfillmentLagTime>
                </inventory>';
    }
}