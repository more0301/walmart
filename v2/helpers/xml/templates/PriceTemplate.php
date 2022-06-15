<?php

declare(strict_types=1);

namespace WB\Helpers\Xml\Templates;

use stdClass;

/**
 * Class PriceTemplate
 *
 * @package Walmart\helpers\xml\templates
 */
class PriceTemplate
{
    /**
     *
     * @param array $data
     *
     * @return string
     */
    public static function getFeed(array $data): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<PriceFeed xmlns="http://walmart.com/">
  <PriceHeader>
    <version>1.5.1</version>
  </PriceHeader>
' . self::priceCreate($data) . '
</PriceFeed>';
    }

    /**
     * @param array $data
     *
     * @return string
     */
    public static function priceCreate(array $data): string
    {
        $prices = '';

        foreach ($data as $item) {
            if (isset($item['sku'])) {
                $item_data = [
                    'sku'      => $item['sku'],
                    'currency' => 'CAD',
                    'amount'   => $item['price']
                ];

                $prices .= self::priceTemplate($item_data);
            }
        }

        if (empty($prices)) {
            return '';
        }

        return $prices;
    }

    /**
     * @param array $data
     *
     * @return string
     */
    public static function priceTemplate(array $data): string
    {
        return '<Price>
    <itemIdentifier>
      <sku>' . $data['sku'] . '</sku>
    </itemIdentifier>
    <pricingList>
      <pricing>
        <currentPrice>
          <value currency="' . $data['currency'] . '" amount="'
               . $data['amount'] . '"/>
        </currentPrice>
      </pricing>
    </pricingList>
  </Price>';
    }
}
