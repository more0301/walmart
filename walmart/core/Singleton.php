<?php
declare( strict_types=1 );

namespace Walmart\core;

use Exception;

class Singleton
{
    private static $instances = [];

    protected function __construct()
    {
    }

    public static function getInstance()
    {
        $subclass = static::class;
        if ( !isset( self::$instances[ $subclass ] ) ) {
            self::$instances[ $subclass ] = new static;
        }

        return self::$instances[ $subclass ];
    }

    protected function __clone()
    {
    }

    public function __wakeup()
    {
        throw new Exception( 'Cannot unserialize singleton' );
    }
}