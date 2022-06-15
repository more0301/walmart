<?php

declare(strict_types=1);

namespace WB\Modules\PriceSubmit;

use WB\Core\App;
use WB\Helpers\Logger;
use WB\Helpers\Validation\ItemExport;

require_once APP_ROOT . '/helpers/validation/ItemExport.php';
require_once APP_ROOT . '/modules/price_submit/SubmitCycle.php';

class PriceSubmitModule
{
    public string $requestMethod = 'POST';
    public string $requestUrl    = 'https://marketplace.walmartapis.com/v3/ca/feeds?feedType=price';

    public function run(): void
    {
        $data = $this->getItems();

        if (empty($data)) {
            return;
        }

        $data_valid = $this->validationData(
            $this->convertAndMarkup(
                $this->mergePrices($data)
            )
        );

        if (empty($data_valid)) {
            Logger::log('No valid items', __METHOD__, 'info', alert: true);

            return;
        }

        $sent = (new SubmitCycle())->cycle($data_valid);

        Logger::log(
                   'Sent ' . $sent . ' prices',
                   __METHOD__,
                   'info',
            alert: true
        );
    }

    private function getItems(): array
    {
        $sql = 'SELECT :shop_id AS shop_id,
               report.sku,
               product.price_amazon,
               product.price_prime,
               \'USD\' AS currency
        FROM walmart_ca.report_copy AS report
        
                 LEFT JOIN walmart_ca.item_preload AS preload
                           ON preload.upc = report.upc
        
                 LEFT JOIN walmart_ca.price_black AS black
                           ON black.sku = report.sku
                               AND black.shop_id = :shop_id
        
                 LEFT JOIN external_usa_bezoxx.usa_product AS product
                           ON product.asin = preload.asin
        
        WHERE report.shop_id = :shop_id
        
        AND report.is_send_price = FALSE
        
          AND report.sku IS NOT NULL
          AND (report.publish_status = \'PUBLISHED\'
            OR report.publish_status = \'UNPUBLISHED\')
        
          AND product.asin IS NOT NULL
        
          AND (
                (product.price_amazon::DECIMAL > 0
                    AND report.price <> round(product.price_amazon * :cad_rate * :markup, 2))
                OR (product.price_prime::DECIMAL > 0
                AND report.price <> round(product.price_prime * :cad_rate * :markup, 2)))
        
          AND black.sku IS NULL';

        $data = App::$db->run(
            $sql,
            [
                ':shop_id'  => App::$shopId,
                ':cad_rate' => App::$options['shops'][App::$shopId]['cad_rate'],
                ':markup'   => App::$options['shops'][App::$shopId]['markup']
            ]
        )->fetchAll();

        $count = count($data);

        if (!isset($data) || !is_array($data) || $count <= 0) {
            Logger::log(
                       'Data import not received',
                       __METHOD__,
                       'info',
                alert: true
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

    private function mergePrices(array $data): array
    {
        foreach ($data as &$item) {
            $amazon = (float)$item['price_amazon'];
            $prime  = (float)$item['price_prime'];

            $item['price'] = $amazon > 0 ? $amazon : (max($prime, 0));
        }

        return $data;
    }

    private function convertAndMarkup(array $data): array
    {
        foreach ($data as &$item) {
            if (isset($item['sku'])
                && (!isset($item['currency']) || $item['currency'] === 'USD')) {
                $price = round(
                    floatval($item['price'])
                    * App::$options['shops'][App::$shopId]['cad_rate']
                    * App::$options['shops'][App::$shopId]['markup'],
                    2
                );

                if ($price
                    < App::$options['shops'][App::$shopId]['min_price']) {
                    $item['price']
                        = App::$options['shops'][App::$shopId]['min_price'];
                } elseif ($price
                          > App::$options['shops'][App::$shopId]['max_price']) {
                    $item['price']
                        = App::$options['shops'][App::$shopId]['max_price'];
                } else {
                    $item['price'] = $price;
                }
            }
        }

        return $data;
    }

    public function validationData(array $data): array
    {
        $valid_items = [];
        $dependence  = [
            'shop_id',
            'sku',
            'price',
            'currency'
        ];

        foreach ($data as $item) {
            $el = (new ItemExport())->createItem($item, $dependence);
            if (!empty($el)) {
                $valid_items[] = $el;
            }
        }

        $count = count($valid_items);

        if ($count > 0) {
            Logger::log(
                       $count . ' valid items',
                       __METHOD__,
                       'info',
                alert: true
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
