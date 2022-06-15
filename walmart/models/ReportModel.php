<?php

declare(strict_types=1);

namespace Walmart\models;

use Throwable;
use Walmart\core\App;
use Walmart\helpers\Alerts;
use Walmart\helpers\database\Db;
use Walmart\helpers\Logger;
use Walmart\helpers\request\Curl;
use Walmart\helpers\request\RequestParameters;
use Walmart\helpers\Sanitize;
use Walmart\helpers\Validation;

/**
 * Class TotalReport
 *
 * @package Walmart\models\monitoring
 */
class ReportModel
{
    use Sanitize;
    use Validation;

    private Db $db;

    public function __construct()
    {
        $this->db = new Db();
    }

    /**
     * @return bool|mixed
     */
    public function setRequestData()
    {
        $request_data = RequestParameters::exec('get_report');

        if (false === $request_data) {
            Logger::log('Error creating query parameters', __METHOD__);

            return false;
        }

        return $request_data;
    }

    public function saveCsvFileNew(array $request_data)
    {
        $dir      = APP_ROOT . '/cache/report/' . App::$shopId;
        $zip_file = $dir . '/report.zip';

//        if (!file_exists($dir)) {
//            mkdir($dir, 0755, true);
//        }

        // clear dir
        array_map(
            fn($item) => !is_file($item) || unlink($item),
            glob($dir . '/*')
        );

        // get report
        $response = Curl::getRequest(
            $request_data['url'],
            $request_data['timestamp'],
            $request_data['sign']
        );

        try {
            file_put_contents($zip_file, $response);
        } catch (Throwable $e) {
            Logger::log($e->getMessage(), __METHOD__);
        }

        // unzipя
        exec('unzip -qo ' . $zip_file . ' -d ' . $dir);

        $glob = glob($dir . '/*.csv');

        if (!isset($glob[0])
            || empty($glob[0])
            || pathinfo($glob[0], PATHINFO_EXTENSION) != 'csv') {
            print_r("sosi\n");
            return '';
        }

        // alert - outdated report
        $mask_date         = '_' . date('Y-m-d') . 'T';
        $mask_date_minus_1 = '_' . date('Y-m-d', strtotime('-1 day')) . 'T';

        if (false === str_contains($glob[0], $mask_date)
            && false === str_contains($glob[0], $mask_date_minus_1)
        ) {
            preg_match(
                '/_(' . date('Y') . '-[0-9]{2}-[0-9]{2})T/',
                basename($glob[0], '.csv'),
                $match
            );

            $report_date = isset($match[1]) && !empty($match[1])
                ? $match[1] : '';

            Alerts::sendTelegram(
                'Report date (' . $report_date . ') differs from the set date'
            );
        }

        return $glob[0];
    }

    public function saveCsvFile(array $request_data)
    {
        $dir      = APP_ROOT . '/cache/report/' . App::$shopId;
        $zip_file = $dir . '/report.zip';

        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        $mask_date = '_' . date('Y-m-d') . 'T';

        // if not exists zip file
        if (file_exists($zip_file) && filesize($zip_file) >= 1000) {
            $file_time       = filemtime($zip_file);
            $report_interval = App::$reportInterval * 3600;
            $time_different  = time() - $file_time;

            // check report period
            if ($time_different < $report_interval) {
                Logger::log(
                    'Previous report uploaded less than ' .
                    App::$reportInterval . ' hours ago',
                    __METHOD__,
                    'dev'
                );

                $glob = glob($dir . '/*.csv');

                if (empty($glob)) {
                    return '';
                }

                $tmp          = [];
                $file_by_name = '';

                foreach ($glob as $item) {
                    $tmp[$item] = filemtime($item);

                    if (str_contains($item, $mask_date)) {
                        $file_by_name = $item;
                        break;
                    }
                }

                $file_by_filemtime = array_search(max($tmp), $tmp);

                if ($file_by_filemtime === $file_by_name) {
                    if (!empty($file_by_filemtime)
                        && !empty($file_by_name)) {
                        $newest_report = $file_by_filemtime;
                    }
                }

                return isset($newest_report)
                       && pathinfo($newest_report, PATHINFO_EXTENSION) == 'csv'
                    ? $newest_report : '';
            }
        }

        $response = Curl::getRequest(
            $request_data['url'],
            $request_data['timestamp'],
            $request_data['sign']
        );

        try {
            file_put_contents($zip_file, $response);
        } catch (Throwable $e) {
            Logger::log($e->getMessage(), __METHOD__);
        }

        // unzip
        exec('unzip -qo ' . $zip_file . ' -d ' . $dir);

        $glob = glob($dir . '/*.csv');

        if (empty($glob)) {
            return '';
        }

        $tmp          = [];
        $file_by_name = '';

        foreach ($glob as $item) {
            $tmp[$item] = filemtime($item);

            if (str_contains($item, $mask_date)) {
                $file_by_name = $item;
            }
        }

        $file_by_filemtime = array_search(max($tmp), $tmp);

        if ($file_by_filemtime === $file_by_name) {
            if (!empty($file_by_filemtime)
                && !empty($file_by_name)) {
                $newest_report = $file_by_filemtime;
            }
        }

        $csv_file_new = isset($newest_report)
                        && pathinfo($newest_report, PATHINFO_EXTENSION) == 'csv'
            ? $newest_report : '';

        //$csv_file_new = isset( $glob[0] ) && pathinfo( $glob[0], PATHINFO_EXTENSION ) == 'csv' ? $glob[0] : '';

        if (file_exists($csv_file_new)) {
            return $csv_file_new;
        }

        Logger::log('Not found the report file', __METHOD__);

        return '';
    }

    /**
     * @param string $file_path
     *
     * @return array
     */
    public function readCsvFile(string $file_path)
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
                    'dev'
                );
            }
            fclose($handle);
        }

        return $results;
    }

    public function saveToReportTable(array $items)
    {
        // delete by shop_id
        $sql = 'DELETE FROM walmart_ca.report 
                WHERE shop_id=' . App::$shopId;
        $this->db->run($sql);

        $sql_copy_delete = 'DELETE FROM walmart_ca.report_copy
                WHERE shop_id=' . App::$shopId;
        $this->db->run($sql_copy_delete);


        foreach ($items as $item) {
            $sql
                = 'INSERT INTO walmart_ca.report (shop_id,partner_id,sku,product_name,product_category,price,currency,publish_status,status_change_reason,lifecycle_status,inventory_count,ship_methods,wpid,item_id,gtin,upc,primary_image_url,shelf_name,primary_cat_path,offer_start_date,offer_end_date,item_creation_date,item_last_updated,item_page_url,reviews_count,average_rating,searchable) VALUES ';
            $sql_copy_insert
                = 'INSERT INTO walmart_ca.report_copy (shop_id,partner_id,sku,product_name,product_category,price,currency,publish_status,status_change_reason,lifecycle_status,inventory_count,ship_methods,wpid,item_id,gtin,upc,primary_image_url,shelf_name,primary_cat_path,offer_start_date,offer_end_date,item_creation_date,item_last_updated,item_page_url,reviews_count,average_rating,searchable) VALUES ';

            $values = '';

            $partner_id = $this->checkString($item['PARTNER ID']) ?
                App::$dbh->quote($this->sanitizeString($item['PARTNER ID']))
                : 'null';

            $sku = $this->checkString($item['SKU']) ?
                App::$dbh->quote($this->sanitizeString($item['SKU'])) : 'null';

            $product_name = $this->checkString($item['PRODUCT NAME']) ?
                App::$dbh->quote(
                    $this->sanitizeStringImport($item['PRODUCT NAME'])
                ) : 'null';

            $product_category = $this->checkString($item['PRODUCT CATEGORY']) ?
                App::$dbh->quote(
                    $this->sanitizeString($item['PRODUCT CATEGORY'])
                ) : 'null';

            $price = $this->checkFloat($item['PRICE']) ?
                $this->sanitizeFloat($item['PRICE']) : 'null';

            $currency = $this->checkString($item['CURRENCY']) ?
                App::$dbh->quote($this->sanitizeString($item['CURRENCY']))
                : 'null';

            $publish_status = $this->checkString($item['PUBLISH STATUS']) ?
                App::$dbh->quote($this->sanitizeString($item['PUBLISH STATUS']))
                : 'null';

            $status_change_reason = $this->checkString(
                $item['STATUS CHANGE REASON']
            ) ?
                App::$dbh->quote(
                    $this->sanitizeString($item['STATUS CHANGE REASON'])
                ) : 'null';

            $lifecycle_status = $this->checkString($item['LIFECYCLE STATUS']) ?
                App::$dbh->quote(
                    $this->sanitizeString($item['LIFECYCLE STATUS'])
                ) : 'null';

            $inventory_count = $this->checkInt($item['INVENTORY COUNT']) ?
                $this->sanitizeInt($item['INVENTORY COUNT']) : 'null';

            $ship_methods = $this->checkString($item['SHIP METHODS']) ?
                App::$dbh->quote($this->sanitizeString($item['SHIP METHODS']))
                : 'null';

            $wpid = $this->checkString($item['WPID']) ?
                App::$dbh->quote($this->sanitizeString($item['WPID'])) : 'null';

            $item_id = $this->checkString($item['ITEM ID']) ?
                App::$dbh->quote($this->sanitizeString($item['ITEM ID']))
                : 'null';

            $gtin = $this->checkString($item['GTIN']) ?
                App::$dbh->quote($this->sanitizeString($item['GTIN'])) : 'null';

            $upc = $this->checkString($item['UPC']) ?
                App::$dbh->quote($this->sanitizeString($item['UPC'])) : 'null';

            $primary_image_url = $this->checkString($item['PRIMARY IMAGE URL'])
                ?
                App::$dbh->quote(
                    $this->sanitizeString($item['PRIMARY IMAGE URL'])
                ) : 'null';

            $shelf_name = $this->checkString($item['SHELF NAME']) ?
                App::$dbh->quote($this->sanitizeString($item['SHELF NAME']))
                : 'null';

            $primary_cat_path = $this->checkString($item['PRIMARY CAT PATH']) ?
                App::$dbh->quote(
                    $this->sanitizeString($item['PRIMARY CAT PATH'])
                ) : 'null';

            $offer_start_date = $this->checkString($item['OFFER START DATE']) ?
                App::$dbh->quote(
                    $this->dateConvert(
                        $this->sanitizeString($item['OFFER START DATE'])
                    )
                ) : 'null';

            $offer_end_date = $this->checkString($item['OFFER END DATE']) ?
                App::$dbh->quote(
                    $this->dateConvert(
                        $this->sanitizeString($item['OFFER END DATE'])
                    )
                ) : 'null';

            $item_creation_date = $this->checkString(
                $item['ITEM CREATION DATE']
            ) ?
                App::$dbh->quote(
                    $this->dateConvert(
                        $this->sanitizeString($item['ITEM CREATION DATE'])
                    )
                ) : 'null';

            $item_last_updated = $this->checkString($item['ITEM LAST UPDATED'])
                ?
                App::$dbh->quote(
                    $this->dateConvert(
                        $this->sanitizeString($item['ITEM LAST UPDATED'])
                    )
                ) : 'null';

            $item_page_url = $this->checkString($item['ITEM PAGE URL']) ?
                App::$dbh->quote($this->sanitizeString($item['ITEM PAGE URL']))
                : 'null';

            $reviews_count = $this->checkInt($item['REVIEWS COUNT']) ?
                $this->sanitizeInt($item['REVIEWS COUNT']) : 'null';

            $average_rating = $this->checkInt($item['AVERAGE RATING']) ?
                $this->sanitizeInt($item['AVERAGE RATING']) : 'null';

            $searchable = $this->checkString($item['SEARCHABLE?']) ?
                App::$dbh->quote($this->sanitizeString($item['SEARCHABLE?']))
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

            $values = '(' . implode(',', $data) . ')';
            $sql    .= $values . ' ON CONFLICT DO NOTHING RETURNING sku';

            $sql_copy_insert .= $values . ' ON CONFLICT DO NOTHING RETURNING sku';
            $this->db->run($sql_copy_insert);

            $this->db->run($sql);
        }

        return 1;
    }

    private function dateConvert(string $date)
    {
        $array = explode('/', $date);

        return $array[2] . '-' . $array[0] . '-' . $array[1];
    }
}
