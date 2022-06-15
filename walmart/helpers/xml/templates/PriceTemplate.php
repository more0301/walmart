<?php
declare( strict_types=1 );

namespace Walmart\helpers\xml\templates;

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
    public static function getFeed( array $data )
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<PriceFeed xmlns="http://walmart.com/">
  <PriceHeader>
    <version>1.5.1</version>
  </PriceHeader>
' . self::priceCreate( $data ) . '
</PriceFeed>';
    }

    /**
     * @param array $data
     *
     * @return string
     */
    public static function priceCreate( array $data )
    {
        $prices = '';
        foreach ( $data as $item ) {
            if ( isset( $item['sku'] ) ) {
                $item_data = self::setPriceData( $item );
                $prices    .= self::priceTemplate( $item_data );
            }
        }

        if ( empty( $prices ) ) {
            return false;
        }

        return $prices;
    }

    /**
     * @param $item
     *
     * @return stdClass
     */
    public static function setPriceData( $item )
    {
        $item_data = new stdClass();

        $item_data->sku      = $item['sku'];
        $item_data->currency = 'CAD';
        $item_data->amount = $item['price'];

        return $item_data;
    }

    /**
     * @param object $data
     *
     * @return string
     */
    public static function priceTemplate( object $data )
    {
        return '<Price>
    <itemIdentifier>
      <sku>' . $data->sku . '</sku>
    </itemIdentifier>
    <pricingList>
      <pricing>
        <currentPrice>
          <value currency="' . $data->currency . '" amount="' . $data->amount . '"/>
        </currentPrice>
      </pricing>
    </pricingList>
  </Price>';
    }
}