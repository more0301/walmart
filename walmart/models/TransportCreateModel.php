<?php
declare( strict_types=1 );

namespace Walmart\models;

use Walmart\core\App;
use Walmart\helpers\database\Db;

class TransportCreateModel
{
    public Db $db;

    public string $dumpDirExport       = APP_ROOT . '/dumps/export/';
    public string $dumpDirExportOrders = APP_ROOT . '/dumps/export_orders/';

    public function __construct()
    {
        $this->db = new Db();

        if ( !file_exists( $this->dumpDirExport ) ) {
            mkdir( $this->dumpDirExport, 0777, true );
        }
        if ( !file_exists( $this->dumpDirExportOrders ) ) {
            mkdir( $this->dumpDirExportOrders, 0777, true );
        }
    }

    public function makeFileOrders()
    {
        $file   = $this->dumpDirExportOrders . 'orders_walmart_ca.gz';
        $select = 'SELECT * FROM walmart_ca.orders_walmart_ca';

        $this->makeFile( $select, $file );
    }

    public function makeFileReport()
    {
        // temp report
        $temp_report = 'CREATE TEMP TABLE report_temp
(
    shop_id              INT NOT NULL,
    partner_id           TEXT,
    sku                  TEXT,
    product_name         TEXT,
    product_category     TEXT,
    price                DECIMAL(10, 2),
    currency             TEXT,
    publish_status       TEXT,
    status_change_reason TEXT,
    lifecycle_status     TEXT,
    inventory_count      SMALLINT,
    ship_methods         TEXT,
    wpid                 TEXT,
    item_id              TEXT,
    gtin                 TEXT,
    upc                  TEXT,
    primary_image_url    TEXT,
    shelf_name           TEXT,
    primary_cat_path     TEXT,
    offer_start_date     DATE,
    offer_end_date       DATE,
    item_creation_date   DATE,
    item_last_updated    DATE,
    item_page_url        TEXT,
    reviews_count        SMALLINT,
    average_rating       SMALLINT,
    searchable           TEXT,
    date_create          TIMESTAMP DEFAULT current_timestamp,
    last_sent_price      DECIMAL(10, 2),
    date_sent_price      TIMESTAMP DEFAULT current_timestamp,
    brand                TEXT,
    tax_code             INT,
    short_description    TEXT,
    shipping_weight      DECIMAL(10, 2),
    category             TEXT,
    subcategory          TEXT,
    gender               TEXT,
    color                TEXT,
    size                 TEXT
)';
        $this->db->run( $temp_report );

        $insert_report = 'INSERT INTO report_temp (shop_id,partner_id,sku,product_name,product_category,price,currency,publish_status,status_change_reason,lifecycle_status,inventory_count,ship_methods,wpid,item_id,gtin,upc,primary_image_url,shelf_name,primary_cat_path,offer_start_date,offer_end_date,item_creation_date,item_last_updated,item_page_url,reviews_count,average_rating,searchable,date_create,brand,tax_code,short_description,shipping_weight,category,subcategory,gender,color,size) 
SELECT report.shop_id,report.partner_id,report.sku,report.product_name,report.product_category,report.price,report.currency,report.publish_status,report.status_change_reason,report.lifecycle_status,report.inventory_count,report.ship_methods,report.wpid,report.item_id,report.gtin,report.upc,report.primary_image_url,report.shelf_name,report.primary_cat_path,report.offer_start_date,report.offer_end_date,report.item_creation_date,report.item_last_updated,report.item_page_url,report.reviews_count,report.average_rating,report.searchable,report.date_create,item_preload.brand,item_preload.tax_code,item_preload.short_description,item_preload.shipping_weight,item_preload.category,item_preload.subcategory,item_preload.gender,item_preload.color,item_preload.size
FROM walmart_ca.report

         LEFT JOIN walmart_ca.item_preload
                   ON item_preload.upc = report.upc

WHERE report.sku IS NOT NULL
ON CONFLICT DO NOTHING';
        $this->db->run( $insert_report );

        $insert_report_last_price = 'UPDATE report_temp SET last_sent_price=last_sent_prices.price,date_sent_price=last_sent_prices.date_at FROM walmart_ca.last_sent_prices WHERE report_temp.sku=last_sent_prices.sku';
        $this->db->run( $insert_report_last_price );

        // report
        $file   = $this->dumpDirExport . 'reports_walmart_ca.gz';
        $select = 'SELECT * FROM report_temp';
        $this->makeFile( $select, $file );
    }

    public function makeFileCurrentBrands()
    {
        // temp current_brands_walmart_ca
        $temp_current_brands_walmart_ca = 'CREATE TEMP TABLE current_brands_walmart_ca_temp
(
    id          SERIAL PRIMARY KEY,
    brand       VARCHAR(255),
    total_items INT,
    date_at     TIMESTAMP DEFAULT current_timestamp
)';
        $this->db->run( $temp_current_brands_walmart_ca );

        // current brands walmart ca
        $insert_current_brands_temp = 'INSERT INTO current_brands_walmart_ca_temp
                (brand,total_items)
                SELECT DISTINCT brand, count(*)
                FROM walmart_ca.item_preload preload
                         LEFT JOIN external_usa_bezoxx.usa_product product
                                   ON product.asin = preload.asin
                WHERE length(preload.brand) > 0
                  AND length(preload.product_name) > 0
                  AND length(preload.short_description) > 0
                  AND length(preload.image) > 0
                  AND length(preload.category) > 0
                  AND (length(preload.subcategory) > 0
                           AND preload.subcategory <> \'Other\')
                  --AND length(preload.gender) > 0
                  --AND length(preload.color) > 0
                  --AND length(preload.size) > 0
                  AND (product.price_amazon::DECIMAL > 0
                    OR product.price_prime::DECIMAL > 0)
                GROUP BY preload.brand
                ON CONFLICT DO NOTHING';
        $this->db->run( $insert_current_brands_temp );

        $file   = $this->dumpDirExport . 'current_brands_walmart_ca.gz';
        $select = 'SELECT * FROM current_brands_walmart_ca_temp';
        $this->makeFile( $select, $file );
    }

    public function makeFileAvailableBrands()
    {
        // shop and user id
        $sql_shop_and_user_id = 'SELECT user_id,shop_id FROM walmart_ca.user_shop_id';
        $shop_and_user_id     = $this->db->run( $sql_shop_and_user_id )->fetchAll();

        // available brands walmart ca
        $file = $this->dumpDirExport . 'available_brands_walmart_ca.gz';

        if ( isset( $shop_and_user_id ) && !empty( $shop_and_user_id ) ) {
            foreach ( $shop_and_user_id as $item ) {

                $custom_where_rules =
                    false !== $this->getWhereRules( $this->db, $item['shop_id'] ) ?
                        $this->getWhereRules( $this->db, $item['shop_id'] ) : '';

                $select = 'SELECT DISTINCT ' . $item['user_id'] . ',temp.id, count(*),temp.date_at
        FROM walmart_ca.item_preload preload
                 LEFT JOIN external_usa_bezoxx.usa_product product
                           ON product.asin = preload.asin   
                 LEFT JOIN current_brands_walmart_ca_temp temp
                            ON temp.brand = preload.brand
        WHERE temp.id is not null AND length(preload.brand) > 0
          AND length(preload.product_name) > 0
          AND length(preload.short_description) > 0
          AND length(preload.image) > 0
          AND length(preload.category) > 0
          AND (length(preload.subcategory) > 0 
                   AND preload.subcategory <> \'Other\')
          --AND length(preload.gender) > 0
          --AND length(preload.color) > 0
          --AND length(preload.size) > 0
          AND (product.price_amazon::DECIMAL > 0
            OR product.price_prime::DECIMAL > 0)    
          ' . $custom_where_rules . '
        GROUP BY temp.id';

                $this->makeFile( $select, $file );
            }
        }
    }

    public function makeFileRuleBrands()
    {
        // shop and user id
        $sql_shop_and_user_id = 'SELECT user_id,shop_id FROM walmart_ca.user_shop_id';
        $shop_and_user_id     = $this->db->run( $sql_shop_and_user_id )->fetchAll();

        // rule brands walmart ca
        $file = $this->dumpDirExport . 'rule_brands_walmart_ca.gz';

        if ( isset( $shop_and_user_id ) && !empty( $shop_and_user_id ) ) {
            foreach ( $shop_and_user_id as $item ) {

                $sql = 'SELECT DISTINCT ' . $item['user_id'] . ' user_id,value brand,action
                        FROM walmart_ca.rules
                        WHERE shop_id = ' . $item['shop_id'] . '
                          AND rule = \'brand\'';
                $this->makeFile( $sql, $file );
            }
        }
    }

    public function makeFileOthers()
    {
        $file   = $this->dumpDirExport . 'others_walmart_ca.gz';
        $select = 'SELECT preload.asin,preload.upc,preload.product_name,preload.brand,preload.tax_code,preload.short_description,preload.shipping_weight,preload.image,preload.category,preload.subcategory,preload.gender,preload.color,preload.size,product.price_amazon,product.price_prime
                FROM walmart_ca.item_preload preload
                         LEFT JOIN external_usa_bezoxx.usa_product product
                                   ON product.asin = preload.asin
                         LEFT JOIN walmart_ca.item_black black
                                   ON black.upc = preload.upc
                WHERE preload.asin IS NOT NULL
                  AND preload.upc IS NOT NULL
                  AND length(preload.product_name) > 0
                  AND length(preload.brand) > 0
                  AND length(preload.short_description) > 0
                  AND length(preload.image) > 0
                  AND length(preload.category) > 0
                  AND length(preload.subcategory) > 0
                  --AND (length(preload.gender) > 0 AND length(preload.color) > 0 AND
                       --(length(preload.size) > 0))
                
                  AND (product.price_amazon::DECIMAL > 0 OR
                       product.price_prime::DECIMAL > 0)
                
                  AND (preload.category = \'OtherCategory\')
                  
                  AND black.upc IS NULL';

        $this->makeFile( $select, $file );
    }

    public function makeFileCatKeywords()
    {
        $file   = $this->dumpDirExport . 'cat_keywords.gz';
        $select = 'SELECT * FROM ' . App::$dbSchema . '.' . App::$catKeywordsT;
        $this->makeFile( $select, $file );
    }

    private function makeFile( string $select, string $file )
    {
        $record_type = '>';
        if ( false !== stripos( $file, 'available_brands_walmart_ca' ) ) {
            $record_type = '>>';
        }
        elseif ( false !== stripos( $file, 'rule_brands_walmart_ca' ) ) {
            $record_type = '>>';
        }

        $sql = 'COPY (' . $select . ') TO PROGRAM \'gzip ' . $record_type . ' ' .
            $file . ' && chmod 0777 ' . $file . '\'';

        $this->db->run( $sql );
    }

    private function getWhereRules( Db $db, int $shop_id )
    {
        $types =
            [
                'asin'              => 'string',
                'upc'               => 'string',
                'sku'               => 'string',
                'product_name'      => 'string',
                'brand'             => 'string',
                'price'             => 'float',
                'tax_code'          => 'int',
                'short_description' => 'string',
                'shipping_weight'   => 'float',
                'image'             => 'string',
                'category'          => 'string',
                'subcategory'       => 'string'
            ];

        $sql = 'SELECT * FROM walmart_ca.rules WHERE shop_id=?';
        //$rules = Database::request( $sql, __METHOD__, true );
        $rules = $db->run( $sql, [ $shop_id ] )->fetchAll();

        if ( false === $rules || count( $rules ) <= 0 ) {
            return false;
        }

        $where_leave  = [];
        $where_remove = [];

        foreach ( $rules as $item ) {

            if ( $item['condition'] === 'equal' ) {
                $value = ( $types[ $item['rule'] ] === 'string' ) ?
                    App::$dbh->quote( $item['value'] ) : $item['value'];

                switch ( $item['action'] ) {
                    case 'include':
                        $where_leave[] = 'PRELOAD.' . $item['rule'] . '=' . $value;
                        break;
                    case 'exclude':
                        $where_remove[] = 'PRELOAD.' . $item['rule'] . '<>' . $value;
                        break;
                }
            }
            elseif ( $item['condition'] === 'similar' ) {
                $value = App::$dbh->quote( '(%' . $item['value'] . '%)' );

                switch ( $item['action'] ) {
                    case 'include':
                        $where_leave[] = 'PRELOAD.' . $item['rule'] . ' SIMILAR TO ' . $value;
                        break;
                    case 'exclude':
                        $where_remove[] = 'PRELOAD.' . $item['rule'] . ' NOT SIMILAR TO ' . $value;
                        break;
                }
            }
        }

        return ' AND (' . implode( ' OR ', $where_leave ) . ') 
        AND (' . implode( ' OR ', $where_remove ) . ')';
    }

    public function cleanDumpDirExport()
    {
        exec( 'cd ' . $this->dumpDirExport . '; rm *.gz' );
    }

    public function cleanDumpDirExportOrders()
    {
        exec( 'cd ' . $this->dumpDirExportOrders . '; rm *.gz' );
    }
}
