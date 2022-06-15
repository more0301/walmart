<?php
declare( strict_types=1 );

namespace Walmart\controllers;

use Walmart\core\App;
use Walmart\core\Controller;

class RetireController extends Controller
{
    public function start()
    {
        $items = $this->getListToRetire();

        if ( empty( $items ) ) {
            return false;
        }

        $this->retireItems( $items );

        return true;
    }

    private function getListToRetire() :array
    {
        $items = $this->model->getListToRetire();

        if ( false === $items ) {
            return [];
        }

        $count_items = count( $items );
        App::telegramLog( 'Items to remove: ' . $count_items );

        // debug
        if ( true === App::$debug ) {
            echo 'Items to retire: ' . $count_items . PHP_EOL;
            $continue = readline( 'Next retire items. Continue? (y/n): ' );
            if ( $continue === 'n' || $continue !== 'y' ) {
                return [];
            }
        }

        return $items;
    }

    private function retireItems( array $items )
    {
        $retire_result = $this->model->retireItem( $items );

        App::telegramLog( 'Deleted items: ' . (int)$retire_result );

        // debug
        if ( true === App::$debug ) {
            echo 'Deleted items: ' . (int)$retire_result . ' items' . PHP_EOL;
        }
    }
}