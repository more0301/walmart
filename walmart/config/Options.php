<?php
declare( strict_types=1 );

namespace Walmart\config;

/**
 * Trait Options
 *
 * Static options from options table. But are triggered in a loop when
 * the current shop_id number is known
 *
 * @package Walmart\config
 */
trait Options
{
    public static int   $maxItemSubmit;
    public static int   $maxInventorySubmit;
    public static int   $maxPriceSubmit;

    public static int   $maxInventoryQuantity;
    public static int   $minInventoryQuantity;

    public static int   $inventoryFulfillment;

    public static int   $inventoryRequestProcessingTime;
    public static int   $itemRequestProcessingTime;
    public static int   $priceRequestProcessingTime;

    public static float $markup;
    public static float $cadRate;
    public static float $minPrice;
    public static float $maxPrice;

    public static string $consumerId;
    public static string $consumerChannelType;
    public static string $privateKey;

    // manual submit additional data (gender, color, size) + current price
    //    public static bool $manualSubmit = false;

    // required or not gender, color, size
    public static bool $requiredGCS = true;
}