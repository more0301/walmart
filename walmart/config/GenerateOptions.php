<?php
declare( strict_types=1 );

namespace Walmart\config;

use Walmart\core\App;
use Walmart\helpers\database\Database;
use Walmart\helpers\Logger;

trait GenerateOptions
{
    /**
     * Phased mode, from the console
     *
     * @var bool
     */
    public static bool    $debug;

    /**
     * Array with store identifiers. Source - shop_id column in options table
     *
     * @var array
     */
    public static array   $shopIds;

    /**
     * ID of current store in format 000001
     *
     * @var string
     */
    public static string  $shopIdStr;

    /**
     * Current controller. Equals $argv[1]
     *
     * @var string
     */
    public static string  $controller;

    /**
     * Current controller. Equals $argv[2]
     *
     * @var string
     */
    public static string  $method;

    /**
     * Current log file. Equals controller + method
     *
     * @var string
     */
    public static string  $logFile;

    /**
     * @return bool|int|mixed
     */
    public static function shopIds()
    {
        if ( isset( self::$shopIds ) ) {
            return self::$shopIds;
        }

        $sql = 'SELECT shop_id FROM walmart_ca.options';

        $data = Database::request( $sql, __METHOD__, true );

        if ( isset( $data[0]['shop_id'] ) ) {
            self::$shopIds = array_column( $data, 'shop_id' );

            return self::$shopIds;
        }

        return false;
    }

    public static function shopIdStr()
    {
        if ( isset( self::$shopIdStr ) ) {
            return self::$shopIdStr;
        }

        $width = 6;
        $digit = App::$shopId;

        while ( strlen( (string)$digit ) < $width ) {
            $digit = '0' . $digit;
        }

        $shop_id_str     = (string)$digit;
        self::$shopIdStr = $shop_id_str;

        return $shop_id_str;
    }

    /**
     * @return mixed|string
     */
    public static function controller()
    {
        IF ( isset( self::$controller ) ) {
            return self::$controller;
        }

        global $argv;

        // controller
        IF ( !isset( $argv[1] ) || !is_string( $argv[1] ) ) {
            $message = 'You must specify the name of the controller';
            Logger::log( $message, __METHOD__ );

            return $message;
        }

        self::$controller = $argv[1];

        return self::$controller;
    }

    /**
     * @return mixed|string
     */
    public static function method()
    {
        if ( isset( self::$method ) ) {
            return self::$method;
        }

        global $argv;

        // method
        if ( !isset( $argv[2] ) || !is_string( $argv[2] ) ) {
            $message = 'You must specify the name of the action';
            Logger::log( $message, __METHOD__ );

            return $message;
        }

        self::$method = $argv[2];

        return self::$method;
    }

    /**
     * @return string
     */
    public static function logFile()
    {
        if ( isset( self::$logFile ) ) {
            return self::$logFile;
        }

        self::$logFile = self::controller() . '_' . self::method();

        return self::$logFile;
    }

    /**
     * @return bool|null
     */
    public static function debug()
    {
        if ( isset( self::$debug ) ) {
            return self::$debug;
        }

        global $argv;

        if ( isset( $argv[3] ) && $argv[3] === 'debug' ) {
            self::$debug = true;

            return true;
        }

        self::$debug = false;

        return false;
    }
}