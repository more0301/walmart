<?php

declare(strict_types=1);

namespace WB\Helpers\Xml;

use Throwable;
use WB\Helpers\JsonToArray;
use WB\Helpers\Logger;

require_once APP_ROOT . '/helpers/JsonToArray.php';

class XmlRead
{
    public static function exec(string $data, string $ns = null)
    {
        try {
            $xml = simplexml_load_string($data);
        } catch (Throwable $e) {
            Logger::log(
                'Error reading response. Original data: ' . $data,
                __METHOD__,
                'info'
            );

            return false;
        }

        if (isset($ns) && false !== stripos($data, $ns)) {
            $namespaces = $xml->getNameSpaces(true);

            try {
                $current_ns = $namespaces[$ns];
            } catch (Throwable $e) {
                Logger::log($e->getMessage(), __METHOD__, 'info');

                return JsonToArray::encode(json_encode($data));
            }

            return JsonToArray::encode(
                json_encode($xml->children($current_ns))
            );
        }

        return JsonToArray::encode(json_encode($xml));
    }
}
