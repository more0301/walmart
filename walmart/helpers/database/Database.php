<?php
declare( strict_types=1 );

namespace Walmart\helpers\database;

use PDO;
use PDOException;
use Walmart\core\App;
use Walmart\helpers\Logger;

/**
 * Class Database
 *
 * @package Walmart\helpers\database
 */
class Database
{
    /**
     * @return bool|PDO
     */
    public static function setConnect()
    {
        try {
            $dbh = new PDO( 'pgsql:
            host=' . App::$dbServer . ';
            port=' . App::$dbPort . ';
            dbname=' . App::$dbName,
                App::$dbUser,
                App::$dbPass );
        }
        catch ( PDOException $e ) {
            Logger::log( 'Error!: ' . $e->getMessage(), __METHOD__, 'dev' );

            return false;
        }

        Logger::log( 'Database connection established', __METHOD__, 'dev' );

        return $dbh;
    }

    /**
     * @param $errorInfo
     * @param $method
     */
    private static function logStmt( $errorInfo, $method ) :void
    {
        if ( $errorInfo[0] === '00000' ) {
            Logger::log( 'Successful PDOStatement operation', $method, 'dev' );
        }
        else {
            Logger::log( implode( ';', $errorInfo ), $method );
        }
    }

    /**
     * @param string $sql
     * @param string $method
     * @param bool   $fetch_all : for select
     *
     * @return array|mixed
     */
    public static function request( string $sql, string $method, bool $fetch_all = false )
    {
        $stmt = App::$dbh->prepare( $sql );
        $stmt->execute();

        // select
        if ( true === $fetch_all ) {
            $result = $stmt->fetchAll( PDO::FETCH_ASSOC );
        }
        // select
        elseif ( false !== strpos( $sql, 'SELECT ' ) ) {
            $result = $stmt->fetch( PDO::FETCH_ASSOC );
        }
        // insert, update, delete
        else {
            $result = $stmt->rowCount();
        }

        self::logStmt( $stmt->errorInfo(), $method );

        $stmt = null;

        return $result;
    }

    public static function saveToDb( $sql, $table, $method )
    {
        $result = Database::request( $sql, $method, true );

        $count = count( $result );
        if ( false === $result || $count <= 0 ) {
            Logger::log( 'There are no records to copy to ' . $table . ' table', $method );

            return 0;
        }

        Logger::log( $count . ' items recorded in table ' . $table, $method, 'dev' );

        return $count;
    }
}