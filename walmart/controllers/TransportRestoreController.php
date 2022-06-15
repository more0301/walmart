<?php
declare( strict_types=1 );

namespace Walmart\controllers;

use Walmart\core\App;
use Walmart\helpers\Categories;
use Walmart\models\TransportRestoreModel;

class TransportRestoreController
{
    public TransportRestoreModel $model;

    public function __construct()
    {
        $this->model = new TransportRestoreModel();
    }

    public function start()
    {
        if ( App::shopIds()[ array_key_first( App::shopIds() ) ] === App::$shopId ) {
            global $argv;

            // example: php index.php transport_restore start orders
            if ( isset( $argv[1] )
                && $argv[1] == 'transport_restore'
                && isset( $argv[3] ) ) {

                switch ( $argv[3] ) {

                    case 'orders':
                        $this->restoreOrders();
                        break;

                    case 'order_acknowledge':
                        $this->restoreOrdersAcknowledge();
                        break;

                    case 'scraper':
                        $this->restoreScraper();
                        break;
                }
            }
            else {
                $this->restoreFull();
            }
        }
    }

    public function restoreFull()
    {
        $this->model->restoreBlackSku();
        $this->model->restoreBaseSettings();
        $this->model->restoreAwaitingDeleteBrands();
        $this->model->restoreAwaitingAddBrands();
        $this->restoreCatKeywords();
    }

    public function restoreOrders()
    {
        $this->model->restoreOrders();
    }

    public function restoreOrdersAcknowledge()
    {
        $this->model->restoreOrdersAcknowledge();
    }

    public function restoreScraper()
    {
        $this->model->restoreScraper();

        $this->model->sendStatToViber();
    }

    public function restoreCatKeywords()
    {
        // drop temp tables
        $this->model->dropTable( '', 'awaiting_add_cat_keywords_walmart_ca' );
        $this->model->dropTable( '', 'awaiting_delete_cat_keywords_walmart_ca' );

        $this->model->restoreCatKeywords();

        // restart category generation
        // this is for table awaiting_add_cat_keywords_walmart_ca
        $categories = new Categories();
        $categories->setCategories();

        // for table awaiting_delete_cat_keywords_walmart_ca
        $categories->unsetCategories();
    }
}
