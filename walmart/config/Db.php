<?php
declare( strict_types=1 );

namespace Walmart\config;

use PDO;
use Walmart\helpers\database\Database;

trait Db
{
    /**
     * Database Connection
     *
     * @var PDO
     */
    public static PDO $dbh;

    // items
    public static string   $itemPreloadT = 'item_preload';
    public static string   $itemAddingT  = 'item_adding';
    // all items sent and all possible information on them
    public static string   $itemTotalSentT = 'item_total_sent';
    // table with successful items
    public static string   $itemSuccessT = 'item_success';
    // table with error items
    public static string   $itemErrorT = 'item_error';
    // table with error items
    public static string   $itemBlackT  = 'item_black';
    public static string   $itemResendT = 'item_resend';

    // price preload
    public static string   $pricePreloadT = 'price_preload';
    // adding price
    public static string   $priceAddingT = 'price_adding';
    // all prices send
    public static string   $priceTotalSentT = 'price_total_sent';
    // table with successful prices
    public static string   $priceSuccessT = 'price_success';
    // table with error prices
    public static string   $priceErrorT  = 'price_error';
    public static string   $priceBlackT  = 'price_black';
    public static string   $priceResendT = 'price_resend';

    // price preload
    public static string   $inventoryPreloadT = 'inventory_preload';
    // adding price
    public static string   $inventoryAddingT = 'inventory_adding';
    // sent prices
    public static string   $lastSentPricesT = 'last_sent_prices';

    public static string   $inventoryTotalSentT = 'inventory_total_sent';
    // table with successful prices
    public static string   $inventorySuccessT = 'inventory_success';
    // table with error prices
    public static string   $inventoryErrorT  = 'inventory_error';
    public static string   $inventoryBlackT  = 'inventory_black';
    public static string   $inventoryResendT = 'inventory_resend';

    // feeds
    public static string   $itemFeedT      = 'item_feed';
    public static string   $priceFeedT     = 'price_feed';
    public static string   $inventoryFeedT = 'inventory_feed';

    // category
    public static string   $categoryT    = 'category';
    public static string   $subCategoryT = 'subcategory';
    public static string   $catKeywordsT = 'cat_keywords';

    // table rules
    public static string   $rulesT = 'rules';

    // table options
    public static string   $optionsT        = 'options';
    public static string   $defaultOptionsT = 'default_options';

    // report
    public static string   $reportT = 'report';

    // sku list for removal from the store
    public static string   $retireT    = 'retire';
    public static string   $retireLogT = 'retire_log';

    // orders
    public static string $ordersWalmartCaT = 'orders_walmart_ca';

    public static string $userShopIdT = 'user_shop_id';

    /**
     * Amazon
     */
    public static string   $amazonDbName   = 'amazon';
    public static string   $amazonDbSchema = 'external_usa_bezoxx';
    // New asins. The dump_asin table is created new every day from 13 to 13:30
    public static string   $amazonDumpAsinT = 'dump_asin';
    // New prices. The usa_product table is created every day from 13 to 13:30
    public static string   $amazonUsaProductT    = 'usa_product';
    public static string   $amazonBlacklistAsinT = 'blacklist_asin';

    /**
     * Pic scraper (db amazon)
     */
    public static string   $picScraperSchema   = 'united_scraper';
    public static string   $picScraperContentT = 'content';

    /**
     * external_dump
     */
    public static string   $externalSchema      = 'external_usa_bezoxx';
    public static string   $externalDumpAsinT   = 'dump_asin';
    public static string   $externalUsaProductT = 'usa_product';

    /**
     * @return bool|PDO
     */
    public static function dbh()
    {
        // db connect
        if ( isset( self::$dbh ) ) {
            return self::$dbh;
        }

        self::$dbh = Database::setConnect();

        return self::$dbh;
    }
}
