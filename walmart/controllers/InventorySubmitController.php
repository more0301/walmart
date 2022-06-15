<?php

declare(strict_types=1);

namespace Walmart\controllers;

use Walmart\core\App;
use Walmart\core\Controller;

/**
 * Class InventorySubmitController
 *
 * @package Walmart\controllers\submit
 */
class InventorySubmitController extends Controller
{
    public function start()
    {
        $data = $this->getDataToSend();

        if (false === $data) {
            App::$doNotSendTelegram = true;

            return false;
        }

        $data_valid = $this->validationData($data);

        if (false === $data_valid) {
            return false;
        }

        $inventory_parts = array_chunk(
            (array)$data_valid,
            App::$maxInventorySubmit
        );
        $inventory_parts = array_slice($inventory_parts, 0, 10);
        $last_key        = array_key_last($inventory_parts);

        // total sent items
        $sent = 0;

        foreach ($inventory_parts as $key => $part) {
            $request_parameters = $this->setRequestParameters();

            if (false === $request_parameters && $key === $last_key
                && $sent === 0) {
                App::telegramLog('Sent 0 items');

                return false;
            } elseif (false === $request_parameters) {
                continue;
            }

            $xml_feed = $this->createXmlFeed($part);

            if (false === $xml_feed && $key === $last_key && $sent === 0) {
                App::telegramLog('Sent 0 items');

                return false;
            } elseif (false === $xml_feed) {
                continue;
            }

            $response_feed = $this->sendData($request_parameters, $xml_feed);

            if (false !== $response_feed) {
                $count_part = count($part);

                $this->recordResponseFeed($response_feed, $count_part);

                $this->model->isSendUpdate($part);

                App::telegramLog('Inventories sent. Feed id received');

                $sent += $count_part;
            } else {
                App::telegramLog('Error sending inventories');
            }
        }

        App::telegramLog('Sent ' . $sent . ' items');

        return true;
    }

    private function getDataToSend()
    {
        $data = $this->model->getDataToSend();

        if (false === $data) {
            return false;
        }

        $count_data = count($data);

        App::telegramLog('Inventories to send: ' . $count_data);

        // debug
        if (true === App::$debug) {
            echo PHP_EOL . 'Inventories to send: ' . $count_data . PHP_EOL;
            $continue = readline('Next validation data. Continue? (y/n): ');
            if ($continue === 'n' || $continue !== 'y') {
                return false;
            }
        }

        return $data;
    }

    private function validationData($data)
    {
        $data_valid = $this->model->validationData($data);

        if (false === $data_valid) {
            return false;
        }
        $data = null;

        $count_valid = count($data_valid);

        App::telegramLog('Valid inventories: ' . $count_valid);

        // debug
        if (true === App::$debug) {
            echo PHP_EOL . 'Valid inventories: ' . $count_valid . PHP_EOL;
            $continue = readline(
                'Next set request parameters. Continue? (y/n): '
            );
            if ($continue === 'n' || $continue !== 'y') {
                return false;
            }
        }

        return $data_valid;
    }

    private function setRequestParameters()
    {
        $request_parameters = $this->model->setRequestParameters();

        if (false === $request_parameters) {
            return false;
        }

        // debug
        if (true === App::$debug) {
            echo PHP_EOL . 'Request parameters:', PHP_EOL,
                implode(PHP_EOL, $request_parameters) . PHP_EOL;
            $continue = readline('Next create xml feed. Continue? (y/n): ');
            if ($continue === 'n' || $continue !== 'y') {
                return false;
            }
        }

        return $request_parameters;
    }

    private function createXmlFeed($part)
    {
        $xml_feed = $this->model->createXmlFeed($part);

        if (false === $xml_feed) {
            return false;
        }

        // debug
        if (true === App::$debug) {
            echo PHP_EOL . 'Xml feed: ', substr($xml_feed, 0, 10000) . PHP_EOL;
            $continue = readline('Next send data. Continue? (y/n): ');
            if ($continue === 'n' || $continue !== 'y') {
                return false;
            }
        }

        return $xml_feed;
    }

    private function sendData($request_parameters, $xml_feed)
    {
        $response_feed = $this->model->sendData($request_parameters, $xml_feed);

        if (false !== $response_feed) {
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
        $result_record_feed = $this->model
            ->recordResponseFeed($response_feed, $count_part);
        if (true === App::$debug) {
            if (false !== $result_record_feed) {
                echo PHP_EOL . 'Response feed ' . $response_feed .
                     ' recorded in the ' . App::$inventoryFeedT . ' table'
                     . PHP_EOL;
            }
        }

        return true;
    }
}
