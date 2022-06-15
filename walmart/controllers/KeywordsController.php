<?php
declare( strict_types=1 );

namespace Walmart\controllers;

use Walmart\core\Controller;

/**
 * Class KeywordsController
 * Run:
 * php index.php keywords start debug
 * Next - add keywords list. First - subcategory name
 * Example: SubcatName,key1,key2...
 *
 * @package Walmart\controllers
 */
class KeywordsController extends Controller
{
    public string $subcategory = '';

    public function start()
    {
        $diff = $this->checkKeywords();

        if ( isset( $diff['not_in_database'] ) && !empty( $diff['not_in_database'] ) ) {

            echo 'Not in database:' . PHP_EOL;
            print_r( $diff['not_in_database'] );
            echo PHP_EOL;

            if ( $this->subcategory == 'Total' ) {
                echo 'Not in checking:' . PHP_EOL;
                print_r( $diff['not_in_checking'] );
                echo PHP_EOL;
            }

            $add_readline = readline( 'Add to database? (y/n): ' );

            if ( $add_readline === 'y' ) {
                $this->addKeywords( $diff['not_in_database'] );
            }
        }
    }

    private function checkKeywords()
    {
        $keys_readline     = readline( 'Add keywords "Subcategory,key,key2...": ' );
        $keys_for_checking = $this->model->getKeysForChecking( $keys_readline );

        $this->subcategory = $keys_for_checking[0];

        $keys_from_database = $this->model->getKeywordsFromDatabase();

        return $this->model->keywordsDiff( $keys_for_checking[1], $keys_from_database );
    }

    private function addKeywords( array $keywords )
    {
        $subcat_id = $this->model->getCatId( $this->subcategory );

        $save_result = 0;

        if ( isset( $subcat_id ) ) {
            $save_result = $this->model->addKeyToDb( $subcat_id, $keywords );
        }

        echo 'Added ' . $save_result . ' keywords to subcategory ' . $this->subcategory . PHP_EOL;
    }
}