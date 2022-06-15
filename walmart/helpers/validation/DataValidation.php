<?php
declare( strict_types=1 );

namespace Walmart\helpers\validation;

use Walmart\config\Options;
use Walmart\core\App;
use Walmart\helpers\Logger;
use Walmart\libraries\FastImage;

/**
 * Class DataValidation
 *
 * @package Walmart\helpers\validation
 */
class DataValidation
{
    /**
     * @param array $data
     *
     * @return array|bool
     */
    public static function exec( array $data )
    {
        $rules = [
            'asin' => [
                'type'     => 'string',
                'length'   => 10,
                'sanitize' => false
            ],
            'sku' => [
                'type'     => 'string',
                'length'   => 39,
                'sanitize' => false
            ],
            'upc' => [
                'type'     => 'string',
                'length'   => 12,
                'sanitize' => false
            ],
            'product_name' => [
                'type'     => 'string',
                'sanitize' => true
            ],
            'category' => [
                'type'     => 'string',
                'sanitize' => true
            ],
            'sub_category' => [
                'type'     => 'string',
                'sanitize' => true
            ],
            'short_description' => [
                'type'     => 'string',
                'sanitize' => true
            ],
            'image' => [
                'type'            => 'string',
                'image_extension' => [ 'jpg', 'jpeg', 'png' ],
                'image_size'      => [
                    'min_width'  => 500,
                    'min_height' => 500
                ],
                'sanitize'        => false,
                'url_encode'      => true
            ],
            'brand' => [
                'type'     => 'string',
                'sanitize' => true
            ],
            'price' => [
                'type'      => 'float',
                'min_price' => App::$minPrice,
                'sanitize'  => true
            ],
            'shipping_weight' => [
                'type'     => 'float',
                'allowed'  => 'empty',
                'sanitize' => true
            ],
            'tax_code'    => [
                'type'     => 'int',
                'allowed'  => 'empty',
                'sanitize' => true
            ],
            // inventory
            'unit'        => [
                'type'     => 'string',
                'sanitize' => false
            ],
            'amount'      => [
                'type'         => 'int',
                'max_quantity' => App::$maxInventoryQuantity,
                'allowed'      => 'empty',
                'sanitize'     => false
            ],
            'fulfillment' => [
                'type'     => 'int',
                'sanitize' => false
            ],
            // item info from walmart
            //            'wpid'        => [
            //                'type'     => 'string',
            //                'sanitize' => true
            //            ],
            //            'gtin'        => [
            //                'type'     => 'string',
            //                'sanitize' => true
            //            ],
            'productName' => [
                'type'     => 'string',
                'sanitize' => true
            ],
            //            'shelf'       => [
            //                'type'     => 'string',
            //                'sanitize' => true
            //            ],
            'productType' => [
                'type'     => 'string',
                'sanitize' => true
            ]
        ];

        $valid = [];

        $error = [];
        foreach ( $data as $item ) {

            foreach ( $item as $key => &$value ) {

                // check for a rule
                if ( isset( $rules[ $key ] ) ) {
                    $rule = $rules[ $key ];

                    // filter types
                    if ( $rule['type'] === 'string' ) {
                        if ( true === $rule['sanitize'] ) {
                            //                            $value = filter_var( $value, FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_AMP );
                            $value = filter_var( $value, FILTER_UNSAFE_RAW, FILTER_FLAG_ENCODE_LOW | FILTER_FLAG_STRIP_HIGH );
                            $value = htmlspecialchars( strip_tags( $value ), ENT_QUOTES, 'UTF-8', false );
                        }
                        else {
                            $value = (string)( $value );
                        }
                    }
                    elseif ( $rule['type'] === 'float' ) {
                        if ( true === $rule['sanitize'] ) {
                            $value = (float)filter_var( $value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION );
                        }
                        else {
                            $value = (float)( $value );
                        }
                    }
                    elseif ( $rule['type'] === 'int' ) {
                        if ( true === $rule['sanitize'] ) {
                            $value = (int)filter_var( $value, FILTER_SANITIZE_NUMBER_INT );
                        }
                        else {
                            $value = (int)( $value );
                        }
                    }

                    // check type
                    $type_func = 'is_' . $rule['type'];
                    if ( false === $type_func( $value ) ) {

                        // error count
                        if ( isset( $error['check_type'] ) ) {
                            $error['check_type'] += 1;
                        }
                        else {
                            $error['check_type'] = 1;
                        }

                        continue 2;
                    }

                    // empty value
                    if ( empty( $value ) ) {
                        if ( !isset( $rule['allowed'] ) || $rule['allowed'] !== 'empty' ) {

                            // error count
                            if ( isset( $error['empty_value'] ) ) {
                                $error['empty_value'] += 1;
                            }
                            else {
                                $error['empty_value'] = 1;
                            }

                            continue 2;
                        }
                    }

                    // length
                    if ( isset( $rule['length'] ) ) {

                        if ( strlen( $value ) !== $rule['length'] ) {

                            // error count
                            if ( isset( $error['length'] ) ) {
                                $error['length'] += 1;
                            }
                            else {
                                $error['length'] = 1;
                            }

                            continue 2;
                        }
                    }

                    // min length
                    if ( isset( $rule['min_length'] ) ) {
                        if ( strlen( $value ) < $rule['min_length'] ) {

                            // error count
                            if ( isset( $error['min_length'] ) ) {
                                $error['min_length'] += 1;
                            }
                            else {
                                $error['min_length'] = 1;
                            }

                            continue 2;
                        }
                    }

                    // max length
                    if ( isset( $rule['max_length'] ) ) {
                        if ( strlen( $value ) > $rule['max_length'] ) {

                            // error count
                            if ( isset( $error['max_length'] ) ) {
                                $error['max_length'] += 1;
                            }
                            else {
                                $error['max_length'] = 1;
                            }

                            continue 2;
                        }
                    }

                    // image extension
                    if ( isset( $rule['image_extension'] ) ) {
                        $current_ext = pathinfo( $value, PATHINFO_EXTENSION );
                        if ( false === in_array( $current_ext, $rule['image_extension'] ) ) {

                            // error count
                            if ( isset( $error['image_extension'] ) ) {
                                $error['image_extension'] += 1;
                            }
                            else {
                                $error['image_extension'] = 1;
                            }

                            continue 2;
                        }
                    }

                    // image size
                    if ( isset( $rule['image_size'] ) ) {
                        $min_width  = $rule['image_size']['min_width'];
                        $min_height = $rule['image_size']['min_height'];

                        // getimagesize or fast_image
                        switch ( App::$imagesizeFunc ) {
                            case 'getimagesize':
                                [
                                    $current_width,
                                    $current_height
                                ] = @getimagesize( $value );
                                break;
                            case 'fast_image':
                                $fast_image = new FastImage( $value );
                                [
                                    $current_width,
                                    $current_height
                                ] = $fast_image->getSize();
                                $fast_image = null;
                                break;
                            default:
                                $current_width = $current_height = 0;
                        }

                        if ( $current_width < $min_width || $current_height < $min_height ) {

                            // error count
                            if ( isset( $error['min_width'] ) ) {
                                $error['min_width'] += 1;
                            }
                            else {
                                $error['min_width'] = 1;
                            }

                            continue 2;
                        }
                    }

                    // max_quantity
                    if ( isset( $rule['max_quantity'] ) ) {
                        if ( (int)$value > $rule['max_quantity'] ) {

                            // error count
                            if ( isset( $error['max_quantity'] ) ) {
                                $error['max_quantity'] += 1;
                            }
                            else {
                                $error['max_quantity'] = 1;
                            }

                            continue 2;
                        }
                    }

                    // min price
                    if ( isset( $rule['min_price'] ) ) {
                        $value = $value >= $rule['min_price'] ?
                            $value : $rule['min_price'];
                    }
                }
            }
            $valid[] = $item;
        }

        foreach ( $error as $key => $item_e ) {
            Logger::log( 'Validation errors: ' . $key . ': ' . $item_e, __METHOD__ );
        }

        return count( $valid ) > 0 ? $valid : false;
    }
}