<?php

declare(strict_types=1);

namespace WB\Modules\InventorySubmit;

use WB\Core\App;
use WB\Helpers\Logger;
use WB\Helpers\Validation\ItemExport;

require_once APP_ROOT . '/helpers/validation/ItemExport.php';
require_once APP_ROOT . '/modules/inventory_submit/SubmitCycle.php';

class InventorySubmitModule
{
    protected string $requestUrl    = 'https://marketplace.walmartapis.com/v3/ca/feeds?feedType=inventory';
    protected string $requestMethod = 'POST';

    public function run(): void
    {
        $data = $this->getData();

        if (empty($data)) {
            return;
        }

        $data_valid = $this->validationData($data);

        if (empty($data_valid)) {
            return;
        }

        $sent = (new SubmitCycle())->cycle($data_valid);

        Logger::log(
                   'Sent ' . $sent . ' inventories',
                   __METHOD__,
                   'info',
            alert: true
        );
    }

    public function getData(): array
    {
        $sql = 'SELECT :shop_id AS shop_id,
               report.sku,
               \'EACH\' inventory_unit,
               1 inventory_amount,
               :inventory_fulfillment AS inventory_fulfillment
        FROM walmart_ca.report_copy report
        
                 LEFT JOIN walmart_ca.item_preload preload
                           ON preload.upc = report.upc
        
                 LEFT JOIN walmart_ca.price_black black
                           ON black.sku = report.sku
                               AND black.shop_id = :shop_id
        
                 LEFT JOIN external_usa_bezoxx.usa_product product
                           ON product.asin = preload.asin
        
        WHERE report.shop_id = :shop_id
        
        AND report.is_send_inventory = FALSE
        
          AND report.sku IS NOT NULL
          AND (report.publish_status = \'PUBLISHED\'
            OR report.publish_status = \'UNPUBLISHED\')
        
          AND product.asin IS NOT NULL
        
          AND (
                (product.price_amazon::DECIMAL > 0
                    AND report.price <> round(product.price_amazon * :cad_rate * :markup, 2))
                OR (product.price_prime::DECIMAL > 0
                AND report.price <> round(product.price_prime * :cad_rate * :markup, 2)))
                
          OR (report.shop_id = :shop_id
           AND
       (report.price::DECIMAL = price_amazon::DECIMAL
           AND report.inventory_count IS NULL)
    OR (report.price::DECIMAL = price_prime::DECIMAL
        AND report.inventory_count IS NULL)
       )
        
          AND black.sku IS NULL';

        $data = App::$db->run(
            $sql,
            [
                ':shop_id'               => App::$shopId,
                ':cad_rate'              => App::$options['shops'][App::$shopId]['cad_rate'],
                ':markup'                => App::$options['shops'][App::$shopId]['markup'],
                ':inventory_fulfillment' => App::$options['shops'][App::$shopId]['inventory_fulfillment']
            ]
        )->fetchAll();

        $count = count($data);

        if ($count <= 0) {
            Logger::log(
                       'Data import not received',
                       __METHOD__,
                       'info'
            );

            return [];
        }

        Logger::log(
                   'Received ' . $count . ' items',
                   __METHOD__,
                   'info',
            alert: true
        );

        return $data;
    }

    public function validationData(array $items): array
    {
        $valid_items = [];

        $dependence = [
            'sku',
            'inventory_unit',
            'inventory_amount',
            'inventory_fulfillment'
        ];

        foreach ($items as $item) {
            $valid_item = (new ItemExport())->createItem($item, $dependence);
            if (!empty($valid_item)) {
                $valid_items[] = $valid_item;
            }
        }

        $count = count($valid_items);

        if ($count > 0) {
            Logger::log(
                       $count . ' valid items',
                       __METHOD__,
                       'info'
            );

            return $valid_items;
        }

        Logger::log(
                   'Data failed validation and filtering',
                   __METHOD__,
                   'info'
        );

        return [];
    }
}
