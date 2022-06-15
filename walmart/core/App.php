<?php

declare(strict_types=1);

namespace Walmart\core;

use Walmart\config\Access;
use Walmart\config\Db;
use Walmart\config\DefaultOptions;
use Walmart\config\DynamicOptions;
use Walmart\config\GenerateOptions;
use Walmart\config\Options;
use Walmart\helpers\database\Database;
use Walmart\helpers\Error;
use Walmart\helpers\Functions;

class App
{
    use Access;
    use Db;
    use DefaultOptions;
    use DynamicOptions;
    use Error;
    use Functions;
    use GenerateOptions;
    use Options;

    public static bool $appResult = false;

    public static string $runTime = '';

    // статические из таблицы options
    // статические из таблицы default_options
    // статические, генерируются методами по разным данным
    // динамические (инициируются, меняются по ходу приложения)

    public static function setConfig()
    {
        $options_data
            = [
            'max_item_submit'                   => 'int',
            'max_inventory_submit'              => 'int',
            'max_inventory_quantity'            => 'int',
            'inventory_fulfillment'             => 'int',
            'markup'                            => 'float',
            'min_inventory_quantity'            => 'int',
            'inventory_request_processing_time' => 'int',
            'cad_rate'                          => 'float',
            'item_request_processing_time'      => 'int',
            'min_price'                         => 'float',
            'max_price'                         => 'float',
            'price_request_processing_time'     => 'int',
            'max_price_submit'                  => 'int',
            'consumer_id'                       => 'string',
            'consumer_channel_type'             => 'string',
            'private_key'                       => 'string'
        ];

        $default_options_data
            = [
            'upc_length'                    => 'int',
            'walmart_xml_ns'                => 'string',
            'imagesize_func'                => 'string',
            'inventory_max_attempts_resend' => 'int',
            'primary_price'                 => 'float',
            'price_max_attempts_resend'     => 'int',
            'item_max_attempts_resend'      => 'int',
            'asin_zero_position'            => 'array',
            'asin_reverse'                  => 'array',
            'sku_length'                    => 'int',
            'sku_source_to'                 => 'string',
            'sku_source_from'               => 'string',
            'sku_country'                   => 'string',
            'report_interval'               => 'int',
            'dev_mode'                      => 'bool',
        ];

        $all_options_data = array_merge($options_data, $default_options_data);

        $exclude = ['shop_id', 'date_create'];

        // get option from database, table options
        $sql     = 'SELECT *
                FROM walmart_ca.options
                WHERE shop_id=' . App::$shopId;
        $options = Database::request($sql, __METHOD__, true);

        // get option from database, table default_options
        $sql_default     = 'SELECT * FROM walmart_ca.default_options';
        $default_options = Database::request($sql_default, __METHOD__, true);

        $all_options = array_merge($options[0], $default_options[0]);

        foreach ($all_options as $option_name => $value) {
            if (in_array($option_name, $exclude)) {
                continue;
            }

            $option = self::camelCase($option_name, 2);

            switch ($all_options_data[$option_name]) {
                case 'int':
                    self::$$option = (int)$value;
                    break;
                case 'float':
                    self::$$option = (float)$value;
                    break;
                case 'bool':
                    self::$$option = (bool)$value;
                    break;
                case 'array':
                    self::$$option = self::jsonToArray($value);
                    break;
                default:
                    self::$$option = (string)$value;
            }
        }
    }

    public static function generateOptions()
    {
        self::dbh();
        self::shopIds();
        self::shopIdStr();
        self::controller();
        self::method();
        self::logFile();
        self::debug();
    }

    /**
     * @param $name
     * @param $arguments
     *
     * @return bool|mixed
     */
    //    public static function __callStatic( $name, $arguments )
    //    {
    //        // camelCase to camel_case
    //        $chars = array_map( function( $char ) {
    //            return true === ctype_upper( $char ) ? '_' . strtolower( $char ) : $char;
    //        }, str_split( $name ) );
    //        $name  = implode( $chars );
    //
    //        // get option from database, table options
    //        $sql = 'SELECT ' . $name . '
    //                FROM walmart_ca.options
    //                WHERE shop_id=' . self::$shopId . ' LIMIT 1';
    //
    //        $data = Database::request( $sql, __METHOD__ );
    //
    //        // get option from database, table default_options
    //        if ( false === $data ) {
    //            $sql = 'SELECT ' . $name . '
    //            FROM walmart_ca.default_options LIMIT 1';
    //
    //            $data = Database::request( $sql, __METHOD__ );
    //        }
    //
    //        if ( isset( $data[ $name ] ) ) {
    //
    //            // custom processing of variables
    //            if ( $name == 'asin_reverse' || $name == 'asin_zero_position' ) {
    //                return JsonToArray::exec( $data[ $name ] );
    //            }
    //
    //            return $data[ $name ];
    //        }
    //
    //        return false;
    //    }
}
