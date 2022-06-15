<?php

declare(strict_types=1);

namespace Walmart\controllers;

use Walmart\core\App;
use Walmart\models\OrdersModel;

class OrdersController
{
    public array $statuses
        = [
            'Created',
            'Acknowledged',
            'Shipped',
            'Cancelled'
        ];

    public OrdersModel $model;

    public function __construct()
    {
        $this->model = new OrdersModel();
    }

    public function start(): void
    {
        // truncate table, first iteration
        //if ( App::shopIds()[ array_key_first( App::shopIds() ) ] === App::$shopId ) {
        //    $this->model->cleanOrderTable();
        //}

        $next_cursor = '';
        $first_iteration = true;

        for ($i = 0; $i < count($this->statuses); ++$i) {
            if ($i > 0 && !empty($next_cursor)) {
                --$i;
            }

            $status = $this->statuses[$i];

            $request_parameters = $this->model->setRequestParameters(
                $status,
                $next_cursor
            );

            if (empty($request_parameters)) {
                echo 'Request parameters not received';
                continue;
            }

            $orders = $this->model->getOrders($request_parameters);
            if (empty($orders)) {
                echo 'The list of orders is empty.';
                continue;
            }

            $data = $this->model->parseData($orders);
            if (empty($data)) {
                echo 'Data not received';
                continue;
            }

            $next_cursor = $data['next_cursor'] ?? '';

            $orders_data = $this->model->getAdditionalOrdersData($data);

            if (empty($orders_data)) {
                echo 'Data not received';
                continue;
            }

            $this->model->addToDb($orders_data, $status, $first_iteration);

            $first_iteration = false;
        }
    }
}
