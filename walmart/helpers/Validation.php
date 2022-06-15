<?php

declare(strict_types=1);

namespace Walmart\helpers;

use Walmart\config\Options;
use Walmart\core\App;
use Walmart\libraries\FastImage;

/**
 * Trait Validation
 * Return
 * true - validation success
 * false - filed validation
 *
 * @package Walmart\helpers
 */
trait Validation
{
    public $validationId = '';

    private array  $rules;
    private string $name;
    private        $value;
    private string $type;

    public float $declaredPrice; // sent price

    public function checkString($value)
    {
        return $this->checkValue('string', $value);
    }

    public function checkFloat($value)
    {
        return $this->checkValue('float', $value);
    }

    public function checkInt($value)
    {
        return $this->checkValue('int', $value);
    }

    /**
     * @param $value
     *
     * @return bool
     */
    public function checkAsin($value)
    {
        return $this->checkValue('asin', $value);
    }

    /**
     * @param $value
     *
     * @return bool
     */
    public function checkSku($value)
    {
        return $this->checkValue('sku', $value);
    }

    /**
     * @param $value
     *
     * @return bool
     */
    public function checkUpc($value)
    {
        return $this->checkValue('upc', $value);
    }

    /**
     * @param $value
     *
     * @return bool
     */
    public function checkProductName($value)
    {
        return $this->checkValue('product_name', $value);
    }

    /**
     * @param $value
     *
     * @return bool
     */
    public function checkCategory($value)
    {
        return $this->checkValue('category', $value);
    }

    /**
     * @param $value
     *
     * @return bool
     */
    public function checkSubCategory($value)
    {
        return $this->checkValue('subcategory', $value);
    }

    /**
     * @param $value
     *
     * @return bool
     */
    public function checkShortDescription($value)
    {
        return $this->checkValue('short_description', $value);
    }

    /**
     * @param $value
     *
     * @return bool
     */
    public function checkImage($value)
    {
        return $this->checkValue('image', $value);
    }

    /**
     * @param $value
     *
     * @return bool
     */
    public function checkBrand($value)
    {
        return $this->checkValue('brand', $value);
    }

    /**
     * @param $value
     *
     * @return bool
     */
    public function checkPrice($value)
    {
        return $this->checkValue('price', $value);
    }

    public function checkPriceAmazon($value)
    {
        return $this->checkValue('price_amazon', $value);
    }

    public function checkPricePrime($value)
    {
        return $this->checkValue('price_prime', $value);
    }

    /**
     * @param $value
     *
     * @return bool
     */
    public function checkShippingWeight($value)
    {
        return $this->checkValue('shipping_weight', $value);
    }

    /**
     * @param $value
     *
     * @return bool
     */
    public function checkTaxCode($value)
    {
        return $this->checkValue('tax_code', $value);
    }

    /**
     * @param $value
     *
     * @return bool
     */
    public function checkInventoryUnit($value)
    {
        return $this->checkValue('inventory_unit', $value);
    }

    /**
     * @param $value
     *
     * @return bool
     */
    public function checkInventoryAmount($value)
    {
        return $this->checkValue('inventory_amount', $value);
    }

    /**
     * @param $value
     *
     * @return bool
     */
    public function checkInventoryFulfillment($value)
    {
        return $this->checkValue('inventory_fulfillment', $value);
    }

    public function checkGender($value)
    {
        return $this->checkValue('gender', $value);
    }

    public function checkColor($value)
    {
        return $this->checkValue('color', $value);
    }

    public function checkSize($value)
    {
        return $this->checkValue('size', $value);
    }

    // ========== Response validation ========== //

    /**
     *
     * @param $value : item received with walmart
     *
     * @return bool
     */
    public function checkItemStatus($value)
    {
        if (isset($value['ItemResponse']['publishedStatus'])) {
            return $this->checkValue(
                'item_status',
                $value['ItemResponse']['publishedStatus']
            );
        }

        return false;
    }

    /**
     * @param $declared_price : sent to the store
     * @param $price_received : real in store
     */
    public function checkPriceStatus($declared_price, $price_received)
    {
        $this->declaredPrice = $declared_price;
    }

    public function checkInventoryStatus()
    {
    }

    /**
     * @param $value
     *
     * @return bool
     */
    public function checkShelf($value)
    {
        return $this->checkValue('shelf', $value);
    }

    /**
     * @param $value
     *
     * @return bool
     */
    public function checkProductType($value)
    {
        return $this->checkValue('product_type', $value);
    }

    /**
     * @param $value
     *
     * @return bool
     */
    public function checkPriceCurrency($value)
    {
        return $this->checkValue('price_currency', $value);
    }

    /**
     * @param $value
     *
     * @return bool
     */
    public function checkPriceAmount($value)
    {
        return $this->checkValue('price_amount_type', $value);
    }

    /**
     * @param $name  : name of the variable to be checked
     * @param $value : value of the variable to be checked
     *
     * @return bool
     */
    private function checkValue($name, $value): bool
    {
        $this->rules = $this->setRules($name);

        $allowed_null = $this->rules['allowed_null'] ?? false; // null

        if (false === $allowed_null
            && false === $this->checkIsset(
                $this->rules,
                $name,
                $value
            )) {
            return false;
        }

        $this->name    = $name;
        $this->value   = $value;
        $this->type    = $this->rules['type'];
        $allowed_zero  = $this->rules['allowed_zero'] ?? false;  // int/float
        $allowed_empty = $this->rules['allowed_empty'] ?? false; // string

        if (false === $this->checkType()) {
            return false;
        }

        if (false === $this->checkEmpty(
                $allowed_zero,
                $allowed_empty,
                $allowed_null
            )) {
            return false;
        }

        if (isset($this->rules['length'])) {
            if (false === $this->checkLength(
                    $this->rules['length']
                )) {
                return false;
            }
        }

        if (isset($this->rules['image_extension'])) {
            if (false === $this->checkImageExtension()) {
                return false;
            }
        }

        if (isset($this->rules['image_size'])) {
            if (false === $this->checkImageSize()) {
                return false;
            }
        }

        if (isset($this->rules['min_value'])) {
            if (false === $this->checkMinValue()) {
                return false;
            }
        }

        if (isset($this->rules['max_value'])) {
            if (false === $this->checkMaxValue()) {
                return false;
            }
        }

        if (isset($this->rules['equal'])) {
            if (false === $this->checkEqualValue()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param        $rules
     * @param string $name
     * @param        $value
     *
     * @return bool
     */
    private function checkIsset($rules, $name, $value): bool
    {
        if (isset($rules) && null !== $value) {
            return true;
        }

        Logger::log(
            'Id: ' . $this->validationId . ' | ' . ucfirst($name)
            . ' does not exist',
            __METHOD__,
            'dev'
        );

        return false;
    }

    /**
     * @param $allowed_zero
     *
     * @param $allowed_empty
     *
     * @param $allowed_null
     *
     * @return bool
     */
    private function checkEmpty(
        $allowed_zero,
        $allowed_empty,
        $allowed_null
    ): bool {
        switch ($this->type) {
            case 'string':
                if (true === $allowed_null) {
                    return true;
                }
                if (true === $allowed_empty) {
                    return true;
                }
                if (!empty($this->value) && $this->value !== '') {
                    return true;
                }
                break;
            case 'int' || 'float':
                if (true === $allowed_zero) {
                    if ($this->value >= 0) {
                        return true;
                    }
                } else {
                    if (!empty($this->value) && $this->value > 0) {
                        return true;
                    }
                }
                break;
        }

        Logger::log(
            'Id: ' . $this->validationId . ' | ' .
            ucfirst($this->name) . ' is empty',
            __METHOD__,
            'dev'
        );

        return false;
    }

    /**
     * @param $value
     *
     * @return bool
     */
    private function checkType(): bool
    {
        $type_func = 'is_' . $this->type;

        switch ($this->type) {
            case 'string':
                $this->value = (string)$this->value;
                break;
            case 'int':
                $this->value = (int)$this->value;
                break;
            case 'float':
                $this->value = (float)$this->value;
                break;
            case 'bool':
                $this->value = (bool)$this->value;
                break;
        }

        if (true === $type_func($this->value)) {
            return true;
        }

        Logger::log(
            'Id: ' . $this->validationId . ' | ' . ucfirst($this->name)
            . ' is not a ' . $this->type,
            __METHOD__,
            'dev'
        );

        return false;
    }

    /**
     * @param int $reference_length
     *
     * @return bool
     */
    private function checkLength(int $reference_length): bool
    {
        if (is_string($this->value)) {
            $value  = ($this->name == 'sku') ?
                str_replace('-', '', $this->value ?? '') : $this->value;
            $length = strlen($value);
        } elseif (is_int($this->value)) {
            $length = count(str_split((string)$this->value));
        } elseif (is_float($this->value)) {
            // casting to int is needed to remove extra zeros
            $length = count(str_split((string)$this->value));
        } else {
            $length = 0;
        }

        if ($length == $reference_length) {
            return true;
        }

        Logger::log(
            'Id: ' . $this->validationId . ' | ' . ucfirst($this->name)
            . ' is different from the reference length ' . $reference_length
            . ' of ' . $length . ' characters',
            __METHOD__,
            'dev'
        );

        return false;
    }

    /**
     * @return bool
     */
    private function checkImageExtension()
    {
        $current_ext = pathinfo($this->value, PATHINFO_EXTENSION);

        if (false === in_array(
                $current_ext,
                $this->rules['image_extension']
            )) {
            Logger::log(
                'Id: ' . $this->validationId . ' | Invalid image extension '
                . $this->value,
                __METHOD__,
                'dev'
            );

            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    private function checkImageSize()
    {
        $min_width  = $this->rules['image_size']['min_width'];
        $min_height = $this->rules['image_size']['min_height'];

        // getimagesize or fast_image
        switch (App::$imagesizeFunc) {
            case 'getimagesize':
                [
                    $current_width,
                    $current_height
                ]
                    = @getimagesize($this->value);
                break;
            case 'fast_image':
                $fast_image = new FastImage($this->value);
                [
                    $current_width,
                    $current_height
                ]
                    = $fast_image->getSize();
                $fast_image = null;
                break;
            default:
                $current_width = $current_height = 0;
        }

        if ($current_width < $min_width && $current_height < $min_height) {
            Logger::log(
                'Id: ' . $this->validationId . ' | Invalid image sizes '
                . $this->value,
                __METHOD__,
                'dev'
            );

            return false;
        } elseif ($current_width < $min_width) {
            Logger::log(
                'Id: ' . $this->validationId . ' | Invalid image width '
                . $this->value,
                __METHOD__,
                'dev'
            );

            return false;
        } elseif ($current_height < $min_height) {
            Logger::log(
                'Id: ' . $this->validationId . ' | Invalid image height '
                . $this->value,
                __METHOD__,
                'dev'
            );

            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    private function checkMinValue()
    {
        if ($this->value >= $this->rules['min_value']) {
            return true;
        }

        Logger::log(
            'Id: ' . $this->validationId . ' | ' . ucfirst($this->name) . ' '
            . $this->value .
            ' is less than the minimum value ' . $this->rules['min_value'],
            __METHOD__,
            'dev'
        );

        return false;
    }

    /**
     * @return bool
     */
    private function checkMaxValue()
    {
        if ($this->value <= $this->rules['max_value']) {
            return true;
        }

        Logger::log(
            'Id: ' . $this->validationId . ' | ' . ucfirst($this->name) . ' '
            . $this->value .
            ' value is greater than maximum value ' .
            $this->rules['max_value'],
            __METHOD__,
            'dev'
        );

        return false;
    }

    /**
     * @return bool
     */
    private function checkEqualValue()
    {
        if ($this->value === $this->rules['equal']) {
            return true;
        }

        Logger::log(
            'Id: ' . $this->validationId . ' | ' . ucfirst($this->name) . ' '
            . $this->value . ' different from default ' . $this->rules['equal'],
            __METHOD__,
            'dev'
        );

        return false;
    }

    /**
     * @param string $name
     *
     * @return array
     */
    private function setRules(string $name)
    {
        switch ($name) {
            case 'string':
            case 'product_name':
            case 'category':
            case 'subcategory':
            case 'short_description':
            case 'brand':
            case 'inventory_unit':
            case 'shelf':
            case 'product_type':
            case 'price_currency':
            case 'gender':
            case 'color':
            case 'size':
                return ['type' => 'string'];

            case 'float':
            case 'price_amount_type':
            case 'price_amazon':
            case 'price_prime':
                return ['type' => 'float'];

            case 'int':
                return ['type' => 'int'];

            case 'asin':
                return ['type' => 'string', 'length' => 10];

            case 'sku':
                return ['type' => 'string', 'length' => 36];

            case 'upc':
                return ['type' => 'string', 'length' => 12];

            case 'image':
                return [
                    'type'            => 'string',
                    'image_extension' => ['jpg', 'jpeg', 'png'],
                    'image_size'      => [
                        'min_width'  => 500,
                        'min_height' => 500
                    ]
                ];

            case 'price':
                return ['type' => 'float', 'min_value' => App::$minPrice];

            case 'shipping_weight':
                return [
                    'type'         => 'float',
                    'allowed_zero' => true,
                    'allowed_null' => true
                ];

            case 'tax_code':
                return ['type' => 'int', 'allowed_zero' => true];

            case 'inventory_amount':
                return [
                    'type'         => 'int',
                    'max_value'    => App::$maxInventoryQuantity,
                    'min_value'    => App::$minInventoryQuantity,
                    'allowed_zero' => true
                ];
            case 'inventory_fulfillment':
                return ['type' => 'int', 'equal' => App::$inventoryFulfillment];

            // check walmart response
            // $item['ItemResponse']['publishedStatus']
            case 'item_status':
            case 'price_status':
                return ['type' => 'string', 'equal' => 'PUBLISHED'];

            case 'price_difference':
                return ['type' => 'float', 'equal' => $this->declaredPrice];

            default:
                return [];
        }
    }
}
