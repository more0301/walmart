<?php

declare(strict_types=1);

namespace Walmart\config;

trait Access
{
    public static string $dbServer = 'localhost';
    public static string $dbPort   = '5432';
    public static string $dbName   = 'walmart';
    public static string $dbSchema = '';
    public static string $dbUser   = 'postgres';
    public static string $dbPass   = '111111';

    // current server
    public static string $user = 'postgres';
    public static int    $port = 5432;
    public static string $pass = '111111';
    public static string $host = 'localhost';

    // scraper
    public static string $passScraper = '';
    public static int    $portScraper = 5432;
    public static string $userScraper = '';
    public static string $hostScraper = '';
}
