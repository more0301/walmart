<?php

declare(strict_types=1);

namespace Walmart\helpers;

use Walmart\config\Options;
use Walmart\core\App;

/**
 * Class CurrencyConverter
 *
 * @package Walmart\components
 */
class CurrencyConverter
{
    /**
     * @param array $data
     *
     * @return array|bool
     */
    public static function convertAndMarkup(array $data)
    {
        foreach ($data as &$item) {
            if (isset($item['sku'])
                && (!isset($item['currency']) || $item['currency'] === 'USD')) {
                $price = round(
                    floatval($item['price']) * App::$cadRate * App::$markup,
                    2
                );

                //$item['price'] = ( $price >= App::$minPrice ) ?
                //    $price : App::$minPrice;

                if ($price < App::$minPrice) {
                    $item['price'] = App::$minPrice;
                } elseif ($price > App::$maxPrice) {
                    $item['price'] = App::$maxPrice;
                } else {
                    $item['price'] = $price;
                }
            }
        }

        return $data;
    }
}
