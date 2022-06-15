<?php

declare(strict_types=1);

namespace Walmart\controllers;

use Walmart\core\App;
use Walmart\core\Controller;

/**
 * Class ReportController
 * Getting and parsing a general csv report
 *
 * @package Walmart\controllers\report
 */
class ReportController extends Controller
{
    public function start()
    {
        $request_data = $this->setRequestData();

        if (false === $request_data) {
            return false;
        }

        $file_path = $this->saveCsvFile($request_data);

        if (false === $file_path) {
            return false;
        }

        $report_array = $this->readCsvFile($file_path);

        if (false === $report_array) {
            return false;
        }

        $this->saveToReportTable($report_array);

        return true;
    }

    private function setRequestData()
    {
        $request_data = $this->model->setRequestData();

        if (false === $request_data) {
            App::telegramLog('Request data not received');

            return false;
        }

        // debug
        if (true === App::$debug) {
            echo 'Request data: ' . PHP_EOL;
            var_dump($request_data);
            $continue = readline('Next save csv file. Continue? (y/n): ');
            if ($continue === 'n' || $continue !== 'y') {
                return false;
            }
        }

        return $request_data;
    }

    private function saveCsvFile($request_data)
    {
        $file_path = $this->model->saveCsvFileNew($request_data);
        //$file_path = $this->model->saveCsvFile($request_data);

        if ($file_path === '') {
            App::telegramLog('Report file not received');

            return false;
        }

        App::telegramLog('Report file: ' . basename($file_path));

        // debug
        if (true === App::$debug) {
            echo PHP_EOL . 'Report file: ' . $file_path . PHP_EOL;
            $continue = readline('Next read csv file. Continue? (y/n): ');
            if ($continue === 'n' || $continue !== 'y') {
                return false;
            }
        }

        return $file_path;
    }

    private function readCsvFile($file_path)
    {
        $report_array = $this->model->readCsvFile($file_path);

        if (empty($report_array)) {
            App::telegramLog('Error reading report file');

            return false;
        }

        $count_report_lines = count($report_array);

        App::telegramLog('Number of lines: ' . $count_report_lines);

        // debug
        if (true === App::$debug) {
            echo PHP_EOL . 'Report array count: ' . $count_report_lines
                 . PHP_EOL;
            $continue = readline(
                'Next save to report table. Continue? (y/n): '
            );
            if ($continue === 'n' || $continue !== 'y') {
                return false;
            }
        }

        return $report_array;
    }

    private function saveToReportTable($report_array)
    {
        $result_save = $this->model->saveToReportTable($report_array);

        App::telegramLog('Recorded in ' . App::$reportT . ': ' . $result_save);

        // debug
        if (true === App::$debug) {
            echo PHP_EOL . 'The report table contains ' . $result_save
                 . ' items' . PHP_EOL;
        }

        return $result_save;
    }
}
