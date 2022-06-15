<?php
declare( strict_types=1 );

namespace Walmart\models;

use Walmart\core\App;
use Walmart\helpers\database\Database;
use Walmart\helpers\database\Db;
use Walmart\helpers\Logger;
use Walmart\helpers\request\Curl;
use Walmart\helpers\request\RequestParameters;
use Walmart\helpers\xml\XmlRead;
use Walmart\views\MainLayout;
use Walmart\views\orders_list\OrdersTable;

class AsinByOrderModel
{
    /*    public function setRequestParameters()
        {
            $parameters = RequestParameters::exec( 'get_orders' );

            if ( false === $parameters ) {
                Logger::log( 'Error creating request data', __METHOD__ );

                return false;
            }

            return $parameters;
        }*/

    public function setRequestParameters()
    {
        $data = [
            'order_date'   => App::getDateInterval( 'P1W', 'sub' ),
            'order_status' => 'Created',
            'order_limit'  => 200
        ];

        $parameters = RequestParameters::exec( 'get_orders_u', $data );

        if ( false === $parameters ) {
            Logger::log( 'Error creating request data', __METHOD__ );

            return false;
        }

        return $parameters;
    }

    public function getOrders( array $request_parameters )
    {
        $response = Curl::getRequest( $request_parameters['url'],
            $request_parameters['timestamp'], $request_parameters['sign'] );

        $orders = XmlRead::exec( $response, 'ns3' );

        if ( isset( $orders['meta']['totalCount'] ) &&
            (int)( $orders['meta']['totalCount'] ) > 0 ) {
            return $orders;
        }

        return false;
    }

    /*    public function getSkuList( array $orders )
        {
            $data = [];

            // one item
            if ( isset( $orders['elements']['order']['orderLines']['orderLine']['item'] ) ) {
                $order = $orders['elements']['order'];

                $data[] = [
                    'order_id'   => $order['purchaseOrderId'],
                    'order_date' => $order['orderDate'],
                    'sku'        => $order['orderLines']['orderLine']['item']['sku'],
                    'upc'        => substr( $order['orderLines']['orderLine']['item']['sku'], strrpos( $order['orderLines']['orderLine']['item']['sku'], '-' ) + 1 ),
                    'price'      => $order['orderLines']['orderLine']['charges']['charge'][0]['chargeAmount']['amount']
                ];
            }
            else {
                foreach ( $orders['elements']['order'] as $order ) {

                    if ( isset( $order['orderLines']['orderLine']['item'] ) ) {
                        $data[] = [
                            'order_id'   => $order['purchaseOrderId'],
                            'order_date' => $order['orderDate'],
                            'sku'        => $order['orderLines']['orderLine']['item']['sku'],
                            'upc'        => substr( $order['orderLines']['orderLine']['item']['sku'], strrpos( $order['orderLines']['orderLine']['item']['sku'], '-' ) + 1 ),
                            'price'      => $order['orderLines']['orderLine']['charges']['charge'][0]['chargeAmount']['amount']
                        ];
                    } // when multiple items in one order
                    else {
                        foreach ( $order['orderLines']['orderLine'] as $orderLine ) {
                            $data[] = [
                                'order_id'   => $order['purchaseOrderId'],
                                'order_date' => $order['orderDate'],
                                'sku'        => $orderLine['item']['sku'],
                                'upc'        => substr( $orderLine['item']['sku'], strrpos( $orderLine['item']['sku'], '-' ) + 1 ),
                                'price'      => $orderLine['charges']['charge'][0]['chargeAmount']['amount']
                            ];
                        }
                    }
                }
            }

            return $data;
        }

        public function getAllData( array $sku_list )
        {
            $data = [];

            foreach ( $sku_list as $item ) {

                if ( !isset( $item['sku'] ) ) {
                    continue;
                }

                $key = $item['sku'];

                $sql_adding   = 'SELECT * FROM walmart_ca.report
                                    WHERE sku=\'' . $item['sku'] . '\'';
                $data[ $key ] = Database::request( $sql_adding, __METHOD__ );

                $sql_preload                       = 'SELECT * FROM walmart_ca.item_preload
                                    WHERE upc=\'' . $data[ $key ]['upc'] . '\'';
                $data_preload                      = Database::request( $sql_preload, __METHOD__ );
                $data[ $key ]['asin']              = $data_preload['asin'];
                $data[ $key ]['image']             = $data_preload['image'];
                $data[ $key ]['shipping_weight']   = $data_preload['shipping_weight'];
                $data[ $key ]['tax_code']          = $data_preload['tax_code'];
                $data[ $key ]['short_description'] = $data_preload['short_description'];
                $data[ $key ]['subcategory']       = $data_preload['subcategory'];
                $data[ $key ]['category']          = $data_preload['category'];
                $data[ $key ]['gender']            = $data_preload['gender'];
                $data[ $key ]['color']             = $data_preload['color'];
                $data[ $key ]['size']              = $data_preload['size'];
                $data[ $key ]['brand']             = ucfirst( $data_preload['brand'] );

                $data[ $key ]['price'] = $item['price'];

                $data[ $key ]['order_id']   = $item['order_id'];
                $data[ $key ]['order_date'] = date( 'Y-m-d H:i:s', strtotime( $item['order_date'] ) );
            }

            return $data;
        }*/

    public function getAdditionalOrdersData( array $order_data, Db $db )
    {
        $data = [];

        foreach ( $order_data as $item ) {

            if ( !isset( $item['sku'] ) ) {
                continue;
            }

            $key = $item['sku'];

            $sql_report   = 'SELECT * FROM walmart_ca.report WHERE sku=?';
            $data[ $key ] = $db->run( $sql_report, [ $item['sku'] ] )->fetch();

            $sql_preload  = 'SELECT * FROM walmart_ca.item_preload WHERE upc=?';
            $data_preload = $db->run( $sql_preload, [ $data[ $key ]['upc'] ] )->fetch();

            $data[ $key ]['asin']              = $data_preload['asin'];
            $data[ $key ]['image']             = $data_preload['image'];
            $data[ $key ]['shipping_weight']   = (float)$data_preload['shipping_weight'];
            $data[ $key ]['tax_code']          = (float)$data_preload['tax_code'];
            $data[ $key ]['short_description'] = $data_preload['short_description'];
            $data[ $key ]['subcategory']       = $data_preload['subcategory'];
            $data[ $key ]['category']          = $data_preload['category'];
            $data[ $key ]['gender']            = $data_preload['gender'];
            $data[ $key ]['color']             = $data_preload['color'];
            $data[ $key ]['size']              = $data_preload['size'];
            $data[ $key ]['brand']             = ucfirst( $data_preload['brand'] );

            $data[ $key ]['price'] = $item['price'];

            $data[ $key ]['order_id']   = $item['order_id'];
            $data[ $key ]['order_date'] = date( 'Y-m-d H:i:s', strtotime( $item['order_date'] ) );
        }

        return $data;
    }

    public function parseData( $data )
    {
        $result = [];

        $orders = $data['elements']['order'];

        // multiple orders
        if ( isset( $orders[0] ) ) {

            foreach ( $orders as $order ) {
                $order_line = $order['orderLines']['orderLine'];

                $result[] = $this->getOrderLineData( $order, $order_line );
            }
        }
        // one order
        else {
            $order_line = $orders['orderLines']['orderLine'];

            $result[] = $this->getOrderLineData( $orders, $order_line );
        }

        $order_array = [];

        foreach ( $result as $item ) {
            if ( count( $item ) > 1 ) {
                foreach ( $item as $i ) {
                    $order_array[] = $i;
                }
            }
            else {
                foreach ( $item as $ii ) {
                    $order_array[] = $ii;
                }
            }
        }

        return $order_array;
    }

    public function getOrderLineData( $orders, $order_line )
    {
        $result = [];

        if ( isset( $order_line[0] ) ) {

            foreach ( $order_line as $line_item ) {
                [ $sku, $upc, $product_name ] = $this
                    ->getOrderSkuUpcProductName( $line_item );

                $result[ $sku ]['sku']          = $sku;
                $result[ $sku ]['upc']          = $upc;
                $result[ $sku ]['product_name'] = $product_name;

                [ $order_id, $order_date ] = $this->getOrderHeader( $orders );
                $result[ $sku ]['order_id']   = $order_id;
                $result[ $sku ]['order_date'] = $order_date;

                $result[ $sku ]['price'] = $this->getPrice( $line_item );
            }
        }
        // one line
        else {
            [ $sku, $upc, $product_name ] = $this
                ->getOrderSkuUpcProductName( $order_line );

            $result[ $sku ]['sku']          = $sku;
            $result[ $sku ]['upc']          = $upc;
            $result[ $sku ]['product_name'] = $product_name;

            [ $order_id, $order_date ] = $this->getOrderHeader( $orders );
            $result[ $sku ]['order_id']   = $order_id;
            $result[ $sku ]['order_date'] = $order_date;

            $result[ $sku ]['price'] = $this->getPrice( $order_line );
        }

        return $result;
    }

    public function getOrderHeader( $data )
    {
        return [ $data['purchaseOrderId'], $data['orderDate'] ];
    }

    public function getOrderSkuUpcProductName( $data )
    {
        return [
            $data['item']['sku'],
            substr( $data['item']['sku'],
                strrpos( $data['item']['sku'], '-' ) + 1 ),
            $data['item']['productName']
        ];
    }

    public function getPrice( $data )
    {
        if ( isset( $data['charges']['charge'][0] ) ) {
            return $data['charges']['charge'][0]['chargeAmount']['amount'];
        }
        elseif ( isset( $data['charges']['charge']['chargeAmount'] ) ) {
            return $data['charges']['charge']['chargeAmount']['amount'];
        }

        return 0;
    }

    public function tableOutput( array $all_data )
    {
        $data['meta_title']       = 'Orders list';
        $data['meta_description'] = 'Orders list';
        $data['page_content']     = OrdersTable::getOrdersTable( $all_data );

        echo MainLayout::render( $data );
    }
}