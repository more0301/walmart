<?php

declare(strict_types=1);

namespace Walmart\helpers\request;

use Walmart\core\App;
use Walmart\core\Singleton;
use Walmart\helpers\Logger;

/**
 * Class RequestData
 *
 * @package WalmartApi\components
 */
class RequestParameters extends Singleton
{
    /**
     * @param string $method
     * @param array  $data
     *
     * @return mixed
     */
    public static function exec(string $method, array $data = [])
    {
        $instance = static::getInstance();

        return $instance->getParameters($method, $data);
    }

    /**
     * @param string $method
     * @param array  $data
     *
     * @return array|bool
     */
    public function getParameters($method, $data)
    {
        $timestamp = (int)(microtime(true) * 1000);
        $feed_id   = isset($data['feed_id']) ? $data['feed_id']
            : (isset($data['feedId']) ? $data['feedId'] : false);

        switch ($method) {
            // bulk item upload
            case 'item_submit':
                $request_url
                                = 'https://marketplace.walmartapis.com/v3/ca/feeds?feedType=item';
                $request_method = 'POST';
                break;

            // get all feed statuses
            case 'all_feed_info':
                if (isset($feed_id)) {
                    $request_method = 'GET';
                    $request_url
                                    = 'https://marketplace.walmartapis.com/v3/ca/feeds?feedId='
                                      . $feed_id;
                } else {
                    Logger::log('Feed id not specified', __METHOD__);

                    return false;
                }
                break;

            // Get feed and item status
            case 'items_info':
                $request_method = 'GET';
                $suffix         = isset($data['limit']) ? '&limit='
                                                          . $data['limit']
                                                          . '&offset='
                                                          . $data['offset']
                    : '';
                $request_url
                                = 'https://marketplace.walmartapis.com/v3/ca/feeds/'
                                  . $feed_id . '?includeDetails=true' . $suffix;
                break;

            // Get an item
            case 'item_info':
                $request_url
                                = 'https://marketplace.walmartapis.com/v3/ca/items/'
                                  . $data['sku'];
                $request_method = 'GET';
                break;

            // Update bulk inventory
            case 'inventory_update':
                $request_url
                                = 'https://marketplace.walmartapis.com/v3/ca/feeds?feedType=inventory';
                $request_method = 'POST';
                break;

            // Check inventory
            case 'inventory_check':
                $request_url
                                = 'https://marketplace.walmartapis.com/v3/ca/inventory?sku='
                                  . $data['sku'];
                $request_method = 'GET';
                break;

            case 'price_update':
                $request_url
                                = 'https://marketplace.walmartapis.com/v3/ca/feeds?feedType=price';
                $request_method = 'POST';
                break;

            case 'retire_item':
                $request_url
                                = 'https://marketplace.walmartapis.com/v3/ca/items/'
                                  . $data['sku'];
                $request_method = 'DELETE';
                break;

            case 'item_report':
                $request_url
                                = 'https://marketplace.walmartapis.com/v3/ca/getReport?type=item_ca';
                $request_method = 'GET';
                break;

            case 'get_report':
                $request_url
                                = 'https://marketplace.walmartapis.com/v3/getReport?type=item_ca';
                $request_method = 'GET';
                break;

            case 'get_orders':
                $request_url
                                = 'https://marketplace.walmartapis.com/v3/ca/orders?createdStartDate='
                                  . App::getDateInterval('P1W', 'sub')
                                  . '&status=Created&limit=200';
                $request_method = 'GET';
                break;

            case 'get_orders_u':
                $get_parameters = isset($data['next_cursor'])
                                  && empty($data['next_cursor']) ?
                    '?createdStartDate=' . $data['order_date'] . '&status='
                    . $data['order_status'] . '&limit=' . $data['order_limit']
                    : $data['next_cursor'];

                $request_url
                                = 'https://marketplace.walmartapis.com/v3/ca/orders'
                                  . $get_parameters;
                $request_method = 'GET';
                break;

            case 'shipping_update':
                $request_url
                                = 'https://marketplace.walmartapis.com/v3/ca/orders/'
                                  . $data['purchaseOrderId'] . '/shipping';
                $request_method = 'POST';
                break;

            case 'acknowledge_order':
                $request_url
                                = 'https://marketplace.walmartapis.com/v3/ca/orders/'
                                  .
                                  $data['purchaseOrderId'] . '/acknowledge';
                $request_method = 'POST';
                break;

            default:
                Logger::log(
                    'The method ' . $method
                    . ' is not listed. Request address not received',
                    __METHOD__
                );

                return false;
        }

        $request_sign = Signature::exec(
            [
                'url'         => $request_url,
                'http_method' => $request_method,
                'timestamp'   => $timestamp
            ]
        );

        if (false === $request_sign
            || !isset($request_url, $request_method, $timestamp)
            || empty($request_url)
            || empty($request_method)
            || empty($timestamp)
            || empty($request_sign)) {
            return false;
        }

        return [
            'url'         => $request_url,
            'http_method' => $request_method,
            'timestamp'   => $timestamp,
            'sign'        => $request_sign
        ];
    }
}
