<?php

declare(strict_types=1);

namespace WB\Core;

use WB\config\Access;
use PDO;
use PDOException;
use WB\Helpers\Logger;

require_once APP_ROOT . '/config/Access.php';

class Db extends PDO
{
    public function __construct()
    {
        parent::__construct(
            'pgsql:
            host=' . Access::$dbServer . ';
            port=' . Access::$dbPort . ';
            dbname=' . Access::$dbName,
            Access::$dbUser,
            Access::$dbPass,
            [
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            ]
        );
    }

    public function run(string $sql, array $args = []): bool|\PDOStatement
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
                'database',
                'error'
            );
        }

        return $stmt ?? false;
    }
}
