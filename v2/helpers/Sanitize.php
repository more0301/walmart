<?php
declare( strict_types=1 );

namespace WB\Helpers;

/**
 * Class Sanitize
 *
 * @package Walmart\helpers
 */
class Sanitize
{
    /**
     * @param $value
     *
     * @return string
     */
    public static function sanitizeString( $value )
    {
        $value = filter_var( $value, FILTER_UNSAFE_RAW, FILTER_FLAG_ENCODE_LOW | FILTER_FLAG_STRIP_HIGH );

        return htmlspecialchars( strip_tags( $value ), ENT_QUOTES, 'UTF-8', false );
    }

    public static function sanitizeStringImport( $value )
    {
        return htmlspecialchars( $value, ENT_NOQUOTES | ENT_HTML5,
            'UTF-8', false );
    }

    public static function sanitizeStringExport( $value )
    {
        return htmlspecialchars( $value, ENT_NOQUOTES | ENT_HTML5,
            'UTF-8', false );
    }

    /**
     * @param $value
     *
     * @return float
     */
    public static function sanitizeInt( $value )
    {
        return (int)filter_var( $value, FILTER_SANITIZE_NUMBER_INT );
    }

    /**
     * @param $value
     *
     * @return float
     */
    public static function sanitizeFloat( $value )
    {
        return (float)filter_var( $value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION );
    }
}
