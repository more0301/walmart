<?php
declare( strict_types=1 );

namespace Walmart\helpers;

defined( 'WM_ACCESS' ) or die( 'Permission denied' );

trait Messages
{
    public static function error403()
    {
        header( 'Permission denied', true, 403 );
        die( 'Permission denied' );
    }
}