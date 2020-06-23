<?php

namespace Extension\DataCapture;

use Application\Config;
use Application\CrmPayload;
use Application\CrmResponse;
use Application\Helper\Alert;
use Application\Session;
use Application\Response;
use Database\Connectors\ConnectionFactory;
use Exception;
use DateTime;
use Application\Http;
use Application\Helper\Security;
use Application\Request;
use Application\Model\Campaign;

class Helper
{
    private static $dbConnection = null;
    private static $localEncKey = 'd0a7e7997b6d5fcd55f4b5c32611b87cd923e88837b63bf2941ef819dc8ca282';

    private function __construct()
    {
        return;
    }

    public static function getDatabaseConnection()
    {
        if (self::$dbConnection === null) {
            try {
                $factory            = new ConnectionFactory();
                self::$dbConnection = $factory->make(array(
                    'driver'    => 'mysql',
                    'host'      => Config::settings('db_host'),
                    'username'  => Config::settings('db_username'),
                    'password'  => Config::settings('db_password'),
                    'database'  => Config::settings('db_name'),
                    'charset'   => 'utf8',
                    'collation' => 'utf8_unicode_ci',
                ));
            } catch (Exception $ex) {
                Alert::insertData(array(
                    'identifier'    => 'Data Capture',
                    'text'          => 'Please check your database credential',
                    'type'          => 'error',
                    'alert_handler' => 'extensions',
                ));
                return false;
            }
        }
        return self::$dbConnection;
    }
    
    public static function cleanString($string)
    {
        return preg_replace('/[^A-Za-z0-9\-]/', '', str_replace('-', '_', $string)); 
    }
    
    public static function checkTableExists($tableName) {

        $dbName = Config::settings('db_name');
        $connection = self::getDatabaseConnection();
        $query = sprintf("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = '%s' AND table_name = '%s' LIMIT 1", $dbName, $tableName);
        $statement = $connection->fetch($query);
        
        return $statement['count'] > 0 ? true : false;
    }

}
