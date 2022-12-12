<?php

namespace Jobsys\Survey\Utils;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

class Db
{

    public static ?Connection $connection = null;

    public static function getConnection($db_params): Connection
    {

        if (!isset($db_params) || !count($db_params)) {
            $config = include_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config.php';
            $db_params = $config['database'];
        }
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
