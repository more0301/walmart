<?php

declare(strict_types=1);

namespace WB\Modules\TransportCreate;

use WB\Core\App;

require_once APP_ROOT . '/modules/transport_create/DumpOrders.php';

class TransportCreateModule
{
    public function run(): void
    {
        match (App::$clArgument2) {
            'order' => (new DumpOrders())->createDump()
        };
    }
}
