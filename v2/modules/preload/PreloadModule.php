<?php

declare(strict_types=1);

namespace WB\Modules\Preload;

use WB\Helpers\Logger;
use WB\Core\App;

require_once APP_ROOT . '/modules/preload/Categories.php';
require_once APP_ROOT . '/modules/preload/Images.php';

class PreloadModule
{
    public function run()
    {
        $this->importFromExternalDump();

        $this->importScraperContent();

        (new Categories())->setCategories();

        (new Images())->setImages();
    }

    /**
     * Import data from full_usa and dump_asin, external_usa_bezoxx schema
     *
     * @return void
     */
    private function importFromExternalDump(): void
    {
        $sql = 'INSERT
                INTO walmart_ca.item_preload
                    (asin, upc, product_name, brand)
                SELECT t.asin, t.upc, t.title, t.brand
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
                           AND (full_usa.brand IS NOT NULL
                             AND length(full_usa.brand) > 0)
                
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
                           AND (dump_asin.brand IS NOT NULL
                             AND length(dump_asin.brand) > 0))
                         AS t
                         LEFT JOIN walmart_ca.item_preload
                                   ON item_preload.upc = t.upc
                WHERE item_preload.upc IS NULL
                                ON CONFLICT DO NOTHING';

        $result = (int)App::$db->run($sql)->rowCount();

        if ($result === 0) {
            $message = 'Nothing is imported from dump_asin table';
        } else {
            $message = $result . ' items were imported from dump_asin table';
        }

        Logger::log($message, __METHOD__, 'info', alert: true);
    }

    private function importScraperContent(): void
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

        $response = (int)App::$db->run($sql)->fetchColumn();

        if ($response > 0) {
            $message = $response . ' items were imported from content tables';
        } else {
            $message = 'Nothing is imported from content tables';
        }

        Logger::log($message, __METHOD__, 'info', alert: true);
    }
}
