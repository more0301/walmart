<?php

declare(strict_types=1);

namespace WB\Modules\Preload;

use WB\Core\App;
use WB\Helpers\Logger;

class Images
{
    public function setImages(): void
    {
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

        $items = App::$db->run($sql)->fetchAll();

        if (!isset($items) || !is_array($items) || empty($items)) {
            return;
        }

        $values = [];
        $count  = 0;

        foreach ($items as $item) {
            $img_exists = false;

            // http://hadminka.com/B/0/1/F/6/B/Y/T/U/Y/B01F6BYTUY_.jpg
            $split = implode('/', str_split($item['asin']));

            // set image pref
            for ($i = 0; $i < 5; ++$i) {
                $img_num = $i === 0 ? '' : $i;

                $image = 'http://185.197.160.85/' . $split . '/' .
                         $item['asin'] . '_' . $img_num . '.jpg';

                $headers = get_headers($image, true);

                if (isset($headers['Content-Type'])
                    && str_contains($headers['Content-Type'], 'image/')
                    && str_contains($headers[0], '200 OK')) {
                    $img_exists = true;
                    break;
                }
            }

            if (!$img_exists) {
                continue;
            }

            $values[] = '(\'' . $item['asin'] . '\',\'' . $image . '\')';

            if (count($values) > 100) {
                $values_str = implode(',', $values);

                $sql = 'UPDATE walmart_ca.item_preload AS preload
                    SET image = tmp.image
                    FROM (VALUES ' . $values_str . ') AS tmp (asin, image)
                    WHERE tmp.asin = preload.asin';

                $count += (int)App::$db->run($sql)->rowCount();

                $values = [];
            }
        }

        $message = isset($count) && $count > 0 ?
            'To table item_preload added ' . $count . ' image addresses'
            : 'To table item_preload no images added';

        Logger::log($message, __METHOD__, 'info', alert: true);
    }
}
