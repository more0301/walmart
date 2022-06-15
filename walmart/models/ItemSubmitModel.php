<?php

declare(strict_types=1);

namespace Walmart\models;

use Walmart\core\App;
use Walmart\helpers\CurrencyConverter;
use Walmart\helpers\database\Database;
use Walmart\helpers\item\ItemExport;
use Walmart\helpers\Logger;
use Walmart\helpers\request\Curl;
use Walmart\helpers\request\RequestParameters;
use Walmart\helpers\xml\templates\ItemTemplate;
use Walmart\helpers\xml\XmlRead;

/**
 * Class SubmitItem
 *
 * @package Walmart\models\submit
 */
class ItemSubmitModel
{
    use ItemExport;

    public function getDataToSend()
    {
        $sql = 'SELECT * 
                FROM walmart_ca.item_adding 
                WHERE is_send = FALSE AND shop_id=' . App::$shopId;

        $data = Database::request($sql, __METHOD__, true);

        if (count($data) > 0) {
            return $data;
        }

        Logger::log('Data import not received', __METHOD__);

        return false;
    }

    public function validationData(array $items)
    {
        $valid_items = [];
        $dependence  = [
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

        foreach ($items as $key => $item) {
            //if ($item['upc']=='881988363741') {
            //    var_dump($item);
            //    exit();
            //}

            if (true === App::$debug) {
                if (!isset($start_time)) {
                    $start_time = time();
                }
                if ($key % 1000 === 0) {
                    var_dump($key, (time() - $start_time));
                    echo PHP_EOL;
                    $start_time = null;
                }
            }

            $valid_item = $this->createItem($item, $dependence);

            if (false !== $valid_item) {
                $valid_items[] = array_merge($item, $valid_item);
            }
        }

        // TEST
        //        file_put_contents( __DIR__ . '/1804.txt',
        //            implode( PHP_EOL, array_column( $valid_items, 'sku' ) ) );

        if (count($valid_items) > 0) {
            return $valid_items;
        }

        Logger::log('Data failed validation and filtering', __METHOD__);

        return false;
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

    public function setRequestParameters()
    {
        $parameters = RequestParameters::exec('item_submit');

        if (false === $parameters) {
            Logger::log('Error creating request data', __METHOD__);

            return false;
        }

        return $parameters;
    }

    public function createXmlFeed(array $data_valid)
    {
        $xml_feed = ItemTemplate::getFeed($data_valid);

        //file_put_contents( '/home/svb_1xpp7xdfizlq-5-g/walmart/logs/1/xml_parts/' . time() . '.xml', $xml_feed );
        //file_put_contents( '/home/svb_1xpp7xdfizlq-5-g/walmart/logs/1/xml_parts/' . time() . '.txt', serialize( $data_valid ) );

        if (false === $xml_feed) {
            Logger::log('Error preparing feed', __METHOD__);

            return false;
        }

        //if ( false === XmlValid::exec( $xml_feed ) ) {
        //    Logger::log( 'Xml feed failed validation', __METHOD__ );
        //
        //    return false;
        //}

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

        $response = is_string($response) ? $response : 'error';

        /*$response = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><ns2:FeedAcknowledgement xmlns:ns2="http://walmart.com/"><ns2:feedId>A89B999173634321A9448B630D43A689@AQMBCgA</ns2:feedId></ns2:FeedAcknowledgement>';*/

        $feed = XmlRead::exec($response, App::$walmartXmlNs);
        //$feed = $response;

        // debug
        if (true === App::$debug) {
            echo '##### RESPONSE #####' . PHP_EOL;
            print_r($response);

            //file_put_contents( APP_ROOT . '/logs/' . App::$shopId .
            //    '/xml_parts/' . date( 'Y-m-d_H:i:s' ) . '_response.txt',
            //    $response );
        }

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

    public function addResponseFeedToDb(string $feed, int $total_items)
    {
        $sql = 'INSERT INTO ' . App::$dbSchema . '.' . App::$itemFeedT . ' 
        (shop_id,feed_id,items) 
        VALUES (' . App::$shopId . ',\'' . $feed . '\',' . $total_items . ') 
        ON CONFLICT DO NOTHING
        RETURNING feed_id';

        $result = Database::request($sql, __METHOD__, true);

        if (isset($result[0]['feed_id']) && $result[0]['feed_id'] === $feed) {
            Logger::log(
                'Feed ' . $feed . ' recorded in the ' . App::$itemFeedT
                . ' table',
                __METHOD__
            );

            return true;
        }

        Logger::log(
            'Feed ' . $feed . ' not recorded in the ' . App::$itemFeedT
            . ' table',
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

        $result = Database::request($sql, __METHOD__, true);

        $count = count($result);
        Logger::log(
            $count . ' prices recorded in the ' .
            App::$lastSentPricesT . ' table',
            __METHOD__
        );

        return $count;
    }

    public function isSendUpdate(array $data)
    {
        $sku_list = implode(
            ',',
            array_map(
                fn($val) => '\'' . $val . '\'',
                array_column($data, 'sku')
            )
        );

        $sql = 'UPDATE walmart_ca.item_adding 
                    SET is_send = TRUE
                    WHERE sku IN (' . $sku_list . ')';

        Database::request($sql, __METHOD__, false);
    }
}
