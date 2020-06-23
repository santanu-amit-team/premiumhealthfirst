<?php

namespace Extension\DelayedTransactions;

use Application\Config;
use Application\Request;
use Exception;

class Actions
{
    protected $arrayCols = array();
    protected $alterFlag = false;
    protected $alterScrapFlag = false;

    public function save()
    {
        if (!extension_loaded('pdo_mysql')) {
            throw new Exception("Mysq PDO extension is not installed.");
        }

        $tableName = Request::form()->get('table_name');
        $dbName    = Config::settings('db_name');

        if (empty($tableName)) {
            throw new Exception('Please enter a valid table name.');
        }

        $sql = "CREATE TABLE IF NOT EXISTS $tableName ("
            . "     id INT NOT NULL AUTO_INCREMENT,"
            . "     parentOrderId VARCHAR(100) NOT NULL,"
            . "     configId INT NOT NULL,"
            . "     crmId INT NOT NULL,"
            . "     crmType VARCHAR(20) NOT NULL,"
            . "     combined TINYINT NOT NULL DEFAULT 0,"
            . "     crmPayload TEXT NOT NULL,"
            . "     crmResponse TEXT NULL DEFAULT NULL,"
            . "     rawPayload TEXT NULL DEFAULT NULL,"
            . "     rawResponse TEXT NULL DEFAULT NULL,"
            . "     processing TINYINT NOT NULL DEFAULT 0,"
            . "     processedAt DATETIME NULL DEFAULT NULL,"
            . "     scheduledAt DATETIME NOT NULL,"
            . "     createdAt DATETIME NOT NULL,"
            . "     PRIMARY KEY (id)"
            . ");";

        $alterSql = "ALTER TABLE $tableName "
            . "ADD orderId VARCHAR(100) NULL AFTER parentOrderId, "
            . "ADD step TINYINT(3) NULL AFTER orderId, "
            . "ADD type VARCHAR(20) NULL AFTER step";

        $checkColSql = "SELECT `COLUMN_NAME` FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `TABLE_SCHEMA`='$dbName' AND `TABLE_NAME`='$tableName'";

        $dbConnection = Helper::getDatabaseConnection();

        $cols = $dbConnection->fetchAll($checkColSql);

        array_walk_recursive(
            $cols,
            function (&$v) {
                array_push($this->arrayCols, $v);
                return $this->arrayCols;
            }
        );

        $newKeys = array('orderId', 'step', 'type');

        if (count(array_intersect($this->arrayCols, $newKeys)) != count($newKeys)) {
            $this->alterFlag = true;
        }

        if (!$dbConnection) {
            throw new Exception(
                'Couldn\'t authenticate database credentials. Please recheck your settings.'
            );
        }

        try {
            $dbConnection->query($sql);
            if ($this->alterFlag) {
                $dbConnection->query($alterSql);
            }
        } catch (Exception $ex) {
            throw new Exception(
                'Table could not be created. Please recheck your settings.'
            );
        }
        
        $enablePreauthLog = Request::form()->get('enable_preauth_log');
        if(!empty($enablePreauthLog))
        {
            return $this->createPreauthTable($dbConnection);
        }
        

        return true;

    }
    
    public function createPreauthTable($dbConnection)
    {

        $tableName = 'preauth_logger';

        if (empty($tableName))
        {
            throw new Exception('Please enter a valid pre auth log table name.');
        }

        $sql = "CREATE TABLE IF NOT EXISTS $tableName ("
            . "     id INT NOT NULL AUTO_INCREMENT,"
            . "     email VARCHAR(100) NOT NULL,"
            . "     crmPayload TEXT NOT NULL,"
            . "     crmResponse TEXT NULL DEFAULT NULL,"
            . "     rawPayload TEXT NULL DEFAULT NULL,"
            . "     rawResponse TEXT NULL DEFAULT NULL,"
            . "     createdAt DATETIME NOT NULL,"
            . "     PRIMARY KEY (id)"
            . ");";



        try
        {
            $dbConnection->query($sql);
        }
        catch (Exception $ex)
        {
            throw new Exception(
            'Preauth Table could not be created. Please recheck your settings.'
            );
        }

        return true;
    }
}
