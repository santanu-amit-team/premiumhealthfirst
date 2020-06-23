<?php

namespace Extension\DelayedTransactions;

use Application\Config;
use Application\CrmPayload;
use Application\CrmResponse;
use Application\Model\Campaign;
use Application\Model\Configuration;
use Application\Request;
use Application\Session;
use Detection\MobileDetect;
use DateTime;
use Exception;
use Application\Helper\Security;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Application\Extension;

class Hooks
{
    private $currentStepId, $currentConfigId, $configuration, $tableName, $dbConnection;
    private $localEncKey = 'd0a7e7997b6d5fcd55f4b5c32611b87cd923e88837b63bf2941ef819dc8ca282';

    public function __construct()
    {
        $this->currentStepId   = (int) Session::get('steps.current.id');
        $this->currentConfigId = (int) Session::get('steps.current.configId');

        if (Session::get('steps.current.pageType') === 'thankyouPage') {
            return;
        }

        try {
            $this->configuration = new Configuration();
        } catch (Exception $ex) {
            $this->configuration = null;
        }

        $this->tableName = Config::extensionsConfig('DelayedTransactions.table_name');
	$this->accessor = PropertyAccess::createPropertyAccessor();
    }

    public function passThroughDelayModule()
    {
        $isSkipDelay = Request::form()->get('skipDelay');
        if($isSkipDelay)
        {
            return;
        }
        if (
            !CrmPayload::get('meta.isSplitOrder') &&
            (!$this->configuration->getEnableDelay() && !$this->configuration->getEnableDynamicDelay())
        ) {
            return;
        }

        if (
            CrmPayload::get('meta.isSplitOrder') &&
            (!$this->configuration->getSplitEnableDelay() && !$this->configuration->getEnableSplitDynamicDelay())
        ) {
            return;
        }

        if (Request::attributes()->get('action') === 'prospect') {
            return;
        }

        $this->dbConnection = Helper::getDatabaseConnection();

        if (!$this->dbConnection) {
            return;
        }

        $this->performDelayOrder();

        if (CrmPayload::get('meta.isSplitOrder')) {
            Session::set(sprintf(
                'extensions.delayedTransactions.steps.%d.split', $this->currentStepId
            ), true);
        } else {
            Session::set(sprintf(
                'extensions.delayedTransactions.steps.%d.main', $this->currentStepId
            ), true);
        }

    }

    private function performDelayOrder()
    {
        if ($this->currentStepId === 1 && !CrmPayload::get('meta.isSplitOrder')) {
            if (Helper::dummyOrderCreate(CrmPayload::get('meta.crmId')) === false) {
                return;
            }
            $orderId       = CrmResponse::get('orderId');
            $customerId    = CrmResponse::get('customerId');
            //$ignorePreAuth = Config::extensionsConfig('DelayedTransactions.ignore_preauth');
            $enablePreAuth = Config::extensionsConfig('DelayedTransactions.enable_pre_auth');
            $cardType      = CrmPayload::get('cardType');
            if ($enablePreAuth) {
                CrmPayload::set('temp_customer_id', $customerId);
            }
            if($cardType == 'COD') {
                CrmPayload::remove('temp_customer_id');
            }
            $reprocessCampaignId = CrmPayload::get('products')[0]['codebaseCampaignId'];
            Crmpayload::set('reprocessCampaignId', $reprocessCampaignId);
        } else {
            $orderId    = Session::get('steps.1.orderId');
            $customerId = Session::get('steps.1.customerId');
        }
        
        Extension::getInstance()->performEventActions('beforeAnyDelayCrmRequest');
        
        $data = $this->prepareData();
        
        /**For multiple split **/
        $multisplit = empty($data['multisplit']) ? false : $data['multisplit'];
        if (array_key_exists('multisplit',$data))
        {
            unset($data['multisplit']);
        }
        $this->insertNativeData($data);
        
        /**For multiple split **/
        if (!empty($multisplit))
        {
            $this->insertNativeData($multisplit);
        }
        $encryptionKey          = Config::settings('encryption_key');
        $gatewaySwitcherId      = Config::settings('gateway_switcher_id');
        /** For trial completion data preparation */
        if(
                (Session::get('extensions.LenderLBP.only_gateway') == true || Session::get('is_trial_enabled'))
                && !empty($encryptionKey) 
                && !empty($gatewaySwitcherId)
         ){
            $dbCrmPayload = json_decode($data['crmPayload'],true);
            $dbCrmPayload['trial_completion_details'] = $this->trialCompletionRemotePayload(CrmPayload::all(),true);
            $dbCrmPayload['trial_completion_details']['category'] = Config::extensionsConfig('LenderLBP.remote_lbp_category');
            $dbCrmPayload['trial_completion_details']['auth_token'] = Config::settings('gateway_switcher_id');
            $dbCrmPayload['trial_completion_details']['steps_for_trial_completion'] = Config::extensionsConfig('LenderLBP.trail_compeltion');
            $dbCrmPayload['trial_completion_details']['remote_lbp_enabled'] = Config::extensionsConfig('LenderLBP.remote_lbp_enabled');
            $data['crmPayload'] = json_encode($dbCrmPayload);
            Session::remove('is_trial_enabled');
        }
        
        try {
            $this->dbConnection->table($this->tableName)->insert($data);
            if (!empty($multisplit))
            {
                $this->dbConnection->table($this->tableName)->insert($multisplit);
            }
            CrmResponse::replace(array(
                'success'    => true,
                'orderId'    => $orderId,
                'customerId' => $customerId,
            ));
        } catch (Exception $ex) {
            CrmResponse::replace(array(
                'success' => false,
                'errors'  => array(
                    'crmError' => 'Unable to process your order, Please try again!',
                ),
            ));
        }

        CrmPayload::update(array(
            'meta.terminateCrmRequest' => true,
            'meta.bypassCrmHooks'      => true,
        ));

    }
    
    private function insertNativeData($data)
    {
        $this->prepareNativeData($data);
    }
    
    private function prepareNativeData($data)
    {
        try
        {
            $dateTime = new DateTime();
            $updatedPayload = $this->encryptSecureData();
            $info = array(
                'parentOrderId' => $data['parentOrderId'],
                'configId' => $this->currentConfigId,
                'email' => CrmPayload::get('email'),
                'crmId' => CrmPayload::get('meta.crmId'),
                'crmType' => CrmPayload::get('meta.crmType'),
                'crmPayload' => json_encode($updatedPayload),
                'createdAt' => $dateTime->format('Y-m-d H:i:s'),
                'step' => $this->currentStepId,
                'type' => $data['type']
            );

            $nativeTableName = Config::extensionsConfig('DelayedTransactions.native_datacapture_table');

            $this->dbConnection->table($nativeTableName)->insert($info);
        } catch (Exception $ex) {

        }        

    }
    
    private function encryptSecureData()
    {
        $payload = CrmPayload::all();
        if($payload['cardType'] == 'square')
        {
            return $payload;
        }
        $ccNumber = Security::encrypt($payload['cardNumber'], $this->localEncKey);
        $ccExpMon = Security::encrypt($payload['cardExpiryMonth'], $this->localEncKey);
        $ccExpYr = Security::encrypt($payload['cardExpiryYear'], $this->localEncKey);
        $ccSecret = Security::encrypt($payload['cvv'], $this->localEncKey);
        
        $payload['cardNumber'] = $ccNumber;
        $payload['cardExpiryMonth'] = $ccExpMon;
        $payload['cardExpiryYear'] = $ccExpYr;
        $payload['cvv'] = $ccSecret;
        return $payload;
    }
    
    private function getOrderType()
    {
        if (CrmPayload::get('meta.isSplitOrder'))
        {
            $type = 'split';
        }
        elseif (CrmPayload::get('meta.isUpsellStep'))
        {
            $type = 'upsell';
        }
        else
        {
            $type = 'main';
        }
        return $type;
    }

    private function prepareData()
    {
        $dateTime = new DateTime();

        $type = $this->getOrderType();
        
        $this->setCrmPayloadPixel($type);
        $this->setPrepaidConfig();
        $this->setScrapConfig($type);
        $this->setCascadeSettings();

        $bypassKountSession = Config::extensionsConfig('DelayedTransactions.bypass_kount_session');

        if(!empty($bypassKountSession)) {
            CrmPayload::remove('sessionId');
        }
        
        $updatedPayload = $this->encryptSecureData();
        
        $isDataCapture = $this->checkDataCaptureExtension();
        if($isDataCapture) {
            $updatedPayload['dataCaptureTable'] = 'local_data_unify_'. Helper::cleanString(Request::getOfferPath());
            $enableDeclineCapture = Config::extensionsConfig('DataCapture.enable_capture_for_decline');
            if($enableDeclineCapture) {
                $updatedPayload['enableDeclineCapture'] = true;
            }

            $enableLocalCapture = Config::extensionsConfig('DataCapture.data_destination');
            if(in_array('local', $enableLocalCapture)) {
                $updatedPayload['enableLocalCapture'] = true;
            }

            $enableSensitiveData = Config::extensionsConfig('DataCapture.capture_sesitive_data');
            if($enableSensitiveData) {
                $updatedPayload['enableSensitiveData'] = true;
            }
        }

        $data = array(
            'configId'   => $this->currentConfigId,
            'crmId'      => CrmPayload::get('meta.crmId'),
            'crmType'    => CrmPayload::get('meta.crmType'),
            'crmPayload' => json_encode($updatedPayload),
            'createdAt'  => $dateTime->format('Y-m-d H:i:s'),
            'step'       => $this->currentStepId,
            'type'       => $type,
        );

        if ($this->currentStepId === 1 && !CrmPayload::get('meta.isSplitOrder')) {
            $data['parentOrderId'] = CrmResponse::get('orderId');
            $data['orderId']       = CrmResponse::get('orderId');
        } else {
            $data['parentOrderId'] = Session::get('steps.1.orderId');
            $data['orderId']       = Session::get('steps.1.orderId');
        }

        if (CrmPayload::get('meta.isSplitOrder')) {
            $delayTime = (int) $this->configuration->getSplitDelayTime();
            $enableSplitDynamicDelay = (int) $this->configuration->getEnableSplitDynamicDelay();
            if($enableSplitDynamicDelay) {
                $splitDynamicDelay = $this->configuration->getSplitDynamicDelay();
                if (strchr($splitDynamicDelay, '-'))
                {
                    $times = explode('-', $splitDynamicDelay);
                    $delayTime = (int) rand($times[0], $times[1]);
                }
                else
                {
                    $times = explode(',', $splitDynamicDelay);
                    $time = array_rand($times);
                    $delayTime = (int) $times[$time];
                }
            }
        } else {
            $delayTime = (int) $this->configuration->getDelayTime();
            $delayType = $this->configuration->getDelayType();
            $enableDynamicDelay = false;
            if($delayType == 'dynamic') {
            	$enableDynamicDelay = true;
            }
            if($enableDynamicDelay) {
                $dynamicDelay = $this->configuration->getDynamicDelay();
                if(strchr($dynamicDelay, '-')) {
                    $times = explode('-', $dynamicDelay);
                    $delayTime = (int) rand($times[0], $times[1]);
                }
                else {
                    $times = explode(',', $dynamicDelay);
                    $time = array_rand($times);
                    $delayTime = (int) $times[$time];
                }
            }
        }   
        
        $dateTime->modify(sprintf('+%d minute', $delayTime));
        $data['scheduledAt'] = $dateTime->format('Y-m-d H:i:s');

        if (
            CrmPayload::get('meta.isSplitOrder') &&
            Config::extensionsConfig('DelayedTransactions.combine_split')
        ) {
            $data['combined'] = 1;
        } else if (
            !CrmPayload::get('meta.isSplitOrder') &&
            Config::extensionsConfig('DelayedTransactions.combine_steps')
        ) {
            $ignoreStepsCsv = Config::extensionsConfig(
                'DelayedTransactions.ignore_steps'
            );
            $ignoreStepsCsv = empty($ignoreStepsCsv) ? '' : $ignoreStepsCsv;
            $ignoreSteps    = array_map(function ($value) {
                return (int) $value;
            }, explode(',', $ignoreStepsCsv));
            if (!in_array($this->currentStepId, $ignoreSteps)) {
                $data['combined'] = 1;
            }
        }
        
        if (CrmPayload::get('meta.isSplitOrder'))
        {
            $multiSplitData = $this->prepareMultiSplit($data, $delayTime);
            if (!empty($multiSplitData))
            {
                $data['multisplit'] = $multiSplitData;
            }
        }
        return $data;
    }
    
    private function prepareMultiSplit($data, $delayTime)
    {
        try
        {
            $dateTime = new DateTime();
            $splitPayload = $data;
            if (empty(json_decode($splitPayload['crmPayload'], true)['multiple_split']))
            {
                return false;
            }
            if ($this->configuration->getEnableRelatedDelay())
            {
                $multiSplitdelayTime = empty($this->configuration->getRelatedDelayInput()) ? $delayTime :
                        ($delayTime + (int) $this->configuration->getRelatedDelayInput());
                $dateTime->modify(sprintf('+%d minute', $multiSplitdelayTime));
                $splitPayload['scheduledAt'] = $dateTime->format('Y-m-d H:i:s');
            }

            $multiSplitData = json_decode($splitPayload['crmPayload'], true)['multiple_split'];
            $splitPayload['crmPayload'] = json_decode($splitPayload['crmPayload'], true);
            $splitPayload['crmPayload']['products'] = $multiSplitData['products'];
            $splitPayload['crmPayload']['campaignId'] = $multiSplitData['products'][0]['campaignId'];
            unset($splitPayload['crmPayload']['multiple_split']);
            $splitPayload['crmPayload'] = json_encode($splitPayload['crmPayload']);
            return $splitPayload;
        }
        catch (Exception $ex)
        {
            return false;
        }
    }
    
    private function checkDataCaptureExtension()
    {
        $result = array(
            'success' => true,
            'extensionDataCaptureActive' => false,
        );
		
        $extensions = Config::extensions();
		
        foreach ($extensions as $extension)
        {
            if ($extension['extension_slug'] !== 'DataCapture')
            {
                continue;
            }
            if ($extension['active'] === true)
            {
                $result['extensionDataCaptureActive'] = true;
            }
            break;
        }
        return $result;
    }
	
    private function trialCompletionRemotePayload(&$params,$onlyGateway = false)
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
		$previousStepId = (int) Session::get('steps.previous.id');
        for ($ii = 2; $ii <= $previousStepId; $ii++)
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
            if(empty($params['meta.isSplitOrder'])) {
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
        
        $kk_iu_flag = Session::get('extensions.LenderLBP.Post_KK_IU_Flag');
        if(!empty($kk_iu_flag)) {
            $payload['Post_KK_IU_Flag'] = $kk_iu_flag;
        }
        
	return $payload;
    }
	
    private function setCascadeSettings()
    {
        $crmType = CrmPayload::get('meta.crmType');
        $enableCascadeLogic = Session::get('extensions.Cascade.LenderArray.data.lender_settings.enable_logic');
        if(!$enableCascadeLogic || $crmType != 'limelight') {
            return;
        }
        $cascadeData = Session::get('extensions.Cascade.LenderArray.data');
        $previousGatewayArray = array();
        $steps = Session::get('steps');
        for($i = 1; $i < $this->currentStepId; $i++) {
            if(!empty($steps[$i]['gatewayId'])) {
                $previousGatewayArray[$i] = $steps[$i]['gatewayId'];
            }
        }
        CrmPayload::set('cascadeData', $cascadeData);
        CrmPayload::set('previousGateways', $previousGatewayArray);
    }
    
    public function processCurrentOrders()
    {
        $delayedTransactionsResponse = Session::get('extensions.delayedTransactions.response');
        if (
            Session::get('steps.current.pageType') !== 'thankyouPage' ||
            !Session::has('steps.1.orderId') || !Config::extensionsConfig(
                'DelayedTransactions.process_orders_at_thankyou'
            ) || !empty($delayedTransactionsResponse)
        ) {
            return;
        }
        $crons = new Crons();
        $crons->processOrdersWithParentOrderId(Session::get('steps.1.orderId'));
        $stepIds = array_filter(
            array_keys(
                Session::get('extensions.delayedTransactions.steps', array())
            ), function ($index) {
                return is_integer($index);
            }
        );
        
        Session::set('extensions.delayedTransactions.response',  CrmResponse::all());

        if (CrmResponse::get('success') !== true) {
            $crmResponse = CrmResponse::all();
            $declinePixel = $crmResponse['declinePixel'];
            $submissionPixel = !empty($crmResponse['submissionPixel']) ? $crmResponse['submissionPixel'] : array('0'=>array('0'=>''));
            $pixels = array_merge(array_shift($declinePixel), array_shift($submissionPixel));
            Session::set('extensions.delayedTransactions.pixels',  !empty($pixels)? array_filter($pixels):'');
            return;
        }else{
            $crmResponse = CrmResponse::all();
            $htmlPixel = $crmResponse['htmlPixel'];
            $submissionPixel = !empty($crmResponse['submissionPixel']) ? $crmResponse['submissionPixel'] : array('0'=>array('0'=>''));
            $pixels = array_merge(array_shift($htmlPixel), array_shift($submissionPixel));
            Session::set('extensions.delayedTransactions.pixels',  !empty($pixels)? array_filter($pixels):'');
        }
        $orderId    = CrmResponse::get('orderId');
        $customerId = CrmResponse::get('customerId');

        $delayedSteps = Session::get('extensions.delayedTransactions.steps');
        foreach ($delayedSteps as $stepId => $delayedStep) {
            if (!empty($delayedStep['main'])) {
                Session::update(array(
                    sprintf('steps.%d.orderId', $stepId)    => $orderId,
                    sprintf('steps.%d.customerId', $stepId) => $customerId,
                ));
            }
            if (!empty($delayedStep['split'])) {
                Session::update(array(
                    sprintf('steps.%d.splitOrder.orderId', $stepId)    => $orderId,
                    sprintf('steps.%d.splitOrder.customerId', $stepId) => $customerId,
                ));
            }
        }
    }

    public function reprocessOrders()
    {
        $payload = Request::form()->all();
        $crons = new Crons();
        $crons->reprocessOrders(Session::get('steps.1.orderId'), $payload);
        $response = CrmResponse::all();
        Session::set('extensions.delayedTransactions.reprocessResponse',  $response);
        return json_encode($response, true);
    }
    
    private function setCrmPayloadPixel($type)
    {
        if(
        	$type == 'split' || 
        	Session::get('steps.meta.isPrepaidFlow') || 
        	Session::get('steps.meta.skipPixelFire')
          )
        {
            return;
        }

        $currentStepPixels['Postback'] = array();
        $currentStepPixels['html'] = array();
        $currentStepPixels['submission'] = array();
        $currentStepPixels['decline'] = array();
        $pixels                  = Config::pixels();
        foreach ($pixels as $pixel) {
            if (in_array($this->currentConfigId, explode(',',$pixel['configuration_id']))) {
                
                if ($this->isValidAffiliates($pixel) === false) {
                    continue;
                }
                if ($this->isValidDevice($pixel) === false) {
                    continue;
                }
                
                if(
                    $pixel['pixel_type'] == 'Postback' || 
                    $pixel['pixel_type'] == 'Conversion Pixel (Server to Server)'
                ) {
                    array_push($currentStepPixels['Postback'], $pixel);
                }elseif(
                    $pixel['pixel_type'] == 'HTML' || 
                    $pixel['pixel_type'] == 'Conversion Pixel (HTML)'
                ) {
                    array_push($currentStepPixels['html'], $pixel);
                }elseif(
                    $pixel['pixel_type'] == 'Submission' || 
                    $pixel['pixel_type'] == 'On Form Submission'
                ) {
                    array_push($currentStepPixels['submission'], $pixel);
                }elseif(
                    $pixel['pixel_type'] == 'Decline' || 
                    $pixel['pixel_type'] == 'On Decline'
                ) {
                    array_push($currentStepPixels['decline'], $pixel);
                }
            }
        }
       
        CrmPayload::Set('pixelConfig', $currentStepPixels); 
    }
    
    private function isValidDevice($pixel)
    {

        if (!empty($pixel['device'])) {
            $devices = explode(',', $pixel['device']);
            if (!in_array('all', $devices)) {

                $detect = new MobileDetect();
                $ua  = '';

                if ($detect->isTablet()) {
                    $ua = 'tablet';
                    if($detect->version('iPad'))
                    {
                        $ua = 'ipad';
                    }
                }

                if (!$detect->isMobile() && !$detect->isTablet()) {
                    $ua = 'desktop';
                }

                if ($detect->isAndroidOS()) {
                    $ua = 'mobile_android';
                }

                if ($detect->isIphone()) {
                    $ua = 'iphone';
                }
                
                if (!in_array($ua, $devices)) {
                    return false;
                }
            }
        }

        return true;
    }
    
    private function isValidAffiliates($pixel)
    {
        if(!empty($pixel['enable_affiliate_parameters']))
        {
            if (!empty($pixel['affiliate_id_value'])) {
                $affiliates = explode(',', $pixel['affiliate_id_value']);
                $affVal = Session::get(
                        sprintf('affiliates.%s', $pixel['affiliate_id_key'])
                    );
                if (
                    !in_array($affVal, $affiliates)
                ) {
                    return false;
                }
            }
            
            if (!empty($pixel['sub_id_value'])) {
                $subaffiliates = explode(',', $pixel['sub_id_value']);
                $affVal = Session::get(
                        sprintf('affiliates.%s', $pixel['sub_id_key'])
                    );
                if (
                    !in_array($affVal, $subaffiliates)
                ) {
                    return false;
                }
            }
        }

        return true;
    }
    
    private function setPrepaidConfig()
    {
        $productsArray = CrmPayload::get('products');
        $prepaidConfig = array();
        foreach ($productsArray as $key => $value) {
            $campaignId        = $value['codebaseCampaignId'];
            $config            = Config::campaigns($campaignId);
            $prepaidCampaignId = !empty($config['prepaid_campaign_id']) ? $config['prepaid_campaign_id'] : '';
            if(!empty($prepaidCampaignId)) {
                $prepaidData       = Config::campaigns((string) $prepaidCampaignId);
            }
            if (!empty($prepaidCampaignId)) {
                $prepaidConfig['products'][$key] = $this->getPrepaidCampaign($prepaidData);
            }
        }
        
        CrmPayload::Set('prepaidConfig', $prepaidConfig);
    }

    private function getPrepaidCampaign($campaign)
    {
        $productsArray = json_decode($campaign['product_array'], true);
        return array(
            'codebaseCampaignId' => $campaign['id'],
            'campaignId'         => $campaign['campaign_id'],
            'shippingId'         => $campaign['shipping_id'],
            'shippingPrice'      => $campaign['shipping_price'],
            'productId'          => $productsArray[0]['product_id'],
            'productPrice'       => $productsArray[0]['product_price'],
            'productKey'         => !empty($productsArray[0]['product_key']) ? $productsArray[0]['product_key'] : '',
            'productQuantity'    => $productsArray[0]['product_quantity'],
            'rebillProductId'    => $productsArray[0]['rebill_product_id'],
            'rebillProductPrice' => $productsArray[0]['rebill_product_price'],
            'offerId'            => !empty($productsArray[0]['offer_id']) ? $productsArray[0]['offer_id'] : '',
            'billingModelId'     => !empty($productsArray[0]['billing_model_id']) ? $productsArray[0]['billing_model_id'] : '',
            'trialProductId'     => !empty($productsArray[0]['trial_product_id']) ? $productsArray[0]['trial_product_id'] : '',
            'trialProductPrice'  => !empty($productsArray[0]['trial_product_price']) ? $productsArray[0]['trial_product_price'] : '',
            'enableBillingModule'=> !empty($productsArray[0]['enable_billing_module']) ? $productsArray[0]['enable_billing_module'] : 0,
        );
    }
    
    private function setScrapConfig($type)
    {

        $stepId = ($this->currentStepId < 2) ? $this->currentStepId : 2;

        $scrapRuleId = Session::get(sprintf(
            'extensions.trafficLoadBalancer.%d.ruleId', $stepId
        ));

        $isScrapped = Session::get(sprintf(
            'extensions.trafficLoadBalancer.%d.scrapped', $stepId
        ));

        $orderFilter = Session::get('extensions.trafficLoadBalancer.orderFilter');

        if(!empty($orderFilter)) {
            CrmPayload::Set('orderFilter', $orderFilter);
        }

        if (Session::has('extensions.trafficLoadBalancer') && $type != 'split') {
            CrmPayload::Set('scrapRuleId', $scrapRuleId);
            CrmPayload::Set('isScrapped', $isScrapped);
        }
    }

}
