<?php

declare(strict_types=1);

namespace Walmart\controllers;

use Walmart\core\App;
use Walmart\core\Controller;
use Walmart\helpers\database\Database;
use Walmart\models\ItemSubmitModel;

/**
 * Class ItemSubmitController
 *
 * @package Walmart\controllers\submit
 */
class ItemSubmitController extends Controller
{
    /**
     * @return bool
     */
    public function start()
    {
        $items = $this->getDataToSend();

        if (!$items) {
            App::$doNotSendTelegram = true;

            return false;
        }

        $data_valid = $this->validationData($items);

        if (empty($data_valid)) {
            return false;
        }

        $data_merge = $this->mergePrices($data_valid);

        if (empty($data_merge)) {
            return false;
        }

        $data_convert = $this->convertPrice($data_merge);

        if (empty($data_convert)) {
            return false;
        }

        $item_parts = array_chunk($data_convert, App::$maxItemSubmit);
        $item_parts = array_slice($item_parts, 0, 10);

        //$item_parts = unserialize( file_get_contents( '/home/svb_1xpp7xdfizlq-5-g/walmart/logs/1/item_parts.txt' ) );

        $last_key = array_key_last($item_parts);

        // total sent items
        $sent = 0;

        // delete by shop_id
        $sql_del = 'DELETE FROM walmart_ca.last_sent_prices 
                    WHERE shop_id=' . App::$shopId;
        Database::request($sql_del, __METHOD__);

        // TEST
        //file_put_contents( APP_ROOT . '/logs/' . App::$shopId .
        //    '/item_parts.txt', serialize( $item_parts ) );

        foreach ($item_parts as $key => $part) {
            // debug
            if (true === App::$debug) {
                //file_put_contents( APP_ROOT . '/logs/' . App::$shopId .
                //    '/xml_parts/' . date( 'Y-m-d_H:i:s_part' ) . '.txt',
                //    serialize( $part ) );
            }

            $xml_feed = $this->createXmlFeed($part);

            // debug
            if (true === App::$debug) {
                //file_put_contents( APP_ROOT . '/logs/' . App::$shopId .
                //    '/xml_parts/test.xml',
                //    $xml_feed );
                //exit();
            }

            if (!$xml_feed && $key === $last_key && $sent === 0) {
                App::telegramLog('Sent 0 items');

                return false;
            } elseif (!$xml_feed) {
                continue;
            }

            $request_parameters = $this->setRequestParameters();

            if (!$request_parameters && $key === $last_key && $sent === 0) {
                App::telegramLog('Sent 0 items');

                return false;
            } elseif (!$request_parameters) {
                continue;
            }

            $response_feed = $this->sendData($request_parameters, $xml_feed);

            // TEST: log xml
            $xml_log_name = 'item_submit_';
            $xml_log_name .= $response_feed ? $response_feed . '_' . date(
                    'Y-m-d_H:i:s'
                ) : date('Y-m-d_H:i:s');
            $xml_log_name .= '.xml';

            file_put_contents(
                APP_ROOT . '/logs/' . App::$shopId .
                '/xml/' . $xml_log_name,
                $xml_feed
            );
            // TEST end

            if ($response_feed) {
                $count_part = count($part);

                $this->recordResponseFeed($response_feed, $count_part);

                $this->recordSentPrices($part);

                $this->model->isSendUpdate($part);

                App::telegramLog('Items sent. Feed id received');

                $sent += $count_part;
            } else {
                App::telegramLog('Error sending items');
            }

            sleep(5);
        }

        App::telegramLog('Sent ' . $sent . ' items');

        return true;
    }

    private function getDataToSend()
    {
        $items = $this->model->getDataToSend();

        if (false === $items) {
            return false;
        }

        $count_items = count($items);

        App::telegramLog('Items to send: ' . $count_items);

        // debug
        if (true === App::$debug) {
            echo PHP_EOL . 'Items to send: ' . $count_items . PHP_EOL;

            $continue = readline('Next validation data. Continue? (y/n): ');
            if ($continue === 'n' || $continue !== 'y') {
                return false;
            }
        }

        return $items;
    }

    private function validationData($items): array
    {
        if (true === App::$debug) {
            $continue = readline('Validate? (y/n): ');
            if ($continue === 'n') {
                return $items;
            }
        }

        $data_valid = $this->model->validationData($items);

        if (!$data_valid) {
            return [];
        }
        $items = null;

        $count_valid = count($data_valid);

        App::telegramLog('Items valid: ' . $count_valid);

        // debug
        if (true === App::$debug) {
            echo PHP_EOL . 'Items valid: ' . $count_valid . PHP_EOL;

            $continue = readline('Next merge prices. Continue? (y/n): ');
            if ($continue === 'n' || $continue !== 'y') {
                return [];
            }
        }

        return $data_valid;
    }

    private function mergePrices($data)
    {
        $result = $this->model->mergePriceAmazonAndPrime($data);

        if (false === $result) {
            return [];
        }

        $count_data = count($result);

        App::telegramLog('Items to send after price merging: ' . $count_data);

        // debug
        if (true === App::$debug) {
            echo PHP_EOL . 'Items to send after price merging: ' . $count_data
                 . PHP_EOL;

            $continue = readline('Next convert price. Continue? (y/n): ');
            if ($continue === 'n' || $continue !== 'y') {
                return [];
            }
        }

        return $result;
    }

    private function convertPrice($data)
    {
        $data_convert = $this->model->convertPrice($data);

        if (false === $data_convert) {
            return [];
        }

        $count_convert = count($data_convert);

        App::telegramLog('Price converted: ' . $count_convert);

        // debug
        if (true === App::$debug) {
            echo PHP_EOL . 'Price converted: ' . $count_convert . PHP_EOL;

            $continue = readline('Next create xml feed. Continue? (y/n): ');
            if ($continue === 'n' || $continue !== 'y') {
                return [];
            }
        }

        return $data_convert;
    }

    private function createXmlFeed($part)
    {
        //$xml_feed = $this->model->createXmlFeed( $part );
        $model = new ItemSubmitModel();
        $xml_feed = $model->createXmlFeed($part);

        if (!$xml_feed) {
            return false;
        }

        // debug
        if (true === App::$debug) {
            echo PHP_EOL . 'Xml feed: ', substr($xml_feed, 0, 10000) . PHP_EOL;

            $continue = readline(
                'Next set request parameters. Continue? (y/n): '
            );
            if ($continue === 'n' || $continue !== 'y') {
                return false;
            }
        }

        return $xml_feed;
    }

    private function setRequestParameters()
    {
        $request_parameters = $this->model->setRequestParameters();

        if (!$request_parameters) {
            return false;
        }

        // debug
        if (true === App::$debug) {
            echo PHP_EOL . 'Request parameters:',
                PHP_EOL . implode(PHP_EOL, $request_parameters) . PHP_EOL;

            $continue = readline('Next send data. Continue? (y/n): ');
            if ($continue === 'n' || $continue !== 'y') {
                return false;
            }
        }

        return $request_parameters;
    }

    private function sendData($request_parameters, $xml_feed)
    {
        $response_feed = $this->model->sendData($request_parameters, $xml_feed);

        if ($response_feed) {
            // debug
            if (true === App::$debug) {
                echo PHP_EOL . 'Response_feed:' . $response_feed . PHP_EOL;

                $continue = readline(
                    'Next record response feed. Continue? (y/n): '
                );
                if ($continue === 'n' || $continue !== 'y') {
                    return false;
                }
            }
        }

        return $response_feed;
    }

    private function recordResponseFeed($response_feed, $count_part)
    {
        $result_record_feed = $this->model->addResponseFeedToDb(
            $response_feed,
            $count_part
        );

        if (true === App::$debug) {
            if (false !== $result_record_feed) {
                echo PHP_EOL . 'Response feed ' . $response_feed .
                     ' recorded in the ' . App::$itemFeedT . ' table' . PHP_EOL;

                $continue = readline(
                    'Next record sent prices. Continue? (y/n): '
                );
                if ($continue === 'n' || $continue !== 'y') {
                    return false;
                }
            }
        }
    }

    private function recordSentPrices($data)
    {
        $result_record_set_prices = $this->model->addSentPricesToDb($data);

        if (true === App::$debug) {
            if (false !== $result_record_set_prices) {
                echo PHP_EOL . 'Record to db ' . $result_record_set_prices .
                     ' prices in the ' . App::$lastSentPricesT . ' table'
                     . PHP_EOL;
            }
        }
    }
}
