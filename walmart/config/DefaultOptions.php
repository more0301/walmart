<?php
declare( strict_types=1 );

namespace Walmart\config;

trait DefaultOptions
{
    /**
     * Length upc. Walmart does not accept upc not equal to 12 characters
     *
     * @var int
     */
    public static int    $upcLength;

    /**
     * Walmart xml namespace
     *
     * @var string
     */
    public static string $walmartXmlNs;

    /**
     * Method for checking image sizes
     *
     * @var string
     */
    public static string $imagesizeFunc;

    /**
     * The number of sending attempts, after which the record is copied to the black table
     *
     * @var int
     */
    public static int    $inventoryMaxAttemptsResend;

    /**
     * The number of sending attempts, after which the record is copied to the black table
     *
     * @var int
     */
    public static int    $priceMaxAttemptsResend;

    /**
     * The number of sending attempts, after which the record is copied to the black table
     *
     * @var int
     */
    public static int    $itemMaxAttemptsResend;
    public static array  $asinZeroPosition;
    public static array  $asinReverse;
    public static int    $skuLength;
    public static string $skuSourceTo;
    public static string $skuSourceFrom;
    public static string $skuCountry;
    public static float  $primaryPrice; // price for first submit item
    public static int    $reportInterval;
    public static bool   $devMode = false; // on-off developer mode
}