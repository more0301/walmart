<?php

declare(strict_types=1);

namespace Walmart\controllers;

use Walmart\core\App;
use Walmart\core\Controller;

/**
 * Class ItemPreloadController
 *
 * @package Walmart\controllers\preload
 */
class ItemPreloadController extends Controller
{
    /**
     * Collection of data from various sources
     *
     * @return bool
     */
    public function start(): bool
    {
        var_dump('importExternalDump');
        if (!$this->importExternalDump()) {
            return false;
        }

        var_dump('importPicScraperContent');
        if (!$this->importPicScraperContent()) {
            return false;
        }

        var_dump('setCategories');
        if (!$this->setCategories()) {
            return false;
        }

        var_dump('setImages');
        if (!$this->setImages()) {
            return false;
        }

        return true;
    }

    private function importExternalDump(): bool
    {
        $result_import_external = $this->model->importExternalDump();

        App::$telegramLog[] = 'Received from ' . App::$externalDumpAsinT . ': '
                              .
                              (int)$result_import_external;

        // debug
        if (true === App::$debug) {
            echo 'Imported ' . (int)$result_import_external
                 . ' records from table ' .
                 App::$externalDumpAsinT . PHP_EOL;

            $continue = readline(
                'The next step is to import from the ' .
                App::$picScraperContentT . ' table. Continue? (y/n): '
            );

            if ($continue === 'n' || $continue !== 'y') {
                return false;
            }
        }

        return true;
    }

    private function importPicScraperContent(): bool
    {
        $result_import_content = $this->model->importPicScraperContent();

        App::$telegramLog[] = 'Received from ' . App::$picScraperContentT . ': '
                              .
                              $result_import_content;

        // debug
        if (true === App::$debug) {
            echo PHP_EOL . 'Imported ' . $result_import_content .
                 ' records from table ' . App::$picScraperContentT . PHP_EOL;

            $continue = readline(
                'The next step is set categories. Continue? (y/n): '
            );
            if ($continue === 'n' || $continue !== 'y') {
                return false;
            }
        }

        return true;
    }

    private function setCategories(): bool
    {
        $cat_result = $this->model->setCategories();

        App::$telegramLog[] = 'Categories processed: ' . $cat_result;

        // debug
        if (true === App::$debug) {
            echo PHP_EOL . 'Created by ' . $cat_result . ' categories'
                 . PHP_EOL;

            $continue = readline(
                'The next step is set images. Continue? (y/n): '
            );

            if ($continue === 'n' || $continue !== 'y') {
                return false;
            }
        }

        return true;
    }

    private function setImages(): bool
    {
        $img_result = $this->model->setImages();

        App::$telegramLog[] = 'Images processed: ' . $img_result;

        // debug
        if (true === App::$debug) {
            echo PHP_EOL . 'Created by ' . $img_result . ' images' . PHP_EOL;
        }

        return true;
    }
}
