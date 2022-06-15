<?php
declare( strict_types=1 );

namespace Walmart\helpers;

trait Error
{
    public static function error403()
    {
        header( 'Permission denied', true, 403 );
        die( 'Permission denied' );
    }

    public static function error404()
    {
        header( 'Not found', true, 404 );
        die( 'Not found' );
    }
}