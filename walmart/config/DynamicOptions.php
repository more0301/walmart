<?php
declare( strict_types=1 );

namespace Walmart\config;

trait DynamicOptions
{
    /**
     * ID of current store
     *
     * @var int
     */
    public static int     $shopId = 0;

    /**
     * Flag - truncate the report table only at the first iteration
     *
     * @var bool
     */
    public static bool $truncateReport = false;

    /**
     * List of main application logs
     *
     * @var array
     */
    public static array $telegramLog       = [];
    public static bool  $doNotSendTelegram = false;

    public static function telegramLog( string $str,
                                        int $position = null ) :void
    {
        if ( null === $position ) {
            self::$telegramLog[] = $str;
        }
        else {
            if ( isset( self::$telegramLog[ $position ] ) ) {
                $part1             = array_slice( self::$telegramLog, 0, $position );
                $part2             = array_slice( self::$telegramLog, $position );
                self::$telegramLog = array_merge( $part1, [ $position => $str ], $part2 );
            }
            elseif ( count( self::$telegramLog ) > 0 ) {
                self::$telegramLog[ $position ] = $str;
            }
            else {
                self::$telegramLog[] = $str;
            }
        }
    }
}