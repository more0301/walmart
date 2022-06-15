<?php

declare(strict_types=1);

namespace WB\Helpers\Xml;

use WB\Helpers\Logger;

class XmlValid
{
    public static function exec(string $xml): bool
    {
        libxml_use_internal_errors(true);
        $doc       = simplexml_load_string($xml);
        $xml_error = libxml_get_last_error();

        // debug
        if ($doc === false) {
            $errors = libxml_get_errors();

            foreach ($errors as $error) {
                $message = self::getXmlError($error, $xml);

                Logger::log($message, __METHOD__, 'info', hide_log: true);
            }

            libxml_clear_errors();
        }

        return !isset($xml_error->line);
    }

    public static function getXmlError($error, $xml)
    {
        $return = $xml[$error->line - 1] . "\n";
        $return .= str_repeat('-', $error->column) . "^\n";

        switch ($error->level) {
            case LIBXML_ERR_WARNING:
                $return .= "Warning $error->code: ";
                break;
            case LIBXML_ERR_ERROR:
                $return .= "Error $error->code: ";
                break;
            case LIBXML_ERR_FATAL:
                $return .= "Fatal Error $error->code: ";
                break;
        }

        $return .= trim($error->message) .
                   "\n  Line: $error->line" .
                   "\n  Column: $error->column";

        if ($error->file) {
            $return .= "\n  File: $error->file";
        }

        return "$return\n\n--------------------------------------------\n\n";
    }
}
