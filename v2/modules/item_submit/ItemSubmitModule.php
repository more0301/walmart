<?php

declare(strict_types=1);

namespace WB\Modules\ItemSubmit;

use WB\Core\App;
use WB\Helpers\Alert;
use WB\Helpers\Logger;
use WB\Helpers\Validation\ItemExport;

require_once APP_ROOT . '/helpers/validation/ItemExport.php';
require_once APP_ROOT . '/modules/item_submit/SubmitCycle.php';

class ItemSubmitModule
{
    public string $requestMethod = 'POST';
    public string $requestUrl    = 'https://marketplace.walmartapis.com/v3/ca/feeds?feedType=item';

    public function run()
    {
        $items = $this->getItems();

        if (empty($items)) {
            return;
        }

        $data_valid = $this->validationData($items);

        if (empty($data_valid)) {
            return;
        }

        $data_merge = $this->mergePrice($data_valid);

        if (empty($data_merge)) {
            return;
        }

        $sent = (new SubmitCycle())->cycle(
            $this->convertAndMarkup($data_merge)
        );

        Logger::log(
                   'Sent ' . $sent . ' items',
                   __METHOD__,
                   'info',
            alert: true
        );
    }

    private function getItems(): array
    {
        $sql = 'SELECT * 
                FROM walmart_ca.item_adding 
                WHERE is_send = FALSE AND shop_id = ?';

        $data = App::$db->run($sql, [App::$shopId])->fetchAll();

        if (isset($data) && is_array($data) && !empty($data)) {
            $message = 'Received ' . count($data) . ' items';

            Logger::log($message, __METHOD__, 'info', alert: true);

            return $data;
        }

        $message = 'Data import not received';

        Logger::log($message, __METHOD__, 'info', alert: true);

        return [];
    }

    private function validationData(array $items): array
    {
        $valid_items = [];

        $dependence = [
            'shop_id',
            'sku',
            'upc',
            'product_name',
            'short_description',
            'brand',
            'shipping_weight',
            'tax_code',
            'image',
            'category',
            'subcategory',
            'gender',
            'color',
            'size',
            'price_amazon',
            'price_prime'
        ];

        foreach ($items as $item) {
            $valid_item = (new ItemExport())->createItem($item, $dependence);

            if (!empty($valid_item)) {
                $valid_items[] = array_merge($item, $valid_item);
            }
        }

        if (empty($valid_items)) {
            Logger::log(
                       'Data failed validation and filtering',
                       __METHOD__,
                       'info'
            );

            return [];
        }

        Logger::log(
                   count($valid_items) . ' valid items',
                   __METHOD__,
                   'info',
            alert: true
        );

        return $valid_items;
    }

    private function mergePrice(array $data): array
    {
        foreach ($data as &$item) {
            $amazon = (float)$item['price_amazon'];

            $item['price'] = $amazon > 0 ?
                $amazon : (max((float)$item['price_prime'], 0));
        }

        if (count($data) <= 0) {
            Logger::log('Price merge error', __METHOD__, 'info');

            return [];
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
}
