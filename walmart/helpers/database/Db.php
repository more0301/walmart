<?php

declare(strict_types=1);

namespace Walmart\helpers\database;

use PDO;
use Walmart\core\App;
use Walmart\helpers\Logger;
use PDOException;

class Db extends PDO
{
    public function __construct()
    {
        parent::__construct(
            'pgsql:
            host=' . App::$dbServer . ';
            port=' . App::$dbPort . ';
            dbname=' . App::$dbName,
            App::$dbUser,
            App::$dbPass,
            [
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            ]
        );
    }

    public function run(string $sql, array $args = []): bool
    {
        try {
            $stmt = $this->prepare($sql);

            if (empty($args)) {
                $stmt->execute();
            } else {
                $stmt->execute($args);
            }
        } catch (PDOException $exception) {
            Logger::log(
                $exception->getMessage(),
                'db_error',
                'dev'
            );
        }

        return $stmt ?? false;
    }

    //public function run(string $sql, array $args = [])
    //{
    //    if (empty($args)) {
    //        return $this->query($sql);
    //    }
    //
    //    $stmt = $this->prepare($sql);
    //    $stmt->execute($args);
    //
    //    return $stmt;
    //}
}
