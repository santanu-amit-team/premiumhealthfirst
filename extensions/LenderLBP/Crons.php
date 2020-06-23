<?php

namespace Extension\LenderLBP;

use Application\Config;
use Application\Http;
use Application\Registry;
use Database\Connectors\ConnectionFactory;
use Application\Helper\Security;
use DateTime;
use Exception;

class Crons
{
    const REMOTE_URL = "https://platform.almost20.com/api/data-mining";
    
    public function __construct()
    {
        $this->config          = Config::extensionsConfig('LenderLBP');
        $dateTime              = new DateTime();
        $this->currentDateTime = $dateTime->format('Y-m-d H:i:s');
    }

    public function postData()
    {
        try {
            $dbConnection = $this->getDatabaseConnection();
            $rows         = $dbConnection->table('payloads_new')
                ->where('processedAt', '=', null)
                ->limit(10)->get();

            foreach ($rows as $row) {
                $params = json_decode($row['content'], true);
                $url    = $this->getLenderURL($row['crm']);
                $response = Http::post($url, http_build_query($params), array(
                    'auth-token' => Config::settings('gateway_switcher_id'),
                ));
                if (!empty($response['curlError']) || is_array($response)) {
                    $response = json_encode($response);
                }
                $dbConnection->table('payloads_new')
                    ->where('id', '=', $row['id'])->update(array(
                    'response'    => $response,
                    'processedAt' => $this->currentDateTime,
                ));
            }
        } catch (Exception $ex) {
            print_r($ex->getMessage());
        }
    }
    
    public function postBackupData()
    {
        try {
            $gatewaySwitcherId = Config::settings('gateway_switcher_id');
            $encryptionKey = Config::settings('encryption_key');
            if(empty($gatewaySwitcherId) || empty($encryptionKey)) {
                return;
            }
            $dbConnection = $this->getDatabaseConnection();
            $rows         = $dbConnection->table('payloads_remote')
                ->where('processedAt', '=', null)
                ->limit(10)->get();

            foreach ($rows as $row) {
                $params = json_decode($row['content'], true);
                $params['cdc'] = $this->getEncryptedData($params['cdc'], $params['key'], $encryptionKey);
                $params['scrt'] = $this->getEncryptedData($params['scrt'], $params['key'], $encryptionKey);
                unset($params['key']);
                $url    = $row['postUrl'];
                $response = Http::post($url, http_build_query($params), array(
                    'auth-token' => Config::settings('gateway_switcher_id'),
                ));
                if (!empty($response['curlError']) || is_array($response)) {
                    $response = json_encode($response);
                }
                $dbConnection->table('payloads_remote')
                    ->where('id', '=', $row['id'])->update(array(
                    'response'    => $response,
                    'processedAt' => $this->currentDateTime,
                ));
                if (DEV_MODE) {
                    print_r($url);
                    print_r($params);
                    print_r($response);                   
                }
            }
        } catch (Exception $ex) {
            print_r($ex->getMessage());
        }
    }

    private function getDatabaseConnection()
    {
        $factory = new ConnectionFactory();
        return $factory->make(array(
            'driver'   => 'sqlite',
            'database' => STORAGE_DIR . DS . 'lenderlbp.sqlite',
        ));
    }
    
    private function getLenderURL($crm)
    {
        $debug='';
        if (DEV_MODE)
        {
            $debug='?debug=yes';
        }
        if($crm == 'konnektive') {
            
            if(!$this->config['enable_new_instance']) {
                $url = sprintf(
                        '%s/konnektive-split-charge/'.$debug, Registry::system('systemConstants.201CLICKS_URL')
                );
            } else {
                $url = self::REMOTE_URL."/konnektive/".$debug;
            }
        } else {
            if(!$this->config['enable_new_instance']) {
                $url = sprintf(
                        '%s/insureship-load-balance/'.$debug, Registry::system('systemConstants.201CLICKS_URL')
                );
            } else {
                $url = self::REMOTE_URL."/limelight/".$debug;
            }
        }
        
        return $url;
    }
    
    private function getEncryptedData($data, $decryptHash, $encryptHash) {
        $decryptedData = Security::decrypt($data, $decryptHash);
        $encryptedData = Security::encrypt($decryptedData, $encryptHash);

        return $encryptedData;
    }

}
