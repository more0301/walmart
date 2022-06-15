<?php

declare(strict_types=1);

namespace Walmart\models;

use Walmart\core\App;
use Walmart\helpers\Categories;
use Walmart\helpers\database\Db;
use Walmart\helpers\Logger;

/**
 * Class PreloadCompileItem
 *
 * @package Walmart\models\preload\compile
 */
class ItemPreloadModel
{
    private Db $db;

    public function __construct()
    {
        $this->db = new Db();
    }

    /**
     * Import data from dump_asin table, external_usa_bezoxx schema
     *
     * @return bool|int
     */
    public function importExternalDump()
    {
        //$sql = 'WITH dump_not_exists_preload AS (
        //            SELECT dump_asin.asin, dump_asin.upc,
        //            dump_asin.title, dump_asin.brand
        //            FROM external_dump.dump_asin
        //                     LEFT JOIN walmart_ca.item_preload
        //                               ON item_preload.asin = dump_asin.asin
        //            WHERE item_preload.asin IS NULL
        //              AND length(dump_asin.upc) = 12
        //              AND dump_asin.asin IS NOT NULL
        //              AND dump_asin.upc IS NOT NULL
        //              AND dump_asin.title IS NOT NULL
        //              AND dump_asin.brand IS NOT NULL
        //        ),
        //             full_not_exists_preload AS (
        //                 SELECT full_usa.asin, full_usa.upc,
        //                 full_usa.title, full_usa.brand
        //                 FROM external_dump.full_usa
        //                          LEFT JOIN walmart_ca.item_preload
        //                                    ON item_preload.asin = full_usa.asin
        //                 WHERE item_preload.asin IS NULL
        //                   AND length(full_usa.upc) = 12
        //                   AND full_usa.asin IS NOT NULL
        //                   AND full_usa.upc IS NOT NULL
        //                   AND full_usa.title IS NOT NULL
        //                   AND full_usa.brand IS NOT NULL
        //                 ),
        //             total AS (
        //                 SELECT external.asin, external.upc,
        //                 external.title, external.brand
        //                 FROM (
        //                          SELECT asin, upc, title, brand
        //                          FROM dump_not_exists_preload
        //
        //                          UNION
        //
        //                          SELECT asin, upc, title, brand
        //                          FROM full_not_exists_preload
        //                      ) AS external
        //             )
        //        INSERT INTO walmart_ca.item_preload
        //            (asin, upc, product_name, brand)
        //        SELECT asin, upc, title, brand
        //        FROM total
        //        ON CONFLICT DO NOTHING';

        //$sql = 'INSERT INTO walmart_ca.item_preload
        //            (asin, upc, product_name, brand)
        //
        //            SELECT external.asin, external.upc,
        //                       external.title, external.brand
        //                FROM (
        //                     SELECT full_usa.asin, full_usa.upc,
        //                            full_usa.title, full_usa.brand
        //                     FROM external_dump.full_usa
        //                              LEFT JOIN walmart_ca.item_preload
        //                                        ON item_preload.asin = full_usa.asin
        //                     WHERE item_preload.asin IS NULL
        //                       AND length(full_usa.upc) = 12
        //                       AND full_usa.asin IS NOT NULL
        //                       AND full_usa.upc IS NOT NULL
        //                       AND full_usa.title IS NOT NULL
        //                       AND full_usa.brand IS NOT NULL
        //
        //                     UNION
        //
        //                     SELECT dump_asin.asin, dump_asin.upc,
        //                            dump_asin.title, dump_asin.brand
        //                     FROM external_dump.dump_asin
        //                              LEFT JOIN walmart_ca.item_preload
        //                                        ON item_preload.asin = dump_asin.asin
        //                     WHERE item_preload.asin IS NULL
        //                       AND length(dump_asin.upc) = 12
        //                       AND dump_asin.asin IS NOT NULL
        //                       AND dump_asin.upc IS NOT NULL
        //                       AND dump_asin.title IS NOT NULL
        //                       AND dump_asin.brand IS NOT NULL
        //                 ) AS external
        //
        //        ON CONFLICT DO NOTHING';

        $sql = 'WITH not_exists_asin AS (
                    SELECT external.asin,
                           external.upc,
                           external.title,
                           external.brand
                    FROM (
                             SELECT full_usa.asin,
                                    full_usa.upc,
                                    full_usa.title,
                                    full_usa.brand
                             FROM external_dump.full_usa
                                      LEFT JOIN walmart_ca.item_preload
                                                ON item_preload.asin = full_usa.asin
                             WHERE item_preload.asin IS NULL
                               AND length(full_usa.upc) = 12
                               AND full_usa.asin IS NOT NULL
                               AND full_usa.upc IS NOT NULL
                               AND full_usa.title IS NOT NULL
                               AND full_usa.brand IS NOT NULL
                               
                             UNION
                
                             SELECT dump_asin.asin,
                                    dump_asin.upc,
                                    dump_asin.title,
                                    dump_asin.brand
                             FROM external_usa_bezoxx.dump_asin
                                      LEFT JOIN walmart_ca.item_preload
                                                ON item_preload.asin = dump_asin.asin
                             WHERE item_preload.asin IS NULL
                               AND length(dump_asin.upc) = 12
                               AND dump_asin.asin IS NOT NULL
                               AND dump_asin.upc IS NOT NULL
                               AND dump_asin.title IS NOT NULL
                               AND dump_asin.brand IS NOT NULL
                         ) AS external),
                     not_exists_upc AS (
                         SELECT nea.asin, nea.upc, nea.title, nea.brand
                         FROM not_exists_asin AS nea
                         WHERE NOT exists(SELECT 1
                                          FROM walmart_ca.item_preload
                                          WHERE item_preload.upc = nea.upc)
                     )
                INSERT
                INTO walmart_ca.item_preload
                    (asin, upc, product_name, brand)
                SELECT asin, upc, title, brand
                FROM not_exists_upc
                ON CONFLICT DO NOTHING';

        $result = (int)$this->db->run($sql)->rowCount();

        if ($result < 1) {
            Logger::log('Nothing is imported from dump_asin table', __METHOD__);

            return false;
        }

        Logger::log(
            $result . ' items were imported from dump_asin table',
            __METHOD__
        );

        return $result;
    }

    /**
     * Import data from content table, united_scraper schema
     *
     * @return int
     */
    public function importPicScraperContent(): int
    {
        $sql = 'WITH scraper_content_update AS (
                    UPDATE walmart_ca.item_preload AS preload
                        SET short_description = content.description,
                            shipping_weight = content.shipping_weight,
                            gender = content.gender,
                            color = content.color,
                            size = content.size
                        FROM walmart_ca.scraper_content AS content
                        WHERE content.asin = preload.asin
                            AND (content.description IS NOT NULL
                                AND length(content.description) > 0
                                AND
                                 (length(content.description) <>
                                  length(preload.short_description)
                                     OR preload.short_description IS NULL))
                        RETURNING preload.asin),
                     scraper_content_from_only_content_update AS (
                         UPDATE walmart_ca.item_preload AS preload
                             SET short_description = content.description,
                                 shipping_weight = content.shipping_weight,
                                 gender = content.gender,
                                 color = content.color,
                                 size = content.size
                             FROM walmart_ca.scraper_content_from_only_content AS content
                             WHERE content.asin = preload.asin
                                 AND (content.description IS NOT NULL
                                     AND length(content.description) > 0
                                     AND (length(content.description) <>
                                          length(preload.short_description)
                                         OR preload.short_description IS NULL))
                             RETURNING preload.asin),
                     scraper_content_from_search_update AS (
                         UPDATE walmart_ca.item_preload AS preload
                             SET short_description = content.description,
                                 shipping_weight = content.shipping_weight,
                                 gender = content.gender,
                                 color = content.color,
                                 size = content.size
                             FROM walmart_ca.scraper_content_from_search AS content
                             WHERE content.asin = preload.asin
                                 AND (content.description IS NOT NULL
                                     AND length(content.description) > 0
                                     AND (length(content.description) <>
                                          length(preload.short_description)
                                         OR preload.short_description IS NULL))
                             RETURNING preload.asin)
                SELECT (SELECT count(*) FROM scraper_content_update) +
                       (SELECT count(*) FROM scraper_content_from_only_content_update) +
                       (SELECT count(*) FROM scraper_content_from_search_update) AS count';

        $response = $this->db->run($sql)->fetch();

        $result = isset($response['count']) ? (int)$response['count'] : 0;

        //$tables_content = [
        //    'scraper_content',
        //    'scraper_content_from_only_content',
        //    'scraper_content_from_search'
        //];
        //
        //
        //foreach ($tables_content as $table) {
        //    $sql = 'UPDATE walmart_ca.item_preload AS preload
        //
        //        SET short_description = content.description,
        //            shipping_weight   = content.shipping_weight,
        //            gender=content.gender,
        //            color=content.color,
        //            size=content.size
        //
        //        FROM walmart_ca.' . $table . ' as content
        //
        //        WHERE content.asin = preload.asin
        //          -- title
        //          --AND (length(content.title) > 0
        //            --AND (length(content.title) <> length(preload.product_name)
        //                --OR preload.product_name IS NULL))
        //          -- description
        //          AND (content.description IS NOT NULL
        //            AND length(content.description) > 0
        //            AND (length(content.description) <> length(preload.short_description)
        //                OR preload.short_description IS NULL))
        //          -- gender
        //          --AND (content.gender IS NOT NULL
        //            --AND length(content.gender) > 0
        //            --AND (content.gender <> preload.gender OR preload.gender IS NULL))
        //          -- color
        //          --AND (content.color IS NOT NULL
        //            --AND length(content.color) > 0
        //            --AND (content.color <> preload.color
        //                --OR preload.color IS NULL))
        //          -- size
        //          --AND (content.size IS NOT NULL
        //            --AND length(content.size) > 0
        //            --AND (content.size <> preload.size OR preload.size IS NULL))
        //
        //        RETURNING preload.asin';
        //
        //    //$result = Database::request( $sql, __METHOD__, true );
        //    $result += (int)$this->db->run($sql)->rowCount();
        //}

        if ($result > 0) {
            Logger::log(
                $result . ' items were imported from content tables',
                __METHOD__
            );
        } else {
            Logger::log('Nothing is imported from content tables', __METHOD__);
        }

        return $result;
    }

    /**
     * @return int
     */
    public function setCategories(): int
    {
        $categories = new Categories();
        $result     = $categories->setCategories();

        $message = $result > 0
            ? 'For ' . $result
              . ' entries created the names of categories and subcategories'
            : 'Category and subcategory names not created';

        Logger::log($message, __METHOD__);

        return $result;
    }

    /**
     * @return int
     */
    public function setImages(): int
    {
        //$sql = 'SELECT asin
        //        FROM walmart_ca.item_preload
        //        WHERE short_description IS NOT NULL AND image IS NULL';

        $sql = 'SELECT preload.asin
                FROM walmart_ca.item_preload AS preload
                
                         LEFT JOIN walmart_ca.scraper_content AS sc
                                   ON sc.asin = preload.asin
                                       AND sc.has_image = TRUE
                
                         LEFT JOIN walmart_ca.scraper_content_from_only_content AS scfoc
                                   ON scfoc.asin = preload.asin
                                       AND scfoc.has_image = TRUE
                
                         LEFT JOIN walmart_ca.scraper_content_from_search AS scfs
                                   ON scfs.asin = preload.asin
                                       AND scfs.has_image = TRUE
                
                WHERE (sc.asin IS NOT NULL 
                           OR scfoc.asin IS NOT NULL 
                           OR scfs.asin IS NOT NULL)
                  AND preload.short_description IS NOT NULL
                  AND preload.image IS NULL';

        $items = $this->db->run($sql)->fetchAll();

        //$items = Database::request($sql, __METHOD__, true);

        if (!isset($items) || !is_array($items) || count($items) <= 0) {
            return 0;
        }

        //$images_dir = dirname( APP_ROOT ) . '/amazon_scraper_images';

        $values = [];

        foreach ($items as $item) {
            $img_exists = false;

            // http://hadminka.com/B/0/1/F/6/B/Y/T/U/Y/B01F6BYTUY_.jpg
            $split = implode('/', str_split($item['asin']));

            //$path = $images_dir . '/' . $split . '/';

            //if ( !file_exists( $path . $item['asin'] . '_.jpg' ) ) {
            //    continue;
            //}

            // set image pref
            for ($i = 0; $i < 1; ++$i) {
                $img_num = $i === 0 ? '' : $i;

                $image = 'http://185.197.160.85/' . $split . '/' .
                         $item['asin'] . '_' . $img_num . '.jpg';

                $headers = get_headers($image, true);

                if (isset($headers['Content-Type'])
                    && str_contains($headers['Content-Type'], 'image/')) {
                    $img_exists = true;
                    break;
                }
            }

            if (!$img_exists) {
                continue;
            }

            $values[] = '(\'' . $item['asin'] . '\',\'' . $image . '\')';

            //$sql = 'UPDATE walmart_ca.item_preload
            //        SET image = :image
            //        WHERE asin = :asin';
            //
            //$result += $this->db->run(
            //    $sql,
            //    [
            //        ':image' => $image,
            //        ':asin'  => $item['asin']
            //    ]
            //);
            //$result[] = Database::request($sql, __METHOD__);
        }
        //$result = array_filter($result);
        //$count  = count($result);

        if (count($values) > 0) {
            $values_str = implode(',', $values);

            $sql = 'UPDATE walmart_ca.item_preload AS preload
                    SET image = tmp.image
                    FROM (VALUES ' . $values_str . ') AS tmp (asin, image)
                    WHERE tmp.asin = preload.asin;';
        }

        $response = $this->db->run($sql)->rowCount();

        $result = isset($response) && !empty($response) ? (int)$response : 0;

        //var_dump($sql, $response, $result);
        //exit();

        $message = $result > 0
            ? 'To table ' . App::$itemPreloadT . ' added ' .
              $result . ' image addresses'
            : 'To table ' . App::$itemPreloadT .
              ' no images added';

        Logger::log($message, __METHOD__);

        return $result;
    }
}
