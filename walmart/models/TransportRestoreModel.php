<?php
declare( strict_types=1 );

namespace Walmart\models;

use Walmart\core\App;
use Walmart\helpers\database\Db;

class TransportRestoreModel
{
    public Db $db;

    public string $dumpDirImport                  = APP_ROOT . '/dumps/import/';
    public string $dumpDirImportOrders            = APP_ROOT . '/dumps/import_orders/';
    public string $dumpDirImportOrdersAcknowledge = APP_ROOT . '/dumps/import_order_acknowledge/';
    public string $dumpDirImportScraper           = APP_ROOT . '/dumps/import_from_scraper/';

    public function __construct()
    {
        $this->db = new Db();

        if ( !file_exists( $this->dumpDirImport ) ) {
            return false;
        }
        if ( !file_exists( $this->dumpDirImportOrders ) ) {
            return false;
        }
    }

    public function restoreOrders() :void
    {
        $sql = 'CREATE TEMP TABLE orders_walmart_ca_temp
        (
            shop_id                 INTEGER NOT NULL,

            order_id                TEXT,
            order_date              TIMESTAMP,
        
            partner_id              TEXT,
        
            sku                     TEXT,
            upc                     TEXT,
            asin                    TEXT,
        
            product_name            TEXT,
            product_category        TEXT,
            price                   NUMERIC(10, 2),
            currency                TEXT,
            publish_status          TEXT,
            status_change_reason    TEXT,
            lifecycle_status        TEXT,
            inventory_count         INTEGER,
            ship_methods            TEXT,
            wpid                    TEXT,
            item_id                 TEXT,
            gtin                    TEXT,
            primary_image_url       TEXT,
            shelf_name              TEXT,
            primary_cat_path        TEXT,
            offer_start_date        TEXT,
            offer_end_date          TEXT,
            item_creation_date      TEXT,
            item_last_updated       TEXT,
            item_page_url           TEXT,
            reviews_count           TEXT,
            average_rating          TEXT,
            searchable              TEXT,
            image                   TEXT,
            shipping_weight         TEXT,
            tax_code                TEXT,
            short_description       TEXT,
            subcategory             TEXT,
            category                TEXT,
            gender                  TEXT,
            color                   TEXT,
            size                    TEXT,
            brand                   TEXT,
        
            status                  TEXT,
        
            carrier                 TEXT,
            tracking_number         TEXT,
        
            -- shipping info
            phone                   TEXT,
            estimated_delivery_date TEXT,
            estimated_ship_date     TEXT,
            method_code             TEXT,
            postal_name             TEXT,
            postal_address1         TEXT,
            postal_address2         TEXT,
            postal_city             TEXT,
            postal_state            TEXT,
            postal_code             TEXT,
            postal_country          TEXT,
            postal_address_type     TEXT,
        
            weight                  TEXT,
            weight_unit             TEXT,
        
            dimensions              TEXT,
            dimensions_unit         TEXT,
            
            email                   TEXT,
        
            UNIQUE (shop_id, order_id, sku)
        )';
        $this->db->run( $sql );

        $file     = $this->dumpDirImportOrders . 'orders_walmart_ca.gz';
        $sql_copy = 'COPY orders_walmart_ca_temp FROM PROGRAM \'gzip -cd ' . $file . '\'';
        $this->db->run( $sql_copy );

        $sql_update = 'UPDATE walmart_ca.orders_walmart_ca 
                        SET carrier=orders_walmart_ca_temp.carrier,
                            tracking_number=orders_walmart_ca_temp.tracking_number
                        FROM orders_walmart_ca_temp
                        WHERE orders_walmart_ca_temp.shop_id = 
                              orders_walmart_ca.shop_id
                          AND orders_walmart_ca_temp.order_id = 
                                  orders_walmart_ca.order_id
                          AND orders_walmart_ca_temp.asin = 
                              orders_walmart_ca.asin';

        $this->db->run( $sql_update );
    }

    public function restoreOrdersAcknowledge() :void
    {
        $sql = 'TRUNCATE TABLE walmart_ca.order_acknowledge_walmart_ca 
                RESTART IDENTITY';
        $this->db->run( $sql );

        $file = $this->dumpDirImportOrdersAcknowledge . 'order_acknowledge_walmart_ca.gz';

        $sql_copy = 'COPY walmart_ca.order_acknowledge_walmart_ca FROM PROGRAM \'gzip -cd ' . $file . '\'';

        $this->db->run( $sql_copy );
    }

    public function restoreBlackSku()
    {
        $sql_temp_black_sku_walmart_ca = 'CREATE TEMP TABLE black_sku_walmart_ca
        (
            user_id integer,
            sku varchar(39),
            date_at TIMESTAMP DEFAULT now()
        )';
        $this->db->run( $sql_temp_black_sku_walmart_ca );

        $file               = $this->dumpDirImport . 'black_sku_walmart_ca.gz';
        $sql_copy_black_sku = 'COPY black_sku_walmart_ca FROM PROGRAM \'gzip -cd ' . $file . '\'';
        $this->db->run( $sql_copy_black_sku );

        $sql_insert_black_sku_walmart_ca = 'INSERT INTO walmart_ca.item_black
SELECT user_shop_id.shop_id, black_sku_walmart_ca.sku, report.upc
FROM black_sku_walmart_ca
         LEFT JOIN walmart_ca.user_shop_id
                   ON user_shop_id.user_id = black_sku_walmart_ca.user_id
         LEFT JOIN walmart_ca.report
                   ON report.sku = black_sku_walmart_ca.sku
WHERE black_sku_walmart_ca.sku IS NOT NULL AND report.upc IS NOT NULL 
ON CONFLICT DO NOTHING';
        $this->db->run( $sql_insert_black_sku_walmart_ca );
    }

    public function restoreBaseSettings()
    {
        // base settings walmart ca
        $sql_temp_base_settings = 'CREATE TEMP TABLE base_settings_walmart_ca
        (
            id integer unique,
            user_id integer,
            relationship_id integer,
            currency_ratio decimal(10,2),
            wrapping_ratio decimal(10,2),
            min_price decimal(10,2),
            max_price decimal(10,2),
            date_at TIMESTAMP DEFAULT now()
        )';
        $this->db->run( $sql_temp_base_settings );

        $file                        = $this->dumpDirImport . 'base_settings_walmart_ca.gz';
        $sql_copy_temp_base_settings = 'COPY base_settings_walmart_ca FROM PROGRAM \'gzip -cd ' . $file . '\'';
        $this->db->run( $sql_copy_temp_base_settings );

        // credentials
        $sql_temp_credentials = 'CREATE TEMP TABLE credentials_walmart_ca
        (
            id integer unique,
            user_id integer,
            consumer_id text,
            private_key text,
            channel_type text,
            service varchar(255)
        )';
        $this->db->run( $sql_temp_credentials );

        $file                                 = $this->dumpDirImport . 'credentials_walmart_ca.gz';
        $sql_copy_temp_credentials_walmart_ca = 'COPY credentials_walmart_ca FROM PROGRAM \'gzip -cd ' . $file . '\'';
        $this->db->run( $sql_copy_temp_credentials_walmart_ca );

        $insert_to_options = 'INSERT INTO walmart_ca.options
(shop_id, consumer_id, consumer_channel_type, private_key, cad_rate, markup, min_price,max_price)
SELECT user_shop_id.shop_id,
       credentials_walmart_ca.consumer_id,
       credentials_walmart_ca.channel_type,
       credentials_walmart_ca.private_key,
       base_settings_walmart_ca.currency_ratio,
       base_settings_walmart_ca.wrapping_ratio,
       base_settings_walmart_ca.min_price,
       base_settings_walmart_ca.max_price
FROM walmart_ca.user_shop_id

         LEFT JOIN credentials_walmart_ca
                   ON credentials_walmart_ca.user_id = user_shop_id.user_id
    
         LEFT JOIN base_settings_walmart_ca
                   ON base_settings_walmart_ca.user_id = user_shop_id.user_id

WHERE user_shop_id.shop_id IS NOT NULL AND credentials_walmart_ca.consumer_id IS NOT NULL AND credentials_walmart_ca.channel_type IS NOT NULL AND credentials_walmart_ca.private_key IS NOT NULL
ON CONFLICT (consumer_id) DO UPDATE SET cad_rate=excluded.cad_rate,markup=excluded.markup,min_price=excluded.min_price,max_price=excluded.max_price,date_create=excluded.date_create
';
        $this->db->run( $insert_to_options );
    }

    public function restoreAwaitingDeleteBrands()
    {
        $sql = 'CREATE TEMP TABLE awaiting_delete_brands_walmart_ca
        (
            user_id     INTEGER NOT NULL,
            brand_id    INTEGER NOT NULL,
            brand_title TEXT    NOT NULL,
            date_at     TIMESTAMP DEFAULT now() 
        )';
        $this->db->run( $sql );

        $file = $this->dumpDirImport . 'awaiting_delete_brands_walmart_ca.gz';
        $sql  = 'COPY awaiting_delete_brands_walmart_ca FROM PROGRAM \'gzip -cd ' . $file . '\'';
        $this->db->run( $sql );

        // select
        $sql  = 'SELECT awaiting_delete_brands_walmart_ca.brand_title brand,user_shop_id.shop_id,user_shop_id.user_id 
FROM awaiting_delete_brands_walmart_ca
LEFT JOIN walmart_ca.user_shop_id
ON user_shop_id.user_id = awaiting_delete_brands_walmart_ca.user_id
WHERE awaiting_delete_brands_walmart_ca.user_id IS NOT NULL';
        $data = $this->db->run( $sql )->fetchAll();

        // truncate retire
        $sql = 'TRUNCATE walmart_ca.transport_retire';
        $this->db->run( $sql );

        foreach ( $data as $item ) {
            // delete from rules
            $sql = 'DELETE FROM walmart_ca.rules 
            WHERE shop_id=? AND rule=? AND value=?';
            $this->db->run( $sql, [ $item['shop_id'], 'brand', $item['brand'] ] );

            // retire from walmart
            $sql = 'INSERT INTO walmart_ca.transport_retire
            (shop_id, sku) 
            SELECT report.shop_id, report.sku
            FROM walmart_ca.item_preload
            
                     LEFT JOIN walmart_ca.report
                               ON report.upc = item_preload.upc
                AND report.shop_id = ' . $item['shop_id'] . '
            
                     LEFT JOIN awaiting_delete_brands_walmart_ca
                               ON awaiting_delete_brands_walmart_ca.brand_title = 
                                  item_preload.brand
            
            WHERE report.shop_id = ' . $item['shop_id'] . '
                AND report.publish_status = \'PUBLISHED\'
                AND awaiting_delete_brands_walmart_ca.brand_title IS NOT NULL 
            ON CONFLICT DO NOTHING';

            $this->db->run( $sql );
        }

        // removal of goods that are in the report, but whose brands are
        // not in the rules of the user
        $shop_ids = App::shopIds();
        if ( isset( $shop_ids )
            && is_array( $shop_ids )
            && !empty( $shop_ids ) ) {

            $report_brands = $rule_brands = [];

            foreach ( $shop_ids as $shop_id ) {

                // get rule brands
                $sql    = 'SELECT DISTINCT value FROM walmart_ca.rules
                WHERE shop_id=? AND rule=\'brand\' AND action=\'include\'';
                $brands = $this->db->run( $sql, [ $shop_id ] )->fetchAll();
                if ( isset( $brands[0]['value'] ) ) {
                    $rule_brands = array_column( $brands, 'value' );
                }

                // get report brands
                $sql = 'SELECT DISTINCT item_preload.brand FROM walmart_ca.report
    
                    LEFT JOIN walmart_ca.item_preload
                    ON item_preload.upc=report.upc
                    and report.shop_id=' . $shop_id . '
                    
                    WHERE report.shop_id=' . $shop_id . '
                      AND item_preload.brand IS NOT NULL 
                      AND report.upc IS NOT NULL
                      AND report.publish_status=\'PUBLISHED\'';

                $rep_brands = $this->db->run( $sql )->fetchAll();
                if ( isset( $rep_brands[0]['brand'] ) ) {
                    $report_brands = array_column( $rep_brands, 'brand' );
                }

                $diff_brands = array_diff( $report_brands, $rule_brands );

                if ( !empty( $diff_brands ) ) {
                    foreach ( $diff_brands as $diff_brand ) {
                        $sql = '
                    INSERT INTO walmart_ca.transport_retire
                    (shop_id, sku) 
                    SELECT ' . $shop_id . ',report.sku
                    FROM walmart_ca.report
                    
                    LEFT JOIN walmart_ca.item_preload
                    ON item_preload.upc = report.upc
                    AND report.shop_id = ' . $shop_id . '
                    
                    WHERE report.shop_id=' . $shop_id . '
                      AND item_preload.brand IS NOT NULL 
                      AND item_preload.brand = \'' . $diff_brand . '\'
                      AND report.upc IS NOT NULL
                      AND report.publish_status=\'PUBLISHED\'
                      
                      ON CONFLICT DO NOTHING';
                        $this->db->run( $sql );
                    }
                }

                unset( $report_brands, $diff_brands );
            }
        }
    }

    public function restoreAwaitingAddBrands()
    {
        $sql = 'CREATE TEMP TABLE awaiting_add_brands_walmart_ca 
        (
            user_id  INTEGER NOT NULL,
            brand_id INTEGER NOT NULL,
            brand_title TEXT    NOT NULL,
            date_at  TIMESTAMP DEFAULT now()
        )';
        $this->db->run( $sql );

        $file = $this->dumpDirImport . 'awaiting_add_brands_walmart_ca.gz';
        $sql  = 'COPY awaiting_add_brands_walmart_ca FROM PROGRAM \'gzip -cd ' . $file . '\'';
        $this->db->run( $sql );

        $sql = 'INSERT INTO walmart_ca.rules (shop_id, rule, value, condition, action) 
SELECT user_shop_id.shop_id,
       \'brand\',
       awaiting_add_brands_walmart_ca.brand_title,
       \'equal\',
       \'include\'

FROM awaiting_add_brands_walmart_ca

LEFT JOIN walmart_ca.user_shop_id
ON user_shop_id.user_id=awaiting_add_brands_walmart_ca.user_id

WHERE user_shop_id.shop_id IS NOT NULL AND awaiting_add_brands_walmart_ca.brand_title IS NOT NULL 
    -- check unique shop_id and brand
  AND NOT EXISTS(
          SELECT shop_id,rule,value FROM walmart_ca.rules WHERE rules.shop_id=user_shop_id.shop_id AND rule=\'brand\' AND value=awaiting_add_brands_walmart_ca.brand_title)

ON CONFLICT DO NOTHING';
        $this->db->run( $sql );
    }

    public function restoreScraper()
    {
        // file => table
        $tables = [
            'content'                   => 'scraper_content',
            'content_from_search'       => 'scraper_content_from_search',
            'content_from_only_content' => 'scraper_content_from_only_content'
        ];

        foreach ( $tables as $file_name => $table ) {

            $file_path = $this->dumpDirImportScraper . $file_name . '.gz';

            if ( file_exists( $file_path ) ) {

                $sql_truncate = 'TRUNCATE ' . App::$dbSchema . '.' . $table .
                    ' RESTART IDENTITY';
                $this->db->run( $sql_truncate );

                $sql_vacuum = 'VACUUM ' . App::$dbSchema . '.' . $table;
                $this->db->run( $sql_vacuum );

                $sql = 'COPY ' . App::$dbSchema . '.' . $table .
                    ' FROM PROGRAM \'gzip -cd ' . $file_path . '\'';
                $this->db->run( $sql );
            }
        }
    }

    public function restoreCatKeywords()
    {
        $file_path = $this->dumpDirImport . 'awaiting_add_cat_keywords_walmart_ca.gz';

        if ( file_exists( $file_path ) ) {

            $sql = 'CREATE TABLE IF NOT EXISTS awaiting_add_cat_keywords_walmart_ca
                    (
                        subcategory_id INTEGER      NOT NULL,
                        keyword        VARCHAR(255) NOT NULL UNIQUE,
                        date_at        TIMESTAMP DEFAULT now()
                    )';
            $this->db->run( $sql );

            $file = $this->dumpDirImport . 'awaiting_add_cat_keywords_walmart_ca.gz';
            $sql  = 'COPY awaiting_add_cat_keywords_walmart_ca FROM PROGRAM \'gzip -cd ' . $file . '\'';
            $this->db->run( $sql );

            $sql = 'INSERT INTO walmart_ca.cat_keywords 
                    (subcategory_id, title) 
                    SELECT subcategory_id,keyword 
                    FROM awaiting_add_cat_keywords_walmart_ca
                    ON CONFLICT DO NOTHING';
            $this->db->run( $sql );
        }

        $file_path = $this->dumpDirImport . 'awaiting_delete_cat_keywords_walmart_ca.gz';
        if ( file_exists( $file_path ) ) {

            $sql = 'CREATE TABLE IF NOT EXISTS awaiting_delete_cat_keywords_walmart_ca
                    (
                        subcategory_id INTEGER      NOT NULL,
                        keyword        VARCHAR(255) NOT NULL UNIQUE,
                        date_at        TIMESTAMP DEFAULT now()
                    )';
            $this->db->run( $sql );

            $file = $this->dumpDirImport . 'awaiting_delete_cat_keywords_walmart_ca.gz';
            $sql  = 'COPY awaiting_delete_cat_keywords_walmart_ca FROM PROGRAM \'gzip -cd ' . $file . '\'';
            $this->db->run( $sql );
        }
    }

    public function dropTable( $schema, $table )
    {
        $schema = !empty( $schema ) ? $schema . '.' : '';
        $sql    = 'DROP TABLE IF EXISTS ' . $schema . $table;
        $this->db->run( $sql );
    }

    public function sendStatToViber()
    {
        //$sql = 'SELECT count(*)
        //        FROM walmart_ca.item_preload
        //        WHERE image IS NOT NULL
        //          AND (product_name IS NOT NULL AND length(product_name) > 0)
        //          AND (brand IS NOT NULL AND length(brand) > 0)
        //          AND (short_description IS NOT NULL AND length(short_description) > 0)
        //          AND (gender IS NOT NULL AND length(gender) > 0)
        //          AND (color IS NOT NULL AND length(color) > 0)
        //          AND (size IS NOT NULL AND length(size) > 0)
        //          AND category <> \'OtherCategory\'
        //          AND (date_create BETWEEN now() - INTERVAL \'24 hours\' AND now())';

        $count = 0;

        $sql   = 'SELECT count(*)
                FROM walmart_ca.scraper_content
                WHERE has_image = TRUE
                  AND (description IS NOT NULL AND length(description) > 0)
                  AND (gender IS NOT NULL AND length(gender) > 0)
                  AND (color IS NOT NULL AND length(color) > 0)
                  AND (size IS NOT NULL AND length(size) > 0)
                  AND (date_at BETWEEN now() - INTERVAL \'72 hours\' AND now())';
        $count += (int)$this->db->run( $sql )->fetch()['count'];

        $sql   = 'SELECT count(*)
                FROM walmart_ca.scraper_content_from_search
                WHERE has_image = TRUE
                  AND (description IS NOT NULL AND length(description) > 0)
                  AND (gender IS NOT NULL AND length(gender) > 0)
                  AND (color IS NOT NULL AND length(color) > 0)
                  AND (size IS NOT NULL AND length(size) > 0)
                  AND (date_at BETWEEN now() - INTERVAL \'72 hours\' AND now())';
        $count += (int)$this->db->run( $sql )->fetch()['count'];

        $sql   = 'SELECT count(*)
                FROM walmart_ca.scraper_content_from_only_content
                WHERE has_image = TRUE
                  AND (description IS NOT NULL AND length(description) > 0)
                  AND (gender IS NOT NULL AND length(gender) > 0)
                  AND (color IS NOT NULL AND length(color) > 0)
                  AND (size IS NOT NULL AND length(size) > 0)
                  AND (date_at BETWEEN now() - INTERVAL \'72 hours\' AND now())';
        $count += (int)$this->db->run( $sql )->fetch()['count'];

        //$stat = '\nitems:' . $count;

        $text = 'text=Walmart CA \n New Valid Product:' . $count .
            '&members_group=top_managers,dev,s.bondarenko';

        if ($count > 0) {
            file_get_contents( 'http://185.197.160.162/?' . $text );
        }
    }
}
