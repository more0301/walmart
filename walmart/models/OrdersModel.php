<?php
declare( strict_types=1 );

namespace Walmart\models;

use Walmart\core\App;
use Walmart\helpers\{database\Db, Logger, request\Curl, request\RequestParameters, xml\XmlRead};

class OrdersModel
{
    private Db $db;

    public function __construct()
    {
        $this->db = new Db();
    }

    public function setRequestParameters( string $status,
                                          string $next_cursor = '' ) :array
    {
        $interval = 'P1M';
        //$interval = $status == 'Acknowledged' ? 'P2W' : 'P1M';

        $data = [
            'order_date'   => App::getDateInterval( $interval, 'sub' ),
            'order_status' => $status,
            'order_limit'  => 50,
            'next_cursor'  => $next_cursor
        ];

        $parameters = RequestParameters::exec( 'get_orders_u', $data );

        if ( false === $parameters ) {
            Logger::log( 'Error creating request data', __METHOD__ );

            return [];
        }

        return $parameters;
    }

    public function getOrders( array $request_parameters ) :array
    {
        $response = Curl::getRequest( $request_parameters['url'],
            $request_parameters['timestamp'], $request_parameters['sign'] );

        if ( true === App::$debug ) {
            file_put_contents(
                __DIR__ . '/' . $request_parameters['timestamp'] . '.txt',
                $response
            );
        }

        $response = preg_replace(
            '/ns\d+:/ui', '', $response );

        $orders = XmlRead::exec( $response );

        if ( isset( $orders['meta']['totalCount'] ) &&
            (int)( $orders['meta']['totalCount'] ) > 0 ) {
            return $orders;
        }

        return [];
    }

    public function getAdditionalOrdersData( array $order_data ) :array
    {
        $data = [];

        foreach ( $order_data as $item ) {

            if ( !isset( $item['sku'] ) ) {
                continue;
            }

            $key = $item['sku'];

            $sql_report   = 'SELECT * FROM walmart_ca.report WHERE sku=?';
            $data[ $key ] = $this->db
                ->run( $sql_report, [ $item['sku'] ] )->fetch();

            $sql_preload  = 'SELECT * FROM walmart_ca.item_preload WHERE upc=?';
            $data_preload = $this->db
                ->run( $sql_preload, [ $data[ $key ]['upc'] ] )->fetch();

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
            $data[ $key ]['order_date'] = date( 'Y-m-d H:i:s',
                strtotime( $item['order_date'] ) );

            $data[ $key ]['email'] = $item['customer_email'];

            $weight_and_dimensions = $this
                ->getWeightAndDimensions( $data_preload['asin'] );

            $data[ $key ]['weight_and_dimensions'] = isset( $weight_and_dimensions )
            && !empty( $weight_and_dimensions ) ?
                $weight_and_dimensions : null;

            $data[ $key ] = array_merge( $data[ $key ], $item );
        }

        return $data;
    }

    public function parseData( $data ) :array
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

        // next cursor
        $order_array['next_cursor'] = isset( $data['meta']['nextCursor'] ) ?
            $data['meta']['nextCursor'] : '';

        return $order_array;
    }

    // order id, order date
    public function getOrderHeader( $data ) :array
    {
        return [
            isset( $data['purchaseOrderId'] ) ? $data['purchaseOrderId'] : '',
            isset( $data['orderDate'] ) ? $data['orderDate'] : '',
            isset( $data['customerEmailId'] ) ? $data['customerEmailId'] : ''
        ];
    }

    public function getShippingInfo( $data ) :array
    {
        if ( !isset( $data['shippingInfo'] )
            || !isset( $data['shippingInfo']['postalAddress'] ) ) {
            return [];
        }

        $info   = $data['shippingInfo'];
        $postal = $info['postalAddress'];

        $result['phone']                   = $info['phone'];
        $result['estimated_delivery_date'] = $info['estimatedDeliveryDate'];
        $result['estimated_ship_date']     = $info['estimatedShipDate'];
        $result['method_code']             = $info['methodCode'];

        $result['postal']['name']     = $postal['name'];
        $result['postal']['address1'] = $postal['address1'];
        if ( isset( $postal['address2'] ) ) {
            $result['postal']['address2'] = $postal['address2'];
        }
        $result['postal']['city']         = $postal['city'];
        $result['postal']['state']        = $postal['state'];
        $result['postal']['postal_code']  = $postal['postalCode'];
        $result['postal']['country']      = $postal['country'];
        $result['postal']['address_type'] = $postal['addressType'];

        return $result;
    }

    public function getOrderSkuUpcProductName( $data ) :array
    {
        return [
            $data['item']['sku'],
            substr( $data['item']['sku'],
                strrpos( $data['item']['sku'], '-' ) + 1 ),
            $data['item']['productName']
        ];
    }

    public function getPrice( $data ) :float
    {
        if ( isset( $data['charges']['charge'][0] ) ) {
            return (float)$data['charges']['charge'][0]['chargeAmount']['amount'];
        }
        elseif ( isset( $data['charges']['charge']['chargeAmount'] ) ) {
            return (float)$data['charges']['charge']['chargeAmount']['amount'];
        }

        return 0;
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

                [ $order_id, $order_date, $customer_email ] =
                    $this->getOrderHeader( $orders );
                $result[ $sku ]['order_id']       = $order_id;
                $result[ $sku ]['order_date']     = $order_date;
                $result[ $sku ]['customer_email'] = $customer_email;

                $result[ $sku ]['price'] = $this->getPrice( $line_item );

                $result[ $sku ]['shipping_info'] =
                    $this->getShippingInfo( $orders );
            }
        }
        // one line
        else {
            [ $sku, $upc, $product_name ] = $this
                ->getOrderSkuUpcProductName( $order_line );

            $result[ $sku ]['sku']          = $sku;
            $result[ $sku ]['upc']          = $upc;
            $result[ $sku ]['product_name'] = $product_name;

            [ $order_id, $order_date, $customer_email ] =
                $this->getOrderHeader( $orders );
            $result[ $sku ]['order_id']       = $order_id;
            $result[ $sku ]['order_date']     = $order_date;
            $result[ $sku ]['customer_email'] = $customer_email;

            $result[ $sku ]['price'] = $this->getPrice( $order_line );

            $result[ $sku ]['shipping_info'] =
                $this->getShippingInfo( $orders );
        }

        return $result;
    }

    public function getWeightAndDimensions( string $asin ) :array
    {
        $sql = 'SELECT item_weight, dimensions
                FROM walmart_ca.scraper_content_from_search
                WHERE asin=? LIMIT 1';

        $data = $this->db->run( $sql, [ $asin ] )->fetch();

        if ( !isset( $data ) || false === $data ) {
            return [];
        }

        // weight
        $weight      = '';
        $weight_unit = '';

        if ( isset( $data['item_weight'] ) && !empty( $data['item_weight'] ) ) {

            $weight = filter_var(
                $data['item_weight'],
                FILTER_SANITIZE_NUMBER_FLOAT,
                FILTER_FLAG_ALLOW_FRACTION
            );

            if ( false !== stripos( $data['item_weight'], 'pounds' ) ) {
                $weight_unit = 'LB';
            }
        }

        // dimensions
        $dimensions      = '';
        $dimensions_unit = '';

        if ( isset( $data['dimensions'] ) && !empty( $data['dimensions'] ) ) {
            if ( preg_match(
                    '/^.*?([0-9.]+[\s\t]?[xX][\s\t]?[0-9.]+[\s\t]?[xX][\s\t]?[0-9.]+)/ui',
                    $data['dimensions'],
                    $match
                ) === 1 ) {
                $dimensions = $match[1];
            }

            if ( false !== stripos( $data['dimensions'], 'inches' ) ) {
                $dimensions_unit = 'IN';
            }
        }

        return array_filter( [
            'weight'          => $weight,
            'weight_unit'     => $weight_unit,
            'dimensions'      => $dimensions,
            'dimensions_unit' => $dimensions_unit
        ] );
    }

    //public function cleanOrderTable()
    //{
    //    $sql = 'TRUNCATE TABLE walmart_ca.orders_walmart_ca';
    //
    //    $this->db->run( $sql );
    //}

    public function addToDb( array $data, string $status,
                             bool $first_iteration ) :bool
    {
        foreach ( $data as $item ) {
            $new_data[] = array_filter(
                $item,
                fn( $val, $key ) => $key !== 'date_create',
                ARRAY_FILTER_USE_BOTH
            );
        }

        if ( empty( $new_data ) ) {
            return false;
        }

        // delete only on the first iteration
        if ( true === $first_iteration ) {
            $sql_del = 'DELETE FROM walmart_ca.orders_walmart_ca 
                        WHERE shop_id=?';
            $this->db->run( $sql_del, [ App::$shopId ] );
        }

        $insert_values = str_repeat( '?,', 57 ) . '?';

        $sql_insert = 'INSERT INTO walmart_ca.orders_walmart_ca (shop_id,order_id,order_date,partner_id,sku,upc,asin,product_name,product_category,price,currency,publish_status,status_change_reason,lifecycle_status,inventory_count,ship_methods,wpid,item_id,gtin,primary_image_url,shelf_name,primary_cat_path,offer_start_date,offer_end_date,item_creation_date,item_last_updated,item_page_url,reviews_count,average_rating,searchable,image,shipping_weight,tax_code,short_description,subcategory,category,gender,color,size,brand,status,phone,estimated_delivery_date,estimated_ship_date,method_code,postal_name,postal_address1,postal_address2,postal_city,postal_state,postal_code,postal_country,postal_address_type,weight,weight_unit,dimensions,dimensions_unit,email) 
VALUES (' . $insert_values . ') 
ON CONFLICT DO NOTHING';

        foreach ( $new_data as $_item ) {

            $this->db->run(
                $sql_insert,
                $this->adapterInsertValues( $_item, $status )
            );
        }

        return true;
    }

    private function adapterInsertValues( $item, $status ) :array
    {
        return [
            $item['shop_id'],
            $item['order_id'],
            $item['order_date'],
            $item['partner_id'],
            $item['sku'],
            $item['upc'],
            $item['asin'],
            $item['product_name'],
            $item['product_category'],
            $item['price'],
            $item['currency'],
            $item['publish_status'],
            $item['status_change_reason'],
            $item['lifecycle_status'],
            $item['inventory_count'],
            $item['ship_methods'],
            $item['wpid'],
            $item['item_id'],
            $item['gtin'],
            $item['primary_image_url'],
            $item['shelf_name'],
            $item['primary_cat_path'],
            $item['offer_start_date'],
            $item['offer_end_date'],
            $item['item_creation_date'],
            $item['item_last_updated'],
            $item['item_page_url'],
            $item['reviews_count'],
            $item['average_rating'],
            $item['searchable'],
            $item['image'],
            $item['shipping_weight'],
            $item['tax_code'],
            $item['short_description'],
            $item['subcategory'],
            $item['category'],
            $item['gender'],
            $item['color'],
            $item['size'],
            $item['brand'],
            $status,
            $item['shipping_info']['phone'],
            $item['shipping_info']['estimated_delivery_date'],
            $item['shipping_info']['estimated_ship_date'],
            $item['shipping_info']['method_code'],
            $item['shipping_info']['postal']['name'],
            $item['shipping_info']['postal']['address1'],
            isset( $item['shipping_info']['postal']['address2'] ) ?
                $item['shipping_info']['postal']['address2'] : null,
            $item['shipping_info']['postal']['city'],
            $item['shipping_info']['postal']['state'],
            $item['shipping_info']['postal']['postal_code'],
            $item['shipping_info']['postal']['country'],
            $item['shipping_info']['postal']['address_type'],

            isset( $item['weight_and_dimensions']['weight'] ) ?
                $item['weight_and_dimensions']['weight'] : null,
            isset( $item['weight_and_dimensions']['weight_unit'] ) ?
                $item['weight_and_dimensions']['weight_unit'] : null,
            isset( $item['weight_and_dimensions']['dimensions'] ) ?
                $item['weight_and_dimensions']['dimensions'] : null,
            isset( $item['weight_and_dimensions']['dimensions_unit'] ) ?
                $item['weight_and_dimensions']['dimensions_unit'] : null,
            isset( $item['email'] ) ? $item['email'] : null
        ];
    }
}
