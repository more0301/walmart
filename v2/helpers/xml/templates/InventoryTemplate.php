<?php

declare(strict_types=1);

namespace WB\helpers\xml\templates;

class InventoryTemplate
{
    public static function getFeed(array $data):string
    {
        $items = '';

        foreach ($data as $item) {
            $items .= self::inventoryItem($item);
        }

        if (empty($items)) {
            return '';
        }

        return self::inventoryFeed($items);
    }

    private static function inventoryFeed(string $items): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
                <InventoryFeed xmlns="http://walmart.com/">
                  <InventoryHeader>
                    <version>1.4</version>
                  </InventoryHeader>
                  ' . $items . '
                </InventoryFeed>';
    }

    private static function inventoryItem(array $item): string
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
