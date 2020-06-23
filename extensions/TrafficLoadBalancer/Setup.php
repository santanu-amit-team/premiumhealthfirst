<?php

namespace Extension\TrafficLoadBalancer;

use Database\Connectors\ConnectionFactory;
use Exception;

class Setup
{

    public static function createConnection()
    {
        $dbFileName = sprintf('%s%strafficlb.sqlite', STORAGE_DIR, DS);

        if (!file_exists($dbFileName)) {
            file_put_contents($dbFileName, '', LOCK_EX);
            chmod($dbFileName, 0777);
        }

        if (!extension_loaded('pdo_sqlite')) {
            throw new Exception('PDO SQLite extension not installed!');
        }

        $factory = new ConnectionFactory();

        $connection = $factory->make(array(
            'driver'   => 'sqlite',
            'database' => $dbFileName,
        ));

        return $connection;
    }

    public static function tableExists()
    {
        try
        {
            $sql = "CREATE TABLE IF NOT EXISTS 'scrapper' ("
                . "     id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, "
                . "     scrappedCount INTEGER, "
                . "     hitsCount INTEGER,"
                . "     scrapped INTEGER, "
                . "     hits INTEGER,"
                . "     percentage TEXT,"
                . "     scrapStep INTEGER DEFAULT 1, "
                . "     afId TEXT DEFAULT NULL, "
                . "     affId TEXT DEFAULT NULL, "
                . "     sId TEXT DEFAULT NULL, "
                . "     c1 TEXT DEFAULT NULL, "
                . "     c2 TEXT DEFAULT NULL, "
                . "     c3 TEXT DEFAULT NULL, "
                . "     c4 TEXT DEFAULT NULL, "
                . "     c5 TEXT DEFAULT NULL, "
                . "     aId TEXT DEFAULT NULL, "
                . "     opt TEXT DEFAULT NULL, "
                . "     clickId TEXT DEFAULT NULL,"
                . "    card_details TEXT DEFAULT NULL "
                . ")";

            $query = self::createConnection()->query($sql);
			
            $checkColSql = "PRAGMA table_info(scrapper)";
            $cols = self::createConnection()->fetchAll($checkColSql);
            $colsCount = count($cols);
            if($colsCount < 19) {
                    $alterSql = "ALTER TABLE scrapper ADD COLUMN card_details TEXT DEFAULT ''";
                    $query = self::createConnection()->query($alterSql);
            }
            return array('success' => true);
        } catch (Exception $ex) {
            return array(
                'success'       => false,
                'error_message' => $ex->getMessage(),
            );
        }
    }

    public static function cacheFolderChecking()
    {
        $status = array(
            'success' => true,
        );
        $storagePath = STORAGE_DIR . DS . '.lbcache';
        if (is_dir($storagePath)) {
            if (!is_writable($storagePath)) {
                if (!chmod($storagePath, 0644)) {
                    $status = array(
                        'success'       => false,
                        'error_message' => "Unable to write cache directory",
                    );
                }
            }
        } else {
            if (!mkdir($storagePath, 0644)) {
                $status = array(
                    'success'       => false,
                    'error_message' => "Unable to create cache directory",
                );
            }
        }
        return $status;
    }

}
