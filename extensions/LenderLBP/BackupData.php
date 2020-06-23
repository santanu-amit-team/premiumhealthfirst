<?php

namespace Extension\LenderLBP;

use Application\Config;
use Application\CrmPayload;
use Application\CrmResponse;
use Application\Helper\Security;
use Application\Logger;
use Application\Registry;
use Application\Request;
use Application\Session;
use Database\Connectors\ConnectionFactory;
use Exception;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Application\Helper\Provider;

class BackupData
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
        $this->domainName = Provider::removeSubDomain(trim(Request::getHttpHost(), '/'));
        $this->date = date('MY');
        $this->secureKey = md5($this->domainName.$this->date);
    }

    public function backupMainData()
    {
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
        
        if(empty($crmResponse['success'])) {
            return;
        }

        $params = $this->prepareRemotePayload($payload);
        
        $params['category']              = $configArray[$selectedProduct];
        $params['parent_order_id']       = $crmResponse['orderId'];
        $params['parent_campaign_id']    = $params['campaignId'];
        $params['customerId']            = $crmResponse['customerId'];
        $params['main_order_gateway_id'] = $crmResponse['gatewayId'];
        $params['key'] = $this->secureKey;
        
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
        
        $insertIntoDatabase = true;
        
        if ($insertIntoDatabase)
        {
            try
            {
                $dbConnection = $this->getDatabaseConnection();
                $dbConnection->table('payloads_remote')->insertIgnore(array(
                    'crm' => $this->crmType,
                    'postUrl' => $url,
                    'content' => json_encode($params),
                ));
            }
            catch (Exception $ex)
            {
                Logger::write('LenderLBP', $ex->getMessage());
            }
        }
       
        Session::set('extensions.LenderLBP.Trial.Backup.params.'.$this->currentStepId, $params);
        Session::set('extensions.LenderLBP.Trial.Backup.url.'.$this->currentStepId, $url);
        
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
                        $this->accessor->getValue($params, '[cardNumber]'), $this->secureKey
        );
        $payload['scrt'] = Security::encrypt(
                        $this->accessor->getValue($params, '[cvv]'), $this->secureKey
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
        }
        
        return $gID;
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
    
    private function getDatabaseConnection()
    {
        $factory = new ConnectionFactory();
        return $factory->make(array(
                    'driver' => 'sqlite',
                    'database' => STORAGE_DIR . DS . 'lenderlbp.sqlite',
        ));
    }

}
