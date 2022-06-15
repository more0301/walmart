<?php

declare(strict_types=1);

namespace WB\Modules\TransportCreate;

use WB\Core\App;

abstract class TransportCreateAbstract
{
    protected int $firstShopId;
    protected int $lastShopId;

    public function __construct()
    {
        $this->setFirstShopId();
        $this->setLastShopId();
    }

    public function makeFile(string $select, string $file)
    {
        $record_type = '>';

        if (false !== stripos($file, 'available_brands_walmart_ca')) {
            $record_type = '>>';
        } elseif (false !== stripos($file, 'rule_brands_walmart_ca')) {
            $record_type = '>>';
        }

        $sql = 'COPY (' . $select . ') TO PROGRAM \'gzip ' . $record_type . ' '
               . $file . ' && chmod 0777 ' . $file . '\'';

        App::$db->run($sql);
    }

    private function setFirstShopId(): void
    {
        $this->firstShopId = (int)App::$options['shops'][array_key_first(
            App::$options['shops']
        )]['shop_id'];
    }

    private function setLastShopId(): void
    {
        $this->lastShopId = (int)App::$options['shops'][array_key_last(
            App::$options['shops']
        )]['shop_id'];
    }
}
