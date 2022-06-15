<?php

declare(strict_types=1);

namespace WB\Helpers;

use Exception;

class JsonToArray
{
    public static function encode(string $json)
    {
        try {
            $data = json_decode($json, true);

            switch (json_last_error()) {
                case JSON_ERROR_NONE:
                    Logger::log(
                        'Json data successfully converted',
                        __METHOD__,
                        'info'
                    );

                    return $data;

                case JSON_ERROR_DEPTH:
                    throw new Exception('Maximum stack depth reached');

                case JSON_ERROR_STATE_MISMATCH:
                    throw new Exception('Incorrect bits or mode mismatch');

                case JSON_ERROR_CTRL_CHAR:
                    throw new Exception('Invalid control character');

                case JSON_ERROR_SYNTAX:
                    throw new Exception('Syntax error, invalid JSON');

                case JSON_ERROR_UTF8:
                    throw new Exception(
                        'Incorrect UTF-8 characters, possibly incorrectly encoded'
                    );

                default:
                    throw new Exception('Unknown error');
            }
        } catch (\Throwable $e) {
            Logger::log($e->getMessage(), __METHOD__, 'info');

            return false;
        }
    }
}
