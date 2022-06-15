<?php

declare(strict_types=1);

namespace WB\Modules\Adding;

use WB\Core\App;
use WB\Helpers\Logger;
use WB\Helpers\Validation\ItemExport;

require_once APP_ROOT . '/helpers/validation/ItemExport.php';
require_once APP_ROOT . '/modules/adding/Sku.php';

class AddingModule
{
    public function run()
    {
        $items = $this->getItems();

        if (empty($items)) {
            return;
        }

        $valid_items = $this->sanitizeAndValidation($items);

        if (empty($valid_items)) {
            return;
        }

        $items_with_sku = (new Sku())->create($valid_items);

        if (empty($items_with_sku)) {
            return;
        }

        $this->transferToAdding($items_with_sku);
    }

    private function getItems(): array
    {
        $where_rules = $this->getWhereRules();

        $sql = 'SELECT preload.asin,
                        preload.upc,
                        preload.product_name,
                        preload.brand,
                        preload.tax_code,
                        preload.short_description,
                        preload.shipping_weight,
                        preload.image,
                        preload.category,
                        preload.subcategory,
                        preload.gender,
                        preload.color,
                        preload.size, 
                        product.price_amazon, 
                        product.price_prime
                        FROM walmart_ca.item_preload AS preload
                        
                                 LEFT JOIN external_usa_bezoxx.usa_product AS product
                                           ON product.asin = preload.asin
                        
                                 LEFT JOIN walmart_ca.report AS report
                                           ON report.upc = preload.upc
                                               AND report.shop_id = :shop_id
                        
                                 LEFT JOIN walmart_ca.item_black AS black
                                           ON black.upc = preload.upc
                                               AND black.shop_id = :shop_id
                        
                        WHERE (
                                (report.sku IS NULL
                                    OR
                                 (report.sku IS NOT NULL 
                                      AND report.sku NOT LIKE \'%-WL000000-%\' 
                                      AND length(report.sku) = 39 
                                      AND report.publish_status <> \'PUBLISHED\' 
                                      AND black.sku IS NULL))
                        
                                -- preload
                                AND preload.asin IS NOT NULL
                                AND preload.upc IS NOT NULL
                        
                                -- content
                                AND length(preload.product_name) > 0
                                AND length(preload.brand) > 0
                                AND length(preload.short_description) > 0
                                AND length(preload.image) > 0
                                AND length(preload.category) > 0
                                AND length(preload.subcategory) > 0
                        
                                -- usa product price
                                AND (product.price_amazon::DECIMAL > 0
                                OR product.price_prime::DECIMAL > 0)
                        
                                -- custom rules
                                ' . $where_rules . ') 
                        
                           -- report, status UNPUBLISHED
                           OR ((report.sku IS NOT NULL 
                           AND report.sku NOT LIKE \'%-WL000000-%\' 
                           AND length(report.sku) = 39 
                           AND black.sku IS NULL)
                            AND report.price::DECIMAL > 0
                            AND report.publish_status = \'UNPUBLISHED\'
                            AND (product.price_amazon::DECIMAL > 0
                                OR product.price_prime::DECIMAL > 0))
                                
                                AND (preload.category IS NOT NULL 
                                    AND length(preload.category)>0 
                                    AND preload.category <> \'OtherCategory\')';

        $response = App::$db->run($sql, ['shop_id' => App::$shopId])
                            ->fetchAll();

        if (!isset($response) || !is_array($response)) {
            $message = 'Received 0 items';

            Logger::log($message, __METHOD__, 'info', alert: true);

            return [];
        }

        $message = 'Received ' . count($response) . ' items';

        Logger::log($message, __METHOD__, 'info', alert: true);

        return $response;
    }

    private function getWhereRules(): string
    {
        $types = [
            'asin'              => 'string',
            'upc'               => 'string',
            'sku'               => 'string',
            'product_name'      => 'string',
            'brand'             => 'string',
            'price'             => 'float',
            'tax_code'          => 'int',
            'short_description' => 'string',
            'shipping_weight'   => 'float',
            'image'             => 'string',
            'category'          => 'string',
            'subcategory'       => 'string'
        ];

        $sql = 'SELECT * FROM walmart_ca.rules WHERE shop_id = ?';

        $rules = App::$db->run($sql, [App::$shopId])->fetchAll();

        if (false === $rules || count($rules) <= 0) {
            return '';
        }

        $where_leave  = [];
        $where_remove = [];

        foreach ($rules as $item) {
            if ($item['condition'] === 'equal') {
                $value = ($types[$item['rule']] === 'string') ?
                    App::$db->quote($item['value']) : $item['value'];

                switch ($item['action']) {
                    case 'include':
                        $where_leave[] = 'PRELOAD.' . $item['rule'] . '='
                                         . $value;
                        break;
                    case 'exclude':
                        $where_remove[] = 'PRELOAD.' . $item['rule'] . '<>'
                                          . $value;
                        break;
                }
            } elseif ($item['condition'] === 'similar') {
                $value = App::$db->quote('(%' . $item['value'] . '%)');

                switch ($item['action']) {
                    case 'include':
                        $where_leave[] = 'PRELOAD.' . $item['rule']
                                         . ' SIMILAR TO ' . $value;
                        break;
                    case 'exclude':
                        $where_remove[] = 'PRELOAD.' . $item['rule']
                                          . ' NOT SIMILAR TO ' . $value;
                        break;
                }
            }
        }

        return ' AND (' . implode(' OR ', $where_leave) . ') 
        AND (' . implode(' OR ', $where_remove) . ')';
    }

    public function sanitizeAndValidation(array $items): array
    {
        $valid_items = [];

        $dependence = [
            'asin',
            'upc',
            'product_name',
            'brand',
            'tax_code',
            'short_description',
            'shipping_weight',
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
                $valid_items[] = $valid_item;
            }
        }

        $count = count($valid_items);

        if ($count > 0) {
            $message = $count . ' valid items';

            Logger::log($message, __METHOD__, 'info', alert: true);

            return $valid_items;
        }

        $message = 'Data failed validation and filtering';

        Logger::log($message, __METHOD__, 'info');

        return [];
    }

    public function transferToAdding(array $items): void
    {
        // delete by shop_id
        App::$db->run(
            'DELETE FROM walmart_ca.item_adding WHERE shop_id = ?',
            [App::$shopId]
        );

        $columns_table
            = 'shop_id,sku,upc,product_name,short_description,brand,shipping_weight,tax_code,image,category,subcategory,gender,color,size,price_amazon,price_prime';

        $columns_update
            = 'product_name=excluded.product_name,short_description=excluded.short_description,brand=excluded.brand,shipping_weight=excluded.shipping_weight,tax_code=excluded.tax_code,image=excluded.image,category=excluded.category,subcategory=excluded.subcategory,gender=excluded.gender,color=excluded.color,size=excluded.size,price_amazon=excluded.price_amazon,price_prime=excluded.price_prime';

        $chunk_items = array_chunk($items, 1000);
        $result      = 0;

        foreach ($chunk_items as $chunk_item) {
            $values = '';

            $sql = 'INSERT INTO walmart_ca.item_adding (' .
                   $columns_table . ') VALUES ';

            foreach ($chunk_item as $item) {
                $data = [
                    App::$shopId,
                    '\'' . $item['sku'] . '\'',
                    '\'' . $item['upc'] . '\'',
                    App::$db->quote($item['product_name']),
                    App::$db->quote($item['short_description']),
                    App::$db->quote($item['brand']),
                    $item['shipping_weight'],
                    $item['tax_code'],
                    App::$db->quote($item['image']),
                    '\'' . $item['category'] . '\'',
                    '\'' . $item['subcategory'] . '\'',
                    App::$db->quote($item['gender']),
                    App::$db->quote($item['color']),
                    '\'' . $item['size'] . '\'',
                    $item['price_amazon'],
                    $item['price_prime']
                ];

                $values .= '(' . implode(',', $data) . '),';
            }

            $values = trim($values, ',');

            $sql .= $values . ' ON CONFLICT (sku) DO UPDATE 
                    SET ' . $columns_update . '
                    RETURNING sku';

            $result += (int)App::$db->run($sql)->rowCount();
        }

        //$count = count( $result );
        if ($result < 1) {
            Logger::log(
                'Error: Items not copied to item_adding table',
                __METHOD__,
                'info'
            );

            return;
        }

        $sql = 'UPDATE walmart_ca.item_adding
                SET manufacturer_description=scfs.manufacturer_description,
                    band_color=scfs.band_color,
                    band_length=scfs.band_length,
                    band_material=scfs.band_material,
                    band_width=scfs.band_width,
                    age_group=scfs.age_group,
                    material=scfs.material,
                    battery=scfs.battery,
                    bezel_function=scfs.bezel_function,
                    bezel_material=scfs.bezel_material,
                    calendar=scfs.calendar,
                    case_diameter=scfs.case_diameter,
                    case_material=scfs.case_material,
                    case_thickness=scfs.case_thickness,
                    charge_time=scfs.charge_time,
                    dial_color=scfs.dial_color,
                    display_type=scfs.display_type,
                    model_year=scfs.model_year,
                    movement=scfs.movement,
                    screen=scfs.screen,
                    special_features=scfs.special_features,
                    warranty=scfs.warranty,
                    wash=scfs.wash,
                    water_resistant_depth=scfs.water_resistant_depth,
                    working_time=scfs.working_time,
                    wrist_strap=scfs.wrist_strap,
                    dimensions=scfs.dimensions,
                    dimensions_sum=scfs.dimensions_sum,
                    item_weight=scfs.item_weight
                
                FROM walmart_ca.scraper_content_from_search AS scfs
                WHERE item_adding.upc =
                      (SELECT upc
                       FROM walmart_ca.item_preload
                       WHERE scfs.asin = item_preload.asin)';

        App::$db->run($sql);

        $message = 'Recorded to item_adding: ' . $result;

        Logger::log($message, __METHOD__, 'info', alert: true);
    }
}
