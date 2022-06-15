<?php
declare( strict_types=1 );

namespace Walmart\helpers;

use Exception;
use ReflectionClass;
use Walmart\core\App;
use Walmart\helpers\database\Database;

trait Functions
{
    /**
     * Guid generator
     *
     * @return string
     */
    public static function getGuid()
    {
        if ( true === function_exists( 'com_create_guid' ) ) {
            return trim( com_create_guid(), '{}' );
        }

        return sprintf( '%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand( 0, 65535 ), mt_rand( 0, 65535 ), mt_rand( 0, 65535 ), mt_rand( 16384, 20479 ), mt_rand( 32768, 49151 ), mt_rand( 0, 65535 ), mt_rand( 0, 65535 ), mt_rand( 0, 65535 ) );
    }

    /**
     * Returns the name of the class, without namespace
     *
     * @param string $str
     *
     * @return string
     */
    public static function methodName( string $str ) :string
    {
        $str_arr = explode( '\\', $str );
        $key     = array_key_last( $str_arr );

        return isset( $str_arr[ $key ] ) ? $str_arr[ $key ] : '';
    }

    /**
     * Returns a list of methods for the specified class
     *
     * @param object $class
     * @param string $end_method
     *
     * @return array|bool
     */
    public static function getClassMethods( object $class, string $end_method = '' )
    {
        try {
            $reflection = new ReflectionClass( $class );
        }
        catch ( \ReflectionException $e ) {
            Logger::log( $e->getMessage(), __METHOD__ );

            return false;
        }

        $methods = [];

        foreach ( $reflection->getMethods() as $method ) {
            $methods[] = $method->name;

            if ( !empty( $end_method ) && $method->name == $end_method ) {
                break;
            }
        }

        return $methods;
    }

    /**
     * Data output, modifiers:
     * * 1: var_dump
     * * 2: print_r
     * * 3: echo
     *
     * @param     $data
     * @param int $mode
     *
     * @return bool
     */
    public static function wtf( $data, int $mode = 1 )
    {
        if ( false === App::$debug ) {
            return false;
        }

        switch ( $mode ) {
            case 2:
                print_r( $data );
                break;
            case 3:
                if ( !is_countable( $data ) ) {
                    echo $data . PHP_EOL;
                }
                else {
                    echo 'Is countable data' . PHP_EOL;
                }
                break;
            default:
                var_dump( $data );
        }
    }

    public static function camelCase( string $val, int $mode )
    {
        // camelCase to camel_case
        if ( $mode === 1 ) {
            //            $chars = array_map( function( $char ) {
            //                return true === ctype_upper( $char ) ? '_' . strtolower( $char ) : $char;
            //            }, str_split( $val ) );
            $chars = array_map( fn( $char ) => ( true === ctype_upper( $char ) ) ?
                '_' . strtolower( $char ) : $char
                , str_split( $val ) );

            return implode( $chars );
        }
        // camel_case to camelCase
        elseif ( $mode === 2 ) {
            $words = explode( '_', $val );
            $first = array_shift( $words );

            return $first . implode( array_map( 'ucfirst', $words ) );
        }

        return $val;
    }

    public static function jsonToArray( string $json )
    {
        try {
            $data = json_decode( $json, true );

            switch ( json_last_error() ) {
                case JSON_ERROR_NONE:
                    Logger::log( 'Json data successfully converted', __METHOD__, 'dev' );

                    return $data;
                    break;
                case JSON_ERROR_DEPTH:
                    throw new Exception( 'Maximum stack depth reached' );
                    break;
                case JSON_ERROR_STATE_MISMATCH:
                    throw new Exception( 'Incorrect bits or mode mismatch' );
                    break;
                case JSON_ERROR_CTRL_CHAR:
                    throw new Exception( 'Invalid control character' );
                    break;
                case JSON_ERROR_SYNTAX:
                    throw new Exception( 'Syntax error, invalid JSON' );
                    break;
                case JSON_ERROR_UTF8:
                    throw new Exception( 'Incorrect UTF-8 characters, possibly incorrectly encoded' );
                    break;
                default:
                    throw new Exception( 'Unknown error' );
                    break;
            }
        }
        catch ( \Throwable $e ) {
            Logger::log( $e->getMessage(), __METHOD__ );

            return false;
        }
    }

    /**
     * @param string $table
     * @param bool   $with_bool
     * @param bool   $return_array
     *
     * @param array  $exclude_columns
     *
     * @return array|bool|string
     */
    public static function getTableColumns( string $table, bool $with_bool = false, bool $return_array = false, array $exclude_columns = [] )
    {
        $bool = true === $with_bool ? '' : ' AND data_type!=\'boolean\'';
        $sql  = 'SELECT column_name 
                    FROM information_schema.columns 
                    WHERE table_schema=\'' . App::$dbSchema . '\' 
                    AND table_name=\'' . $table . '\'' . $bool;

        $columns = Database::request( $sql, __METHOD__, true );

        if ( count( $columns ) <= 0 ) {
            return false;
        }

        $array_columns = array_column( $columns, 'column_name' );

        if ( count( $exclude_columns ) > 0 ) {
            foreach ( $array_columns as $key => $column ) {
                if ( in_array( $column, $exclude_columns ) ) {
                    unset( $array_columns[ $key ] );
                }
            }
        }

        if ( true === $return_array ) {
            return $array_columns;
        }

        return implode( ',', $array_columns );
    }

    public static function arrayUniqueKey( $array, $key )
    {
        $tmp = $key_array = [];
        $i   = 0;

        foreach ( $array as $val ) {
            if ( !in_array( $val[ $key ], $key_array ) ) {
                $key_array[ $i ] = $val[ $key ];
                $tmp[ $i ]       = $val;
            }
            $i++;
        }
        return $tmp;
    }

    public static function getDateInterval( string $interval_spec, string $mode = 'sub' )
    {
        $date     = new \DateTime();
        $interval = new \DateInterval( $interval_spec );

        if ( $mode === 'sub' ) {
            $date->sub( $interval );
        }
        else {
            $date->add( $interval );
        }

        return $date->format( 'Y-m-d' );
    }
}