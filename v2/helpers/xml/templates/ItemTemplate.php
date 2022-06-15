<?php

declare(strict_types=1);

namespace WB\Helpers\Xml\Templates;

use WB\Core\App;
use WB\Helpers\Guid;
use WB\Helpers\Xml\XmlValid;

require_once APP_ROOT . '/helpers/Guid.php';
require_once APP_ROOT . '/helpers/xml/XmlValid.php';

/**
 * Class ItemTemplate
 */
class ItemTemplate
{
    /**
     * @param array $data_import
     *
     * @return string
     */
    public static function getFeed(array $data_import): string
    {
        $time = date('Y-m-d\TH:m:s');

        $items = self::itemCreate($data_import, $time);

        if (empty($items)) {
            return '';
        }

        $request_id       = Guid::getGuid();
        $request_batch_id = Guid::getGuid();

        return '<?xml version="1.0" encoding="UTF-8"?>
                <MPItemFeed xmlns:ns2="http://walmart.com/">
                    <MPItemFeedHeader>
                        <version>3.2</version>
                        <requestId>' . $request_id . '</requestId>
                        <requestBatchId>' . $request_batch_id . '</requestBatchId>
                        <feedDate>' . $time . '</feedDate>
                        <mart>WALMART_CA</mart>
                        <locale>en_CA</locale>
                    </MPItemFeedHeader>
                    ' . $items . '
                </MPItemFeed>';
    }

    /**
     * @param array  $data
     * @param string $time
     *
     * @return string
     */
    private static function itemCreate(array $data, string $time): string
    {
        $items = '';

        // for validation
        $head = '<?xml version="1.0" encoding="UTF-8"?>';

        foreach ($data as $item) {
            if (isset($item['sku'])) {
                $item_tmp = self::itemTemplate(self::setItemData($item, $time));

                if (false === XmlValid::exec($head . $item_tmp)) {
                    continue;
                }

                $items .= $item_tmp;
            }
        }

        return $items;
    }

    /**
     * @param $item
     * @param $date
     *
     * @return array
     */
    private static function setItemData($item, $date): array
    {
        $upc      = $item['product_id'] ?? ($item['upc'] ?? false);
        $tax_code = $item['product_tax_code'] ?? ($item['tax_code'] ?? false);
        $image    = $item['main_image_url'] ?? ($item['image'] ?? false);

        if (false === $upc || false === $tax_code || false === $image) {
            return [];
        }

        $price = isset($item['price'])
                 && $item['price']
                    >= App::$options['shops'][App::$shopId]['min_price'] ?
            $item['price'] : App::$options['default']['primary_price'];

        $item['feed_date']         = $date;
        $item['product_id_type']   = 'UPC';
        $item['product_id']        = $upc;
        $item['product_name']      = str_replace(
            '&rsquo;',
            '\'',
            $item['product_name'] ?? ''
        );
        $item['sub_category']      = $item['subcategory'];
        $item['short_description'] = str_replace(
            '&rsquo;',
            '\'',
            $item['short_description'] ?? ''
        );
        $item['main_image_url']    = $image;
        $item['price']             = $price;
        $item['measure']           = $item['shipping_weight'];
        $item['product_tax_code']  = $tax_code;
        // new fields
        $item['gender'] = $item['gender'] ?? null;
        $item['color']  = $item['color'] ?? null;
        $item['size']   = $item['size'] ?? null;

        return $item;
    }

    /**
     * @param array $data
     *
     * @return string
     */
    private static function itemTemplate(array $data)
    {
        return '
<MPItem>
    <feedDate>' . $data['feed_date'] . '</feedDate>
    <sku>' . $data['sku'] . '</sku>
    <productIdentifiers>
        <productIdentifier>
            <productIdType>' . $data['product_id_type'] . '</productIdType>
            <productId>' . $data['product_id'] . '</productId>
        </productIdentifier>
    </productIdentifiers>
    <MPProduct>
        <SkuUpdate>Yes</SkuUpdate>
        <productName>' . $data['product_name'] . '</productName>
        <ProductIdUpdate>Yes</ProductIdUpdate>
        <category>
            <' . $data['category'] . '>
                <' . $data['sub_category'] . '>
                    <shortDescription>' . $data['short_description'] . '</shortDescription>
                    <mainImageUrl>' . $data['main_image_url'] . '</mainImageUrl>
                    <brand>' . $data['brand'] . '</brand>
                    ' . self::setAdditionalParams($data) . '
                </' . $data['sub_category'] . '>
            </' . $data['category'] . '>
        </category>
    </MPProduct>
    <MPOffer>
        <StartDate>' . date('Y-m-d') . '</StartDate>
        <EndDate>' . date(
                'Y-m-d',
                strtotime('+1 month', strtotime(date('Y-m-d')))
            ) . '</EndDate>
        <price>' . $data['price'] . '</price>
            <ShippingWeight>
                <measure>' . $data['measure'] . '</measure>
                <unit>lb</unit>
            </ShippingWeight>
    <ProductTaxCode>' . $data['product_tax_code'] . '</ProductTaxCode>
    </MPOffer>
</MPItem>';
    }

    private static function setAdditionalParams(array $data): string
    {
        // additional params
        $color = isset($data['color']) && strlen($data['color']) > 0 ?
            $data['color'] : false;
        $size  = isset($data['size']) && (float)$data['size'] > 0 ?
            $data['size'] : false;

        $keywords = '';
        if (isset($data['product_name']) && !empty($data['product_name'])) {
            $tmp_name = explode(
                ' ',
                strtolower(
                    trim($data['product_name'])
                )
            );
            $tmp_name = array_filter(
                $tmp_name,
                fn($value) => strlen($value) >= 4 && ctype_alpha($value)
            );

            $keywords = implode(',', $tmp_name);
        }

        $data['age_group'] = str_replace(
            ['Teens'],
            ['Teen'],
            $data['age_group'] ?? ''
        );

        if (!in_array(
            $data['age_group'],
            ['Child', 'Teen', 'Tween', 'Adult', 'Infant', 'Toddler']
        )
        ) {
            $data['age_group'] = 'Adult';
        }

        $add_params = '';
        switch ($data['category']) {
            case 'ClothingCategory':
                $gender = isset($data['gender'])
                          && strtolower($data['gender']) == 'men'
                    ?
                    'Male'
                    : (strtolower($data['gender']) == 'women' ?
                        'Female' : '');

                // params
                $add_params = !empty($gender) ?
                    '<gender>' . $gender . '</gender>' : '';

                if (false !== $color) {
                    $add_params .= '<color>' . $data['color'] . '</color>';
                }
                if (false !== $size) {
                    $add_params .= '<clothingSize>' . $data['size']
                                   . '</clothingSize>';
                }

                $add_params .= isset($data['age_group'])
                               && !empty($data['age_group']) ?
                    '<ageGroup><ageGroupValue>' .
                    $data['age_group'] .
                    '</ageGroupValue></ageGroup>' : '';

                $add_params .= isset($data['material'])
                               && !empty($data['material']) ?
                    '<material>' . $data['material'] . '</material>' : '';

                $add_params .= isset($keywords) && !empty($keywords) ?
                    '<keywords>' . $keywords . '</keywords>' : '';

                break;

            case 'FootwearCategory':
                $gender = isset($data['gender'])
                          && strtolower($data['gender']) == 'men'
                    ?
                    'Men'
                    : (strtolower($data['gender']) == 'women' ?
                        'Women' : '');

                // params
                $add_params = !empty($gender) ?
                    '<gender>' . $gender . '</gender>' : '';

                if (false !== $color) {
                    $add_params .= '<color>' . $data['color'] . '</color>';
                }

                if (false !== $size) {
                    $add_params .= '<shoeSize>' . $data['size'] . '</shoeSize>';
                }

                $add_params .= isset($data['age_group'])
                               && !empty($data['age_group']) ?
                    '<ageGroup><ageGroupValue>' .
                    $data['age_group'] .
                    '</ageGroupValue></ageGroup>' : '';

                $add_params .= isset($data['material'])
                               && !empty($data['material']) ?
                    '<material>' . $data['material'] . '</material>' : '';

                $add_params .= isset($data['dimensions'])
                               && !empty($data['dimensions']) ?
                    '<size>' . $data['dimensions'] . '</size>' : '';

                $add_params .= isset($keywords) && !empty($keywords) ?
                    '<keywords>' . $keywords . '</keywords>' : '';

                break;

            case 'Baby':

                $gender = isset($data['gender'])
                          && strtolower($data['gender']) == 'men'
                    ? 'Male'
                    : (strtolower($data['gender']) == 'women' ?
                        'Female' : '');

                $add_params = !empty($gender) ?
                    '<gender>' . $gender . '</gender>' : '';

                if (false !== $color) {
                    $add_params .= '<color>' . $data['color'] . '</color>';
                }

                if (false !== $size) {
                    $add_params .= '<shoeSize>' . $data['size'] . '</shoeSize>';
                }

                $add_params .= isset($data['material'])
                               && !empty($data['material']) ?
                    '<material>' . $data['material'] . '</material>' : '';

                $add_params .= isset($keywords) && !empty($keywords) ?
                    '<keywords>' . $keywords . '</keywords>' : '';

                break;

            case 'CarriersAndAccessoriesCategory':

                $add_params .= isset($data['age_group'])
                               && !empty($data['age_group']) ?
                    '<ageGroup><ageGroupValue>' .
                    $data['age_group'] .
                    '</ageGroupValue></ageGroup>' : '';

                if (false !== $color) {
                    $add_params .= '<color>' . $data['color'] . '</color>';
                }

                $gender = isset($data['gender'])
                          && strtolower($data['gender']) == 'men'
                    ?
                    'Men'
                    : (strtolower($data['gender']) == 'women' ?
                        'Women' : '');

                $add_params .= !empty($gender) ?
                    '<gender>' . $gender . '</gender>' : '';

                $add_params .= isset($keywords) && !empty($keywords) ?
                    '<keywords>' . $keywords . '</keywords>' : '';

                $add_params .= isset($data['dimensions'])
                               && !empty($data['dimensions']) ?
                    '<size>' . $data['dimensions'] . '</size>' : '';

                $add_params .= isset($data['material'])
                               && !empty($data['material']) ?
                    '<material>' . $data['material'] . '</material>' : '';

                break;

            case 'JewelryCategory':

                $add_params .= isset($data['age_group'])
                               && !empty($data['age_group']) ?
                    '<ageGroup><ageGroupValue>' .
                    $data['age_group'] .
                    '</ageGroupValue></ageGroup>' : '';

                if (false !== $color) {
                    $add_params .= '<color>' . $data['color'] . '</color>';
                }

                $gender = isset($data['gender'])
                          && strtolower($data['gender']) == 'men'
                    ?
                    'Male'
                    : (strtolower($data['gender']) == 'women' ?
                        'Female' : '');

                $add_params .= !empty($gender) ?
                    '<gender>' . $gender . '</gender>' : '';

                $add_params .= isset($keywords) && !empty($keywords) ?
                    '<keywords>' . $keywords . '</keywords>' : '';

                $add_params .= isset($data['dimensions'])
                               && !empty($data['dimensions']) ?
                    '<size>' . $data['dimensions'] . '</size>' : '';

                $add_params .= isset($data['material'])
                               && !empty($data['material']) ?
                    '<material>' . $data['material'] . '</material>' : '';

                break;
        }

        return $add_params;
    }
}
