<?php

declare(strict_types=1);

namespace WB\Modules\PriceSubmit;

use WB\Core\App;
use WB\Helpers\Curl;
use WB\Helpers\Guid;
use WB\Helpers\Logger;
use WB\Helpers\RequestParameters;
use WB\Helpers\Xml\Templates\PriceTemplate;
use WB\Helpers\Xml\XmlRead;

require_once APP_ROOT . '/helpers/RequestParameters.php';
require_once APP_ROOT . '/helpers/Guid.php';
require_once APP_ROOT . '/helpers/xml/templates/PriceTemplate.php';
require_once APP_ROOT . '/helpers/xml/XmlRead.php';

class SubmitCycle extends PriceSubmitModule
{
    public function cycle(array $data): int
    {
        $price_parts = array_chunk(
            (array)$data,
            App::$options['shops'][App::$shopId]['max_item_submit']
        );

        $price_parts = array_slice($price_parts, 0, 10);

        $last_key = array_key_last($price_parts);

        // total sent items
        $sent = 0;

        // delete by shop_id
        App::$db->run(
            'DELETE FROM walmart_ca.last_sent_prices WHERE shop_id = ?',
            [App::$shopId]
        );

        foreach ($price_parts as $key => $part) {
            $request_parameters = RequestParameters::getParameters(
                $this->requestUrl,
                $this->requestMethod
            );

            if (empty($request_parameters)
                && $key === $last_key
                && $sent === 0) {
                Logger::log('Sent 0 items', __METHOD__, 'info');

                return $sent;
            } elseif (empty($request_parameters)) {
                continue;
            }

            $xml_feed = PriceTemplate::getFeed($part);

            if (empty($xml_feed) && $key === $last_key && $sent === 0) {
                return $sent;
            } elseif (!$xml_feed) {
                continue;
            }

            $response_feed = $this->sendData(
                $request_parameters,
                $xml_feed
            );

            if (!empty($response_feed)) {
                $count_part = count($part);

                $this->addFeedToDb($response_feed, $count_part);

                $this->addSentPricesToDb($part);

                $this->isSendUpdate($part);

                $sent += $count_part;
            }

            sleep(5);
        }

        return $sent;
    }

    private function sendData(
        array $request_parameters,
        string $xml_feed
    ): string {
        $response = (new Curl())->postRequest(
            [
                'url'                   => $this->requestUrl,
                'sign'                  => $request_parameters['sign'],
                'timestamp'             => $request_parameters['timestamp'],
                'feed'                  => $xml_feed,
                'guid'                  => Guid::getGuid(),
                'consumer_id'           => App::$options['shops'][App::$shopId]['consumer_id'],
                'consumer_channel_type' => App::$options['shops'][App::$shopId]['consumer_channel_type']
            ]
        );

        $feed = XmlRead::exec(
            $response,
            App::$options['default']['walmart_xml_ns']
        );

        if (isset($feed['feedId']) && is_string($feed['feedId'])
            && !empty($feed)) {
            return filter_var(
                trim($feed['feedId']),
                FILTER_SANITIZE_STRING
            );
        }

        Logger::log('Feed id not received', __METHOD__, 'info');

        return '';
    }

    public function addFeedToDb(string $feed, int $total_items): void
    {
        App::$db->run(
            'INSERT INTO walmart_ca.price_feed 
                (shop_id, feed_id, items) 
                VALUES (:shop_id, :feed, :total_items) 
                ON CONFLICT DO NOTHING'
            ,
            [
                'shop_id'     => App::$shopId,
                'feed'        => $feed,
                'total_items' => $total_items
            ]
        );
    }

    private function addSentPricesToDb(array $data): void
    {
        $values = implode(
            ',',
            array_map(
                fn($item) => '(' . $item['shop_id'] . ',' .
                             App::$db->quote($item['sku']) . ',' .
                             $item['price'] . ')',
                $data
            )
        );

        $values = trim($values, ',');

        App::$db->run(
            'INSERT INTO walmart_ca.last_sent_prices 
                (shop_id,sku,price) VALUES ' . $values .
            ' ON CONFLICT DO NOTHING'
        );
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

        App::$db->run(
            'UPDATE walmart_ca.report_copy 
                    SET is_send_price = TRUE
                    WHERE sku IN (' . $sku_list . ')'
        );
    }
}
