<?php

declare(strict_types=1);

namespace WB\Modules\Report;

use WB\Core\App;
use WB\Helpers\Curl;
use WB\Helpers\Guid;
use WB\Helpers\Logger;
use WB\Helpers\RequestParameters;
use WB\Helpers\Validation\ValidationRules;
use WB\Helpers\Sanitize;

require_once APP_ROOT . '/helpers/Guid.php';
require_once APP_ROOT . '/helpers/RequestParameters.php';
require_once APP_ROOT . '/helpers/validation/ValidationRules.php';

class ReportModule
{
    use ValidationRules;

    public string $requestMethod = 'GET';
    public string $requestUrl    = 'https://marketplace.walmartapis.com/v3/getReport?type=item_ca';

    public function run(): void
    {
        $file_path = $this->saveCsvFile();

        if (empty($file_path)) {
            return;
        }

        $items = $this->readCsvFile($file_path);

        if (empty($items)) {
            return;
        }

        $count = $this->saveReport($items);

        Logger::log(
                   'There are ' . $count . ' products in the report',
                   __METHOD__,
                   'info',
            alert: true
        );
    }

    private function saveCsvFile(): string
    {
        $dir      = APP_ROOT . '/storage/report/' . App::$shopId;
        $zip_file = $dir . '/report.zip';

        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        } else {
            // clear dir
            array_map(
                fn($item) => unlink($item),
                glob($dir . '/*.csv')
            );
        }

        $request_parameters = RequestParameters::getParameters(
            $this->requestUrl,
            $this->requestMethod
        );

        if (empty($request_parameters)) {
            Logger::log('Request parameters failed', __METHOD__, 'info');

            return '';
        }

        // get report
        $response = (new Curl())->getRequest(
            [
                'url'                   => $this->requestUrl,
                'guid'                  => Guid::getGuid(),
                'timestamp'             => $request_parameters['timestamp'],
                'sign'                  => $request_parameters['sign'],
                'consumer_id'           => App::$options['shops'][App::$shopId]['consumer_id'],
                'consumer_channel_type' => App::$options['shops'][App::$shopId]['consumer_channel_type']
            ]
        );

        if (!empty($response)) {
            try {
                file_put_contents($zip_file, $response);
            } catch (\Throwable $e) {
                Logger::log($e->getMessage(), __METHOD__, 'error');
            }
        }

        // unzip
        exec('unzip -qo ' . $zip_file . ' -d ' . $dir);

        $glob = glob($dir . '/*.csv');

        if (!isset($glob[0])
            || empty($glob[0])
            || pathinfo($glob[0], PATHINFO_EXTENSION) != 'csv'
        ) {
            return '';
        }

        Logger::log(
                   'Report file: ' . basename($glob[0]),
                   __METHOD__,
                   'info',
            alert: true
        );

        // alert - outdated report
        $mask_date         = '_' . date('Y-m-d') . 'T';
        $mask_date_minus_1 = '_' . date('Y-m-d', strtotime('-1 day')) . 'T';

        if (
            false === str_contains($glob[0], $mask_date)
            && false === str_contains($glob[0], $mask_date_minus_1)
        ) {
            preg_match(
                '/_(' . date('Y') . '-[0-9]{2}-[0-9]{2})T/',
                basename($glob[0], '.csv'),
                $match
            );

            $report_date = isset($match[1]) && !empty($match[1]) ?
                $match[1] : '';

            Logger::log(
                       'Report date (' . $report_date
                       . ') differs from the set date',
                       __METHOD__,
                       'info',
                alert: true
            );

            return '';
        }

        return $glob[0];
    }

    public function readCsvFile(string $file_path): array
    {
        $col     = 0;
        $results = [];
        $handle  = @fopen($file_path, 'r');

        if ($handle) {
            while (($row = fgetcsv($handle, 4096)) !== false) {
                if (empty($fields)) {
                    $fields = $row;
                    continue;
                }

                foreach ($row as $k => $value) {
                    $results[$col][$fields[$k]] = $value;
                }
                $col++;
                unset($row);
            }
            if (!feof($handle)) {
                Logger::log(
                    'Error: unexpected fgets() fail',
                    __METHOD__,
                    'info'
                );
            }
            fclose($handle);
        }

        return $results;
    }

    public function saveReport(array $items): int
    {
        // delete by shop_id
        App::$db->run(
            'DELETE FROM walmart_ca.report 
                WHERE shop_id = ?',
            [App::$shopId,]
        );

        App::$db->run(
            'DELETE FROM walmart_ca.report_copy
                WHERE shop_id = ?',
            [App::$shopId]
        );

        $sql
            = 'INSERT INTO walmart_ca.report (shop_id,partner_id,sku,product_name,product_category,price,currency,publish_status,status_change_reason,lifecycle_status,inventory_count,ship_methods,wpid,item_id,gtin,upc,primary_image_url,shelf_name,primary_cat_path,offer_start_date,offer_end_date,item_creation_date,item_last_updated,item_page_url,reviews_count,average_rating,searchable) VALUES ';
        $sql_copy_insert
            = 'INSERT INTO walmart_ca.report_copy (shop_id,partner_id,sku,product_name,product_category,price,currency,publish_status,status_change_reason,lifecycle_status,inventory_count,ship_methods,wpid,item_id,gtin,upc,primary_image_url,shelf_name,primary_cat_path,offer_start_date,offer_end_date,item_creation_date,item_last_updated,item_page_url,reviews_count,average_rating,searchable) VALUES ';

        $values = '';

        foreach ($items as $item) {
            $partner_id = $this->checkString($item['PARTNER ID']) ?
                App::$db->quote(Sanitize::sanitizeString($item['PARTNER ID']))
                : 'null';

            $sku = $this->checkString($item['SKU']) ?
                App::$db->quote(Sanitize::sanitizeString($item['SKU']))
                : 'null';

            $product_name = $this->checkString($item['PRODUCT NAME']) ?
                App::$db->quote(
                    Sanitize::sanitizeStringImport($item['PRODUCT NAME'])
                ) : 'null';

            $product_category = $this->checkString($item['PRODUCT CATEGORY']) ?
                App::$db->quote(
                    Sanitize::sanitizeString($item['PRODUCT CATEGORY'])
                ) : 'null';

            $price = $this->checkFloat($item['PRICE']) ?
                Sanitize::sanitizeFloat($item['PRICE']) : 'null';

            $currency = $this->checkString($item['CURRENCY']) ?
                App::$db->quote(Sanitize::sanitizeString($item['CURRENCY']))
                : 'null';

            $publish_status = $this->checkString($item['PUBLISH STATUS']) ?
                App::$db->quote(
                    Sanitize::sanitizeString($item['PUBLISH STATUS'])
                )
                : 'null';

            $status_change_reason = $this->checkString(
                $item['STATUS CHANGE REASON']
            ) ?
                App::$db->quote(
                    Sanitize::sanitizeString($item['STATUS CHANGE REASON'])
                ) : 'null';

            $lifecycle_status = $this->checkString($item['LIFECYCLE STATUS']) ?
                App::$db->quote(
                    Sanitize::sanitizeString($item['LIFECYCLE STATUS'])
                ) : 'null';

            $inventory_count = $this->checkInt($item['INVENTORY COUNT']) ?
                Sanitize::sanitizeInt($item['INVENTORY COUNT']) : 'null';

            $ship_methods = $this->checkString($item['SHIP METHODS']) ?
                App::$db->quote(Sanitize::sanitizeString($item['SHIP METHODS']))
                : 'null';

            $wpid = $this->checkString($item['WPID']) ?
                App::$db->quote(Sanitize::sanitizeString($item['WPID']))
                : 'null';

            $item_id = $this->checkString($item['ITEM ID']) ?
                App::$db->quote(Sanitize::sanitizeString($item['ITEM ID']))
                : 'null';

            $gtin = $this->checkString($item['GTIN']) ?
                App::$db->quote(Sanitize::sanitizeString($item['GTIN']))
                : 'null';

            $upc = $this->checkString($item['UPC']) ?
                App::$db->quote(Sanitize::sanitizeString($item['UPC']))
                : 'null';

            $primary_image_url = $this->checkString($item['PRIMARY IMAGE URL'])
                ?
                App::$db->quote(
                    Sanitize::sanitizeString($item['PRIMARY IMAGE URL'])
                ) : 'null';

            $shelf_name = $this->checkString($item['SHELF NAME']) ?
                App::$db->quote(Sanitize::sanitizeString($item['SHELF NAME']))
                : 'null';

            $primary_cat_path = $this->checkString($item['PRIMARY CAT PATH']) ?
                App::$db->quote(
                    Sanitize::sanitizeString($item['PRIMARY CAT PATH'])
                ) : 'null';

            $offer_start_date = $this->checkString($item['OFFER START DATE']) ?
                App::$db->quote(
                    $this->dateConvert(
                        Sanitize::sanitizeString($item['OFFER START DATE'])
                    )
                ) : 'null';

            $offer_end_date = $this->checkString($item['OFFER END DATE']) ?
                App::$db->quote(
                    $this->dateConvert(
                        Sanitize::sanitizeString($item['OFFER END DATE'])
                    )
                ) : 'null';

            $item_creation_date = $this->checkString(
                $item['ITEM CREATION DATE']
            ) ?
                App::$db->quote(
                    $this->dateConvert(
                        Sanitize::sanitizeString($item['ITEM CREATION DATE'])
                    )
                ) : 'null';

            $item_last_updated = $this->checkString($item['ITEM LAST UPDATED'])
                ?
                App::$db->quote(
                    $this->dateConvert(
                        Sanitize::sanitizeString($item['ITEM LAST UPDATED'])
                    )
                ) : 'null';

            $item_page_url = $this->checkString($item['ITEM PAGE URL']) ?
                App::$db->quote(
                    Sanitize::sanitizeString($item['ITEM PAGE URL'])
                )
                : 'null';

            $reviews_count = $this->checkInt($item['REVIEWS COUNT']) ?
                Sanitize::sanitizeInt($item['REVIEWS COUNT']) : 'null';

            $average_rating = $this->checkInt($item['AVERAGE RATING']) ?
                Sanitize::sanitizeInt($item['AVERAGE RATING']) : 'null';

            $searchable = $this->checkString($item['SEARCHABLE?']) ?
                App::$db->quote(Sanitize::sanitizeString($item['SEARCHABLE?']))
                : 'null';

            $data = [
                'shop_id'              => App::$shopId,
                'partner_id'           => $partner_id,
                'sku'                  => $sku,
                'product_name'         => $product_name,
                'product_category'     => $product_category,
                'price'                => $price,
                'currency'             => $currency,
                'publish_status'       => $publish_status,
                'status_change_reason' => $status_change_reason,
                'lifecycle_status'     => $lifecycle_status,
                'inventory_count'      => $inventory_count,
                'ship_methods'         => $ship_methods,
                'wpid'                 => $wpid,
                'item_id'              => $item_id,
                'gtin'                 => $gtin,
                'upc'                  => $upc,
                'primary_image_url'    => $primary_image_url,
                'shelf_name'           => $shelf_name,
                'primary_cat_path'     => $primary_cat_path,
                'offer_start_date'     => $offer_start_date,
                'offer_end_date'       => $offer_end_date,
                'item_creation_date'   => $item_creation_date,
                'item_last_updated'    => $item_last_updated,
                'item_page_url'        => $item_page_url,
                'reviews_count'        => $reviews_count,
                'average_rating'       => $average_rating,
                'searchable'           => $searchable
            ];

            $values .= '(' . implode(',', $data) . '),';
        }

        $values = trim($values, ',');
        $sql    .= $values . ' ON CONFLICT DO NOTHING RETURNING sku';

        $sql_copy_insert .= $values . ' ON CONFLICT DO NOTHING RETURNING sku';
        App::$db->run($sql_copy_insert);

        return (int)App::$db->run($sql)->rowCount();
    }

    private function dateConvert(string $date): string
    {
        $array = explode('/', $date);

        return $array[2] . '-' . $array[0] . '-' . $array[1];
    }
}
