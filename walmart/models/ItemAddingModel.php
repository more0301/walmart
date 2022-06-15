<?php

declare(strict_types=1);

namespace Walmart\models;

use Walmart\core\App;
use Walmart\helpers\database\Database;
use Walmart\helpers\database\Db;
use Walmart\helpers\item\ItemExport;
use Walmart\helpers\Logger;
use Walmart\helpers\Sku;

/**
 * Class PreloadTransferItem
 *
 * @package Walmart\models\preload\transfer
 */
class ItemAddingModel
{
    use ItemExport;

    /**
     * @inheritDoc
     */
    public function getReadyItems()
    {
        $columns_array = App::getTableColumns(
            App::$itemPreloadT,
            false,
            true,
            ['date_create']
        );

        $where_rules = $this->getWhereRules();
        $where_rules = false !== $where_rules ? $where_rules : '';

        $columns_with_table = array_map(
            fn($val) => 'preload.' . $val,
            $columns_array
        );

        $columns_table = implode(',', $columns_with_table);

        $sql = 'SELECT ' . $columns_table . ', product.price_amazon, product.price_prime
FROM walmart_ca.item_preload preload

         LEFT JOIN external_usa_bezoxx.usa_product as product
                   ON product.asin = preload.asin

         LEFT JOIN walmart_ca.report as report
                   ON report.upc = preload.upc
                       AND report.shop_id = ' . App::$shopId . '

         LEFT JOIN walmart_ca.item_black as black
                   ON black.upc = preload.upc
                       AND black.shop_id = ' . App::$shopId . '

WHERE (
        (report.sku IS NULL
            OR
         (report.sku IS NOT NULL AND report.sku NOT LIKE \'%-WL000000-%\' AND
          length(report.sku) = 39 AND report.publish_status <> \'PUBLISHED\' AND
          black.sku IS NULL))

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

        -- required gender, color, size
        --AND (length(preload.gender) > 0
        --AND length(preload.color) > 0
        --AND (length(preload.size) > 0))

        -- usa product price
        AND (product.price_amazon::DECIMAL > 0
        OR product.price_prime::DECIMAL > 0)

        -- custom rules
        ' . $where_rules . ') 

   -- report, status UNPUBLISHED
   OR ((report.sku IS NOT NULL AND report.sku NOT LIKE \'%-WL000000-%\' AND
        length(report.sku) = 39 AND
        black.sku IS NULL)
    AND report.price::DECIMAL > 0
    AND report.publish_status = \'UNPUBLISHED\'
    AND (product.price_amazon::DECIMAL > 0
        OR product.price_prime::DECIMAL > 0))
        
        AND (preload.category IS NOT NULL 
            AND length(preload.category)>0 
            AND preload.category <> \'OtherCategory\')';

        $items = Database::request($sql, __METHOD__, true);

        if (count($items) <= 0) {
            Logger::log(
                'Items from table ' . App::$itemPreloadT . ' not received',
                __METHOD__
            );

            return false;
        }

        return $items;
    }

    public function sanitizeAndValidation(array $items)
    {
        $valid_items = [];
        $dependence  = [
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
            $valid_item = $this->createItem($item, $dependence);

            //if ($item['upc']=='881988363741') {
            //    var_dump($valid_item);
            //    exit();
            //}

            if (false !== $valid_item) {
                $valid_items[] = $valid_item;
            }
        }

        if (count($valid_items) > 0) {
            return $valid_items;
        }

        Logger::log('Data failed validation and filtering', __METHOD__);

        return false;
    }

    /**
     * @param array $items
     *
     * @return array|bool
     */
    public function setSku(array $items)
    {
        $sku            = new Sku();
        $items_with_sku = $sku->create($items);

        $count = count($items_with_sku);
        if ($count > 0) {
            Logger::log($count . ' sku generated for items', __METHOD__);

            return $items_with_sku;
        }

        Logger::log('Error sku generated', __METHOD__);

        return false;
    }

    /**
     * @param array $items
     *
     * @return bool|int
     */
    public function transferToAdding(array $items)
    {
        // delete by shop_id
        $sql = 'DELETE FROM walmart_ca.item_adding 
                WHERE shop_id=' . App::$shopId;
        Database::request($sql, __METHOD__);

        $columns_table
            = 'shop_id,sku,upc,product_name,short_description,brand,shipping_weight,tax_code,image,category,subcategory,gender,color,size,price_amazon,price_prime';

        $columns_update
            = 'product_name=excluded.product_name,short_description=excluded.short_description,brand=excluded.brand,shipping_weight=excluded.shipping_weight,tax_code=excluded.tax_code,image=excluded.image,category=excluded.category,subcategory=excluded.subcategory,gender=excluded.gender,color=excluded.color,size=excluded.size,price_amazon=excluded.price_amazon,price_prime=excluded.price_prime';

        $chunk_items = array_chunk($items, 1000);
        $result      = 0;

        $db = new Db();

        foreach ($chunk_items as $chunk_item) {
            $values = '';

            $sql = 'INSERT INTO walmart_ca.item_adding (' .
                   $columns_table . ') VALUES ';

            foreach ($chunk_item as $item) {
                $data = [
                    App::$shopId,
                    '\'' . $item['sku'] . '\'',
                    '\'' . $item['upc'] . '\'',
                    App::dbh()->quote($item['product_name']),
                    App::dbh()->quote($item['short_description']),
                    App::dbh()->quote($item['brand']),
                    $item['shipping_weight'],
                    $item['tax_code'],
                    App::dbh()->quote($item['image']),
                    '\'' . $item['category'] . '\'',
                    '\'' . $item['subcategory'] . '\'',
                    App::dbh()->quote($item['gender']),
                    App::dbh()->quote($item['color']),
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

            //$result = Database::request( $sql, __METHOD__, true );
            $result += (int)$db->run($sql)->rowCount();
        }

        //$count = count( $result );
        if ($result < 1) {
            Logger::log(
                'Error: Items not copied to ' . App::$itemAddingT . ' table',
                __METHOD__
            );

            return false;
        }

        $sql = 'UPDATE walmart_ca.item_adding
                SET manufacturer_description=scraper_content_from_search.manufacturer_description,
                    band_color=scraper_content_from_search.band_color,
                    band_length=scraper_content_from_search.band_length,
                    band_material=scraper_content_from_search.band_material,
                    band_width=scraper_content_from_search.band_width,
                    age_group=scraper_content_from_search.age_group,
                    material=scraper_content_from_search.material,
                    battery=scraper_content_from_search.battery,
                    bezel_function=scraper_content_from_search.bezel_function,
                    bezel_material=scraper_content_from_search.bezel_material,
                    calendar=scraper_content_from_search.calendar,
                    case_diameter=scraper_content_from_search.case_diameter,
                    case_material=scraper_content_from_search.case_material,
                    case_thickness=scraper_content_from_search.case_thickness,
                    charge_time=scraper_content_from_search.charge_time,
                    dial_color=scraper_content_from_search.dial_color,
                    display_type=scraper_content_from_search.display_type,
                    model_year=scraper_content_from_search.model_year,
                    movement=scraper_content_from_search.movement,
                    screen=scraper_content_from_search.screen,
                    special_features=scraper_content_from_search.special_features,
                    warranty=scraper_content_from_search.warranty,
                    wash=scraper_content_from_search.wash,
                    water_resistant_depth=scraper_content_from_search.water_resistant_depth,
                    working_time=scraper_content_from_search.working_time,
                    wrist_strap=scraper_content_from_search.wrist_strap,
                    dimensions=scraper_content_from_search.dimensions,
                    dimensions_sum=scraper_content_from_search.dimensions_sum,
                    item_weight=scraper_content_from_search.item_weight
                
                FROM walmart_ca.scraper_content_from_search
                WHERE item_adding.upc =
                      (SELECT upc
                       FROM walmart_ca.item_preload
                       WHERE scraper_content_from_search.asin = item_preload.asin)';

        $db->run($sql);

        return $result;
    }

    /**
     * Where from the 'rules' table to select from the 'item_preload' table
     *
     * @return string
     */
    private function getWhereRules()
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

        $sql   = 'SELECT * FROM walmart_ca.rules WHERE shop_id=' . App::$shopId;
        $rules = Database::request($sql, __METHOD__, true);

        if (false === $rules || count($rules) <= 0) {
            return false;
        }

        $where_leave  = [];
        $where_remove = [];

        foreach ($rules as $item) {
            if ($item['condition'] === 'equal') {
                $value = ($types[$item['rule']] === 'string') ?
                    App::$dbh->quote($item['value']) : $item['value'];

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
                $value = App::$dbh->quote('(%' . $item['value'] . '%)');

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
}
