<?php
declare( strict_types=1 );

namespace Walmart\helpers\xml\templates;

/**
 * Class ShippingTemplate
 *
 * @package Walmart\helpers\xml\templates
 */
class ShippingTemplate
{
    /**
     *
     * @param array $data
     *
     * @return string
     */
    public static function getFeed( array $data ) :string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<orderShipment xmlns:ns2="http://walmart.com/mp/v3/orders" xmlns:ns3="http://walmart.com/">
  <orderLines>
    <orderLine>
      <lineNumber>1</lineNumber>
      <orderLineStatuses>
        <orderLineStatus>
          <status>' . $data['status'] . '</status>
          <statusQuantity>
            <unitOfMeasurement>Each</unitOfMeasurement>
            <amount>1</amount>
          </statusQuantity>
          <trackingInfo>
            <shipDateTime>' . $data['ship_date_time'] . '</shipDateTime>
            <carrierName>
              <otherCarrier>' . $data['carrier'] . '</otherCarrier>
            </carrierName>
            <methodCode>Standard</methodCode>
            <trackingNumber>' . $data['tracking_number'] . '</trackingNumber>
            <trackingURL>' . $data['tracking_url'] . '</trackingURL>
          </trackingInfo>
        </orderLineStatus>
      </orderLineStatuses>
    </orderLine>
  </orderLines>
</orderShipment>';
    }
}