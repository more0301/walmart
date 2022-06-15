<?php

declare(strict_types=1);

namespace Walmart\models;

use Walmart\core\App;
use Walmart\helpers\database\Database;
use Walmart\helpers\database\Db;
use Walmart\helpers\item\ItemExport;
use Walmart\helpers\Logger;
use Walmart\helpers\request\Curl;
use Walmart\helpers\request\RequestParameters;
use Walmart\helpers\xml\templates\InventoryTemplate;
use Walmart\helpers\xml\XmlRead;
use Walmart\helpers\xml\XmlValid;

/**
 * Class SubmitInventory
 *
 * @package Walmart\models\submit
 */
class InventorySubmitModel
{
    use ItemExport;

    private Db $db;

    public function __construct()
    {
        $this->db = new Db();
    }

    /**
     * @return array|bool|mixed
     */
    public function getDataToSend()
    {
        // выбрать из inventory_adding
        // нет в inventory_success
        // или изменились данные

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
                
          OR (report.shop_id = ' . App::$shopId . '
           AND
       (report.price::DECIMAL = price_amazon::DECIMAL
           AND report.inventory_count IS NULL)
    OR (report.price::DECIMAL = price_prime::DECIMAL
        AND report.inventory_count IS NULL)
       )
        
          AND black.sku IS NULL';

        $data = $this->db->run(
            $sql,
            [
                ':shop_id'               => App::$shopId,
                ':cad_rate'              => App::$cadRate,
                ':markup'                => App::$markup,
                ':inventory_fulfillment' => App::$inventoryFulfillment
            ]
        )->fetchAll();

        //$data = Database::request($sql, __METHOD__, true);

        if (count($data) <= 0) {
            Logger::log('Data import not received', __METHOD__);

            return false;
        }

        return $data;
    }

    /**
     * @param array $items
     *
     * @return array|bool
     */
    public function validationData(array $items)
    {
        $valid_items = [];
        $dependence  = [
            'sku',
            'inventory_unit',
            'inventory_amount',
            'inventory_fulfillment'
        ];

        foreach ($items as $item) {
            $valid_item = $this->createItem($item, $dependence);
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

    public function setRequestParameters()
    {
        $parameters = RequestParameters::exec('inventory_update');

        if (false === $parameters) {
            Logger::log('Error creating request data', __METHOD__);

            return false;
        }

        return $parameters;
    }

    public function createXmlFeed(array $data_convert)
    {
        $xml_feed = InventoryTemplate::getFeed($data_convert);

        if (false === $xml_feed || !is_string($xml_feed)) {
            $data_serialize = is_array($data_convert)
                ?
                serialize($data_convert)
                :
                'data_convert is ' . gettype($data_convert);

            Logger::log(
                'Error preparing feed. ' . $data_serialize,
                __METHOD__
            );

            return false;
        }

        if (false === XmlValid::exec($xml_feed)) {
            Logger::log(
                'Xml feed failed validation',
                __METHOD__
            );

            return false;
        }

        return $xml_feed;
    }

    public function sendData(array $request_parameters, string $xml_feed)
    {
        $response = Curl::postRequest(
            $request_parameters['url'],
            $request_parameters['sign'],
            $request_parameters['timestamp'],
            $xml_feed
        );

        $feed = XmlRead::exec($response, App::$walmartXmlNs);

        if (isset($feed['feedId']) && is_string($feed['feedId'])) {
            $feed_id = filter_var(
                trim($feed['feedId']),
                FILTER_SANITIZE_STRING
            );
        }

        if (!isset($feed_id) || false === $feed_id || empty($feed_id)) {
            $feed_id = 'Feed id not received';
            Logger::log('Error validating feed id', __METHOD__);
        }

        return $feed_id;
    }

    /**
     * @param string $feed
     * @param int    $total_items
     *
     * @return array|mixed
     */
    public function recordResponseFeed(string $feed, int $total_items)
    {
        $sql = 'INSERT INTO walmart_ca.inventory_feed 
        (shop_id,feed_id,items) 
        VALUES (' . App::$shopId . ',\'' . $feed . '\',' . $total_items . ') 
        ON CONFLICT DO NOTHING
        RETURNING feed_id';

        $result = Database::request($sql, __METHOD__, true);

        if (isset($result[0]['feed_id']) && $result[0]['feed_id'] === $feed) {
            Logger::log(
                'Feed ' . $feed . ' recorded in the ' .
                App::$inventoryFeedT . ' table',
                __METHOD__
            );

            return true;
        }

        Logger::log(
            'Feed ' . $feed . ' not recorded in the ' .
            App::$inventoryFeedT . ' table',
            __METHOD__
        );

        return false;
    }

    public function isSendUpdate(array $data)
    {
        if (count($data) > 80) {
            $sku_list = 'VALUES ' . implode(
                    ',',
                    array_map(
                        fn($val) => '(\'' . $val . '\')',
                        array_column($data, 'sku')
                    )
                );
        } else {
            $sku_list = implode(
                ',',
                array_map(
                    fn($val) => '\'' . $val . '\'',
                    array_column($data, 'sku')
                )
            );
        }

        $sql = 'UPDATE walmart_ca.report_copy 
                    SET is_send_inventory = TRUE
                    WHERE sku IN (' . $sku_list . ')';

        Database::request($sql, __METHOD__, false);
    }
}
