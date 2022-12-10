<?php

namespace Jobsys\Survey\Utils;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

class Db
{

    public static ?Connection $connection = null;

    public static function getConnection($db_params): Connection
    {
        if (!self::$connection) {
            self::$connection = DriverManager::getConnection([
                'dbname' => $db_params['dbname'],
                'user' => $db_params['user'],
                'password' => $db_params['password'],
                'host' => $db_params['host'],
                'port' => $db_params['port'],
                'driver' => $db_params['driver'] ?? 'pdo_mysql',
            ]);
        }
        return self::$connection;
    }

    public static function getTable($name)
    {
        return $name;
    }
}
