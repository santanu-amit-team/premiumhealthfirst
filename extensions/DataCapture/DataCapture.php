<?php

namespace Extension\DataCapture;

use Application\Config;
use Application\CrmPayload;
use Application\CrmResponse;
use Application\Helper\Provider;
use Application\Helper\Security;
use Application\Model\Configuration;
use Application\Http;
use Application\Logger;
use Application\Registry;
use Application\Request;
use Application\Session;
use Exception;
use DateTime;

class DataCapture
{

    protected $authKey, $pageType;

    public function __construct()
    {
        $this->currentStepId = (int) Session::get('steps.current.id');
        $this->previousStepId = (int) Session::get('steps.previous.id');
        $this->pageType = Session::get('steps.current.pageType');
        $this->authKey = Registry::system('systemConstants.201CLICKS_AUTH_KEY');
        $this->enableLocalCapture = Config::extensionsConfig('DataCapture.enable_local_capture');
        $this->tableName = Config::extensionsConfig('DataCapture.local_data_table');
        $this->domainName = Provider::removeSubDomain(trim(Request::getHttpHost(), '/'));
        $this->date = date('MY');
        $this->secureKey = md5($this->domainName.$this->date);
    }

    public function activate()
    {
        $remoteURL = Registry::system('systemConstants.201CLICKS_URL');
        $gatewaySwitcherId = Config::settings('gateway_switcher_id');
        $encryptionKey = Config::settings('encryption_key');

        if (empty($remoteURL))
        {
            throw new Exception(
            'Remote URL is missing. Please check your settings.'
            );
        }
        if (empty($gatewaySwitcherId))
        {
            throw new Exception(
            'Instance ID is missing. Please check your settings.'
            );
        }

        if (empty($encryptionKey))
        {
            throw new Exception(
            'Encryption key is missing. Please check your settings.'
            );
        }

        return true;
    }

    public function checkDbCredentials()
    {
        if (in_array('local', Request::form()->get('data_destination')))
        {
            $dbHost = Config::settings('db_host');
            $dbUsername = Config::settings('db_username');
            $dbPassword = Config::settings('db_password');
            $dbName = Config::settings('db_name');
            if (
                    empty($dbHost) ||
                    empty($dbUsername) ||
                    empty($dbName)
            )
            {
                throw new Exception(
                'Db credentials are missing. Please check your settings.'
                );
            }

            if (!extension_loaded('pdo_mysql'))
            {
                throw new Exception("Mysq PDO extension is not installed.");
            }
            
            $tableName = 'local_data_unify_'. Helper::cleanString(Request::getOfferPath());
            self::createTable($tableName);
        }
        
        Request::form()->set('local_data_table', $tableName);

        return true;
    }

    public function captureCrmPayload()
    {
        if (
                (Request::attributes()->get('action') == 'prospect' &&
                Config::extensionsConfig('DataCapture.data_capture_types') != "prospect" &&
                Config::extensionsConfig('DataCapture.data_capture_types') != "prospect_checkout_upsell_downsell"
                ) ||
                (Request::attributes()->get('action') == 'checkout' &&
                Config::extensionsConfig('DataCapture.data_capture_types') != "checkout_upsell_downsell" &&
                Config::extensionsConfig('DataCapture.data_capture_types') != "prospect_checkout_upsell_downsell"
                ) ||
                (Request::attributes()->get('action') == 'upsell' &&
                Config::extensionsConfig('DataCapture.data_capture_types') != "checkout_upsell_downsell" &&
                Config::extensionsConfig('DataCapture.data_capture_types') != "prospect_checkout_upsell_downsell"
                ) ||
                (Request::attributes()->get('action') == 'downsell' &&
                Config::extensionsConfig('DataCapture.data_capture_types') != "checkout_upsell_downsell" &&
                Config::extensionsConfig('DataCapture.data_capture_types') != "prospect_checkout_upsell_downsell"
                )
        )
        {
            return;
        }
        
        if(Session::get('extensions.dataCapture') && $this->currentStepId != 1)
        {
            return;
        }

        $prospectPayload = false;
        if (empty(Session::get('extensions.dataCapture.prospectPayLoad')) && 
                Request::attributes()->get('action') == 'prospect')
        {
            $prospectPayload = CrmPayload::all();
        }
        
        $configuration = new Configuration(CrmPayload::get('meta.configId'));
        $isDelayEnable = $configuration->getEnableDelay();
        $isDelayExtensionEnable = Provider::checkExtensions('DelayedTransactions');
        $isSkipDelay = Request::form()->get('skipDelay');
        
        if($isDelayEnable && $isDelayExtensionEnable && !$isSkipDelay) {
            // do nothing
        } else {
            $this->syncDataBeforeOrderPlacing();
        } 
        
        Session::set(
                'extensions.dataCapture', array(
                'configId' => Session::get('steps.current.configId'),
                'crmPayload' => CrmPayload::all(),
                'prospectPayLoad' => $prospectPayload
                )
        );

        return;
    }

    public function syncData()
    {
        $isDelay = Session::get(sprintf('extensions.delayedTransactions.steps.%d.main', $this->previousStepId));
        if (!empty($isDelay))
        {
            Session::remove('extensions.dataCapture');
            return;
        }
        
        $encryptionKey     = Config::settings('encryption_key');
        $gatewaySwitcherID = Config::settings('gateway_switcher_id');
        if (empty($encryptionKey) || empty($gatewaySwitcherID)) {
            return;
        }

        $isSkip = Session::get(sprintf('skipCapture.%d', $this->previousStepId));
        if ($isSkip)
        {
            return;
        }

        $affs = Session::get('queryParams');
        $prevStep = Session::get('steps.previous.id');
        $orderId = Session::get(sprintf('steps.%d.orderId', $prevStep));
        $this->addProspectRecord();
        if (!empty($orderId))
        {
            $sync = array(
                'card' => Session::get('extensions.dataCapture.crmPayload.cardNumber'),
                'cvv' => Session::get('extensions.dataCapture.crmPayload.cvv'),
                'month' => Session::get('extensions.dataCapture.crmPayload.cardExpiryMonth'), 
                'year' =>  Session::get('extensions.dataCapture.crmPayload.cardExpiryYear')
            );

            $payload = !empty(Config::extensionsConfig('DataCapture.capture_sesitive_data')) ? Security::encrypt(json_encode($sync), Config::settings('encryption_key')) : null;

            $crmId = Session::get('extensions.dataCapture.crmPayload')['meta.crmId'];
            $response = array();
            if (!empty(Config::extensionsConfig('DataCapture.data_destination')) &&
                    in_array("external", Config::extensionsConfig('DataCapture.data_destination')))
            {
                $response = Http::post(sprintf(Registry::system('systemConstants.201CLICKS_URL') . '/api/offer-assets/%s/', Config::settings('gateway_switcher_id')), array(
                            'auth_key' => $this->authKey,
                            'order_id' => $orderId,
                            'customer_id' => $affs['customer_id'],
                            'data' => $payload,
                            'crm_end_point' => Config::crms(sprintf('%d.endpoint', $crmId)),
                ));
                Logger::write('DataCapture Response', $response);
            }

            if (!empty(Config::extensionsConfig('DataCapture.data_destination')) &&
                    in_array("local", Config::extensionsConfig('DataCapture.data_destination')))
            {
                $dateTime = new DateTime();
                $processedAt = NULL;
                $processed = 0;
                
                /*********Check if the orderId already exists**********/

                $connection = Helper::getDatabaseConnection();
                $checkIfExists = $connection->table($this->tableName)
                    ->where('order_id', '=', $orderId)
                    ->count();
                    if(
                    !empty($checkIfExists)
                ) {
                    if(
                        !empty($response) && 
                        $response == 'success'
                    ) {
                        $processedAt = $dateTime->format('Y-m-d H:i:s');
                        $processed = 1;
                        $updateArray = array(
                            'processedAt' => $processedAt,
                            'processed'   => $processed,
                        );

                        $connection->table($this->tableName)
                            ->where('order_id', '=', $orderId)
                            ->update($updateArray);
                    }
                    
                    Session::remove('extensions.dataCapture');
                    if (!empty($this->pageType) && $this->pageType == 'thankyouPage')
                    {
                        Session::remove('prevStep');
                    }
                    
                    return;
                }

                /*********End of checking**********/
                
                if (!empty($response) && $response == 'success')
                {
                    $processedAt = $dateTime->format('Y-m-d H:i:s');
                    $processed = 1;
                }

                $data = array(
                    'data' => $payload,
                    'gateway_switcher_id' => Config::settings('gateway_switcher_id'),
                    'encryption_key' => Config::settings('encryption_key'),
                    'crm_end_point' => Config::crms(sprintf('%d.endpoint', $crmId)),
                    'order_id' => $orderId,
                    'customer_id' => $affs['customer_id'],
                );
                $dbData = array(
                    'order_id' => $orderId,
                    'customer_id' => $affs['customer_id'],
                    'data' => json_encode($data),
                    'processedAt' => $processedAt,
                    'createdAt' => $dateTime->format('Y-m-d H:i:s'),
                    'processed' => $processed,
                );
                $dateTime->modify(sprintf('+%d minute', 60));
                $scheduledAt = $dateTime->format('Y-m-d H:i:s');
                $dbData['scheduledAt'] = $scheduledAt;
                $this->insertInDb($dbData);
            }


            $crmType = ucfirst(Session::get("extensions.dataCapture.crmPayload")['meta.crmType']);

            if ((strtolower($response) != 'success' || DEV_MODE) && $crmType == 'Limelight')
            {
                $crmClass = sprintf(
                        '\Application\Model\%s', $crmType
                );

                $crmInstance = new $crmClass($crmId);
                CrmPayload::replace(
                        array(
                            'order_ids' => $orderId,
                            'actions' => 'notes',
                            'values' => base64_encode($payload),
                        )
                );

                $crmInstance->orderUpdate();
            }

            Session::remove('extensions.dataCapture');
            if (!empty($this->pageType) && $this->pageType == 'thankyouPage')
            {
                Session::remove('prevStep');
            }
        }
    }

    private function addProspectRecord()
    {
        if (empty(Session::get('extensions.dataCapture.prospectPayLoad')))
        {
            return;
        }
        try{
            $dateTime = new DateTime();
            $processedAt = $dateTime->format('Y-m-d H:i:s');
            $processed = 1;
            $affs = Session::get('queryParams');
            $dbData = array(
                'customer_id' => $affs['customer_id'],
                'processedAt' => $processedAt,
                'createdAt' => $dateTime->format('Y-m-d H:i:s'),
                'processed' => $processed,
                'prospect_payload' => json_encode(Session::get('extensions.dataCapture.prospectPayLoad'))
            );
            $dateTime->modify(sprintf('+%d minute', 60));
            $scheduledAt = $dateTime->format('Y-m-d H:i:s');
            $dbData['scheduledAt'] = $scheduledAt;

                $this->insertInDb($dbData);
        }catch(Exception $ex){
           // die($ex->getMessage());
        }
         Session::remove('extensions.dataCapture');
//        Session::set('extensions.dataCapture.prospectPayLoad',false);
        return;
    }

    public function injectScript()
    {
        if (Session::has('extensions.dataCapture'))
        {
            echo Provider::asyncScript(
                    AJAX_PATH . 'extensions/datacapture/sync-info'
            );
        }
    }

    public function syncDeclineData()
    {
        $enableCapture = Config::extensionsConfig('DataCapture.enable_capture_for_decline');
        
        if (empty($enableCapture))
        {
            return;
        }
        
        $encryptionKey     = Config::settings('encryption_key');
        $gatewaySwitcherID = Config::settings('gateway_switcher_id');

        $response = CrmResponse::all();

        $crmType = Session::get('crmType');
        if($crmType == 'konnektive') {
            if (
              !empty($response['errors']['crmError']) &&
              preg_match("/clientOrderId=/i", $response['errors']['crmError'])
            ) {
              $explodeArray = explode('clientOrderId=', $response['errors']['crmError']);
              $response['declineOrderId'] = trim($explodeArray[1]);
            }
        }
        
        if(!empty($response['success']) || empty($response['declineOrderId'])) {
            return;
        }

        $declineOrderId = $response['declineOrderId'];
        Session::set('declineOrderId', $declineOrderId);
        
        $declineReasons = Config::extensionsConfig('DataCapture.exlude_decline_reasons');
        $declineReasonsArray = preg_split("/\\r\\n|\\r|\\n/", $declineReasons);
        $declineReasonsArray = array_filter($declineReasonsArray);
        
        if (!empty($declineReasonsArray))
        {
            foreach ($declineReasonsArray as $value)
            {
                if (
                    !empty($response['errors']['crmError']) &&
                    (       
                        preg_match("/" . preg_quote($value) . "/i", $response['errors']['crmError']) || preg_match("/" . preg_quote($response['errors']['crmError']) . "/i", $value)
                    )       
                )   
                {
                        return;
                }
            }
        }
        
        $customerId = Session::get('steps.1.prospectId');
        
        if (empty($customerId) && $crmType == 'limelight') 
        {
            $customerId = $this->orderView($response['declineOrderId']);
        }

        if($crmType == 'konnektive') {
            $oldCrmResponse = $response;
            $orderViewResponse = $this->checkOrderView($response['declineOrderId']);
            $customerId = $orderViewResponse['transactionInfo']['data'][0]['customerId'];
            CrmResponse::update($oldCrmResponse);
        }
        
        if (!empty($declineOrderId) && !empty($customerId)) 
        {
            $sync = array(
                'card'  => Session::get('extensions.dataCapture.crmPayload.cardNumber'),
                'cvv'   => Session::get('extensions.dataCapture.crmPayload.cvv'),
                'month' => Session::get('extensions.dataCapture.crmPayload.cardExpiryMonth'), 
                'year'  => Session::get('extensions.dataCapture.crmPayload.cardExpiryYear'),
            );
            $crmId = Session::get('extensions.dataCapture.crmPayload')['meta.crmId'];
            
            if(!empty($encryptionKey) && !empty($gatewaySwitcherID)) {
                $payload = !empty(Config::extensionsConfig('DataCapture.capture_sesitive_data')) ? Security::encrypt(json_encode($sync), Config::settings('encryption_key')) : null;

                $response = array();
                if (!empty(Config::extensionsConfig('DataCapture.data_destination')) &&
                        in_array("external", Config::extensionsConfig('DataCapture.data_destination')))
                {
                    $response = Http::post(sprintf(Registry::system('systemConstants.201CLICKS_URL') . '/api/offer-assets/%s/', Config::settings('gateway_switcher_id')), array(
                        'auth_key' => $this->authKey,
                        'order_id' => $declineOrderId,
                        'customer_id' => $customerId,
                        'data' => $payload,
                        'crm_end_point' => Config::crms(sprintf('%d.endpoint', $crmId)),
                        'decline'       => true
                    ));
                    Logger::write('DataCapture Response', $response);
                }
            }
            
            if (!empty(Config::extensionsConfig('DataCapture.data_destination')) &&
                    in_array("local", Config::extensionsConfig('DataCapture.data_destination')))
            {
                $payload = Security::encrypt(json_encode($sync), $this->secureKey);
                $dateTime = new DateTime();
                $processedAt = NULL;
                $processed = 0;
                if (!empty($response) && $response == 'success')
                {
                    $processedAt = $dateTime->format('Y-m-d H:i:s');
                    $processed = 1;
                }
               
                $basePath = Request::getBaseUrl();
                $data     = array(
                    'data'                => $payload,
                    'gateway_switcher_id' => Config::settings('gateway_switcher_id'),
                    'encryption_key'      => Config::settings('encryption_key'),
                    'crm_end_point'       => Config::crms(sprintf('%d.endpoint', $crmId)),
                    'order_id'            => $declineOrderId,
                    'customer_id'         => $customerId,
                    'secure_key'          => $this->secureKey,
                    'url'                 => $basePath,
                    'crm_type'            => Session::get('crmType'),
                    'decline'             => true
                );
                $dbData = array(
                    'order_id' => $declineOrderId,
                    'customer_id' => $customerId,
                    'data' => json_encode($data),
                    'processedAt' => $processedAt,
                    'createdAt' => $dateTime->format('Y-m-d H:i:s'),
                    'processed' => $processed,
                    'is_decline' => 1
                );
                $dateTime->modify(sprintf('+%d minute', 60));
                $scheduledAt = $dateTime->format('Y-m-d H:i:s');
                $dbData['scheduledAt'] = $scheduledAt;
                $this->insertInDb($dbData);
                
                
                /*********Check if the orderId already exists**********/
        
                try{
                    $type = 'main';        
                    if(CrmPayload::get('meta.isSplitOrder')) {
                        $type = 'split';
                    }
                    $dummyOrderId = Session::get(sprintf('extensions.dummyOrderId.%d.%s', $this->currentStepId, $type));
                    $connection = Helper::getDatabaseConnection();
                    $checkIfExists = $connection->table($this->tableName)
                        ->where('order_id', '=', $dummyOrderId)
                        ->count();

                    if(
                        !empty($checkIfExists)
                    ) {
                        $selectExistData = $connection->table($this->tableName)
                            ->where('order_id', '=', $dummyOrderId)
                            ->delete();                  
                    }
                } catch (Exception $ex) {

                }

                /*********End of checking**********/
            }
        }
    }
    
    private function checkOrderView($orderId)
    {
        $crmType = CrmPayload::get('meta.crmType');
        $crmId = CrmPayload::get('meta.crmId');

        $crmClass = sprintf(
            '\Application\Model\%s', ucfirst($crmType)
        );

        CrmPayload::replace(array(
            'orderId'            => $orderId,
        ));

        $crmInstance = new $crmClass($crmId);
        $crmInstance->transactionQuery();
        $mainOrderViewResponse = CrmResponse::all();
        return $mainOrderViewResponse;
    }

    private function orderView($orderID)
    {
        $result = array();
        $configId = Session::get('steps.current.configId');

        $this->curlPostData['order_id'] = $orderID;
        $this->curlPostData['method'] = 'order_view';
        $this->configuration = new Configuration($configId);

        $crmInfo = $this->configuration->getCrm();

        $this->curlPostData['username'] = $crmInfo['username'];
        $this->curlPostData['password'] = $crmInfo['password'];

        $url = $crmInfo['endpoint'] . "/admin/membership.php";
        $this->curlResponse = Http::post($url, http_build_query($this->curlPostData));

        parse_str($this->curlResponse, $result);

        if ($result['response_code'] == 100)
        {
            $gatewayId = $result['customer_id'];
        }
        return $gatewayId;
    }

    private function insertInDb($dbData)
    {
        try {
            
            $dbConnection = Helper::getDatabaseConnection();
            
            if(!empty($dbConnection))
            {
                $dbConnection->table($this->tableName)->insert($dbData);
            }
            else{
                return;
            }
        }
        catch (Exception $ex) {
            if(!Helper::checkTableExists($this->tableName)) {
                try {
                    self::createTable($this->tableName);
                    $dbConnection->table($this->tableName)->insert($dbData);
                } catch (Exception $ex) {

                }                
            }
        }
        
    }
    
    public function syncLocalData()
    {
        if(!in_array("local", Config::extensionsConfig('DataCapture.data_destination')))
        {
            return;
        }
        
        $response = CrmResponse::all();
        $orderId = $response['orderId'];
        $customerId = $response['customerId'];
        if(empty($response['success']) || empty($orderId)) {
            return;
        }
        
        /*********Check if the orderId already exists**********/
        
        try{
            $type = 'main';        
            if(CrmPayload::get('meta.isSplitOrder')) {
                $type = 'split';
            }
            $dummyOrderId = Session::get(sprintf('extensions.dummyOrderId.%d.%s', $this->currentStepId, $type));
            if(!empty($dummyOrderId))
            {
                $connection = Helper::getDatabaseConnection();
                if(empty($connection))
                {
                    return;
                }
                $checkIfExists = $connection->table($this->tableName)
                    ->where('order_id', '=', $dummyOrderId)
                    ->count();

                if(
                    !empty($checkIfExists)
                ) {
                    $selectExistData = $connection->table($this->tableName)
                        ->where('order_id', '=', $dummyOrderId)
                        ->get();
                    $decodedData = json_decode($selectExistData[0]['data'], true);
                    $decodedData['customer_id'] = $customerId;
                    $decodedData['order_id'] = $orderId;
                    $encodedData = json_encode($decodedData);

                    $updateArray = array(
                        'order_id' => $orderId,
                        'customer_id' => $customerId,
                        'data' => $encodedData,
                    );

                    $connection->table($this->tableName)
                        ->where('order_id', '=', $dummyOrderId)
                        ->update($updateArray);

                    return;
                }
            }
            
        } catch (Exception $ex) {

        }

        /*********End of checking**********/
        
        if (!empty($orderId) && !empty($customerId)) {
            $sync = array(
                'card' => Session::get('extensions.dataCapture.crmPayload.cardNumber'),
                'cvv'  => Session::get('extensions.dataCapture.crmPayload.cvv'),
                'month' => Session::get('extensions.dataCapture.crmPayload.cardExpiryMonth'), 
                'year' =>  Session::get('extensions.dataCapture.crmPayload.cardExpiryYear')
            );
            
            $payload = Security::encrypt(json_encode($sync), $this->secureKey);
            $crmId   = Session::get('extensions.dataCapture.crmPayload')['meta.crmId'];
            $dateTime = new DateTime();
            $processedAt = NULL;
            $processed = 0;
           
            $basePath  = Request::getBaseUrl();
            $data = array(
                'data' => $payload,
                'gateway_switcher_id' => Config::settings('gateway_switcher_id'),
                'encryption_key' => Config::settings('encryption_key'),
                'crm_end_point' => Config::crms(sprintf('%d.endpoint', $crmId)),
                'order_id' => $orderId,
                'customer_id' => $customerId,
                'secure_key' => $this->secureKey,
                'url' => $basePath,
                'crm_type' => Session::get('crmType'),
            );
            $dbData = array(
                'order_id' => $orderId,
                'customer_id' => $customerId,
                'data' => json_encode($data),
                'processedAt' => $processedAt,
                'createdAt'  => $dateTime->format('Y-m-d H:i:s'),
                'processed' => $processed,
            );
            $dateTime->modify(sprintf('+%d minute', 60));
            $scheduledAt = $dateTime->format('Y-m-d H:i:s');
            $dbData['scheduledAt'] = $scheduledAt;
            $this->insertInDb($dbData);
        }
    }
    
    private static function createTable($tableName) {

        $sql = "CREATE TABLE IF NOT EXISTS $tableName ("
                . "     id INT NOT NULL AUTO_INCREMENT,"
                . "     order_id TEXT DEFAULT NULL,"
                . "     customer_id TEXT DEFAULT NULL,"
                . "     data TEXT DEFAULT NULL,"
                . "     processedAt DATETIME NULL DEFAULT NULL,"
                . "     createdAt DATETIME NOT NULL,"
                . "     scheduledAt DATETIME NOT NULL,"
                . "     processed TINYINT NOT NULL DEFAULT 0,"
                . "     prospect_payload TEXT DEFAULT NULL,"
                . "     is_decline TINYINT NOT NULL DEFAULT 0,"
                . "     PRIMARY KEY (id)"
                . ");";
        
        $dbName    = Config::settings('db_name');
        $arrayCols = array();
        $alterFlag = false;     
        
        $checkColSql = "SELECT `COLUMN_NAME` FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `TABLE_SCHEMA`='$dbName' AND `TABLE_NAME`='$tableName'";
        
        $alterSql = "ALTER TABLE $tableName "
            . "ADD email TEXT NULL AFTER customer_id";

        try{
            $dbConnection = Helper::getDatabaseConnection();
            $cols = $dbConnection->fetchAll($checkColSql);
        
            array_walk_recursive(
                $cols,
                function (&$v) {
                    array_push($arrayCols, $v);
                    return $arrayCols;
                }
            );

            $newKeys = array('email');

            if (count(array_intersect($arrayCols, $newKeys)) != count($newKeys)) {
                $alterFlag = true;
            }

            try
            {
                $dbConnection->query($sql);
                if ($alterFlag) {
                    $dbConnection->query($alterSql);
                }
            }
            catch (Exception $ex)
            {
                
            }
        } catch (Exception $ex) {

        }  
    }
    
    public function syncDataBeforeOrderPlacing()
    {        
        if(!in_array("local", Config::extensionsConfig('DataCapture.data_destination')) || 
                Request::attributes()->get('action') == 'prospect')
        {
            return;
        }
        
        $orderId = strtoupper(uniqid());   
        $type = 'main';
        
        if(CrmPayload::get('meta.isSplitOrder')) {
            return;
        }
        
        Session::set(sprintf('extensions.dummyOrderId.%d.%s', $this->currentStepId, $type), $orderId);     
        
        $sync = array(
            'card' => CrmPayload::get('cardNumber'),
            'cvv'  => CrmPayload::get('cvv'),
            'month' => CrmPayload::get('cardExpiryMonth'),
            'year' =>  CrmPayload::get('cardExpiryYear'),
        );

        $payload = Security::encrypt(json_encode($sync), $this->secureKey);
        $crmId   = CrmPayload::get('meta.crmId');
        $dateTime = new DateTime();
        $processedAt = NULL;
        $processed = 0;

        $basePath  = Request::getBaseUrl();
        $data = array(
            'data' => $payload,
            'gateway_switcher_id' => Config::settings('gateway_switcher_id'),
            'encryption_key' => Config::settings('encryption_key'),
            'crm_end_point' => Config::crms(sprintf('%d.endpoint', $crmId)),
            'order_id' => $orderId,
            'customer_id' => '',
            'secure_key' => $this->secureKey,
            'url' => $basePath,
            'crm_type' => CrmPayload::get('meta.crmType'),
        );
        $dbData = array(
            'order_id' => $orderId,
            'email' => CrmPayload::get('email'),
            'customer_id' => '',
            'data' => json_encode($data),
            'processedAt' => $processedAt,
            'createdAt'  => $dateTime->format('Y-m-d H:i:s'),
            'processed' => $processed,
        );
        $dateTime->modify(sprintf('+%d minute', 60));
        $scheduledAt = $dateTime->format('Y-m-d H:i:s');
        $dbData['scheduledAt'] = $scheduledAt;
        
        try{
            $this->insertInDb($dbData);
        } catch (Exception $ex) {

        }
        
    }

}
