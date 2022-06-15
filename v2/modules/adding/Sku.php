<?php

declare(strict_types=1);

namespace WB\Modules\Adding;

use WB\Core\App;
use WB\Helpers\Logger;

/**
 * Class Sku
 *
 * @package Walmart\helpers
 */
class Sku
{
    /**
     * * SKU formula:
     * * {{Country from code}{Source from code}}-{{Source to code}{Source shop
     * ID}}-{asin(encoded)+2 random symbol}-{UPC}
     *
     * Example:
     * * USAM-WL000001-{encrypted asin(10) + 2 character}-{UPC(12)}
     *
     * @param array $items
     *
     * @return array
     */
    public function create(array $items): array
    {
        $already_items = $this->getAlreadyItems();

        $items_with_sku = [];

        $new_sku = $old_sku = 0;

        foreach ($items as $item) {
            // do not generate new sku if sku is already in the report
            if (isset($already_items[$item['asin']])
                && !empty($already_items[$item['asin']])) {
                $sku = $already_items[$item['asin']];

                ++$old_sku;
            } else {
                $sku = $this->template($item);

                ++$new_sku;
            }

            if (!empty($sku)) {
                $item['sku']      = $sku;
                $items_with_sku[] = $item;
            }
        }

        $message = 'Generated new sku: ' . $new_sku . PHP_EOL;
        $message .= 'Used old sku: ' . $old_sku;

        Logger::log($message, __METHOD__, 'info', alert: true);

        return $items_with_sku;
    }

    /**
     * Sku that are already in the report
     */
    public function getAlreadyItems(): array
    {
        $sql = 'SELECT p.asin, r.sku
                FROM walmart_ca.report AS r
                    LEFT JOIN walmart_ca.item_preload AS p
                        ON p.upc = r.upc        
                WHERE r.shop_id = ? AND r.sku IS NOT NULL';

        $data = App::$db->run($sql, [App::$shopId])->fetchAll();

        $result = [];

        if (isset($data) && !empty($data)) {
            foreach ($data as $item) {
                if (isset($item['asin'], $item['sku'])) {
                    $result[$item['asin']] = $item['sku'];
                }
            }
        }

        return $result;
    }

    /**
     * Sku template
     *
     * @param array $item
     *
     * @return string
     */
    public function template(array $item): string
    {
        $part_1 = App::$options['default']['sku_country']
                  . App::$options['default']['sku_source_from'];

        $part_2_id = str_pad((string)App::$shopId, 6, '0', STR_PAD_LEFT);

        if ($part_2_id === '000000') {
            return '';
        }

        $part_2 = App::$options['default']['sku_source_to'] . $part_2_id;

        $part_3 = $this->asinEncode($item['asin']);

        $part_4 = $item['upc'];

        $sku_36 = $part_1 . $part_2 . $part_3 . $part_4;
        $sku    = $part_1 . '-' . $part_2 . '-' . $part_3 . '-' . $part_4;

        if (strlen($sku_36) === App::$options['default']['sku_length']) {
            return $sku;
        }

        return '';
    }

    /**
     * Rearranges asin characters according to the formula
     *
     * @param string $asin
     *
     * @return string
     */
    public function asinEncode(string $asin): string
    {
        $chars       = str_split($asin);
        $sku_combine = array_combine(
            App::$options['default']['asin_reverse'],
            $chars
        );

        uksort($sku_combine, function ($a, $b) {
            if ($a == $b) {
                return 0;
            }

            return ($a < $b) ? -1 : 1;
        });

        // add zero
        foreach (App::$options['default']['asin_zero_position'] as $position) {
            array_splice($sku_combine, $position, 0, 0);
        }

        return implode($sku_combine);
    }
}
