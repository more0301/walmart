<?php
declare( strict_types=1 );

namespace Walmart\helpers\item;

use Walmart\core\App;
use Walmart\helpers\Sanitize;
use Walmart\helpers\Validation;
use Walmart\helpers\xml\XmlRead;

/**
 * Class ItemObjectExternal
 * Receives input from walmart
 *  * Validates data, determines status
 *  * Cleans, applies filters
 *
 * @package Walmart\helpers
 */
trait ItemImport
{
    use Validation, Sanitize;

    private $response;
    private $sku;

    private array $dependence;

    /**
     * @param $response
     * @param $sku
     * @param array $dependence
     *
     * @return array
     */
    public function createItem( $response, $sku, array $dependence )
    {
        $this->dependence = $dependence;
        $this->response   = $response;
        $this->sku        = $sku;

        if ( false === $this->checkResponse() ) {
            return $this->errorItem();
        }

        $item_array = $this->convertXmlToArray();
        if ( false === $item_array ) {
            return $this->errorItem();
        }

        if ( false === $this->validation( $item_array ) ) {
            $error = null;
            if ( isset( $item_array['ItemResponse'] ) ) {
                if ( isset( $item_array['ItemResponse']['ingestionStatus'] ) ) {
                    $error = $item_array['ItemResponse']['ingestionStatus'];
                }
                elseif ( isset( $item_array['ItemResponse']['publishedStatus'] ) ) {
                    $error = $item_array['ItemResponse']['publishedStatus'];
                }
            }
            elseif ( isset( $item_array['error']['code'] ) ) {
                $error = $item_array['error']['code'];
            }

            return $this->errorItem( $error );
        }

        $sanitize = $this->sanitize( $item_array );
        if ( false === $sanitize ) {
            return $this->errorItem();
        }

        return $sanitize;
    }

    /**
     * @param $response
     *
     * @return bool
     */
    private function checkResponse() :bool
    {
        if ( false === $this->response ) {
            return false;
        }

        return true;
    }

    /**
     * @return array|bool|mixed
     */
    private function convertXmlToArray()
    {
        $item_array = XmlRead::exec( $this->response, App::$walmartXmlNs );

        if ( false === $item_array ) {
            return false;
        }

        return $item_array;
    }

    /**
     * @param $item_array
     *
     * @return bool
     */
    private function validation( $item_array ) :bool
    {
        // check published status
        if ( false === $this->checkItemStatus( $item_array ) ) {
            return false;
        }
        $item = $item_array['ItemResponse'];

        // upc
        if ( in_array( 'upc', $this->dependence ) ) {
            if ( !isset( $item['upc'] ) ||
                false === $this->checkUpc( $item['upc'] ) ) {
                return false;
            }
        }

        // productName
        if ( in_array( 'product_name', $this->dependence ) ) {
            if ( !isset( $item['productName'] ) ||
                false === $this->checkProductName( $item['productName'] ) ) {
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

        // price currency
        if ( in_array( 'price_currency', $this->dependence ) ) {
            if ( !isset( $item['price']['currency'] ) ||
                false === $this->checkPriceCurrency( $item['price']['currency'] ) ) {
                return false;
            }
        }

        // price amount
        if ( in_array( 'price_amount', $this->dependence ) ) {
            if ( !isset( $item['price']['amount'] ) ||
                false === $this->checkPriceAmount( $item['price']['amount'] ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param $data
     *
     * @return array|bool
     */
    private function sanitize( $data )
    {
        $sanitize = [];
        $item     = $data['ItemResponse'];

        if ( in_array( 'upc', $this->dependence ) ) {
            $sanitize['upc'] = isset( $item['upc'] ) ?
                $this->sanitizeString( $item['upc'] ) : null;
        }

        if ( in_array( 'product_name', $this->dependence ) ) {
            $sanitize['product_name'] = isset( $item['productName'] ) ?
                $this->sanitizeString( $item['productName'] ) : null;
        }

        if ( in_array( 'product_type', $this->dependence ) ) {
            $sanitize['product_type'] = isset( $item['productType'] ) ?
                $this->sanitizeString( $item['productType'] ) : null;
        }

        if ( in_array( 'shelf', $this->dependence ) ) {
            $sanitize['shelf'] = isset( $item['shelf'] ) ?
                $this->sanitizeString( $item['shelf'] ) : null;
        }

        if ( in_array( 'sku', $this->dependence ) ) {
            $sanitize['sku'] = isset( $item['sku'] ) ?
                $this->sanitizeString( $item['sku'] ) : null;
        }

        if ( in_array( 'price_currency', $this->dependence ) ) {
            $sanitize['price_currency'] = isset( $item['price']['currency'] ) ?
                $this->sanitizeString( $item['price']['currency'] ) : null;
        }

        if ( in_array( 'price_amount', $this->dependence ) ) {
            $sanitize['price_amount'] = isset( $item['price']['amount'] ) ?
                $this->sanitizeFloat( $item['price']['amount'] ) : null;
        }

        if ( count( $sanitize ) === count( $this->dependence ) ) {
            return $sanitize;
        }

        return $this->errorItem();
    }

    /**
     * @param null $error_description
     *
     * @return array
     */
    private function errorItem( $error_description = null )
    {
        return
            [
                'error_item'        => true,
                'error_description' => $error_description,
                'sku'               => $this->sku
            ];
    }
}