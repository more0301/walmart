<?php
declare( strict_types=1 );

namespace Walmart\controllers;

use Walmart\core\App;
use Walmart\core\Controller;
use Walmart\helpers\database\Database;

/**
 * Class PriceSubmitController
 *
 * @package Walmart\controllers\submit
 */
class PriceSubmitController extends Controller
{

    /**
     * @return bool
     */
    public function start()
    {
        $data = $this->getDataToSend();

        if ( false === $data ) {

            App::$doNotSendTelegram = true;

            return false;
        }

        $data = $this->mergePrices( $data );

        if ( false === $data ) {
            return false;
        }

        $data_convert = $this->convertPrice( $data );

        if ( false === $data_convert ) {
            return false;
        }

        $data_valid = $this->validationData( $data_convert );

        if ( false === $data_valid ) {
            return false;
        }

        $price_parts = array_chunk( (array)$data_valid, App::$maxPriceSubmit );
        $price_parts = array_slice( $price_parts, 0, 10 );
        $last_key    = array_key_last( $price_parts );

        // total sent items
        $sent = 0;

        // delete by shop_id
        $sql_del = 'DELETE FROM ' . App::$dbSchema . '.' . App::$lastSentPricesT . ' WHERE shop_id=' . App::$shopId;
        Database::request( $sql_del, __METHOD__ );

        foreach ( $price_parts as $key => $part ) {

            $request_parameters = $this->setRequestParameters();

            if ( false === $request_parameters && $key === $last_key &&
                $sent === 0 ) {
                App::telegramLog( 'Sent 0 items' );

                return false;
            }
            elseif ( false === $request_parameters ) {
                continue;
            }

            $xml_feed = $this->createXmlFeed( $part );

            if ( false === $xml_feed && $key === $last_key && $sent === 0 ) {
                App::telegramLog( 'Sent 0 items' );

                return false;
            }
            elseif ( false === $xml_feed ) {
                continue;
            }

            $response_feed = $this->sendData( $request_parameters, $xml_feed );

            if ( false !== $response_feed ) {

                $count_part = count( $part );

                $this->recordResponseFeed( $response_feed, $count_part );

                $this->recordSentPrices( $part );

                $this->model->isSendUpdate( $part );

                App::telegramLog( 'Prices sent. Feed id received' );

                $sent += $count_part;
            }
            else {
                App::telegramLog( 'Error sending prices' );
            }
        }

        App::telegramLog( 'Sent ' . $sent . ' items' );

        return true;
    }

    private function getDataToSend()
    {
        $data = $this->model->getDataToSend();

        if ( !$data ) {
            return false;
        }

        $count_data = count( $data );

        App::telegramLog( 'Items to send: ' . $count_data );

        // debug
        if ( true === App::$debug ) {
            echo PHP_EOL . 'Items to send: ' . $count_data . PHP_EOL;

            $continue = readline( 'Next merge price amazon and prime. Continue? (y/n): ' );
            if ( $continue === 'n' || $continue !== 'y' ) {
                return false;
            }
        }

        return $data;
    }

    private function mergePrices( $data )
    {
        $result = $this->model->mergePriceAmazonAndPrime( $data );

        if ( false === $result ) {
            return false;
        }

        $count_data = count( $result );

        App::telegramLog( 'Items to send after price merging: ' . $count_data );

        // debug
        if ( true === App::$debug ) {
            echo PHP_EOL . 'Items to send after price merging: ' . $count_data . PHP_EOL;

            $continue = readline( 'Next convert price. Continue? (y/n): ' );
            if ( $continue === 'n' || $continue !== 'y' ) {
                return false;
            }
        }

        return $result;
    }

    private function convertPrice( $data )
    {
        $data_convert = $this->model->convertPrice( $data );

        if ( false === $data_convert ) {
            return false;
        }

        $count_convert = count( $data_convert );

        App::telegramLog( 'Price converted: ' . $count_convert );

        // debug
        if ( true === App::$debug ) {
            echo PHP_EOL . 'Price converted: ' . $count_convert . PHP_EOL;

            $continue = readline( 'Next validation data. Continue? (y/n): ' );
            if ( $continue === 'n' || $continue !== 'y' ) {
                return false;
            }
        }

        return $data_convert;
    }

    private function validationData( $data_convert )
    {
        $data_valid = $this->model->validationData( $data_convert );

        if ( false === $data_valid ) {
            return false;
        }
        $data_convert = null;

        $count_valid = count( $data_valid );

        App::telegramLog( 'Valid prices: ' . $count_valid );

        // debug
        if ( true === App::$debug ) {
            echo PHP_EOL . 'Valid prices: ' . $count_valid . PHP_EOL;

            $continue = readline( 'Next set request parameters. Continue? (y/n): ' );
            if ( $continue === 'n' || $continue !== 'y' ) {
                return false;
            }
        }

        return $data_valid;
    }

    private function setRequestParameters()
    {
        $request_parameters = $this->model->setRequestParameters();

        if ( false === $request_parameters ) {
            return false;
        }

        // debug
        if ( true === App::$debug ) {
            echo PHP_EOL . 'Request parameters:' . PHP_EOL,
                implode( PHP_EOL, $request_parameters ) . PHP_EOL;

            $continue = readline( 'Next create xml feed. Continue? (y/n): ' );
            if ( $continue === 'n' || $continue !== 'y' ) {
                return false;
            }
        }

        return $request_parameters;
    }

    private function createXmlFeed( $part )
    {
        $xml_feed = $this->model->createXmlFeed( $part );

        if ( false === $xml_feed ) {
            return false;
        }

        // debug
        if ( true === App::$debug ) {
            echo PHP_EOL . 'Xml feed: ', substr( $xml_feed, 0, 10000 ) . PHP_EOL;

            $continue = readline( 'Next send data. Continue? (y/n): ' );
            if ( $continue === 'n' || $continue !== 'y' ) {
                return false;
            }
        }

        return $xml_feed;
    }

    private function sendData( $request_parameters, $xml_feed )
    {
        $response_feed = $this->model->sendData( $request_parameters, $xml_feed );

        if ( false === $response_feed ) {
            return false;
        }

        // debug
        if ( true === App::$debug ) {
            echo PHP_EOL . 'Response_feed:' . $response_feed . PHP_EOL;

            $continue = readline( 'Next record response feed. Continue? (y/n): ' );
            if ( $continue === 'n' || $continue !== 'y' ) {
                return false;
            }
        }

        return $response_feed;
    }

    private function recordResponseFeed( $response_feed, $count_part )
    {
        $result_record_feed = $this->model->recordResponseFeed( $response_feed, $count_part );

        if ( true === App::$debug ) {
            if ( false !== $result_record_feed ) {
                echo PHP_EOL . 'Response feed ' . $response_feed .
                    ' recorded in the ' . App::$priceFeedT . ' table' . PHP_EOL;
            }
        }

        return true;
    }

    private function recordSentPrices( $data )
    {
        $result_record_set_prices = $this->model
            ->addSentPricesToDb( $data );

        if ( true === App::$debug ) {
            if ( false !== $result_record_set_prices ) {
                echo PHP_EOL . 'Record to db ' . $result_record_set_prices .
                    ' prices in the ' . App::$lastSentPricesT . ' table' . PHP_EOL;
            }
        }
    }
}
