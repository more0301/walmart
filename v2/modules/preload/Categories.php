<?php

declare(strict_types=1);

namespace WB\Modules\Preload;

// TODO: rewrite the whole class. one update request, no static properties
use WB\Core\App;
use WB\Helpers\Logger;

/**
 * Class Categories
 *
 * @package Walmart\helpers
 */
class Categories
{
    private array $multiKeywords;
    private array $oneKeywords;
    private array $catAndSubCat;

    private array $updateData = [];

    private int $totalUpdatedCategories = 0;

    public function setCategories(): void
    {
        $keywords = $this->getAllKeywords();

        if (empty($keywords)) {
            return;
        }

        $this->sortKeywords($keywords);

        $products = $this->getProducts();

        if (empty($products)) {
            return;
        }

        $this->setCatAndSubCat();

        if (empty($this->catAndSubCat)) {
            return;
        }

        foreach ($products as $item) {
            $item['words'] = $this->convertItemToWords($item)['product_name'];

            $match = $this->strongMatches($item);

            $this->updateCatAndSubcatName(
                $item['asin'],
                $this->catAndSubCat[$match['subcategory_id']]['category_title'],
                $this->catAndSubCat[$match['subcategory_id']]['subcategory_title']
            );
        }

        $message = 'Total updated ' . $this->totalUpdatedCategories
                   . ' categories';

        Logger::log($message, __METHOD__, 'info', alert: true);
    }

    private function getAllKeywords(): array
    {
        $sql = 'SELECT ck.subcategory_id, ck.title, c.priority
                FROM walmart_ca.cat_keywords AS ck
                    LEFT JOIN walmart_ca.subcategory AS s
                        ON s.id = ck.subcategory_id
                    LEFT JOIN walmart_ca.category AS c
                        ON s.category_id = c.id
                ORDER BY c.priority';

        $response = App::$db->run($sql)->fetchAll();

        return isset($response) && is_array($response) ?
            $response : [];
    }

    private function sortKeywords($keywords): void
    {
        $multi_keywords = array_filter(
            $keywords,
            fn($item) => (str_contains($item['title'], ' '))
        );

        $one_keywords = @array_diff_assoc($keywords, $multi_keywords);

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

        $this->multiKeywords = !empty($multi_keywords)
                               && is_array($multi_keywords)
            ? $multi_keywords : [];

        $this->oneKeywords = !empty($one_keywords)
                             && is_array($one_keywords)
            ? $one_keywords : [];
    }

    public function getProducts(): array
    {
        // 'Other' categories are rechecked each time (in case the keys for
        // the search are updated)

        $sql = 'SELECT asin, product_name 
                FROM walmart_ca.item_preload 
                WHERE short_description IS NOT NULL
                    AND (category IS NULL
                             OR subcategory IS NULL 
                             OR subcategory = \'Other\')';

        $response = App::$db->run($sql)->fetchAll();

        return isset($response) && is_array($response) ? $response : [];
    }

    private function setCatAndSubCat(): void
    {
        $response = App::$db->run(
            'SELECT category.title AS cat_title,
                        subcategory.id AS subcat_id,
                        subcategory.title AS subcat_title
                FROM walmart_ca.category
                    LEFT JOIN walmart_ca.subcategory
                        ON subcategory.category_id = category.id'
        )->fetchAll();

        $result = [];

        if (isset($response) && is_array($response)) {
            foreach ($response as $item) {
                $result[$item['subcat_id']]['category_title']
                    = $item['cat_title'];
                $result[$item['subcat_id']]['subcategory_title']
                    = $item['subcat_title'];
            }
        }

        $this->catAndSubCat = $result;
    }

    public static function convertItemToWords(array $item): array
    {
        foreach ($item as $key => &$row) {
            if ($key === 'product_name' || $key === 'short_description') {
                // all uppercase and remove punctuation
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

    public function strongMatches(array $item): array
    {
        foreach ($this->multiKeywords as $multi_key) {
            if (str_contains($item['product_name'], $multi_key['title'])) {
                return [
                    'asin'           => $item['asin'],
                    'subcategory_id' => $multi_key['subcategory_id']
                ];
            }
        }

        foreach ($this->oneKeywords as $keyword) {
            foreach ($item['words'] as $word) {
                if ($keyword['title'] === $word) {
                    return [
                        'asin'           => $item['asin'],
                        'subcategory_id' => $keyword['subcategory_id']
                    ];
                }
            }
        }

        return [
            'asin'           => $item['asin'],
            'subcategory_id' => 49
        ];
    }

    public function updateCatAndSubcatName(
        string $asin,
        string $cat_name,
        string $subcat_name
    ): void {
        if (count($this->updateData, COUNT_RECURSIVE) > 3000) {
            foreach ($this->updateData as $item) {
                $cat = $subcat = null;

                if (!isset($cat)) {
                    $cat = $item[0]['cat'] ?? null;
                }
                if (!isset($subcat)) {
                    $subcat = $item[0]['subcat'] ?? null;
                }

                $asins = implode(
                    ',',
                    array_map(
                        fn($asin) => '\'' . $asin . '\'',
                        array_column($item, 'asin')
                    )
                );

                if (!empty($asins)) {
                    $sql = 'UPDATE walmart_ca.item_preload
                            SET category = :cat_name, 
                                subcategory = :subcat_name
                            WHERE asin IN (' . $asins . ')';

                    $this->totalUpdatedCategories += App::$db->run(
                        $sql,
                        ['cat_name' => $cat, 'subcat_name' => $subcat,]
                    )->rowCount();
                }
            }

            $this->updateData = [];
        } else {
            $this->updateData[$cat_name . $subcat_name][] = [
                'asin'   => $asin,
                'cat'    => $cat_name,
                'subcat' => $subcat_name
            ];
        }
    }

    public function unsetCategories()
    {
        $keywords = $this->getDeleteKeywords();

        if (false === $keywords) {
            return false;
        }

        $this->sortKeywords($keywords);

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

                $result[] = $this->updateCatAndSubcatName(
                    $item['asin'],
                    $cat_name,
                    $subcat_name
                );
            }
        }

        $count = count(array_filter($result));

        return $count > 0 ? $count : false;
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
}
