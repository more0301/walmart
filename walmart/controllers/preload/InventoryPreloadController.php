<?php
declare( strict_types=1 );

namespace Walmart\controllers\preload;

use Walmart\core\App;
use Walmart\models\preload\compile\PreloadCompileInventory;
use Walmart\models\preload\transfer\PreloadTransferInventory;

/**
 * Class InventoryPreloadController
 *
 * @package Walmart\controllers\preload
 */
class InventoryPreloadController implements PreloadInterface
{
    /**
     * Collection of data from various sources
     *
     * @return bool
     */
    public function compile()
    {
        $model = new PreloadCompileInventory();

        // ========== import data ========== //

        $result = $model->importData();
        if ( false === $result ) {
            return false;
        }

        App::telegramLog( 'Recorded to ' . App::$inventoryPreloadT . ': ' . $result );

        // debug
        if ( true === App::$debug ) {
            var_dump( 'Imported ' . $result . ' records from table ' .
                App::$inventoryPreloadT );
            var_dump( 'Work completed successfully' );
        }

        return true;
    }

    /**
     * Transfer data from preload table to adding table
     *
     * @return bool
     */
    public function transfer()
    {
        $model = new PreloadTransferInventory();

        // ========== transfer to adding ========== //

        $result = $model->transferToAdding();
        if ( false === $result ) {
            return false;
        }

        App::telegramLog( 'Recorded to ' . App::$inventoryAddingT . ': ' . $result );

        // debug
        if ( true === App::$debug ) {
            var_dump( 'Copied ' . $result . ' records from table ' .
                App::$inventoryAddingT );
            var_dump( 'Work completed successfully' );
        }

        return true;
    }
}