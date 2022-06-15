<?php

declare(strict_types=1);

namespace Walmart\models;

use Walmart\core\App;
use Walmart\helpers\CurrencyConverter;
use Walmart\helpers\database\Database;
use Walmart\helpers\database\Db;
use Walmart\helpers\item\ItemExport;
use Walmart\helpers\Logger;
use Walmart\helpers\request\Curl;
use Walmart\helpers\request\RequestParameters;
use Walmart\helpers\xml\templates\PriceTemplate;
use Walmart\helpers\xml\XmlRead;
use Walmart\helpers\xml\XmlValid;

/**
 * Class SubmitPrice
 *
 * @package Walmart\models\submit
 */
class PriceSubmitModel
{
    use ItemExport;

    private Db $db;

    public function __construct()
    {
        $this->db = new Db();
    }

    public function getDataToSend()
    {
        // not in price_total_sent
        // usa_product price> 0 (from the items_price_appeared table)
        // is in price_error
        // not in retire

        $sql = 'SELECT :shop_id AS shop_id,
               report.sku,
               product.price_amazon,
               product.price_prime,
               \'USD\' AS currency
        FROM walmart_ca.report_copy report
        
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

        //var_dump($sql);
        //var_dump(
        //    [
        //        ':shop_id'  => App::$shopId,
        //        ':cad_rate' => App::$cadRate,
        //        ':markup'   => App::$markup
        //    ]
        //);
        //exit();

        $data = $this->db->run(
            $sql,
            [
                ':shop_id'  => App::$shopId,
                ':cad_rate' => App::$cadRate,
                ':markup'   => App::$markup
            ]
        )->fetchAll();
        //$data = Database::request( $sql, __METHOD__, true );

        //var_dump(count($data));
        //exit();

        if (!isset($data) || !is_array($data) || count($data) <= 0) {
            Logger::log('Data import not received', __METHOD__);

            return false;
        }

        return $data;
    }

    public function mergePriceAmazonAndPrime(array $data)
    {
        foreach ($data as &$item) {
            $amazon = (float)$item['price_amazon'];
            $prime  = (float)$item['price_prime'];

            $item['price'] = $amazon > 0
                ?
                $amazon
                : ($prime > 0 ?
                    $prime : 0);
        }

        if (count($data) <= 0) {
            Logger::log('Price merge error', __METHOD__);

            return false;
        }

        return $data;
    }

    public function convertPrice(array $data)
    {
        $data_convert = CurrencyConverter::convertAndMarkup($data);

        if (count($data_convert) <= 0) {
            Logger::log('Price conversion error', __METHOD__);

            return false;
        }

        return $data_convert;
    }

    /**
     * Including the price is equal to the minimum specified in the settings
     *
     * @inheritDoc
     */
    public function validationData(array $items)
    {
        $valid_items = [];
        $dependence  = [
            'shop_id',
            'sku',
            'price',
            'currency'
        ];

        foreach ($items as $item) {
            $el = $this->createItem($item, $dependence);
            if (false !== $el) {
                $valid_items[] = $el;
            }
        }

        if (count($valid_items) > 0) {
            return $valid_items;
        }

        Logger::log('Data failed validation and filtering', __METHOD__);

        return false;
    }

    /**
     * @inheritDoc
     */
    public function setRequestParameters()
    {
        $parameters = RequestParameters::exec('price_update');

        if (false === $parameters) {
            Logger::log('Error creating request data', __METHOD__);

            return false;
        }

        return $parameters;
    }

    /**
     * @inheritDoc
     */
    public function createXmlFeed(array $data)
    {
        $xml_feed = PriceTemplate::getFeed($data);

        if (false === $xml_feed) {
            Logger::log('Error preparing feed', __METHOD__);

            return false;
        }

        if (false === XmlValid::exec($xml_feed)) {
            Logger::log('Xml feed failed validation', __METHOD__);

            return false;
        }

        return $xml_feed;
    }

    /**
     * @inheritDoc
     */
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
        $sql = 'INSERT INTO ' . App::$dbSchema . '.' . App::$priceFeedT . ' 
        (shop_id,feed_id,items) 
        VALUES (' . App::$shopId . ',\'' . $feed . '\',' . $total_items . ') 
        ON CONFLICT DO NOTHING
        RETURNING feed_id';

        $result = Database::request($sql, __METHOD__, true);

        if (isset($result[0]['feed_id']) && $result[0]['feed_id'] === $feed) {
            Logger::log(
                'Feed ' . $feed . ' recorded in the ' .
                App::$priceFeedT . ' table',
                __METHOD__
            );

            return true;
        }

        Logger::log(
            'Feed ' . $feed . ' not recorded in the ' .
            App::$priceFeedT . ' table',
            __METHOD__
        );

        return false;
    }

    public function addSentPricesToDb(array $data)
    {
        $sql = 'INSERT INTO ' . App::$dbSchema . '.' . App::$lastSentPricesT . ' 
        (shop_id,sku,price) VALUES';

        $values = '';
        foreach ($data as $item) {
            $values .= '(' . $item['shop_id'] . ',\'' . $item['sku'] . '\',' .
                       $item['price'] . '),';
        }

        $values = trim($values, ',');
        $sql    .= $values . ' ON CONFLICT DO NOTHING RETURNING sku';

        //$result = Database::request( $sql, __METHOD__, true );
        $result = $this->db->run($sql)->rowCount();

        //$count = count( $result );
        $count = (int)$result;

        Logger::log(
            $count . ' prices recorded in the ' .
            App::$lastSentPricesT . ' table',
            __METHOD__
        );

        return $count;
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
                    SET is_send_price = TRUE
                    WHERE sku IN (' . $sku_list . ')';

        //Database::request( $sql, __METHOD__, false );
        $this->db->run($sql);
    }
}
