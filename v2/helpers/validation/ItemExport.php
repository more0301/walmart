<?php

declare(strict_types=1);

namespace WB\Helpers\Validation;

use WB\Helpers\Logger;
use WB\Helpers\Sanitize;

require_once APP_ROOT . '/helpers/validation/ValidationRules.php';

/**
 * Class ItemExport
 * Creating items for upload to the store
 *
 * @package Walmart\helpers
 */
class ItemExport
{
    use ValidationRules;

    private array $dependence;

    /**
     * @param       $item
     * @param array $dependence
     *
     * @return array
     */
    public function createItem($item, array $dependence): array
    {
        $this->dependence = $dependence;

        if (isset($item['upc'])) {
            $this->validationId = $item['upc'];
        }

        if (false === $this->validation($item)) {
            return [];
        }

        $sanitize_item = $this->sanitize($item);

        if (false === $sanitize_item) {
            return [];
        }

        return $sanitize_item;
    }

    /**
     * @param $item
     *
     * @return bool
     */
    private function validation($item)
    {
        // asin
        if (in_array('asin', $this->dependence)) {
            if (!isset($item['asin'])
                || false === $this->checkAsin(
                    $item['asin']
                )) {
                Logger::log(
                              'Asin invalid',
                              __METHOD__,
                              'info',
                    hide_log: true
                );

                return false;
            }

            Logger::log(
                          'Asin ' . $item['asin'] . ' validation',
                          __METHOD__,
                          'info',
                hide_log: true
            );

            $asin = true;
        }

        // sku
        if (in_array('sku', $this->dependence)) {
            if (!isset($item['sku'])
                || false === $this->checkSku(
                    $item['sku']
                )) {
                Logger::log(
                              'Sku invalid',
                              __METHOD__,
                              'info',
                    hide_log: true
                );

                return false;
            }

            if (!isset($asin)) {
                Logger::log(
                              'Sku ' . $item['sku'] . ' validation',
                              __METHOD__,
                              'info',
                    hide_log: true
                );
            }
        }

        // upc
        if (in_array('upc', $this->dependence)) {
            if (!isset($item['upc'])
                || false === $this->checkUpc(
                    $item['upc']
                )) {
                Logger::log(
                              'Upc invalid',
                              __METHOD__,
                              'info',
                    hide_log: true
                );

                return false;
            }
        }

        // product_name
        if (in_array('product_name', $this->dependence)) {
            if (!isset($item['product_name'])
                || false === $this->checkProductName(
                    $item['product_name']
                )) {
                Logger::log(
                              'Product name invalid',
                              __METHOD__,
                              'info',
                    hide_log: true
                );

                return false;
            }
        }

        // short_description
        if (in_array('short_description', $this->dependence)) {
            if (!isset($item['short_description'])
                || false === $this->checkShortDescription(
                    $item['short_description']
                )) {
                Logger::log(
                              'Short description invalid',
                              __METHOD__,
                              'info',
                    hide_log: true
                );

                return false;
            }
        }

        // brand
        if (in_array('brand', $this->dependence)) {
            if (!isset($item['brand'])
                || false === $this->checkBrand(
                    $item['brand']
                )) {
                Logger::log(
                              'Brand invalid',
                              __METHOD__,
                              'info',
                    hide_log: true
                );

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
        if (in_array('image', $this->dependence)) {
            if (!isset($item['image'])
                || false === $this->checkImage(
                    $item['image']
                )) {
                Logger::log(
                              'Image invalid',
                              __METHOD__,
                              'info',
                    hide_log: true
                );

                return false;
            }
        }

        // category
        if (in_array('category', $this->dependence)) {
            if (!isset($item['category'])
                || false === $this->checkCategory(
                    $item['category']
                )) {
                Logger::log(
                              'Category invalid',
                              __METHOD__,
                              'info',
                    hide_log: true
                );

                return false;
            }
        }

        // subcategory
        if (in_array('subcategory', $this->dependence)) {
            if (!isset($item['subcategory'])
                || false === $this->checkSubCategory(
                    $item['subcategory']
                )) {
                Logger::log(
                              'Subcategory invalid',
                              __METHOD__,
                              'info',
                    hide_log: true
                );

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
        if (in_array('price', $this->dependence)) {
            if (!isset($item['price'])
                || false === $this->checkPrice(
                    $item['price']
                )) {
                Logger::log(
                              'Price invalid',
                              __METHOD__,
                              'info',
                    hide_log: true
                );

                return false;
            }
        }

        // inventory unit
        if (in_array('inventory_unit', $this->dependence)) {
            if (!isset($item['inventory_unit'])
                || false === $this->checkString(
                    $item['inventory_unit']
                )) {
                Logger::log(
                              'Inventory unit invalid',
                              __METHOD__,
                              'info',
                    hide_log: true
                );

                return false;
            }
        }

        // inventory amount
        if (in_array('inventory_amount', $this->dependence)) {
            if (!isset($item['inventory_amount'])
                || false === $this->checkInventoryAmount(
                    $item['inventory_amount']
                )) {
                Logger::log(
                              'Inventory amount invalid',
                              __METHOD__,
                              'info',
                    hide_log: true
                );

                return false;
            }
        }

        // inventory fulfillment
        if (in_array('inventory_fulfillment', $this->dependence)) {
            if (!isset($item['inventory_fulfillment'])
                || false === $this->checkInventoryFulfillment(
                    $item['inventory_fulfillment']
                )) {
                Logger::log(
                              'Inventory fulfillment invalid',
                              __METHOD__,
                              'info',
                    hide_log: true
                );

                return false;
            }
        }

        $in_array_gender = in_array('gender', $this->dependence);
        $gcs             = 0;
        // gender
        if ($in_array_gender) {
            if (isset($item['gender'])
                && false !== $this->checkGender($item['gender'])) {
                $gcs += 1;
            } else {
                Logger::log(
                              'Gender invalid',
                              __METHOD__,
                              'info',
                    hide_log: true
                );
            }
        }

        // color
        $in_array_color = in_array('color', $this->dependence);
        if ($in_array_color) {
            if (isset($item['color'])
                && false !== $this->checkColor($item['color'])) {
                $gcs += 1;
            } else {
                Logger::log(
                              'Color invalid',
                              __METHOD__,
                              'info',
                    hide_log: true
                );
            }
        }

        // size
        $in_array_size = in_array('size', $this->dependence);
        if ($in_array_size) {
            if (isset($item['size'])
                && false !== $this->checkSize($item['size'])) {
                $gcs += 1;
            } else {
                Logger::log(
                              'Size invalid',
                              __METHOD__,
                              'info',
                    hide_log: true
                );
            }
        }

        //if ( true === App::$requiredGCS ) {
        if ($gcs !== 3
            && ($in_array_gender || $in_array_color
                || $in_array_size)) {
            Logger::log(
                          'Gender/color/size invalid',
                          __METHOD__,
                          'info',
                hide_log: true
            );

            return false;
        }
        //}

        // price_amazon
        $in_array_price_amazon = in_array('price_amazon', $this->dependence);
        if ($in_array_price_amazon) {
            $price_amazon = true;
            if (!isset($item['price_amazon'])
                || false === $this->checkPriceAmazon(
                    $item['price_amazon']
                )) {
                $price_amazon = null;

                Logger::log(
                              'Price amazon invalid',
                              __METHOD__,
                              'info',
                    hide_log: true
                );
            }
        }

        // price_prime
        $in_array_price_prime = in_array('price_prime', $this->dependence);
        if ($in_array_price_prime) {
            $price_prime = true;
            if (!isset($item['price_prime'])
                || false === $this->checkPricePrime(
                    $item['price_prime']
                )) {
                $price_prime = null;

                Logger::log(
                              'Price prime invalid',
                              __METHOD__,
                              'info',
                    hide_log: true
                );
            }
        }

        // price_amazon and price_prime
        if (!isset($price_amazon) && !isset($price_prime)
            && $in_array_price_amazon
            && $in_array_price_prime) {
            Logger::log(
                          'Price amazon/prime invalid',
                          __METHOD__,
                          'info',
                hide_log: true
            );

            return false;
        }

        return true;
    }

    /**
     * @param $item
     *
     * @return array|bool
     */
    private function sanitize($item): array|bool
    {
        $sanitize = [];

        if (in_array('shop_id', $this->dependence)) {
            $sanitize['shop_id'] = Sanitize::sanitizeInt($item['shop_id']);
        }

        if (in_array('asin', $this->dependence)) {
            Logger::log(
                          'Asin ' . $item['asin'] . ' sanitize',
                          __METHOD__,
                          'info',
                hide_log: true
            );

            $sanitize['asin'] = Sanitize::sanitizeString($item['asin']);
        }

        if (in_array('sku', $this->dependence)) {
            if (!in_array('asin', $this->dependence)) {
                Logger::log(
                              'Sku ' . $item['sku'] . ' sanitize',
                              __METHOD__,
                              'info',
                    hide_log: true
                );
            }

            $sanitize['sku'] = Sanitize::sanitizeString($item['sku']);
        }

        if (in_array('upc', $this->dependence)) {
            $sanitize['upc'] = Sanitize::sanitizeString($item['upc']);
        }
        if (in_array('product_name', $this->dependence)) {
            $sanitize['product_name'] = Sanitize::sanitizeStringExport(
                $item['product_name']
            );
        }
        if (in_array('short_description', $this->dependence)) {
            $sanitize['short_description'] = Sanitize::sanitizeStringExport(
                $item['short_description']
            );
        }
        if (in_array('brand', $this->dependence)) {
            $sanitize['brand'] = Sanitize::sanitizeStringExport($item['brand']);
        }
        if (in_array('shipping_weight', $this->dependence)) {
            $sanitize['shipping_weight'] = Sanitize::sanitizeFloat(
                $item['shipping_weight']
            );
        }
        if (in_array('tax_code', $this->dependence)) {
            $sanitize['tax_code'] = Sanitize::sanitizeInt($item['tax_code']);
        }
        if (in_array('image', $this->dependence)) {
            $sanitize['image'] = Sanitize::sanitizeString($item['image']);
        }
        if (in_array('category', $this->dependence)) {
            $sanitize['category'] = Sanitize::sanitizeString($item['category']);
        }
        if (in_array('subcategory', $this->dependence)) {
            $sanitize['subcategory'] = Sanitize::sanitizeString(
                $item['subcategory']
            );
        }
        if (in_array('currency', $this->dependence)) {
            $sanitize['currency'] = Sanitize::sanitizeString($item['currency']);
        }
        if (in_array('price', $this->dependence)) {
            $sanitize['price'] = Sanitize::sanitizeFloat($item['price']);
        }
        if (in_array('inventory_unit', $this->dependence)) {
            $sanitize['inventory_unit'] = Sanitize::sanitizeString(
                $item['inventory_unit']
            );
        }
        if (in_array('inventory_amount', $this->dependence)) {
            $sanitize['inventory_amount'] = Sanitize::sanitizeInt(
                $item['inventory_amount']
            );
        }
        if (in_array('inventory_fulfillment', $this->dependence)) {
            $sanitize['inventory_fulfillment'] = Sanitize::sanitizeInt(
                $item['inventory_fulfillment']
            );
        }
        if (in_array('gender', $this->dependence)) {
            $sanitize['gender'] = Sanitize::sanitizeString($item['gender']);
        }
        if (in_array('color', $this->dependence)) {
            $sanitize['color'] = Sanitize::sanitizeString($item['color']);
        }
        if (in_array('size', $this->dependence)) {
            $sanitize['size'] = Sanitize::sanitizeString($item['size']);
        }

        // price_amazon
        if (in_array('price_amazon', $this->dependence)) {
            $sanitize['price_amazon'] = Sanitize::sanitizeFloat(
                $item['price_amazon']
            );
        }
        // price_prime
        if (in_array('price_prime', $this->dependence)) {
            $sanitize['price_prime'] = Sanitize::sanitizeFloat(
                $item['price_prime']
            );
        }

        $total = count($sanitize) === count($this->dependence);

        if (!$total) {
            Logger::log(
                          'Sanitize failed',
                          __METHOD__,
                          'info',
                hide_log: true
            );

            return false;
        }

        Logger::log(
                      'Sanitize success',
                      __METHOD__,
                      'info',
            hide_log: true
        );

        return $sanitize;
    }
}
