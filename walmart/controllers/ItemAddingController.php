<?php
declare( strict_types=1 );

namespace Walmart\controllers;

use Walmart\core\App;
use Walmart\core\Controller;

/**
 * Class ItemPreloadController
 *
 * @package Walmart\controllers\preload
 */
class ItemAddingController extends Controller
{
    /**
     * Transfer data from preload table to adding table
     *
     * @return bool
     */
    public function start()
    {
        $items = $this->getReadyItems();

        if ( !$items ) {
            return false;
        }

        $valid_items = $this->sanitizeAndValidation( $items );

        if ( !$valid_items ) {
            return false;
        }

        $items_with_sku = $this->setSku( $valid_items );

        if ( !$items_with_sku ) {
            return false;
        }

        $result_transfer = $this->transferToAdding( $items_with_sku );

        if ( !$result_transfer ) {
            return false;
        }

        return true;
    }

    private function getReadyItems()
    {
        $items = $this->model->getReadyItems();

        if ( false === $items ) {
            return false;
        }

        $count_items = count( $items );

        App::$telegramLog[] = 'Total items: ' . $count_items;

        // debug
        if ( true === App::$debug ) {
            echo PHP_EOL . 'Items: ' . $count_items . PHP_EOL;

            $continue = readline( 'Next validate items. Continue? (y/n): ' );
            if ( $continue === 'n' || $continue !== 'y' ) {
                return false;
            }
        }

        return $items;
    }

    private function sanitizeAndValidation( $items )
    {
        if ( true === App::$debug ) {
            $continue = readline( 'Validate items? (y/n): ' );
            if ( $continue == 'n' ) {
                return $items;
            }
        }

        $valid_items = $this->model->sanitizeAndValidation( $items );

        if ( false === $valid_items ) {
            return false;
        }

        $items = null;

        $count_valid = count( $valid_items );

        App::$telegramLog[] = 'Valid items: ' . $count_valid;

        // debug
        if ( true === App::$debug ) {
            echo PHP_EOL . 'Valid items: ' . count( $valid_items ) . PHP_EOL;

            $continue = readline( 'Next set sku. Continue? (y/n): ' );
            if ( $continue === 'n' || $continue !== 'y' ) {
                return false;
            }
        }

        return $valid_items;
    }

    private function setSku( $valid_items )
    {
        $items_with_sku = $this->model->setSku( $valid_items );

        if ( false === $items_with_sku ) {
            return false;
        }

        // debug
        if ( true === App::$debug ) {
            echo PHP_EOL . 'Created sku for ' . count( $items_with_sku ) . ' items' . PHP_EOL;

            $continue = readline( 'Transfer to adding? (y/n): ' );
            if ( $continue === 'n' || $continue !== 'y' ) {
                return false;
            }
        }

        return $items_with_sku;
    }

    private function transferToAdding( $items_with_sku )
    {
        $result_transfer = $this->model->transferToAdding( $items_with_sku );

        if ( false === $result_transfer ) {
            return false;
        }

        App::$telegramLog[] = 'Recorded to ' . App::$itemAddingT . ': ' . $result_transfer;

        // debug
        if ( true === App::$debug ) {
            echo PHP_EOL . $result_transfer . ' records added/updated to table ' .
                App::$itemAddingT . PHP_EOL;
        }

        return $result_transfer;
    }
}