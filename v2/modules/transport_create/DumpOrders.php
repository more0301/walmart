<?php

declare(strict_types=1);

namespace WB\Modules\TransportCreate;

use WB\Core\App;
use WB\Modules\Order\OrderModule;

require_once APP_ROOT . '/modules/order/OrderModule.php';
require_once APP_ROOT . '/modules/transport_create/TransportCreateAbstract.php';

class DumpOrders extends TransportCreateAbstract
{
    private string $dumpDir = APP_ROOT . '/dumps/export_orders';

    public function createDump(): void
    {
        (new OrderModule())->run();

        // truncate only first iteration
        if ($this->firstShopId === App::$shopId) {
            exec('cd ' . $this->dumpDir . '; rm *.gz');
        }

        // last iteration
        if ($this->lastShopId === App::$shopId) {
            $this->makeFile(
                'SELECT * FROM walmart_ca.orders_walmart_ca',
                $this->dumpDir . '/orders_walmart_ca.gz'
            );
        }
    }
}
