<?php
declare( strict_types=1 );

namespace Walmart\controllers;

use Walmart\core\App;
use Walmart\core\Controller;

/**
 * Class PricePreloadController
 *
 * @package Walmart\controllers\preload
 */
class PriceCompileController extends Controller
{
    /**
     * Collection of data from various sources
     *
     * @return bool
     */
    public function start()
    {
        $result = $this->model->importData();

        if ( !$result ) {
            return false;
        }

        App::telegramLog( 'Recorded to ' . App::$pricePreloadT . ': ' . $result );

        // debug
        if ( true === App::$debug ) {
            echo PHP_EOL . 'Imported ' . $result . ' records from table ' .
                App::$pricePreloadT . PHP_EOL;
        }

        return true;
    }
}