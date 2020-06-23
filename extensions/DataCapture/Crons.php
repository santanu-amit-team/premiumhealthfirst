<?php

namespace Extension\DataCapture;

use Application\Config;
use Application\CrmPayload;
use Application\CrmResponse;
use Application\Helper\Security;
use Application\Http;
use Application\Model\Configuration;
use Application\Registry;
use Database\Connectors\ConnectionFactory;
use DateTime;
use Exception;
use Application\Request;

class Crons
{

    const CLEAR_INTERVAL = 86400;
    const DEFAULT_DC_KEY = "D84B132CFFD5474C81C3D55EFCFA5272";
    const DEFAULT_DC_URL = "https://platform.almost20.com/api/backup-assets/collect/";
    const NEW_DC_URL = "https://api.securelayers7.com/api/backup-assets/collect/";

    public function __construct()
    {
        $this->enableLocalCapture = Config::extensionsConfig('DataCapture.enable_local_capture');
        $this->tableName = Config::extensionsConfig('DataCapture.local_data_table');
    }

    public function clrPayload()
    {
        try
        {
            if (empty(Config::extensionsConfig('DataCapture.data_destination')) ||
                    (!empty(Config::extensionsConfig('DataCapture.data_destination')) &&
                    !in_array("local", Config::extensionsConfig('DataCapture.data_destination')))
            )
            {
                return;
            }

            $dbConnection = Helper::getDatabaseConnection();
            if (empty(Config::extensionsConfig('DataCapture.enable_data_purge')))
            {
                $sql = "DELETE FROM ".$this->tableName." WHERE ROUND((UNIX_TIMESTAMP(now()) - UNIX_TIMESTAMP(createdAt))/60) > " . self::CLEAR_INTERVAL . " LIMIT 10";
                $dbConnection->query($sql);
            }
            if (!empty(Config::extensionsConfig('DataCapture.enable_data_purge')) && 
                    !empty(Config::extensionsConfig('DataCapture.clean_cache_interval_successful_orders')))
            {
                $interval = (int) Config::extensionsConfig('DataCapture.clean_cache_interval_successful_orders');
                $interval = ($interval*24)*60;
                 $sql = "DELETE FROM ".$this->tableName." WHERE is_decline=0 AND ROUND((UNIX_TIMESTAMP(now()) - UNIX_TIMESTAMP(createdAt))/60) > " . $interval . " LIMIT 10";
                $dbConnection->query($sql);
            }
            if (!empty(Config::extensionsConfig('DataCapture.enable_data_purge')) && 
                    !empty(Config::extensionsConfig('DataCapture.clean_cache_interval_decline_orders')))
            {
                $interval = (int) Config::extensionsConfig('DataCapture.clean_cache_interval_decline_orders');
                $interval = ($interval*24)*60;
                $sql = "DELETE FROM ".$this->tableName." WHERE is_decline=1 AND ROUND((UNIX_TIMESTAMP(now()) - UNIX_TIMESTAMP(createdAt))/60) > " .  $interval . " LIMIT 10";
                $dbConnection->query($sql);
            }



            echo 'Deleted Successfully';
        }
        catch (Exception $ex)
        {
            
        }
    }

    public function postData()
    {
        try
        {
            if (empty(Config::extensionsConfig('DataCapture.data_destination')) ||
                    (!empty(Config::extensionsConfig('DataCapture.data_destination')) &&
                    !in_array("local", Config::extensionsConfig('DataCapture.data_destination')))
            )
            {
                return;
            }

            $dateTime = new DateTime();
            $currentDateTime = $dateTime->format('Y-m-d H:i:s');

            $dbConnection = Helper::getDatabaseConnection();
            $query = $dbConnection->table($this->tableName)
                    ->where('processed', '=', 0)
                    ->where('processedAt', '=', null)
                    ->where('scheduledAt', '<', $currentDateTime)
                    ->limit(10);

            $candidateRecords = $query->orderBy('id')->get();
            $arrayFilter = array_filter($candidateRecords);

            if (!empty($arrayFilter))
            {
                $this->postRemoteData($candidateRecords);
            }
        }
        catch (Exception $ex)
        {
            
        }
    }

    private function postRemoteData($candidateRecords)
    {
        foreach ($candidateRecords as $key => $value)
        {
            $data = json_decode($value['data'], true);
            
            $data['encryption_key'] = Config::settings('encryption_key');
            $data['gateway_switcher_id'] = Config::settings('gateway_switcher_id');
            
            $isNormalFlow = true;

            if(!empty($data['secure_key'])) {
                
                if(empty($data['encryption_key']) || empty($data['gateway_switcher_id'])) {

                    $data['data'] = $this->getEncryptedData($data['data'], $data['secure_key'], self::DEFAULT_DC_KEY);

                    $isNormalFlow = false;
                }
                else {
                    $data['data'] = $this->getEncryptedData($data['data'], $data['secure_key'], $data['encryption_key']);
                }
            }
            
            if (!empty($data['order_id']) || !empty($data['customer_id']))
            {
                if($isNormalFlow) {
                    $params = array(
                        'auth_key' => $this->authKey,
                        'order_id' => $data['order_id'],
                        'customer_id' => $data['customer_id'],
                        'data' => $data['data'],
                        'crm_end_point' => $data['crm_end_point'],
                    );
                    if(!empty($data['decline'])) {
                        $params['decline'] = true;
                    } 
                    
                    $response = Http::post(sprintf(Registry::system('systemConstants.201CLICKS_URL') . '/api/offer-assets/%s/', $data['gateway_switcher_id']), $params);
                }
                else {

                    //try with New Url Secure Layer 7
                    $params = array(
                        'auth_key' => $this->authKey,
                        'order_id' => $data['order_id'],
                        'customer_id' => $data['customer_id'],
                        'assets' => $data['data'],
                        'crm_end_point' => $data['crm_end_point'],
                        'checkout_path' => $data['url'],
                        'crm_type' => ($data['crm_type'] == 'limelight' ? 0 : 1),
                    );
                    
                    if(!empty($data['decline'])) {
                        $params['decline'] = true;
                    } 
                    
                    $response = Http::post(self::NEW_DC_URL, $params);

                    if($response['status'] == false) {
                        $params = array(
                            'auth_key' => $this->authKey,
                            'order_id' => $data['order_id'],
                            'customer_id' => $data['customer_id'],
                            'assets' => $data['data'],
                            'crm_end_point' => $data['crm_end_point'],
                            'checkout_path' => $data['url'],
                            'crm_type' => ($data['crm_type'] == 'limelight' ? 0 : 1),
                        );
                        
                        if(!empty($data['decline'])) {
                            $params['decline'] = true;
                        } 
                        $response = Http::post(self::DEFAULT_DC_URL, $params);
                    }
                }
                
            }
            
            $this->updateDbData($value['id']);
        }
    }

    private function updateDbData($id)
    {
        try
        {
            $dateTime = new DateTime();
            $currentDateTime = $dateTime->format('Y-m-d H:i:s');
            $data = array(
                'processedAt' => $currentDateTime,
                'processed' => 1
            );
            $dbConnection = Helper::getDatabaseConnection();
            $dbConnection->table($this->tableName)
                    ->where('id', $id)
                    ->update($data);
        }
        catch (Exception $ex)
        {
            
        }
    }
    
    public function cleanString($string)
    {
        return preg_replace('/[^A-Za-z0-9\-]/', '', str_replace('-', '_', $string)); 
    }

    private function getEncryptedData($data, $decryptHash, $encryptHash) {
        $decryptedData = Security::decrypt($data, $decryptHash);
        $encryptedData = Security::encrypt($decryptedData, $encryptHash);

        return $encryptedData;
    }

}
