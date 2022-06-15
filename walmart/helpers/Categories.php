<?php

declare(strict_types=1);

namespace Walmart\helpers;

use Walmart\core\App;
use Walmart\helpers\database\Database;

// TODO: rewrite the whole class. one update request, no static properties

/**
 * Class Categories
 *
 * @package Walmart\helpers
 */
class Categories
{
    // all keywords with subcategories id
    public $multiKeywords;
    public $oneKeywords;

    // asin, product name, short_description, brand
    public $items;
    public $processedItem;

    /**
     * @return int
     */
    public function setCategories(): int
    {
        $keywords = $this->getAllKeywords();

        if (false === $keywords) {
            return 0;
        }

        $this->setKeywords($keywords);

        $items = $this->getItems();
        if (false === $items) {
            return 0;
        }

        //$items[0] = ['asin'=>'B072KQC3FQ','product_name'=>'adidas Winners ID Muscle Tank True Green/White XS'];

        $result = [];
        foreach ($items as $key => $item) {
            $item['words'] = $this->convertItemToWords($item)['product_name'];

            $match = $this->strongMatches($item);

            //var_dump($match);
            //exit();

            $subcat      = $this->getSubcatName($match['id']);
            $subcat_name = $subcat['title'];

            $cat      = $this->getCatName($subcat['category_id']);
            $cat_name = $cat['title'];

            $result[] = $this->updateCatAndSubcatName(
                $item['asin'],
                $cat_name,
                $subcat_name
            );
        }

        return count(array_filter($result) ?? []);
    }

    public function unsetCategories()
    {
        $keywords = $this->getDeleteKeywords();

        if (false === $keywords) {
            return false;
        }

        $this->setKeywords($keywords);

        $items = $this->getDeleteItems();
        if (false === $items) {
            return false;
        }

        $result = [];
        foreach ($items as $key => $item) {
            $item['words'] = $this->convertItemToWords($item)['product_name'];

            $match = $this->strongMatches($item);

            if ($match['id'] !== 49) {
                $subcat      = $this->getSubcatName(49);
                $subcat_name = $subcat['title'];

                $cat      = $this->getCatName($subcat['category_id']);
                $cat_name = $cat['title'];

                $result[] = $this                    ->updateCatAndSubcatName(
                        $item['asin'],
                        $cat_name,
                        $subcat_name
                    );
            }
        }

        $count = count(array_filter($result));

        return $count > 0 ? $count : false;
    }

    public function setKeywords($keywords)
    {
        //$keywords = $this->getAllKeywords();

        $multi_keywords = array_filter(
            $keywords,
            fn($item) => (false != strpos($item['title'], ' '))
        );
        $one_keywords   = @array_diff_assoc($keywords, $multi_keywords);

        usort(
            $multi_keywords,
            function ($a, $b) {
                if (!isset($a['priority']) || !isset($b['priority'])) {
                    return $a['priority'] > $b['priority'] ? -1 : 1;
                } else {
                    return $a['priority'] > $b['priority'] ? 1 : -1;
                }
            }
        );

        usort(
            $one_keywords,
            function ($a, $b) {
                if (!isset($a['priority']) || !isset($b['priority'])) {
                    return $a['priority'] > $b['priority'] ? -1 : 1;
                } else {
                    return $a['priority'] > $b['priority'] ? 1 : -1;
                }
            }
        );

        $this->multiKeywords = count($multi_keywords) > 0 ? $multi_keywords
            : [];
        $this->oneKeywords   = count($one_keywords) > 0 ? $one_keywords : [];
    }

    /**
     * @return array|bool|mixed
     */
    public function getAllKeywords()
    {
        //$sql = 'SELECT t1.subcategory_id,t1.title,t3.priority
        //        FROM ' . App::$dbSchema . '.' . App::$catKeywordsT . ' t1
        //        LEFT JOIN ' . App::$dbSchema . '.' . App::$subCategoryT . ' t2
        //        ON t2.id = t1.subcategory_id
        //        LEFT JOIN ' . App::$dbSchema . '.' . App::$categoryT . ' t3
        //        ON t2.category_id = t3.id
        //        ORDER BY t3.priority
        //        ';

        $sql = 'SELECT cat_keywords.subcategory_id, cat_keywords.title, category.priority
                FROM walmart_ca.cat_keywords cat_keywords
                
                         LEFT JOIN walmart_ca.subcategory subcategory
                                   ON subcategory.id = cat_keywords.subcategory_id
                
                         LEFT JOIN walmart_ca.category category
                                   ON subcategory.category_id = category.id
                
                ORDER BY category.priority';

        $keywords = Database::request($sql, __METHOD__, true);
        if (count($keywords) <= 0) {
            return false;
        }

        return $keywords;
    }

    public function getDeleteKeywords()
    {
        $sql = 'SELECT awaiting_delete_cat_keywords_walmart_ca.subcategory_id, awaiting_delete_cat_keywords_walmart_ca.keyword AS title, category.priority
                FROM awaiting_delete_cat_keywords_walmart_ca
                
                         LEFT JOIN walmart_ca.subcategory
                                   ON subcategory.id = awaiting_delete_cat_keywords_walmart_ca.subcategory_id
                
                         LEFT JOIN walmart_ca.category
                                   ON subcategory.category_id = category.id
                
                ORDER BY category.priority';

        $keywords = Database::request($sql, __METHOD__, true);
        if (count($keywords) <= 0) {
            return false;
        }

        return $keywords;
    }

    /**
     * @return array|mixed
     */
    public static function getItems()
    {
        // 'Other' categories are rechecked each time (in case the keys for
        // the search are updated)

        $sql = 'SELECT preload.asin,preload.product_name 
                FROM walmart_ca.item_preload preload 
                WHERE
                    preload.short_description IS NOT NULL
                    AND (preload.category IS NULL 
                             OR preload.subcategory IS NULL 
                             OR preload.subcategory = \'Other\' ) 
                    ';

        $items = Database::request($sql, __METHOD__, true);
        if (count($items) <= 0) {
            return false;
        }

        return $items;
    }

    public static function getDeleteItems()
    {
        $sql = 'SELECT preload.asin,preload.product_name 
                FROM walmart_ca.item_preload preload 
                WHERE
                    preload.short_description IS NOT NULL
                    AND (preload.category IS NOT NULL 
                             AND preload.subcategory IS NOT NULL 
                             AND preload.subcategory <> \'Other\' ) 
                    ';

        $items = Database::request($sql, __METHOD__, true);
        if (count($items) <= 0) {
            return false;
        }

        return $items;
    }

    /**
     * @param array $item
     *
     * @return array
     */
    public static function convertItemToWords(array $item)
    {
        foreach ($item as $key => &$row) {
            if ($key === 'product_name' || $key === 'short_description') {
                // all uppercase and remove punctuation
                //$string = strtolower(
                //    preg_replace( '/[[:punct:]]/u', ' ', $row ) );
                $string = strtolower(
                    preg_replace('/[^A-Za-z0-9-]/u', ' ', $row)
                );

                $row = array_filter(
                    explode(' ', $string),
                    fn($value) => (strlen($value) > 1)
                );

                // unique words
                $row = array_unique($row);
            }
        }

        return $item;
    }

    /**
     * @param array $item
     *
     * @return array
     */
    public function strongMatches(array $item)
    {
        foreach ($this->multiKeywords as $multi_key) {
            if (false !== stripos($item['product_name'], $multi_key['title'])) {
                return [
                    'asin' => $item['asin'],
                    'id'   => $multi_key['subcategory_id']
                ];
            }
        }

        foreach ($this->oneKeywords as $keyword) {
            foreach ($item['words'] as $word) {
                if ($keyword['title'] === $word) {
                    return [
                        'asin' => $item['asin'],
                        'id'   => $keyword['subcategory_id']
                    ];
                }
            }
        }

        return [
            'asin' => $item['asin'],
            'id'   => 49
        ];
    }

    /**
     * @param int $id
     *
     * @return array|bool|mixed
     */
    public function getSubcatName(int $id)
    {
        $sql = 'SELECT category_id,title 
                FROM ' . App::$dbSchema . '.' . App::$subCategoryT . ' 
                WHERE id=' . $id;

        $subcat = Database::request($sql, __METHOD__);
        if (false === $subcat) {
            return false;
        }

        return $subcat;
    }

    /**
     * @param int $id
     *
     * @return array|bool|mixed
     */
    public function getCatName(int $id)
    {
        $sql = 'SELECT title 
                FROM ' . App::$dbSchema . '.' . App::$categoryT . ' 
                WHERE id=' . $id;

        $cat = Database::request($sql, __METHOD__);
        if (false === $cat) {
            return false;
        }

        return $cat;
    }

    /**
     * @param string $asin
     * @param string $cat_name
     * @param string $subcat_name
     * @param \PDO   $dbh
     *
     * @return bool|int
     */
    public function updateCatAndSubcatName(
        string $asin,
        string $cat_name,
        string $subcat_name
    ) {
        $sql = 'UPDATE walmart_ca.item_preload 
        SET 
        category=' . App::dbh()->quote($cat_name) . ', 
        subcategory=' . App::dbh()->quote($subcat_name) . ' 
        WHERE asin=' . App::dbh()->quote($asin) . '
        RETURNING asin';

        return Database::request($sql, __METHOD__);
    }

    /*    public function start()
        {
            $this->setData();
            $update = [];

            foreach ( $this->items as $item ) {
                $this->processedItem = $this->convertItemToWords( $item );

                $id_by_name = $this->matches( 'product_name' );
                if ( is_array( $id_by_name ) ) {
                    $id_by_description = $this->matches( 'short_description' );
                }

                // 49 - other category/subcategory
                $subcat_id = !is_array( $id_by_name ) ? $id_by_name : ( !is_array( $id_by_description ) ? $id_by_description : 49 );

                $subcat      = $this->getSubcatName( $subcat_id );
                $subcat_name = $subcat['title'];
                $cat_name    = $this->getCatName( $subcat['category_id'] )['title'];

                $update[] = $this->updateCatAndSubcatName( $item['asin'], $cat_name, $subcat_name );

                $this->processedItem = null;
            }

            Logger::log( 'Categories for ' . count( $update ) . ' items defined', __METHOD__ );

            return true;
        }

        public function setData()
        {
            $keywords       = $this->getAllKeywords();
            $this->keywords = $this->convertKeywordsToWords( $keywords );

            $this->items = $this->getItems();
        }

        public function matches( string $mode )
        {
            $matches = [];

            foreach ( $this->keywords as $keyword ) {
                foreach ( $this->processedItem[ $mode ] as $word ) {

                    // similar
                    similar_text( $keyword['title'], $word, $percent );

                    if ( (int)$percent >= 85 ) {
                        if ( !isset( $matches[ $keyword['subcategory_id'] ] ) ) {
                            $matches[ $keyword['subcategory_id'] ] = 1;
                        }
                        else {
                            $matches[ $keyword['subcategory_id'] ] += 1;
                        }
                    }
                }
            }

            if ( count( $matches ) > 0 ) {
                // otherwise the biggest will be the category
                return array_search( max( $matches ), $matches );
            }

            return [];
        }

        public static function convertKeywordsToWords( array $keywords )
        {
            $new_keywords = [];

            foreach ( $keywords as $item ) {
                $parts = [];

                if ( false !== strpos( $item['title'], ' ' ) ) {
                    $parts = explode( ' ', $item['title'] );
                    //                $parts = array_map( function( $value ) use ( $item ) {
                    //                    return [
                    //                        'subcategory_id' => $item['subcategory_id'],
                    //                        'title'          => $value
                    //                    ];
                    //                }, $parts );
                    $parts = array_map( fn( $value ) => [
                        'subcategory_id' => $item['subcategory_id'],
                        'title'          => $value
                    ], $parts );

                    if ( count( $parts ) > 0 ) {
                        foreach ( $parts as $part ) {
                            $new_keywords[] = $part;
                        }
                    }
                }
                else {
                    $new_keywords[] = $item;
                }
                unset( $parts );
            }

            return $new_keywords;
        }*/

    //    public static function sGapon()
    //    {
    //        $request_outer="SELECT * FROM blacklist_keyword";
    //        $query_outer = $PDO->query($request_outer);
    //        foreach($query_outer as $key_query_outer=>$row_outer)
    //        {
    //            $counter++;
    //
    //            $request="INSERT INTO blacklist_extract_local (id_keyword, asin)
    //SELECT ".$row_outer['id_keyword'].", asin
    //FROM local_dump_asin
    //WHERE (is_setted = 0) AND (lower(title) ~ E'([^a-z]|^)".(str_replace(array("[","]"), array("\\\\[","\\\\]"), addslashes(strtolower($row_outer['keyword']))))."([^a-z]|$)')
    //ON CONFLICT DO NOTHING";
    //            //var_dump($request);
    //            //die();
    //            $state = $PDO->prepare($request)->execute();
    //            $outer = "append blacklist_extract_local(".$counter."; ".date('Y-m-d H:i:s')."):".(var_export($state, true))."\n";
    //            file_put_contents("log.log", $outer, FILE_APPEND | LOCK_EX);
    //            echo $outer;
    //        }
    //    }

}
