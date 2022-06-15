<?php
declare( strict_types=1 );

namespace Walmart\helpers\item;

use Walmart\core\App;
use Walmart\helpers\Sanitize;
use Walmart\helpers\Validation;

/**
 * Class ItemExport
 * Creating items for upload to the store
 *
 * @package Walmart\helpers
 */
trait ItemExport
{
    use Validation, Sanitize;

    private array $dependence;

    /**
     * @param       $item
     * @param array $dependence
     *
     * @return array|bool
     */
    public function createItem( $item, array $dependence )
    {
        $this->dependence = $dependence;

        //$id = isset( $item['sku'] ) ?
        //    $item['sku'] :
        //    ( isset( $item['asin'] ) ? $item['asin'] :
        //        ( isset( $item['upc'] ) ? $item['upc'] :
        //            '' ) );

        if ( isset( $item['upc'] ) ) {
            $this->validationId = $item['upc'];
        }

        if ( false === $this->validation( $item ) ) {
            return false;
        }

        $sanitize_item = $this->sanitize( $item );
        if ( false === $sanitize_item ) {
            return false;
        }

        return $sanitize_item;
    }

    /**
     * @param $item
     *
     * @return bool
     */
    private function validation( $item )
    {
        // asin
        if ( in_array( 'asin', $this->dependence ) ) {
            if ( !isset( $item['asin'] ) ||
                false === $this->checkAsin( $item['asin'] ) ) {
                return false;
            }
        }

        // sku
        if ( in_array( 'sku', $this->dependence ) ) {
            if ( !isset( $item['sku'] ) ||
                false === $this->checkSku( $item['sku'] ) ) {
                return false;
            }
        }

        // upc
        if ( in_array( 'upc', $this->dependence ) ) {
            if ( !isset( $item['upc'] ) ||
                false === $this->checkUpc( $item['upc'] ) ) {
                return false;
            }
        }

        // product_name
        if ( in_array( 'product_name', $this->dependence ) ) {
            if ( !isset( $item['product_name'] ) ||
                false === $this->checkProductName( $item['product_name'] ) ) {
                return false;
            }
        }

        // short_description
        if ( in_array( 'short_description', $this->dependence ) ) {
            if ( !isset( $item['short_description'] ) ||
                false === $this->checkShortDescription( $item['short_description'] ) ) {
                return false;
            }
        }

        // brand
        if ( in_array( 'brand', $this->dependence ) ) {
            if ( !isset( $item['brand'] ) ||
                false === $this->checkBrand( $item['brand'] ) ) {
                return false;
            }
        }

/*        // shipping_weight
        if ( in_array( 'shipping_weight', $this->dependence ) ) {
            if ( !isset( $item['shipping_weight'] ) ||
                false === $this->checkShippingWeight( $item['shipping_weight'] ) ) {
                return false;
            }
        }

        // tax_code
        if ( in_array( 'tax_code', $this->dependence ) ) {
            if ( !isset( $item['tax_code'] ) ||
                false === $this->checkTaxCode( $item['tax_code'] ) ) {
                return false;
            }
        }*/

        // image
        if ( in_array( 'image', $this->dependence ) ) {
            if ( !isset( $item['image'] ) ||
                false === $this->checkImage( $item['image'] ) ) {
                return false;
            }
        }

        // category
        if ( in_array( 'category', $this->dependence ) ) {
            if ( !isset( $item['category'] ) ||
                false === $this->checkCategory( $item['category'] ) ) {
                return false;
            }
        }

        // subcategory
        if ( in_array( 'subcategory', $this->dependence ) ) {
            if ( !isset( $item['subcategory'] ) ||
                false === $this->checkSubCategory( $item['subcategory'] ) ) {

                return false;
            }
        }

/*        // price currency
        if ( in_array( 'currency', $this->dependence ) ) {
            if ( !isset( $item['currency'] ) ||
                false === $this->checkPriceCurrency( $item['currency'] ) ) {

                return false;
            }
        }*/

        // price
        if ( in_array( 'price', $this->dependence ) ) {
            if ( !isset( $item['price'] ) ||
                false === $this->checkPrice( $item['price'] ) ) {

                return false;
            }
        }

        // inventory unit
        if ( in_array( 'inventory_unit', $this->dependence ) ) {
            if ( !isset( $item['inventory_unit'] ) ||
                false === $this->checkString( $item['inventory_unit'] ) ) {

                return false;
            }
        }

        // inventory amount
        if ( in_array( 'inventory_amount', $this->dependence ) ) {
            if ( !isset( $item['inventory_amount'] ) ||
                false === $this->checkInventoryAmount( $item['inventory_amount'] ) ) {

                return false;
            }
        }

        // inventory fulfillment
        if ( in_array( 'inventory_fulfillment', $this->dependence ) ) {
            if ( !isset( $item['inventory_fulfillment'] ) ||
                false === $this->checkInventoryFulfillment( $item['inventory_fulfillment'] ) ) {

                return false;
            }
        }

        $in_array_gender = in_array( 'gender', $this->dependence );
        $gcs = 0;
        // gender
        if ( $in_array_gender ) {
            if ( isset( $item['gender'] )
                && false !== $this->checkGender( $item['gender'] ) ) {
                $gcs += 1;
            }
        }

        // color
        $in_array_color = in_array( 'color', $this->dependence );
        if ( $in_array_color ) {
            if ( isset( $item['color'] )
                && false !== $this->checkColor( $item['color'] ) ) {
                $gcs += 1;
            }
        }

        // size
        $in_array_size = in_array( 'size', $this->dependence );
        if ( $in_array_size ) {
            if ( isset( $item['size'] )
                && false !== $this->checkSize( $item['size'] ) ) {
                $gcs += 1;
            }
        }

        if ( true === App::$requiredGCS ) {
            if ( $gcs !== 3 &&
                ( $in_array_gender || $in_array_color || $in_array_size ) ) {
                return false;
            }
        }

        // price_amazon
        $in_array_price_amazon = in_array( 'price_amazon', $this->dependence );
        if ( $in_array_price_amazon ) {
            $price_amazon = true;
            if ( !isset( $item['price_amazon'] ) ||
                false === $this->checkPriceAmazon( $item['price_amazon'] ) ) {

                $price_amazon = null;
            }
        }

        // price_prime
        $in_array_price_prime = in_array( 'price_prime', $this->dependence );
        if ( $in_array_price_prime ) {
            $price_prime = true;
            if ( !isset( $item['price_prime'] ) ||
                false === $this->checkPricePrime( $item['price_prime'] ) ) {
                $price_prime = null;
            }
        }

        // price_amazon and price_prime
        if ( !isset( $price_amazon ) && !isset( $price_prime ) &&
            $in_array_price_amazon && $in_array_price_prime ) {
            return false;
        }

        return true;
    }

    /**
     * @param $item
     *
     * @return array|bool
     */
    private function sanitize( $item )
    {
        $sanitize = [];

        if ( in_array( 'shop_id', $this->dependence ) ) {
            $sanitize['shop_id'] = $this->sanitizeInt( $item['shop_id'] );
        }
        if ( in_array( 'asin', $this->dependence ) ) {
            $sanitize['asin'] = $this->sanitizeString( $item['asin'] );
        }
        if ( in_array( 'sku', $this->dependence ) ) {
            $sanitize['sku'] = $this->sanitizeString( $item['sku'] );
        }
        if ( in_array( 'upc', $this->dependence ) ) {
            $sanitize['upc'] = $this->sanitizeString( $item['upc'] );
        }
        if ( in_array( 'product_name', $this->dependence ) ) {
            $sanitize['product_name'] = $this->sanitizeStringExport( $item['product_name'] );
        }
        if ( in_array( 'short_description', $this->dependence ) ) {
            $sanitize['short_description'] =
                $this->sanitizeStringExport( $item['short_description'] );
        }
        if ( in_array( 'brand', $this->dependence ) ) {
            $sanitize['brand'] = $this->sanitizeStringExport( $item['brand'] );
        }
        if ( in_array( 'shipping_weight', $this->dependence ) ) {
            $sanitize['shipping_weight'] =
                $this->sanitizeFloat( $item['shipping_weight'] );
        }
        if ( in_array( 'tax_code', $this->dependence ) ) {
            $sanitize['tax_code'] = $this->sanitizeInt( $item['tax_code'] );
        }
        if ( in_array( 'image', $this->dependence ) ) {
            $sanitize['image'] = $this->sanitizeString( $item['image'] );
        }
        if ( in_array( 'category', $this->dependence ) ) {
            $sanitize['category'] = $this->sanitizeString( $item['category'] );
        }
        if ( in_array( 'subcategory', $this->dependence ) ) {
            $sanitize['subcategory'] = $this->sanitizeString( $item['subcategory'] );
        }
        if ( in_array( 'currency', $this->dependence ) ) {
            $sanitize['currency'] = $this->sanitizeString( $item['currency'] );
        }
        if ( in_array( 'price', $this->dependence ) ) {
            $sanitize['price'] = $this->sanitizeFloat( $item['price'] );
        }
        if ( in_array( 'inventory_unit', $this->dependence ) ) {
            $sanitize['inventory_unit'] = $this->sanitizeString( $item['inventory_unit'] );
        }
        if ( in_array( 'inventory_amount', $this->dependence ) ) {
            $sanitize['inventory_amount'] = $this->sanitizeInt( $item['inventory_amount'] );
        }
        if ( in_array( 'inventory_fulfillment', $this->dependence ) ) {
            $sanitize['inventory_fulfillment'] = $this->sanitizeInt( $item['inventory_fulfillment'] );
        }
        if ( in_array( 'gender', $this->dependence ) ) {
            $sanitize['gender'] = $this->sanitizeString( $item['gender'] );
        }
        if ( in_array( 'color', $this->dependence ) ) {
            $sanitize['color'] = $this->sanitizeString( $item['color'] );
        }
        if ( in_array( 'size', $this->dependence ) ) {
            $sanitize['size'] = $this->sanitizeString( $item['size'] );
        }

        // price_amazon
        if ( in_array( 'price_amazon', $this->dependence ) ) {
            $sanitize['price_amazon'] = $this->sanitizeFloat( $item['price_amazon'] );
        }
        // price_prime
        if ( in_array( 'price_prime', $this->dependence ) ) {
            $sanitize['price_prime'] = $this->sanitizeFloat( $item['price_prime'] );
        }

        return count( $sanitize ) === count( $this->dependence ) ? $sanitize : false;
    }
}