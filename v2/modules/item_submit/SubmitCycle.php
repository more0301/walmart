<?php

declare(strict_types=1);

namespace WB\Modules\ItemSubmit;

use WB\Core\App;
use WB\Helpers\Curl;
use WB\Helpers\Guid;
use WB\Helpers\Logger;
use WB\Helpers\RequestParameters;
use WB\Helpers\Xml\Templates\ItemTemplate;
use WB\Helpers\Xml\XmlRead;

require_once APP_ROOT . '/helpers/xml/templates/ItemTemplate.php';
require_once APP_ROOT . '/helpers/RequestParameters.php';
require_once APP_ROOT . '/helpers/xml/XmlRead.php';

class SubmitCycle extends ItemSubmitModule
{
    public function cycle(array $data): int
    {
        $item_parts = array_chunk(
            $data,
            App::$options['shops'][App::$shopId]['max_item_submit']
        );

        $item_parts = array_slice($item_parts, 0, 10);

        $last_key = array_key_last($item_parts);

        // delete by shop_id
        App::$db->run(
            'DELETE FROM walmart_ca.last_sent_prices WHERE shop_id = ?',
            [App::$shopId]
        );

        // total sent items
        $sent = 0;

        foreach ($item_parts as $key => $part) {
            $xml_feed = ItemTemplate::getFeed($part);

            if (empty($xml_feed) && $key === $last_key && $sent === 0) {
                return $sent;
            } elseif (!$xml_feed) {
                continue;
            }

            $request_parameters = RequestParameters::getParameters(
                $this->requestUrl,
                $this->requestMethod
            );

            if (empty($request_parameters)
                && $key === $last_key
                && $sent === 0) {
                Logger::log('Sent 0 items', __METHOD__, 'info', alert: true);

                return $sent;
            } elseif (empty($request_parameters)) {
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
            && !empty($feed['feedId'])) {
            return filter_var(
                trim($feed['feedId']),
                FILTER_SANITIZE_STRING
            );
        }

        Logger::log('Feed id not received', __METHOD__, '');

        return '';
    }

    private function addFeedToDb(string $feed, int $total_items): void
    {
        $sql = 'INSERT INTO walmart_ca.item_feed
                (shop_id, feed_id, items) 
                VALUES (:shop_id, :feed, :total_items) 
                ON CONFLICT DO NOTHING';

        App::$db->run($sql, [
            'shop_id'     => App::$shopId,
            'feed'        => $feed,
            'total_items' => $total_items
        ]);
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

    private function isSendUpdate(array $data): void
    {
        $sku_list = implode(
            ',',
            array_map(
                fn($val) => '\'' . $val . '\'',
                array_column($data, 'sku')
            )
        );

        App::$db->run(
            'UPDATE walmart_ca.item_adding 
                    SET is_send = TRUE
                    WHERE sku IN (' . $sku_list . ')'
        );
    }
}
