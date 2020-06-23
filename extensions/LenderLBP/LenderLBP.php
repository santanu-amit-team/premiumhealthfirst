<?php

namespace Extension\LenderLBP;

use Application\Config;
use Application\CrmPayload;
use Application\CrmResponse;
use Application\Helper\Security;
use Application\Http;
use Application\Logger;
use Application\Registry;
use Application\Request;
use Application\Session;
use Database\Connectors\ConnectionFactory;
use Exception;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Application\Model\Configuration;
use Application\Model\Campaign;
use Application\Helper\Provider;

class LenderLBP
{

    const ERROR_MESSAGE = "Your order couldn't be processed. Please try again!";
    const REMOTE_URL = "https://platform.almost20.com/api/data-mining";

    public function __construct()
    {
        $this->config = Config::extensionsConfig('LenderLBP');
        $this->currentStepId = (int) Session::get('steps.current.id');
        $this->previousStepId = (int) Session::get('steps.previous.id');
        $this->accessor = PropertyAccess::createPropertyAccessor();
        $this->crmType = Session::get('crmType');
    }

    public function performSplitLocalAction()
    {
        if (
                empty($this->config['split_enabled']) ||
                !CrmPayload::get('meta.isSplitOrder') ||
                !$this->isSplitStepEnabled(
                        $this->config['split_steps']
                ) ||
                !Session::has(sprintf('steps.%d.orderId', $this->currentStepId)) ||
                !Session::has(sprintf('steps.%d.gatewayId', $this->currentStepId))
        )
        {
            return;
        }

        $isGatewaySelectType = !empty($this->config['gateway_select_type']) ? $this->config['gateway_select_type'] : 'default';

        if ($isGatewaySelectType == 'filebased')
        {
            if (
                    empty($this->config['split_local_file_path']) ||
                    !is_readable($this->config['split_local_file_path'])
            )
            {
                Logger::write(
                        'SplitLogic', sprintf(
                                '%s is unreadable', $this->config['split_local_file_path']
                        )
                );
                return;
            }

            $gatewayList = explode(
                    ',', file_get_contents($this->config['split_local_file_path'])
            );
        }
        else
        {
            if (
                    empty($this->config['split_local_gateways'])
            )
            {
                Logger::write(
                        'SplitLogic', 'Local Gateways field is empty'
                );
                return;
            }
            $gatewayList = explode(
                    ',', $this->config['split_local_gateways']
            );
        }

        $parentGatewayId = Session::get(sprintf('steps.%d.gatewayId', $this->currentStepId), 0);

        if (empty($gatewayList) || !in_array($parentGatewayId, $gatewayList))
        {
            Logger::write('SplitLogic', 'Gateway is not matched!');
            CrmPayload::update(array(
                'meta.bypassCrmHooks' => true,
                'meta.terminateCrmRequest' => true,
            ));

            CrmResponse::replace(array(
                'success' => true,
            ));
        }
    }

    private function isSplitStepEnabled($metaString)
    {
        $enabledSteps = explode(",", $metaString);

        if (empty($enabledSteps))
        {
            return false;
        }

        return in_array(
                Session::get('steps.current.id'), $enabledSteps
        );
    }

    public function performLocalAction()
    {
        Session::set('extensions.LenderLBP.localLBPApplied', false);

        if (
                empty($this->config['local_lbp_enabled']) ||
                !Session::has('steps.1.orderId') ||
                !$this->isLBPEnabled(
                        $this->config['local_lbp_steps'], CrmPayload::get('meta.isSplitOrder')
                ) ||
                !Session::has('steps.1.gatewayId')
        )
        {
            return;
        }

        if (
                empty($this->config['local_lbp_data_path']) ||
                !is_readable($this->config['local_lbp_data_path'])
        )
        {
            Logger::write(
                    'LenderLBP', sprintf(
                            '%s is unreadable', $this->config['local_lbp_data_path']
                    )
            );
            return;
        }

        $parentGatewayId = Session::get('steps.1.gatewayId', 0);

        $gatewayList = explode(
                ',', file_get_contents($this->config['local_lbp_data_path'])
        );

        if (empty($gatewayList) || !in_array($parentGatewayId, $gatewayList))
        {
            Logger::write('LenderLBP', 'Gateway is not matched!');
            return;
        }

        CrmPayload::set('forceGatewayId', $parentGatewayId);
        Session::set('extensions.LenderLBP.localLBPApplied', true);
    }

    public function performRemoteAction()
    {
        if($this->crmType == 'konnektive') {
            return;
        }

        if (
                empty($this->config['remote_lbp_enabled']) || !Session::has('steps.1.orderId') ||
                empty($this->config['remote_lbp_steps']) ||
                !$this->isLBPEnabled(
                        $this->config['remote_lbp_steps'], CrmPayload::get('meta.isSplitOrder')
                ) ||
                Session::get('extensions.LenderLBP.localLBPApplied') === true ||
                !Session::has('steps.1.gatewayId')
        )
        {
            if($this->currentStepId > 1) {
                Session::set('extensions.LenderLBP.only_gateway', true);
            }
            return;
        }
        
        $encryptionKey          = Config::settings('encryption_key');
        $gatewaySwitcherId      = Config::settings('gateway_switcher_id');
        if (empty($encryptionKey) || empty($gatewaySwitcherId)) {
            return;
        }

        $payload = CrmPayload::all();

        $params = $this->prepareRemotePayload($payload);
        $params['parent_product_id'] = Session::get('steps.1.products.0.productId');
        
        $params['category'] = $this->config['remote_lbp_category'];
        if (empty($params['category']))
        {
            $params['category'] = 'ProtectShip';
        }
        if (!empty($params['type']))
        {
            $params['category'] = 'Upsell';
        }

        $url = $this->getLenderURL();

        $response = Http::post($url, http_build_query($params), array(
                    'auth-token' => Config::settings('gateway_switcher_id'),
        ));

        Logger::write('LenderLBP', $response);

        $crmResponse = array('success' => false, 'errors' => array(
                '201clicksError' => self::ERROR_MESSAGE,
        ));

        $insertIntoDatabase = false;
        if (!empty($response['curlError']))
        {
            $insertIntoDatabase = true;
        }

        $response = @json_decode($response, true);

        if (empty($response['status']))
        {
            $insertIntoDatabase = true;
        }

        if ($insertIntoDatabase)
        {
            try
            {
                $dbConnection = $this->getDatabaseConnection();
                $dbConnection->table('payloads_new')->insertIgnore(array(
                    'crm' => $this->crmType,
                    'content' => json_encode($params),
                ));
            }
            catch (Exception $ex)
            {
                Logger::write('LenderLBP', $ex->getMessage());
            }
        }

        CrmPayload::update(array(
            'meta.bypassCrmHooks' => true, 'meta.terminateCrmRequest' => true,
        ));

        CrmResponse::replace(array(
            'success' => true, 'orderId' => Session::get('steps.1.orderId'))
        );
    }

    public function performKonnektiveRemoteAction()
    {
        if($this->crmType != 'konnektive') {
            return;
        }

        if (
                empty($this->config['remote_lbp_enabled']) || !Session::has('steps.1.orderId') ||
                empty($this->config['remote_lbp_steps']) ||
                !$this->isLBPEnabled(
                        $this->config['remote_lbp_steps'], CrmPayload::get('meta.isSplitOrder')
                ) ||
                Session::get('extensions.LenderLBP.localLBPApplied') === true 
        )
        {
            if($this->currentStepId > 1) {
                Session::set('extensions.LenderLBP.only_gateway', true);
            }
            
            return;
        }
        
        $encryptionKey          = Config::settings('encryption_key');
        $gatewaySwitcherId      = Config::settings('gateway_switcher_id');
        if (empty($encryptionKey) || empty($gatewaySwitcherId)) {
            return;
        }

        $payload = CrmPayload::all();

        $gatewayId = $this->checkOrderView(CrmResponse::get('orderId'));

        Session::set(sprintf('steps.%d.gatewayId', $this->currentStepId), $gatewayId);

        $params = $this->prepareRemotePayload($payload);

        for ($ii = 2; $ii <= $this->currentStepId; $ii++)
        {
            $params['upsell_gw_id'][$ii] = Session::get(
                            sprintf('steps.%d.gatewayId', $ii), 0
            );
        }
        $products = Session::get(sprintf('steps.%d.products', $this->currentStepId), 0);

        $params['shipping_price'] = $payload['products'][0]['shippingPrice'];
        $params['parent_order_id'] = Session::get(sprintf('steps.%d.orderId', $this->currentStepId), 0);
        $params['parent_campaign_id'] = $products[0]['campaignId'];
        $params['parent_product_id'] = $products[0]['productId'];

        Session::set('extensions.LenderLBP.konnektive.payload.'.$this->currentStepId, $params);

        $params['category'] = $this->config['remote_lbp_category'];
        if (empty($params['category']))
        {
            $params['category'] = 'ProtectShip';
        }
        if (!empty($params['type']))
        {
            $params['category'] = 'Upsell';
        }

        $url = $this->getLenderURL();

        $response = Http::post($url, http_build_query($params), array(
                    'auth-token' => Config::settings('gateway_switcher_id'),
        ));

        Session::set('extensions.LenderLBP.konnektive.response.'.$this->currentStepId, $response);

        Logger::write('LenderLBP', $response);

        $crmResponse = array('success' => false, 'errors' => array(
                '201clicksError' => self::ERROR_MESSAGE,
        ));

        $insertIntoDatabase = false;
        if (!empty($response['curlError']))
        {
            $insertIntoDatabase = true;
        }

        $response = @json_decode($response, true);

        if (empty($response['status']))
        {
            $insertIntoDatabase = true;
        }

        if ($insertIntoDatabase)
        {
            try
            {
                $dbConnection = $this->getDatabaseConnection();
                $dbConnection->table('payloads_new')->insertIgnore(array(
                    'crm' => $this->crmType,
                    'content' => json_encode($params),
                ));
            }
            catch (Exception $ex)
            {
                Logger::write('LenderLBP', $ex->getMessage());
            }
        }

        CrmPayload::update(array(
            'meta.bypassCrmHooks' => true, 'meta.terminateCrmRequest' => true,
        ));

        CrmResponse::replace(array(
            'success' => true, 'orderId' => Session::get('steps.1.orderId'))
        );
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
        
        $gID = '';
        if (CrmResponse::has('success') && CrmResponse::get('transactionInfo')) {
            $transactionDetailsArray = $mainOrderViewResponse['transactionInfo']['data'];
            $reverseArray = array_reverse($transactionDetailsArray);
            $gID = trim($reverseArray[0]['merchantId']);
            foreach($transactionDetailsArray as $key => $value) {
                if($value['responseType'] == 'SUCCESS') {
                    $gID = $value['merchantId'];
                    break;
                }
            }
        }
        
        return $gID;
    }

    public function postData()
    {
        if($this->crmType == 'konnektive') {
            return;
        }

        $postData = Session::get('extensions.LenderLBP.only_gateway');

        if(!$postData || $this->currentStepId <= 1) {
            return;
        }
        
        $encryptionKey          = Config::settings('encryption_key');
        $gatewaySwitcherId      = Config::settings('gateway_switcher_id');
        if (empty($encryptionKey) || empty($gatewaySwitcherId)) {
            return;
        }
        
        $payload = CrmPayload::all();

        $params = $this->prepareRemotePayload($payload, true);

        $params['category'] = $this->config['remote_lbp_category'];
        if (empty($params['category']))
        {
            $params['category'] = 'ProtectShip';
        }
        if (!empty($params['type']))
        {
            $params['category'] = 'Upsell';
        }
        Session::set('extensions.LenderLBP.params.'.$this->currentStepId, $params);
        
        $url = $this->getLenderURL();

        $response = Http::post($url, http_build_query($params), array(
                    'auth-token' => Config::settings('gateway_switcher_id'),
        ));
        
        $insertIntoDatabase = false;
        if (!empty($response['curlError']))
        {
            $insertIntoDatabase = true;
        }

        $response = @json_decode($response, true);

        if (empty($response['status']))
        {
            $insertIntoDatabase = true;
        }
        
        if ($insertIntoDatabase)
        {
            try
            {
                $dbConnection = $this->getDatabaseConnection();
                $dbConnection->table('payloads_new')->insertIgnore(array(
                    'crm' => $this->crmType,
                    'content' => json_encode($params),
                ));
            }
            catch (Exception $ex)
            {
                Logger::write('LenderLBP', $ex->getMessage());
            }
        }
        
        Session::remove('extensions.LenderLBP.only_gateway');
        Session::set('extensions.LenderLBP.response.'.$this->currentStepId, $response);
        Logger::write('LenderLBP', $response);

    }

    public function postMainData()
    {
        if(Request::attributes()->get('action') === 'prospect' || !$this->config['trail_completion_enabled']) {
        	return;
        }
        $payload = CrmPayload::all();
        $oldPayload = $payload;
        $products = $payload['products'];
        $stepsConfig = $this->config['trail_compeltion'];
        $configArray = array();
        $stepsArray = array();
        $isFound = false;
        foreach($stepsConfig as $each) {
            array_push($stepsArray, $each['step']);
            $configArray[$each['step']] = $each['label'];
        }
        
        if(empty($products))
        {
            return;
        }
        
        foreach ($products as $value)
        {
            if(in_array($value['productId'], $stepsArray)) {
                $isFound = true;
                $selectedProduct = $value['productId'];
                break;
            }
        }
        
        if(!$isFound) {
            return;
        }
        
        $crmResponse = CrmResponse::all();
        
        if(
        	$payload['meta.isSplitOrder'] || 
        	empty($crmResponse['success'])
        ) {
        	return;
        }
        
        $encryptionKey          = Config::settings('encryption_key');
        $gatewaySwitcherId      = Config::settings('gateway_switcher_id');
        if (empty($encryptionKey) || empty($gatewaySwitcherId)) {
            $backupData = new BackupData;
            $backupData->backupMainData();
            return;
        }

        $params = $this->prepareRemotePayload($payload);
        
        $params['category']              = $configArray[$selectedProduct];
        $params['parent_order_id']       = $crmResponse['orderId'];
        $params['parent_campaign_id']    = $params['campaignId'];
        $params['customerId']            = $crmResponse['customerId'];
        $params['main_order_gateway_id'] = $crmResponse['gatewayId'];
        
        if($this->crmType == 'konnektive') {

            //check flag is set in session and import upsell method 
            $kk_iu_flag = Session::get('extensions.LenderLBP.Post_KK_IU_Flag');
            if($this->currentStepId > 1 && !strcmp($params['method'], 'importUpsell') && !empty($kk_iu_flag)) {
                $params['split_charge_id'] = $kk_iu_flag;
            }


            $gatewayId = $this->checkOrderView($crmResponse['orderId']);
            $params['main_order_gateway_id'] = $gatewayId;
            
            $url = $this->getLenderURL();
            
            CrmResponse::update($crmResponse);
        } else {
            $url = $this->getLenderURL();
        }

        $response = Http::post($url, http_build_query($params), array(
                    'auth-token' => Config::settings('gateway_switcher_id'),
        ));
        
        $insertIntoDatabase = false;
        if (!empty($response['curlError']))
        {
            $insertIntoDatabase = true;
        }

        $response = @json_decode($response, true);

        if (empty($response['status']))
        {
            $insertIntoDatabase = true;
        }
        
        if ($insertIntoDatabase)
        {
            try
            {
                $dbConnection = $this->getDatabaseConnection();
                $dbConnection->table('payloads_new')->insertIgnore(array(
                    'crm' => $this->crmType,
                    'content' => json_encode($params),
                ));
            }
            catch (Exception $ex)
            {
                Logger::write('LenderLBP', $ex->getMessage());
            }
        }

        if($this->crmType == 'konnektive') {
            $responseArray = $response;
            $disableUpsellLinking = $this->config['unlink_trial_data'];
            if(empty($kk_iu_flag) && array_key_exists('split_charge_id', $responseArray) && !$disableUpsellLinking) {
                Session::set('extensions.LenderLBP.Post_KK_IU_Flag', $responseArray['split_charge_id']);
            }
        }
       
        Session::set('extensions.LenderLBP.Trial.params.'.$this->currentStepId, $params);
        Session::set('extensions.LenderLBP.Trial.url.'.$this->currentStepId, $url);
        Session::set('extensions.LenderLBP.Trial.response.'.$this->currentStepId, $response);
        Logger::write('LenderLBP', $response);
        CrmPayload::update($oldPayload);
    }

    private function prepareRemotePayload(&$params, $onlyGateway = false)
    {
        $type = empty($params['meta.isSplitOrder']) ? $this->currentStepId : 0;
        $payload = array_replace_recursive(array(
            'parent_order_id' => Session::get('steps.1.orderId', 0),
            'parent_campaign_id' => Session::get('steps.1.products.0.campaignId', 0),
            'type' => $type,
                ), $params);

        $payload['method'] = $this->accessor->getValue($params, '[meta.crmMethod]');

        $payload['creditCardType'] = $this->accessor->getValue($params, '[cardType]');
        $payload['cdc'] = Security::encrypt(
                        $this->accessor->getValue($params, '[cardNumber]'), Config::settings('encryption_key')
        );
        $payload['scrt'] = Security::encrypt(
                        $this->accessor->getValue($params, '[cvv]'), Config::settings('encryption_key')
        );
        $payload['expirationDate'] = sprintf(
                '%s%s', $this->accessor->getValue($params, '[cardExpiryMonth]'), $this->accessor->getValue($params, '[cardExpiryYear]')
        );
        unset(
                $params['cardType'], $payload['cardNumber'], $params['cvv'], $payload['cardExpiryMonth'], $params['cardExpiryYear']
        );
        $payload['tranType'] = 'Sale';
        $payload['main_order_gateway_id'] = Session::get('steps.1.gatewayId', 0);
        $payload['only_gateway'] = false;
        $payload['upsell_gw_id'] = array();
        for ($ii = 2; $ii <= $this->previousStepId; $ii++)
        {
            $payload['upsell_gw_id'][$ii] = Session::get(
                            sprintf('steps.%d.gatewayId', $ii), 0
            );
        }
        if($onlyGateway) {
            $payload['upsell_gw_id'][$this->currentStepId] = CrmResponse::get('gatewayId');
            $payload['upsell_order_id'][$this->currentStepId] = CrmResponse::get('orderId');
            $payload['only_gateway'] = true;
        } else {
            $stepsConfig = $this->config['trail_compeltion'];
            $stepsForTrialCompletion = array();
            foreach($stepsConfig as $each) {
                //$temp = json_decode($each, true);
                array_push($stepsForTrialCompletion, $each['step']);
            }

            if(
                empty($params['meta.isSplitOrder']) && 
                (
                        !empty($this->config['remote_lbp_enabled']) 
                        && in_array($this->currentStepId, $this->config['remote_lbp_steps']))
                )
            {
                Session::set('skipCapture.'.$this->currentStepId, true);
            }
        }
        $payload['upsell_gw_id'] = array_filter($payload['upsell_gw_id']);

        $product = $this->accessor->getValue($params, '[products][0]');
        if (empty($product))
        {
            $product = array();
        }
        $payload['productId'] = $this->accessor->getValue($product, '[productId]');
        $payload['product_qty'] = $this->accessor->getValue($product, '[productQuantity]');
        $payload['dynamic_product_price'] = $this->accessor->getValue($product, '[productPrice]');
        $payload['shippingId'] = $this->accessor->getValue($product, '[shippingId]');
        $payload['isPrepaid'] = Session::get('steps.meta.isPrepaidFlow', 0);
        unset($payload['products']);

        $affiliates = $this->accessor->getValue($params, '[affiliates]');
        if (empty($affiliates))
        {
            $affiliates = array();
        }
        foreach (array_keys($affiliates) as $key)
        {
            if ($key === 'clickId')
            {
                $affiliates['click_id'] = $affiliates[$key];
            }
            else
            {
                $affiliates[strtoupper($key)] = $affiliates[$key];
            }
            unset($affiliates[$key]);
        }
        $payload = array_replace_recursive($payload, $affiliates);
        unset($payload['affiliates']);

        $payload['notes'] = sprintf(
                '%s | %s', $this->accessor->getValue($params, '[userIsAt]'), $this->accessor->getValue($params, '[userAgent]')
        );
        unset($payload['userIsAt'], $payload['userAgent']);

        $metaKeys = preg_grep('/^meta\..+$/', array_keys($payload));
        foreach ($metaKeys as $metaKey)
        {
            unset($payload[$metaKey]);
        }
 
        $campaignInfo = $params['products'][0];
        if(!empty($campaignInfo['enableBillingModule']))
        {
            $payload['offerId'] = $campaignInfo['offerId'];
            $payload['billingModelId'] = $campaignInfo['billingModelId'];
            $payload['trialProductId'] = $campaignInfo['trialProductId'];
            $payload['trialProductPrice'] = $campaignInfo['trialProductPrice'];
            $payload['trialProductQuantity'] = $campaignInfo['trialProductQuantity'];
        }

        return $payload;
    }

    private function isLBPEnabled($metaString, $isSplitOrder)
    {
        $prefix = $isSplitOrder ? 'SPLIT' : 'MAIN';
        $selectedLines = array_values(preg_grep(
                        '/^' . $prefix . '/', array_map(function ($value)
                        {
                            return strtoupper(trim($value));
                        }, explode("\n", $metaString))
        ));

        if (empty($selectedLines))
        {
            return false;
        }

        $stepsList = trim(str_replace(
                        ':', '', str_replace($prefix, '', $selectedLines[0])
        ));

        $enabledSteps = array_map(function ($value)
        {
            return trim($value);
        }, explode(',', $stepsList));

        return in_array(
                Session::get('steps.current.id'), $enabledSteps
        );
    }

    public function saveSettings()
    {
        if (!extension_loaded('pdo_sqlite') && !extension_loaded('pdo_sqlite'))
        {
            throw new Exception("Sqlite PDO extension is not installed.");
        }

        if(Request::form()->get('remote_lbp_enabled') || Request::form()->get('trail_completion_enabled')){
            if(!Provider::checkExtensions('DataCapture')){
                throw new Exception("Please install data capture extension for using this feature!");
            }
        }

        $this->checkEncryption();
        $localLbpEnabled = Request::form()->get('local_lbp_enabled');
        $localLbpDataPath = Request::form()->get('local_lbp_data_path');

        if ($localLbpEnabled && !is_readable($localLbpDataPath))
        {
            throw new Exception("Local LBP data file is not readable!");
        }

        $lenderlbpDbFilePath = STORAGE_DIR . DS . 'lenderlbp.sqlite';

        if (!file_exists($lenderlbpDbFilePath))
        {
            file_put_contents($lenderlbpDbFilePath, '');
        }

        if (!is_writable($lenderlbpDbFilePath))
        {
            throw new Exception(
            sprintf("File %s couldn't be created.", $lenderlbpDbFilePath)
            );
        }

        $this->createTableIfNotExists();
        
        
        $localSplitEnabled = Request::form()->get('split_enabled');
        $localSplitSelectType = Request::form()->get('gateway_select_type');
        $localSplitDataPath = Request::form()->get('split_local_file_path');
        
        if ($localSplitEnabled && empty($localSplitSelectType))
        {
            throw new Exception("Gateways select type is missing for Split Logic!");
        }

        if ($localSplitEnabled && $localSplitSelectType == 'filebased' && !is_readable($localSplitDataPath))
        {
            throw new Exception("Split Logic data file is not readable!");
        }
    }

    private function getDatabaseConnection()
    {
        $factory = new ConnectionFactory();
        return $factory->make(array(
                    'driver' => 'sqlite',
                    'database' => STORAGE_DIR . DS . 'lenderlbp.sqlite',
        ));
    }

    public function createTableIfNotExists()
    {
        $sql = "CREATE TABLE IF NOT EXISTS 'payloads_new' ("
                . "     id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,"
                . "     crm TEXT NOT NULL,"
                . "     content TEXT NOT NULL,"
                . "     response TEXT DEFAULT NULL,"
                . "     processedAt DATETIME DEFAULT NULL,"
                . "     createdAt DATETIME DEFAULT CURRENT_TIMESTAMP"
                . ")";

        $this->getDatabaseConnection()->query($sql);
        
        $sql = "CREATE TABLE IF NOT EXISTS 'payloads_remote' ("
                . "     id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,"
                . "     crm TEXT NOT NULL,"
                . "     postUrl TEXT DEFAULT NULL,"
                . "     content TEXT NOT NULL,"
                . "     response TEXT DEFAULT NULL,"
                . "     processedAt DATETIME DEFAULT NULL,"
                . "     createdAt DATETIME DEFAULT CURRENT_TIMESTAMP"
                . ")";

        $this->getDatabaseConnection()->query($sql);
        return true;
    }

    public function postSplitData() {

        if(Request::attributes()->get('action') === 'prospect' || !$this->config['trail_completion_enabled']) {
        	return;
        }
        $payload = CrmPayload::all();
        $products = $payload['products'];
        $stepsConfig = $this->config['trail_compeltion'];
        $configArray = array();
        $stepsArray = array();
        $isFound = false;
        foreach($stepsConfig as $each) {
            array_push($stepsArray, $each['step']);
            $configArray[$each['step']] = $each['label'];
        }
        
        if(empty($products))
        {
            return;
        }
        
        foreach ($products as $value)
        {
            if(in_array($value['productId'], $stepsArray)) {
                $isFound = true;
                $selectedProduct = $value['productId'];
                break;
            }
        }
        
        if(!$isFound) {
            return;
        }
        
        $crmResponse = CrmResponse::all();
        
        if( !$payload['meta.isSplitOrder'] || empty($crmResponse['success'])) {
        	return;
        }
        
        $encryptionKey          = Config::settings('encryption_key');
        $gatewaySwitcherId      = Config::settings('gateway_switcher_id');
        if (empty($encryptionKey) || empty($gatewaySwitcherId)) {
            return;
        }

        $params = $this->prepareRemotePayload($payload);
        
        $params['category']              = $configArray[$selectedProduct];
        $params['parent_order_id']       = $crmResponse['orderId'];
        $params['parent_campaign_id']    = $params['campaignId'];
        $params['customerId']            = $crmResponse['customerId'];
        $params['main_order_gateway_id'] = $crmResponse['gatewayId'];

        if($this->crmType == 'konnektive') {
            //check flag is set in session and import upsell method 
            $kk_iu_flag = Session::get('extensions.LenderLBP.Post_KK_IU_Split_Flag');
            if($this->currentStepId > 1 && !strcmp($params['method'], 'importUpsell') && !empty($kk_iu_flag)) {
                $params['split_charge_id'] = $kk_iu_flag;
            }


            $gatewayId = $this->checkOrderView($crmResponse['orderId']);
            $params['main_order_gateway_id'] = $gatewayId;
            
            $url = $this->getLenderURL();
            
            CrmResponse::update($crmResponse);
        } 
        else {
            $url = $this->getLenderURL();
        }

        $response = Http::post($url, http_build_query($params), array(
                    'auth-token' => Config::settings('gateway_switcher_id'),
        ));
        
        $insertIntoDatabase = false;
        if (!empty($response['curlError']))
        {
            $insertIntoDatabase = true;
        }

        $response = @json_decode($response, true);

        if (empty($response['status']))
        {
            $insertIntoDatabase = true;
        }
        
        if ($insertIntoDatabase)
        {
            try
            {
                $dbConnection = $this->getDatabaseConnection();
                $dbConnection->table('payloads_new')->insertIgnore(array(
                    'crm' => $this->crmType,
                    'content' => json_encode($params),
                ));
            }
            catch (Exception $ex)
            {
                Logger::write('LenderLBP', $ex->getMessage());
            }
        }

        Session::set('extensions.LenderLBP.Trial.Split.params.'.$this->currentStepId, $params);
        Session::set('extensions.LenderLBP.Trial.Split.url.'.$this->currentStepId, $url);
        Session::set('extensions.LenderLBP.Trial.Split.response.'.$this->currentStepId, $response);
        Logger::write('LenderLBP', $response);
        
    }
    
    public function setTrialSettings()
    {
        if(
                Request::attributes()->get('action') === 'prospect' || 
                !$this->config['trail_completion_enabled']) 
        {
        	return;
        }
        
        Session::set('is_trial_enabled', true);
        
    }


    private function checkEncryption() {
        
        $encryptionKey          = Config::settings('encryption_key');
        $gatewaySwitcherId      = Config::settings('gateway_switcher_id');
        $trail_completion_enabled = Request::form()->get('trail_completion_enabled');

        if($trail_completion_enabled && empty( $encryptionKey) ) {
            throw new Exception("Encryption key is missing. Please check in settings section.");
        }
        
        if($trail_completion_enabled && empty( $gatewaySwitcherId) ) {
            throw new Exception("Instance ID is missing. Please check in settings section.");
        }
    }
    
    private function getLenderURL()
    {
        $debug='';
        if (DEV_MODE)
        {
            $debug='?debug=yes';
        }
        if($this->crmType == 'konnektive') {
            
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
}
