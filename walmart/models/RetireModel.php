<?php

declare(strict_types=1);

namespace Walmart\models;

use Walmart\core\App;
use Walmart\helpers\database\Database;
use Walmart\helpers\database\Db;
use Walmart\helpers\Logger;
use Walmart\helpers\request\Curl;
use Walmart\helpers\request\RequestParameters;

class RetireModel
{
    private Db $db;

    public function __construct()
    {
        $this->db = new Db();
    }

    /**
     * @return array
     */
    public function getListToRetire(): array
    {
        $sql = 'SELECT report.sku
        FROM walmart_ca.report report

                 LEFT JOIN walmart_ca.item_adding adding
                           ON adding.upc = report.upc
        
                 LEFT JOIN walmart_ca.item_preload preload
                           ON preload.upc = report.upc
        
                 LEFT JOIN external_usa_bezoxx.usa_product product
                           ON product.asin = preload.asin
        
                LEFT JOIN walmart_ca.transport_retire t_retire
                           ON t_retire.sku = report.sku
        
                LEFT JOIN walmart_ca.item_black black
                           ON black.sku = report.sku
        
        WHERE report.shop_id = ' . App::$shopId . '
            AND report.sku IS NOT NULL
            AND report.publish_status = \'PUBLISHED\'
            AND (product.asin IS NULL
                OR (product.price_amazon::DECIMAL = 0 
                    AND product.price_prime::DECIMAL = 0))
            -- transport retire        
            OR (t_retire.sku IS NOT NULL 
                AND report.publish_status = \'PUBLISHED\')
            -- black list retire
            OR (black.sku IS NOT NULL 
                AND report.publish_status = \'PUBLISHED\')';

        $items = Database::request($sql, __METHOD__, true);

        //foreach ( $items as $item ) {
        //    if ( $item['sku'] == 'USAM-WL000001-00PY00L1EBTG-029311607449' ) {
        //        var_dump( 123 );
        //        exit();
        //    }
        //}
        //exit();

        $count = count($items);
        if (false === $items || $count <= 0) {
            Logger::log(
                'There are no records in table ' .
                App::$retireT . ' table',
                __METHOD__
            );

            return [];
        }

        Logger::log(
            $count . ' items recorded in table ' .
            App::$retireT,
            __METHOD__,
            'dev'
        );

        return $items;
    }

    public function retireItem(array $items)
    {
        foreach ($items as $key => $item) {
            $request_data = $this->setRequestData($item['sku']);
            if (false === $request_data) {
                continue;
            }

            // delete
            $response = Curl::deleteRequest(
                $request_data['url'],
                $request_data['timestamp'],
                $request_data['sign']
            );

            if (App::$devMode) {
                file_put_contents(
                    APP_ROOT . '/logs/' . App::$shopId .
                    '/retire-response.txt',
                    $response . PHP_EOL,
                    FILE_APPEND
                );
            }

            // log
            $message = false !== $response ? $response : false;
            $result  = false !== $message ?
                (false !== stripos($message, 'thank you') ?
                    'submitted' : 'error') : 'error';

            $data = [App::$shopId, $item['sku'], $result];

            $sql = 'INSERT INTO walmart_ca.retire_log 
                        (shop_id,sku,result) 
                        VALUES (?,?,?)
                        ON CONFLICT (sku) DO UPDATE 
                            SET date_retire=current_timestamp';

            $this->db->run($sql, $data);

            if (true === App::$debug) {
                echo 'sku: ' . $item['sku'] . ', result: ' . $result . PHP_EOL;
            }
        }

        //$count = count( $result );
        //if ( false === $result || $count <= 0 ) {
        //    Logger::log( 'Items not saved to ' . App::$retireLogT .
        //        ' table', __METHOD__ );
        //
        //    return false;
        //}
        //
        //return $count;
    }

    private function setRequestData(string $sku)
    {
        $request_data = RequestParameters::exec('retire_item', ['sku' => $sku]);

        if (false === $request_data) {
            Logger::log('Error creating query parameters', __METHOD__);

            return false;
        }

        return $request_data;
    }
}
