<?php

declare(strict_types=1);

namespace WB\Modules\Retire;

use WB\Core\App;
use WB\Helpers\Curl;
use WB\Helpers\Guid;
use WB\Helpers\Logger;
use WB\Helpers\RequestParameters;

require_once APP_ROOT . '/helpers/RequestParameters.php';
require_once APP_ROOT . '/helpers/Guid.php';

class RetireModule
{
    public string $requestMethod = 'DELETE';
    public string $requestUrl    = 'https://marketplace.walmartapis.com/v3/ca/items/';

    public function run(): void
    {
        $items = $this->getItems();

        if (empty($items)) {
            return;
        }

        Logger::log(
                   'Sent ' . $this->retireItems($items) . ' items',
                   __METHOD__,
                   'info',
            alert: true
        );
    }

    private function getItems(): array
    {
        $sql = 'SELECT report.sku
        FROM walmart_ca.report AS report

                 LEFT JOIN walmart_ca.item_adding AS adding
                           ON adding.upc = report.upc
        
                 LEFT JOIN walmart_ca.item_preload AS preload
                           ON preload.upc = report.upc
        
                 LEFT JOIN external_usa_bezoxx.usa_product AS product
                           ON product.asin = preload.asin
        
                LEFT JOIN walmart_ca.transport_retire AS t_retire
                           ON t_retire.sku = report.sku
        
                LEFT JOIN walmart_ca.item_black AS black
                           ON black.sku = report.sku
        
        WHERE report.shop_id = :shop_id
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

        $items = App::$db->run($sql, ['shop_id' => App::$shopId])->fetchAll();

        $count = count($items);

        if (!isset($items) || !is_array($items) || $count <= 0) {
            Logger::log('Items not received', __METHOD__, 'info', alert: true);

            return [];
        }

        Logger::log(
                   'Received ' . $count . ' items',
                   __METHOD__,
                   'info',
            alert: true
        );

        return array_column($items, 'sku');
    }

    private function retireItems(array $items): int
    {
        $data   = [];
        $values = '';

        foreach ($items as $sku) {
            $this->requestUrl .= $sku;

            $request_parameters = RequestParameters::getParameters(
                $this->requestUrl,
                $this->requestMethod
            );

            // delete
            $response = (new Curl())->deleteRequest(
                [
                    'url'                   => $this->requestUrl,
                    'sign'                  => $request_parameters['sign'],
                    'timestamp'             => $request_parameters['timestamp'],
                    'guid'                  => Guid::getGuid(),
                    'consumer_id'           => App::$options['shops'][App::$shopId]['consumer_id'],
                    'consumer_channel_type' => App::$options['shops'][App::$shopId]['consumer_channel_type']
                ]
            );

            // log
            $message = !empty($response) ? $response : false;
            $result  = $message ?
                (str_contains($message, 'thank you') ?
                    'submitted' : 'error') : 'error';

            $data[] = App::$shopId;
            $data[] = $sku;
            $data[] = $result;

            $values .= '(?,?,?),';
        }

        $sql = 'INSERT INTO walmart_ca.retire_log 
                        (shop_id, sku, result) 
                        VALUES ' . trim($values, ',') . '
                        ON CONFLICT (sku) DO UPDATE 
                            SET date_retire = current_timestamp';

        return (int)App::$db->run($sql, $data)->rowCount();
    }
}
