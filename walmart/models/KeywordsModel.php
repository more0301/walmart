<?php

declare(strict_types=1);

namespace Walmart\models;

use Walmart\core\App;
use Walmart\helpers\database\Database;
use Walmart\helpers\database\Db;

class KeywordsModel
{
    public function getKeysForChecking(string $str)
    {
        $keys     = explode(',', $str);
        $category = array_shift($keys);

        return [
            $category,
            array_values(
                array_unique(
                    array_map(
                        fn($val) => str_replace(
                            '’',
                            '\'',
                            strtolower(trim($val ?? ''))
                        ),
                        $keys
                    )
                )
            )
        ];
    }

    public function getKeywordsFromDatabase()
    {
        $sql = 'SELECT keywords.title key_title,
                       category.id cat_id,
                       category.title cat_title,
                       subcategory.id subcat_id,
                       subcategory.title subcat_title,
                       category.priority
                FROM walmart_ca.cat_keywords keywords
                
                         LEFT JOIN walmart_ca.subcategory subcategory
                                   ON subcategory.id = keywords.subcategory_id
                
                         LEFT JOIN walmart_ca.category category
                                   ON category.id = subcategory.category_id';

        return Database::request($sql, __METHOD__, true);
    }

    public function keywordsDiff($keys_for_checking, $keys_from_database)
    {
        $from_database = array_column($keys_from_database, 'key_title');

        return [
            'not_in_database' => array_diff($keys_for_checking, $from_database),
            'not_in_checking' => array_diff($from_database, $keys_for_checking)
        ];
    }

    public function getCatId($subcat_name)
    {
        $sql = 'SELECT id
                FROM walmart_ca.subcategory
                WHERE title = \'' . $subcat_name . '\'';

        return (Database::request($sql, __METHOD__, false))['id'];
    }

    public function addKeyToDb($subcat_id, $keywords)
    {
        $db = new Db();

        $result = 0;

        foreach ($keywords as $key) {
            if (!isset($key) || empty($key)) {
                continue;
            }

            $data = [$subcat_id, $key];

            $sql = 'INSERT INTO ' . App::$dbSchema . '.' . App::$catKeywordsT .
                   ' (subcategory_id,title) VALUES (?,?) 
                ON CONFLICT DO NOTHING';

            $result += (int)$db->run($sql, $data)->rowCount();
        }

        return $result;
    }
}
