<?php

namespace Extension\DelayedTransactions;

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
use Application\Extension;

class Crons
{

    private $data, $dbConnection, $tableName, $currentDateTime, $enableDataCapture, $authKey, $rawCrmResponse, $rawCrmPayload, $isPrepaidFlow;
    private $isParentOrderSuccess     = true;
    private $isMain                   = false;
    private $crmArray                 = array('limelight', 'konnektive');
    private static $lockingCheckCount = 0;
    private $htmlPixel                = array();
    private $declinePixel             = array();
    private $submissionPixel          = array();
    private $postbackResponse         = array();
    private $orderIds                 = array();
    private $isScrappedOrder          = false;
    private $localEncKey              = 'd0a7e7997b6d5fcd55f4b5c32611b87cd923e88837b63bf2941ef819dc8ca282';

    const LOCK_FILE_NAME          = '.delayed_transaction';
    const MAX_LOCK_CHECKING_LIMIT = 20;

    private $mainOrderID;
    private $clearPayloadOrderStatus = array();

    const DECLINE_ORDER_INTERVAL = 10080;
    const SUCCESS_ORDER_INTERVAL = 4320;
    const FETCH_LIMIT            = 30;
    const REMOTE_URL = "https://platform.almost20.com/api/data-mining";

    public function __construct()
    {
        $this->dbConnection = Helper::getDatabaseConnection();
        $this->tableName    = Config::extensionsConfig(
            'DelayedTransactions.table_name'
        );
        $this->enableDataCapture = Config::extensionsConfig(
            'DelayedTransactions.enable_datacapture'
        );
        $this->enableNativeDataCapture = Config::extensionsConfig(
            'DelayedTransactions.enable_native_datacapture'
        );
        $this->allowOriginalMethod = Config::extensionsConfig(
            'DelayedTransactions.allow_original_method'
        );
        $dateTime              = new DateTime();
        $this->currentDateTime = $dateTime->format('Y-m-d H:i:s');
        $this->scrapTableName  = 'scrapper';
        $this->authKey         = Registry::system('systemConstants.201CLICKS_AUTH_KEY');
    }

    public function processOrders($parentOrderIds = array())
    {
        if (empty($parentOrderIds)) {
            echo "<pre>\nDelay order processing started. Please wait...\n";
        }
        
        Extension::getInstance()->performEventActions('beforeDelayDBRequest');

        $candidateRecords = $this->getCandidateRecords($parentOrderIds);
        if (!empty($candidateRecords)) {
            $crmPayloads = $this->prepareCrmPayloads($candidateRecords);

            $this->makeCrmRequestAndUpdateDatabase(
                $crmPayloads, empty($parentOrderIds)
            );
        }
		
        if (empty($parentOrderIds)) {
            echo "Delay order processing completed. Thank you.\n</pre>";
        }
    }

    public function processOrdersWithParentOrderId($orderId)
    {
        $this->processOrders(array($orderId));
    }

    public function reprocessOrders($orderId, $payload)
    {
        try {
            $result = $this->dbConnection->table($this->tableName)->where('parentOrderId', '=', $orderId)->get();
        } catch (Exception $ex) {
            return;
        }
        if (!empty($result)) {
            foreach ($result as $k => $v) {
                if (!empty($v['crmPayload'])) {
                    $id                            = $v['id'];
                    $crmPayload                    = json_decode($v['crmPayload'], true);
                    $crmPayload['cardType']        = $payload['creditCardType'];
                    $crmPayload['cardNumber']      = $payload['creditCardNumber'];
                    $crmPayload['cardExpiryMonth'] = $payload['expmonth'];
                    $crmPayload['cardExpiryYear']  = $payload['expyear'];
                    $crmPayload['cvv']             = $payload['CVV'];
                    $crmPayloadJson                = json_encode($crmPayload);
                    $query                         = $this->dbConnection->table($this->tableName);
                    $query->where('parentOrderId', '=', $orderId)
                        ->where('id', '=', $id);
                    $query->update(array(
                        'crmPayload'  => $crmPayloadJson,
                        'processing'  => 0,
                        'processedAt' => null,
                    ));
                }
            }
        }

        $this->processOrders(array($orderId));
    }

    private function makeCrmRequestAndUpdateDatabase(&$crmPayloads, $log = false)
    {

        foreach ($crmPayloads as $crmPayload) {

            $crmPayload['meta.terminateCrmRequest'] = false;
            $crmPayload['meta.bypassCrmHooks']      = true;
            
            if($crmPayload['cardType'] != 'square')
            {
                $decryptCC = Security::decrypt($crmPayload['cardNumber'], $this->localEncKey);
                $decryptEM = Security::decrypt($crmPayload['cardExpiryMonth'], $this->localEncKey);
                $decryptEY = Security::decrypt($crmPayload['cardExpiryYear'], $this->localEncKey);
                $decryptCV = Security::decrypt($crmPayload['cvv'], $this->localEncKey);

                $crmPayload['cardNumber']      = !empty($decryptCC) ? $decryptCC : $crmPayload['cardNumber'];
                $crmPayload['cardExpiryMonth'] = !empty($decryptEM) ? $decryptEM : $crmPayload['cardExpiryMonth'];
                $crmPayload['cardExpiryYear']  = !empty($decryptEY) ? $decryptEY : $crmPayload['cardExpiryYear'];
                $crmPayload['cvv']             = !empty($decryptCV) ? $decryptCV : $crmPayload['cvv'];
            }
                
            $recordId      = $crmPayload['meta.recordId'];
            $combined      = $crmPayload['meta.combined'];
            $parentOrderId = $crmPayload['meta.parentOrderId'];
            unset(
                $crmPayload['recordId'], $crmPayload['crmId'], $crmPayload['combined'], $crmPayload['parentOrderId']
            );

            if (
                ($crmPayload['meta.type'] == 'split' || $crmPayload['meta.type'] == 'upsell') &&
                in_array($crmPayload['meta.crmType'], $this->crmArray)
            ) {
                $this->checkOrderView($crmPayload);
            }

            $isBypassOrderViewEnable = Config::extensionsConfig(
                'DelayedTransactions.enable_bypass_order_view'
            );

            $bypassOrderViewConfig =  Config::extensionsConfig(
                                        'DelayedTransactions.bypass_orderview_steps'
                                    ); 
            $bypassOrderViewArray = explode(',', $bypassOrderViewConfig);

            if($isBypassOrderViewEnable && in_array($crmPayload['meta.stepId'], $bypassOrderViewArray)) {
                $this->isParentOrderSuccess = true;
            }

            if ($crmPayload['meta.type'] == 'main') {
                $this->isMain = true;
            } else {
                $this->isMain = false;
            }

            if ($this->isParentOrderSuccess) {

                CrmPayload::replace($crmPayload);
                CrmPayload::remove('pixelConfig');

                $crmClass = sprintf(
                    '\Application\Model\%s', ucfirst(CrmPayload::get('meta.crmType'))
                );

                $crmInstance = new $crmClass($crmPayload['meta.crmId']);
                $custID      = CrmPayload::get('customerId');
                if(
                        CrmPayload::get('meta.crmType') == 'konnektive' && 
                        $crmPayload['meta.type'] == 'split' && !is_numeric($custID)) 
                {
                        CrmPayload::set('customerId', '');
                }
                
                if (CrmPayload::get('meta.crmType') == 'limelight' && is_numeric($custID)) {
                    call_user_func_array(array($crmInstance, CrmPayload::get('meta.crmMethod')), array());
                } else {
                    if (CrmPayload::get('meta.crmType') == 'limelight' && !empty($this->rawCrmResponse) && CrmPayload::get('meta.crmMethod') == 'newOrderCardOnFile') {
                        $mainResponse = array();
                        parse_str($this->rawCrmResponse, $mainResponse);
                        CrmPayload::set('customerId', $mainResponse['customerId']);
                        CrmPayload::set('previousOrderId', $mainResponse['orderId']);
                        $evaluateForceGatewayArray = CrmPayload::get('forceGatewayId');
                        $evaluateForceGateway = $evaluateForceGatewayArray['evaluate'];
                        if($evaluateForceGateway) {
                        	CrmPayload::set('forceGatewayId', $mainResponse['gatewayId']);
                                CrmPayload::set('preserveGateway', true);
                        }
                        call_user_func_array(array($crmInstance, CrmPayload::get('meta.crmMethod')), array());
                    } elseif(!empty($this->allowOriginalMethod) && CrmPayload::get('meta.crmType') == 'konnektive' && CrmPayload::get('meta.crmMethod') == 'importUpsell') {
                    	call_user_func_array(array($crmInstance, CrmPayload::get('meta.crmMethod')), array());
                    } else if($crmPayload['meta.crmType'] == "m1billing"){
                        call_user_func_array(array($crmInstance, $crmPayload['meta.crmMethod']), array());
                    } else {
                        CrmPayload::remove('previousOrderId');
                        $crmInstance->newOrder();
                    }
                }

                $this->rawCrmPayload  = Http::getPayload();
                $this->rawCrmResponse = Http::getResponse();

                if (CrmResponse::has('orderId')) {
                    array_push($this->orderIds, CrmResponse::get('orderId'));
                    if ($this->enableNativeDataCapture) {
                        $this->nativeDataCapture($parentOrderId);
                    }
                }

                $this->increaseHitsCount($crmPayload['scrapDetail']);

                if (empty($crmPayload['meta.isSplitOrder']) && !$this->isScrappedOrder) {
                    $this->firePixel($crmPayload);
                }

                $this->processPrepaidOrders($crmPayload);

                if ($log === true) {
                    print_r(CrmResponse::all());
                }
            }
            
            $this->doCascadeLogic($crmPayload);
            
            Extension::getInstance()->performEventActions('afterAnyDelayCrmRequest');

            $this->updateProcessedRecord($recordId, $combined, $parentOrderId);
            
            if(
                CrmResponse::has('orderId') && 
                $crmPayload['cardType'] == 'COD' && 
                $crmPayload['meta.crmType'] == 'limelight'
            ) {
                $this->approveOfflineOrders($crmPayload);
            }
            if(CrmResponse::has('orderId')){
                /** For trial completion data post into Almost20*/
                $this->postData($crmPayload);
                $this->postMainData($crmPayload);
            }
            
            if ($crmPayload['enableSensitiveData']) {
                $this->dataCapture($crmPayload);
            }
            
            $response = CrmResponse::all();
            $reprocessDeclinedOrder = Config::extensionsConfig('DelayedTransactions.reprocess_decline');
            
            
            if(empty($response['success']) && $reprocessDeclinedOrder) 
            {
                ReprocessOrders::reprocessOrders($crmPayload, $response);
            }
            
        }
    }
    
    private function approveOfflineOrders($crmPayload)
    {
        $crmResponse = CrmResponse::all();
        $configId = $crmPayload['meta.configId'];
        $configuration = new Configuration($configId);
        $crmId = $configuration->getCrmId();
        $crm = $configuration->getCrm();
        $crmType = $crm['crm_type'];
        
        $response = CrmResponse::all();
        if(empty($response['success']))
        {
            return;
        }
        
        $crmClass = sprintf(
                '\Application\Model\%s', ucfirst($crmType)
        );
        $data['order_ids'] = $response['orderId'];
        $data['actions'] = 'payment_received';
        $data['values'] = '1';
        CrmPayload::replace($data);
        $crmInstance = new $crmClass($crmId);
        call_user_func_array(array($crmInstance, 'orderUpdate'), array());
        CrmResponse::replace($crmResponse);
    }

    private function firePixel($crmPayload)
    {
        $postbackArray        = array();
        $htmlPixelArray       = array();
        $declinePixelArray    = array();
        $submissionPixelArray = array();

        if (CrmResponse::get('success') != 1) {
            if (empty($crmPayload['isScrapped'])) {
                if (!empty($crmPayload['pixelConfig']['decline'])) {
                    foreach ($crmPayload['pixelConfig']['decline'] as $pixel) {
                        $parsedHtml = $this->parseTokens($pixel['html_pixel']);
                        array_push($declinePixelArray, $parsedHtml);
                    }
                    array_push($this->declinePixel, $declinePixelArray);
                }

                if (!empty($crmPayload['pixelConfig']['submission'])) {
                    foreach ($crmPayload['pixelConfig']['submission'] as $pixel) {
                        $parsedHtml = $this->parseTokens($pixel['html_pixel']);
                        array_push($submissionPixelArray, $parsedHtml);
                    }
                    array_push($this->submissionPixel, $submissionPixelArray);
                }
            }
            return;
        }

        foreach ($crmPayload['pixelConfig']['postback'] as $pixel) {
            $parsedPostback = $this->parseTokens($pixel['postback_url']);
            $response       = Http::get(
                $parsedPostback, array(), array(CURLOPT_CONNECTTIMEOUT => 5)
            );
            array_push($postbackArray, $response);
        }

        foreach ($crmPayload['pixelConfig']['html'] as $pixel) {
            $parsedHtml = $this->parseTokens($pixel['html_pixel']);
            array_push($htmlPixelArray, $parsedHtml);
        }

        if (!empty($crmPayload['pixelConfig']['submission'])) {
            foreach ($crmPayload['pixelConfig']['submission'] as $pixel) {
                $parsedHtml = $this->parseTokens($pixel['html_pixel']);
                array_push($submissionPixelArray, $parsedHtml);
            }
            array_push($this->submissionPixel, $submissionPixelArray);
        }

        $postbackResponse = json_encode($postbackArray);
        array_push($this->htmlPixel, $htmlPixelArray);
        array_push($this->postbackResponse, $postbackResponse);
    }

    private function parseTokens($stringWithTokens)
    {
        return preg_replace_callback(
            "/\{([a-z0-9_]+)\}/i", function ($data) {

                if ($data[1] === 'delay_order_id' || $data[1] === 'delayOrderId' || $data[1] === 'order_id' || $data[1] === 'orderId') {
                    $orderId = CrmResponse::has('orderId') ? CrmResponse::get('orderId') : CrmResponse::get('delayOrderId');
                    return $orderId;
                }
                
                if ($data[1] === 'order_total' || $data[1] === 'orderTotal') {
                    $orderTotal = $this->getOrderTotal();
                    return $orderTotal;
                }
                
                $formData = array(
                    'firstName',
                    'lastName',
                    'email',
                    'phone',
                    'shippingCity',
                    'shippingState',
                    'shippingCountry'
                );
                
                foreach($formData as $formTokens) {
                    if ($data[1] === $formTokens) {
                          return CrmPayload::get($formTokens);
                    }
                }

                $param = strtolower(str_replace('_', '', $data[1]));

                $affiliates = array_change_key_case(CrmPayload::get('affiliates'));

                foreach ($affiliates as $key => $value) {
                    if ($param === $key) {
                        return $value;
                    }
                }
            }, $stringWithTokens
        );
    }
    
    private function getOrderTotal()
    {
        $products = CrmPayload::get('products');
        $orderTotal = 0.00;
        
        if(!empty($products)) 
        {
            foreach ($products as $value)
            {
                $orderTotal += ($value['productPrice'] * $value['productQuantity']) + $value['shippingPrice'];
            }
        }
        
        return $orderTotal;
    }

    private function processPrepaidOrders(&$crmPayload)
    {
        if (CrmResponse::get('isPrepaidDecline') != 1) {
            return;
        }

        $prepaidConfig = CrmPayload::get('prepaidConfig');
        CrmPayload::set('products', $prepaidConfig);
        CrmPayload::remove('prepaidConfig');
        $crmPayload = CrmPayload::all();
        CrmPayload::replace($crmPayload);
        CrmPayload::set('campaignId', $crmPayload['products'][0]['campaignId']);
        $crmClass = sprintf(
            '\Application\Model\%s', ucfirst(CrmPayload::get('meta.crmType'))
        );
        $crmInstance = new $crmClass($crmPayload['meta.crmId']);
        $crmInstance->newOrder();
        $this->rawCrmPayload  = Http::getPayload();
        $this->rawCrmResponse = Http::getResponse();
        $this->isPrepaidFlow  = true;
    }

    public function memberCreate()
    {
        $ifMembershipEnabled = Config::extensionsConfig(
            'DelayedTransactions.enable_membership'
        );

        if (CrmResponse::get('success') != 1 || !$ifMembershipEnabled) {
            return;
        }

        try
        {
            $memberCreateEventId = Config::extensionsConfig(
                'DelayedTransactions.membership_create_event_id'
            );

            $curlPostData['email']       = CrmPayload::get('email');
            $curlPostData['customer_id'] = CrmResponse::get('customerId');
            $curlPostData['method']      = 'member_create';
            $curlPostData['event_id']    = $memberCreateEventId;
            $this->callAPI($curlPostData);
        } catch (Exception $ex) {
            return;
        }
    }

    private function callAPI($curlPostData = array())
    {
        $memberConfigId = Config::extensionsConfig(
            'DelayedTransactions.membership_config'
        );
        $configuration = new Configuration($memberConfigId);
        $crmInfo       = $configuration->getCrm();

        $curlPostData['username'] = $crmInfo['username'];
        $curlPostData['password'] = $crmInfo['password'];

        $url = trim($crmInfo['endpoint'], '/') . "/admin/membership.php";

        $curlResponse = Http::post($url, http_build_query($curlPostData));

        return $curlResponse;
    }

    private function increaseHitsCount($scrapDetail)
    {

        if (!empty($scrapDetail[0]['orderFilter'])) {
            $this->isScrappedOrder = true;
            return;
        }

        if (CrmResponse::get('success') != 1 || empty($scrapDetail)) {
            return;
        }

        $this->scarpDbConnection = $this->getDatabaseConnection();

        foreach ($scrapDetail as $value) {

            $getScrapDetails = $this->scarpDbConnection->table($this->scrapTableName)
                ->where('id', $value['scrapRuleId'])
                ->first();

            $hits          = $getScrapDetails['hits'];
            $scrapped      = $getScrapDetails['scrapped'];
            $hitsCount     = $getScrapDetails['hitsCount'];
            $scrappedCount = $getScrapDetails['scrappedCount'];

            if ($value['isScrapped']) {
                $scrapped += 1;
                $scrappedCount += 1;
                $this->isScrappedOrder = true;
            }

            $updateArray = array(
                'hits'          => $hits + 1,
                'scrapped'      => $scrapped,
                'hitsCount'     => $hitsCount + 1,
                'scrappedCount' => $scrappedCount,
            );

            $this->scarpDbConnection->table($this->scrapTableName)
                ->where('id', $value['scrapRuleId'])
                ->update($updateArray);
            
            $cardDetails = CrmPayload::get('card_details');

            if(!empty($cardDetails))
            {
                $this->scarpDbConnection->table($this->scrapTableName)
                    ->where('id', CrmPayload::get('ruleId'))
                    ->update($cardDetails);
            }
            
        }
    }

    private function checkOrderView(&$crmPayload, $log = false)
    {
        $parentOrderId = $this->dbConnection->table($this->tableName)
            ->select('orderId')
            ->where('parentOrderId', $crmPayload['meta.orderId'])
            ->orderBy('id')
            ->first();
        $this->mainOrderID = $parentOrderId['orderId'];
        
        if(
            $crmPayload['meta.stepId'] > 1 && 
            $crmPayload['meta.isSplitOrder'] &&  
            empty($crmPayload['meta.combined'])
         )
        {
            $parentOrderDetails = $this->dbConnection->table($this->tableName)
            ->select('Id','orderId','crmResponse')
            ->where('parentOrderId', $crmPayload['meta.orderId'])
            ->where('type', 'upsell')
            ->where('step', $crmPayload['meta.stepId'])
            ->orderBy('id')
            ->first();
            
            if(!empty($parentOrderDetails['crmResponse'])){
                $parentOrderDetailsResponse = json_decode($parentOrderDetails['crmResponse'], true);
                if(!$parentOrderDetailsResponse['success'])
                {
                    $this->isParentOrderSuccess = false;
                    return;
                }
            }
        }
        
        CrmPayload::replace($crmPayload);

        $crmType = CrmPayload::get('meta.crmType');

        $crmClass = sprintf(
            '\Application\Model\%s', ucfirst($crmType)
        );

        $orderIds = array('0' => $parentOrderId['orderId']);

        CrmPayload::replace(array(
            'orderIds'            => $orderIds,
            'meta.bypassCrmHooks' => true,
        ));

        $crmInstance = new $crmClass($crmPayload['meta.crmId']);
        $crmInstance->orderView();

        $mainOrder = CrmResponse::all();

        $this->viewOrderStatus($mainOrder, $crmType);
    }

    private function checkOrderGateWayId($orderId){
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

    private function viewOrderStatus($mainOrder, $crmType)
    {
        $crmResponse   = CrmResponse::all();
        $orderStatusLL = !empty($crmResponse['result'][0]['order_status']) ? $crmResponse['result'][0]['order_status'] : '';
        $orderStatusKK = !empty($crmResponse['result'][0]['data'][0]['orderStatus']) ? $crmResponse['result'][0]['data'][0]['orderStatus'] : '';

        if ((!empty($mainOrder) && $crmType == 'Limelight') || ($orderStatusLL == 2 || $orderStatusLL == 8)) {
            $this->isParentOrderSuccess = true;
        } elseif ((!empty($mainOrder) && $crmType == 'Konnektive') || ($orderStatusKK == 'COMPLETE')) {
            $this->isParentOrderSuccess = true;
        } else {
            $this->isParentOrderSuccess = false;
        }
    }

    private function prepareCrmPayloads(&$candidateRecords)
    {
        for ($crmPayloads = array(); count($candidateRecords);) {
            $scrapDetails   = $this->getScrapDetails($candidateRecords);
            $prepaidDetails = $this->getPrepaidDetails($candidateRecords);
            $pixelDetails   = $this->getPixelDetails($candidateRecords);
            $productAttr    = $this->setProductAttr($candidateRecords);
            $data = array_shift($candidateRecords);

            array_push($crmPayloads, $data['crmPayload']);

            $index = count($crmPayloads) - 1;

            $crmPayloads[$index]['meta.crmType']       = $data['crmType'];
            $crmPayloads[$index]['meta.type']          = $data['type'];
            $crmPayloads[$index]['meta.orderId']       = $data['orderId'];
            $crmPayloads[$index]['meta.recordId']      = $data['id'];
            $crmPayloads[$index]['meta.crmId']         = $data['crmId'];
            $crmPayloads[$index]['meta.combined']      = $data['combined'];
            $crmPayloads[$index]['meta.parentOrderId'] = $data['parentOrderId'];
            $crmPayloads[$index]['scrapDetail']        = $scrapDetails;
            $crmPayloads[$index]['prepaidConfig']      = $prepaidDetails;
            $crmPayloads[$index]['pixelConfig']        = $pixelDetails;
            $crmPayloads[$index]['product_attribute']  = $productAttr;

            if (!$data['combined']) {
                continue;
            }

            $otherProducts = $this->getOtherProducts(
                $candidateRecords, $data['parentOrderId']
            );

            foreach ($otherProducts as $product) {
                array_push($crmPayloads[$index]['products'], $product);
            }

        }
        
        return $crmPayloads;
    }
    
    private function setProductAttr(&$candidateRecords)
    {
        $productAttr  = array();

        foreach ($candidateRecords as $key => $data) {
            if($data['combined'] && !empty($data['crmPayload']['product_attribute'])) {
                foreach($data['crmPayload']['product_attribute'] as $k => $d) {
                    $productAttr[$k] = $d;
                }
            }
        }

        return $productAttr;
    }

    private function updateProcessedRecord($recordId, $combined, $parentOrderId)
    {

        $query = $this->dbConnection->table($this->tableName);
        if ($combined) {
            $query->where('parentOrderId', '=', $parentOrderId)
                ->where('combined', '=', 1);
        } else {
            $query->where('id', '=', $recordId);
        }

        if ($this->isMain) {
            $updateOrderId = $this->dbConnection->table($this->tableName);
            $updateOrderId->where('parentOrderId', '=', $parentOrderId)
                ->update(array(
                    'orderId' => CrmResponse::get('orderId'),
                ));
        }

        CrmResponse::set('postbackResponse', $this->postbackResponse);
        CrmResponse::set('htmlPixel', $this->htmlPixel);
        CrmResponse::set('declinePixel', $this->declinePixel);
        CrmResponse::set('submissionPixel', $this->submissionPixel);
        CrmResponse::set('orderIds', $this->orderIds);
        if($this->isPrepaidFlow) {
            CrmResponse::set('isPrepaidFlow', $this->isPrepaidFlow);
        }

        $query->update(array(
            'processing'  => 0,
            'crmResponse' => json_encode(CrmResponse::all()),
        ));

        $this->memberCreate();
    }

    private function getOtherProducts(&$candidateRecords, $parentOrderId)
    {
        $products = $keys = array();
        foreach ($candidateRecords as $key => $data) {
            if (
                $data['parentOrderId'] === $parentOrderId && $data['combined']
            ) {
                $products = array_merge(
                    $products, $data['crmPayload']['products']
                );
                array_push($keys, $key);
            }
        }
        foreach ($keys as $key) {
            unset($candidateRecords[$key]);
        }
        return $products;
    }

    private function getScrapDetails(&$candidateRecords)
    {
        $scrapDetails = array();
        foreach ($candidateRecords as $key => $data) {
            if (!empty($data['crmPayload']['scrapRuleId'])) {
                $scrapDetails[$key]['scrapRuleId'] = $data['crmPayload']['scrapRuleId'];
                $scrapDetails[$key]['isScrapped']  = $data['crmPayload']['isScrapped'];
            }
            if (!empty($data['crmPayload']['orderFilter'])) {
                $scrapDetails[$key]['orderFilter'] = $data['crmPayload']['orderFilter'];
            }
        }
        $scrapDetails = array_map("unserialize", array_unique(array_map("serialize", $scrapDetails)));
        return $scrapDetails;
    }

    private function getPrepaidDetails(&$candidateRecords)
    {
        $prepaidDetails = array();
        foreach ($candidateRecords as $key => $data) {
            if (!empty($data['crmPayload']['prepaidConfig'])) {
                $prepaidDetails = array_merge(
                    $prepaidDetails, $data['crmPayload']['prepaidConfig']['products']
                );
            }
        }

        return $prepaidDetails;
    }

    private function getPixelDetails(&$candidateRecords)
    {
        $pixelDetails['postback']   = array();
        $pixelDetails['html']       = array();
        $pixelDetails['decline']    = array();
        $pixelDetails['submission'] = array();
        foreach ($candidateRecords as $key => $data) {
            if (!empty($data['crmPayload']['pixelConfig'])) {
                $pixelDetails['postback'] = array_merge(
                    $pixelDetails['postback'], $data['crmPayload']['pixelConfig']['Postback']
                );
                $pixelDetails['html'] = array_merge(
                    $pixelDetails['html'], $data['crmPayload']['pixelConfig']['html']
                );
                $pixelDetails['decline'] = array_merge(
                    $pixelDetails['decline'], $data['crmPayload']['pixelConfig']['decline']
                );
                $pixelDetails['submission'] = array_merge(
                    $pixelDetails['submission'], $data['crmPayload']['pixelConfig']['submission']
                );
            }
        }

        return $pixelDetails;
    }

    private function getCandidateRecords($parentOrderIds = array())
    {
        while ($this->runningAnotherProcess()) {
            print_r(
                "Waiting for another process, which is currently running."
            );
            sleep(1);
        }

        $candidateRecordIds = $this->getCandidateRecordIds($parentOrderIds);

        if (empty($candidateRecordIds)) {
            return array();
        }

        $candidateRecords = $this->dbConnection->table($this->tableName)
            ->select(
                'id', 'parentOrderId', 'orderId', 'step', 'type', 'crmId', 'crmType', 'combined', 'crmPayload', 'scheduledAt'
            )
            ->whereIn('id', $candidateRecordIds)
            ->orderBy('id')
            ->get();

        $this->dbConnection->table($this->tableName)
            ->whereIn('id', $candidateRecordIds)
            ->update(array(
                'processing'  => 1,
                'processedAt' => $this->currentDateTime,
            ));

        if (file_exists(STORAGE_DIR . DS . self::LOCK_FILE_NAME)) {
            file_put_contents(STORAGE_DIR . DS . self::LOCK_FILE_NAME, 0, LOCK_EX);
        }

        return array_map(function ($value) {
            $value['crmPayload'] = json_decode($value['crmPayload'], true);
            return $value;
        }, $candidateRecords);
    }

    private function getCandidateRecordIds($parentOrderIds = array())
    {

        $query = $this->dbConnection->table($this->tableName)
            ->select('id', 'parentOrderId', 'combined')
            ->where('processing', '=', 0)
            ->where('processedAt', '=', null);

        if (empty($parentOrderIds)) {
            $query->where(
                'scheduledAt', '<', $this->currentDateTime
            )->limit(10);
        } else {
            $query->whereIn('parentOrderId', $parentOrderIds);
        }
        $candidateRecords = $query->orderBy('id')->get();

        $candidateRecordIds = array_map(function ($record) {
            return $record['id'];
        }, $candidateRecords);

        $combinedParentOrderIds = array_values(array_unique(
            array_filter(
                array_map(function ($record) {
                    if (!$record['combined']) {
                        return null;
                    }
                    return $record['parentOrderId'];
                }, $candidateRecords)
            )
        ));

        if (empty($combinedParentOrderIds)) {
            return $candidateRecordIds;
        }

        $extraRecordIds = array_map(function ($record) {
            return $record['id'];
        }, $this->dbConnection->table($this->tableName)
                ->select('id')
                ->whereIn('parentOrderId', $combinedParentOrderIds)
                ->whereNotIn('id', $candidateRecordIds)
                ->where('combined', '=', 1)
                ->where('processing', '=', 0)
                ->where('processedAt', '=', null)
                ->get()
        );

        return array_merge($candidateRecordIds, $extraRecordIds);
    }

    private function runningAnotherProcess()
    {
        $lockFile = STORAGE_DIR . DS . self::LOCK_FILE_NAME;

        if (!is_writable(STORAGE_DIR)) {
            print_r(
                "Locking not supported, Please check storage dir permission."
            );
            return false;
        }
        if (!file_exists($lockFile)) {
            file_put_contents($lockFile, 1, LOCK_EX);
            return false;
        }
        $flag = (int) file_get_contents($lockFile);
        if (
            self::$lockingCheckCount > self::MAX_LOCK_CHECKING_LIMIT ||
            $flag === 0
        ) {
            file_put_contents($lockFile, 1, LOCK_EX);
            return false;
        }
        self::$lockingCheckCount++;
        return false;
    }

    private function getDatabaseConnection()
    {
        $factory = new ConnectionFactory();
        return $factory->make(array(
            'driver'   => 'sqlite',
            'database' => STORAGE_DIR . DS . 'trafficlb.sqlite',
        ));
    }

    private function dataCapture($crmPayload)
    {
        try
        {
            $isDecline = false;
            $customerId = CrmResponse::get('customerId');
            $orderId    = CrmResponse::get('orderId');
            
            $crmResponseForDataCapture = CrmResponse::all();
            if($crmPayload['meta.crmType'] == 'konnektive') {
                if (
                  !empty($crmResponseForDataCapture['errors']['crmError']) &&
                  preg_match("/clientOrderId=/i", $crmResponseForDataCapture['errors']['crmError'])
                ) {
                  $explodeArray = explode('clientOrderId=', $crmResponseForDataCapture['errors']['crmError']);
                  $crmResponseForDataCapture['declineOrderId'] = trim($explodeArray[1]);
                  CrmResponse::update($crmResponseForDataCapture);
                }
            }
            
            if(
                empty($orderId) && 
                CrmResponse::get('declineOrderId') && 
                $crmPayload['enableDeclineCapture']
            ) {
                $isDecline = true;
                $orderId  = CrmResponse::get('declineOrderId');
                if(empty($customerId) && $crmPayload['meta.crmType'] == 'limelight') {
                    $orderViewResponse = $this->orderView($orderId, $crmPayload);
                    $customerId = $orderViewResponse['customer_id'];
                    if(empty($customerId)){
                        $customerId = !empty($crmPayload['prospectId']) ? $crmPayload['prospectId'] : $crmPayload['customerId'];
                    }
                }
                //Decline Order Process New Block 
                if($crmPayload['meta.crmType'] == 'konnektive') {
                    $oldCrmResponse = CrmResponse::all();
                    $orderViewResponse = $this->getTransactionDetails($oldCrmResponse['declineOrderId']);
                    $customerId = $orderViewResponse['transactionInfo']['data'][0]['customerId'];
                    CrmResponse::update($oldCrmResponse);
                }
            }

            if (!empty($orderId) && !empty($customerId)) {
                $sync = array(
                    'card' => $crmPayload['cardNumber'],
                    'cvv'  => $crmPayload['cvv'],
                    'month' => $crmPayload['cardExpiryMonth'], 
                    'year' =>  $crmPayload['cardExpiryYear']
                );
                
                $encryptionKey     = Config::settings('encryption_key');
                $gatewaySwitcherID = Config::settings('gateway_switcher_id');
                $crmId   = $crmPayload['meta.crmId'];
                
                if(!empty($encryptionKey) && !empty($gatewaySwitcherID)) {
                    $payload = Security::encrypt(json_encode($sync), Config::settings('encryption_key'));
                    $params = array(
                        'auth_key'      => $this->authKey,
                        'order_id'      => $orderId,
                        'customer_id'   => $customerId,
                        'data'          => $payload,
                        'crm_end_point' => Config::crms(sprintf('%d.endpoint', $crmId))
                    );
                    
                    if(!empty($isDecline))
                    {
                        $params['decline'] = $isDecline;
                    }
                    
                    $response = Http::post(sprintf(Registry::system('systemConstants.201CLICKS_URL') . '/api/offer-assets/%s/', Config::settings('gateway_switcher_id')), $params);
                }
                
                if($crmPayload['enableLocalCapture']) {
                    $domainName         = Config::settings('domain');
                    $date               = date('MY');
                    $secureKey          = md5($domainName.$date);
                    $payload = Security::encrypt(json_encode($sync), $secureKey);
                    $dateTime = new DateTime();
                    $processedAt = NULL;
                    $processed = 0;
                    if(!empty($response) && $response == 'success') {
                        $processedAt = $dateTime->format('Y-m-d H:i:s');
                        $processed = 1;
                    }
                    $data = array(
                        'data' => $payload,
                        'gateway_switcher_id' => Config::settings('gateway_switcher_id'),
                        'encryption_key' => Config::settings('encryption_key'),
                        'crm_end_point' => Config::crms(sprintf('%d.endpoint', $crmId)),
                        'order_id' => $orderId,
                        'customer_id' => $customerId,
                        'secure_key' => $secureKey,
                    );
                    if(!empty($isDecline))
                    {
                        $data['decline'] = $isDecline;
                    }
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
                    $this->insertInDb($dbData, $crmPayload['dataCaptureTable']);
                }
                
            }
        } catch (Exception $ex) {

        }

    }
    
    private function insertInDb($dbData, $tableName)
    {
        $this->dbConnection->table($tableName)->insert($dbData);
    }

    private function nativeDataCapture($parentOrderId)
    {
        try
        {
            $responseData        = CrmResponse::all();
            $rawData             = Http::getResponse();
            $data['orderId']     = $responseData['orderId'];
            $data['customerId']  = !empty($responseData['customerId']) ? $responseData['customerId'] : '';
            $data['crmResponse'] = json_encode($rawData);
            $nativeTableName     = Config::extensionsConfig('DelayedTransactions.native_datacapture_table');

            $prevData = $this->dbConnection->table($nativeTableName)
                ->select('id')
                ->where('parentOrderId', $parentOrderId)
                ->where('crmResponse', null)
                ->orderBy('id')
                ->first();

            $this->dbConnection->table($nativeTableName)
                ->where('id', $prevData['id'])
                ->update($data);
        } catch (Exception $ex) {

        }
    }

    public function clrPayload()
    {

        $result = $this->getOrderList();
        if (empty($result)) {
            return;
        }
        foreach ($result as $key => $value) {
            $this->processOrderPayload($key, $value);
        }

        return $this->updateClearPayloadStatus();
    }

    /**
     * Get order payload with defined time duration interval other wise
     * 3 days for successful and 7 days for decline/error
     */
    private function getOrderList()
    {
        try
        {
            $nativeTableName               = Config::extensionsConfig('DelayedTransactions.table_name');
            $successOrderDataClearDuration = empty((int) Config::extensionsConfig('DelayedTransactions.clr_payload_duration_success_orders')) ?
            self::SUCCESS_ORDER_INTERVAL :
            Config::extensionsConfig('DelayedTransactions.clr_payload_duration_success_orders');

            $declineOrderClrDuration = empty((int) Config::extensionsConfig('DelayedTransactions.clr_payload_decline_orders')) ?
            self::DECLINE_ORDER_INTERVAL :
            Config::extensionsConfig('DelayedTransactions.clr_payload_decline_orders');

            $forsuccessSql = '( SELECT id,crmPayload,crmResponse,processing,processedAt '
                . ' FROM '
                . $nativeTableName
                . ' WHERE processing IN(0,1) AND ROUND((UNIX_TIMESTAMP(now()) - UNIX_TIMESTAMP(processedAt))/60) >= '
                . $successOrderDataClearDuration
                . ' AND processedAt IS NOT NULL ) ';

            $forDeclineSql = '( SELECT id,crmPayload,crmResponse,processing,processedAt '
                . ' FROM '
                . $nativeTableName
                . ' WHERE processing IN(3) AND ROUND((UNIX_TIMESTAMP(now()) - UNIX_TIMESTAMP(processedAt))/60) >= '
                . $declineOrderClrDuration
                . ' AND processedAt IS NOT NULL ) ';

            $finalSql = $forsuccessSql . ' UNION ' . $forDeclineSql . ' LIMIT ' . self::FETCH_LIMIT;
            return $this->dbConnection->fetchAll($finalSql);
        } catch (Exception $ex) {
            return false;
        }
    }

    private function processOrderPayload($key, $orders)
    {
        $filteredPayLoad = $orders;
        if (json_decode($orders['crmResponse'], true)['success'] ||
            $orders['processing'] == 3) {
            $filteredPayLoad['crmPayload'] = json_encode(
                $this->removeKeys($orders['crmPayload'])
            );
            $filteredPayLoad['processing'] = 2;
        } else {
            $declineOrderClrDuration = empty((int) Config::extensionsConfig('DelayedTransactions.clr_payload_decline_orders')) ?
            self::DECLINE_ORDER_INTERVAL :
            Config::extensionsConfig('DelayedTransactions.clr_payload_decline_orders');
            if (round(time() - strtotime($orders['processedAt'])) / 60 >= $declineOrderClrDuration) {
                $filteredPayLoad['crmPayload'] = json_encode(
                    $this->removeKeys($orders['crmPayload'])
                );
                $filteredPayLoad['processing'] = 2;
            } else {
                $filteredPayLoad['processing'] = 3;
            }
        }

        $this->clearPayloadOrderStatus[$key] = $filteredPayLoad;
    }

    private function removeKeys($array)
    {
        $removeKeys = array(
            "cardType", "cardNumber",
            "cardExpiryMonth", "cardExpiryYear", "cvv","creditCardType",
			"cdc","scrt","expirationDate"
        );
        $payLoad = json_decode($array, true);
        foreach ($removeKeys as $unwantedKey) {
            if (array_key_exists($unwantedKey, $payLoad)) {
                unset($payLoad[$unwantedKey]);
            }
			if (array_key_exists("trial_completion_details", $payLoad) && 
				array_key_exists($unwantedKey, $payLoad['trial_completion_details'])) {
                unset($payLoad['trial_completion_details'][$unwantedKey]);
            }
        }
        return $payLoad;
    }

    private function updateClearPayloadStatus()
    {
        try
        {
            if (empty($this->clearPayloadOrderStatus)) {
                return;
            }
            $nativeTableName = Config::extensionsConfig('DelayedTransactions.table_name');
            $ids             = array();
            $sql             = 'UPDATE '
                . $nativeTableName;
            $processColumnSql = ' processing = ( CASE ';
            $payloadColumnSql = ' crmPayload = ( CASE ';
            foreach ($this->clearPayloadOrderStatus as $key => $value) {
                $ids[] = $value['id'];
                $processColumnSql .= ' WHEN id = ' . $value['id'] . ' THEN ' . $value['processing'];
                $payloadColumnSql .= ' WHEN id = ' . $value['id'] . ' THEN "' . addslashes($value['crmPayload']) . '" ';
            }
            $processColumnSql .= ' END ) ';
            $payloadColumnSql .= ' END ) ';
            
            $sql = $sql . ' SET rawPayload = NULL, rawResponse = NULL,' . $processColumnSql . ',' . $payloadColumnSql . ' WHERE id IN(' . implode(",", $ids) . ')';
            $this->dbConnection->query($sql);
            return true;
        } catch (Exception $ex) {
            return false;
        }
    }
    
    private function doCascadeLogic($crmPayload)
    {
        $isPrepaidDecline = CrmResponse::get('isPrepaidDecline');
        if ($isPrepaidDecline == 1) {
            return;
        }
        
        $declineReasons = !empty($crmPayload['cascadeData']['cascading_decline_reasons']) ? $crmPayload['cascadeData']['cascading_decline_reasons'] : "";
        $crmResponse = CrmResponse::all();
        
        if(!empty($declineReasons)) 
        {
            foreach ($declineReasons as $value)
            {
                if(
                    !empty($response['errors']['crmError'])
                     &&
                    (       
                        preg_match("/".preg_quote($value)."/i", $crmResponse['errors']['crmError']) 
                        || preg_match("/".preg_quote($crmResponse['errors']['crmError'])."/i", $value)
                    )
                        
                )   {
                        return;
                }
            }
        }
        
        if(empty($crmResponse['declineOrderId']))
        {
            return;
        }
        
        $oderViewResponse = $this->orderView($crmResponse['declineOrderId'], $crmPayload);
        $gatewayId = $oderViewResponse['gateway_id'];
        
        if (empty($crmResponse['errors']['crmError']) || empty($gatewayId))
        {
            return;
        }
        
        $forceMid = $this->findMid($gatewayId, $crmPayload);
        
        if(empty($forceMid)) {
            return;
        }
        
        CrmPayload::set('forceGatewayId', $forceMid);
        $crmClass = sprintf(
                    '\Application\Model\%s', ucfirst(CrmPayload::get('meta.crmType'))
                );

        $crmInstance = new $crmClass($crmPayload['meta.crmId']);
        CrmPayload::set('customNotes', 'Order processed through Almost20 cascade profile, Declined Mid - '.$gatewayId.', Assigned Mid - '.$forceMid);
        call_user_func_array(array($crmInstance, CrmPayload::get('meta.crmMethod')), array());
        
    }
    
    private function findMid($gatewayId, $crmPayload)
    {
        $declinedGateway = $gatewayId;
        
        $midGroupList = $crmPayload['cascadeData']['mid_group_list'];
        foreach ($midGroupList as $val)
        {
            $matchedMidGroup = $val['gateway_ids'];
            $matchedMidsArray = explode(',', $matchedMidGroup);
            if (in_array($declinedGateway, $matchedMidsArray))
            {
                $matchedMidArray['crm_mid_id'] = $val['crm_mid_id'];
                $matchedMidArray['crm_mid_name'] = $val['crm_mid_name'];
                break;
            }
        }

        $lenderMapping = $crmPayload['cascadeData']['lender_settings']['lender_mapping'];
        $currentStep = $crmPayload['meta.stepId'];
        
        $lenderMappingDetails = !empty($lenderMapping['step_' . $currentStep]) ? $lenderMapping['step_' . $currentStep] : '';
        
        if (!empty($lenderMappingDetails))
        {
          
            $gatewayListAssign = $lenderMappingDetails['gateway_list_assign'];
            $commonArrayKeys = array();
            $i = 0;
            foreach ($gatewayListAssign as $key => $val)
            {
                if (in_array($matchedMidArray['crm_mid_id'], $val))
                {
                    $commonArrayKeys[$i] = $key;
                    $i++;
                }
            }

            $lenderToExclude = $lenderMappingDetails['lender_to_exclude'];
            $excludedLenders = array();
            foreach ($lenderToExclude as $key => $val)
            {
                if (in_array($key, $commonArrayKeys))
                {
                    array_push($excludedLenders, $val);
                }
            }

            $excludedLendersArray = $excludedLenders;
            $result = call_user_func_array("array_merge", $excludedLendersArray);
            $finalExcludedLenders = array_unique($result);
        } else {
            $finalExcludedLenders = array();
        }
        
        if($currentStep > 1) {
            $previousLenders = array();
            $previousGateways = $crmPayload['previousGateways'];
            foreach($previousGateways as $key => $val) {
                $getPreviousGatewayLenders = $this->getPreviousGatewayLenders($val, $crmPayload);
                array_push($previousLenders, $getPreviousGatewayLenders);
            }
            $finalExcludedLenders = array_merge($finalExcludedLenders, $previousLenders);
        }

        $productId = $crmPayload['products'][0]['productId'];

        $productMapping = $crmPayload['cascadeData']['lender_settings']['product_mapping'];
        foreach ($productMapping as $key => $val)
        {
            $productIdArray = explode(',', $val['product_id']);
            if (in_array($productId, $productIdArray))
            {
                $lenderIds = $val['lender_id'];
            }
        }
        
        if(empty($lenderIds)) {
            return;
        }
        if(!empty($finalExcludedLenders)) {
            $excludedLenders = array_intersect($lenderIds, $finalExcludedLenders);
            $finalLenders = array_diff($lenderIds, $excludedLenders);
        } else {
            $finalLenders = $lenderIds;
        }

        $gatewayToAssign = $crmPayload['cascadeData']['lender_settings']['gateway_to_assign'];
        $gatewayToAssignArray = explode(',', $gatewayToAssign);
        
        $productMappingEnabled = $crmPayload['cascadeData']['lender_settings']['product_gateway_mapping_enabled'];
        
        if($productMappingEnabled) 
        {
            $productGatewayMapping = $crmPayload['cascadeData']['lender_settings']['product_gateway_mapping'];
            if(!empty($productGatewayMapping))
            {
                foreach($productGatewayMapping as $val) 
                {
                    if(!empty($val['product_id']) && $val['product_id'] == $productId) 
                    {
                        $gatewayToAssignArray = explode(',', $val['gateway_id']);
                    }
                }
            }            
        }

        $matchedMidsList = array();

        foreach ($midGroupList as $val)
        {
            $matchedMidGroup = $val['gateway_ids'];
            $matchedMidsArray = explode(',', $matchedMidGroup);
            if (in_array($val['crm_mid_id'], $finalLenders))
            {
                array_push($matchedMidsList, $matchedMidsArray);
            }
        }

        $result = call_user_func_array("array_merge", $matchedMidsList);
        $finalMatchedMidsList = array_unique($result);

        $commonMids = array_intersect($finalMatchedMidsList, $gatewayToAssignArray);

        $forceMidKey = array_rand($commonMids);
        $forceMid = $commonMids[$forceMidKey];
        
        return $forceMid;
    }
    
    private function getPreviousGatewayLenders($gatewayId, $crmPayload)
    {
        $midPreviousGroupList = $crmPayload['cascadeData']['mid_group_list'];
        foreach ($midPreviousGroupList as $val)
        {
            $matchedPreviousMidGroup = $val['gateway_ids'];
            $matchedPreviousMidsArray = explode(',', $matchedPreviousMidGroup);
            if (in_array($gatewayId, $matchedPreviousMidsArray))
            {
                $matchedPreviousMidArray = $val['crm_mid_id'];
                break;
            }
        }
        return $matchedPreviousMidArray;
    }
    
    private function orderView($orderID, $crmPayload)
    {
        $result = array();
        $configId = $crmPayload['meta.configId'];
        
        $this->curlPostData['order_id'] = $orderID;
        $this->curlPostData['method'] = 'order_view';
        $this->configuration = new Configuration($configId);

        $crmInfo = $this->configuration->getCrm();

        $this->curlPostData['username'] = $crmInfo['username'];
        $this->curlPostData['password'] = $crmInfo['password'];

        $url = $crmInfo['endpoint'] . "/admin/membership.php";
        $this->curlResponse = Http::post($url, http_build_query($this->curlPostData));

        parse_str($this->curlResponse, $result);
        return $result;
    }
	
    public function postData($payload)
    {
        if(empty($payload['trial_completion_details']) || empty($payload['trial_completion_details']['only_gateway']) || $payload['meta.stepId'] <= 1 || !$payload['remote_lbp_enabled']) {
            return;
        }
		
        $crmType = $payload['meta.crmType'];
        $params = $payload['trial_completion_details'];
		
        if (empty($params['category']))
        {
            $params['category'] = 'ProtectShip';
        }
        if (!empty($params['type']))
        {
            $params['category'] = 'Upsell';
        }
		
        $params['upsell_gw_id'][$payload['meta.stepId']] = CrmResponse::get('gatewayId');
		$params['upsell_order_id'][$payload['meta.stepId']] = CrmResponse::get('orderId');
        $newInstance = Config::extensionsConfig('LenderLBP.enable_new_instance');
		
        if(!strcmp('konnektive', $crmType)) {

            $kk_iu_flag = $params['Post_KK_IU_Flag'];
            if(!strcmp($params['method'], 'importUpsell') && !empty($kk_iu_flag)) {
                $params['split_charge_id'] = $kk_iu_flag;
            }

            if(!$newInstance) {
                $url = sprintf(
                        '%s/konnektive-split-charge/', Registry::system('systemConstants.201CLICKS_URL')
                );
            } else {
                $url = self::REMOTE_URL."/konnektive/";
            }
        } else {
            if(!$newInstance) {
                $url = sprintf(
                        '%s/insureship-load-balance/', Registry::system('systemConstants.201CLICKS_URL')
                );
            } else {
                $url = self::REMOTE_URL."/limelight/";
            }
        }

        $response = Http::post($url, http_build_query($params), array(
                    'auth-token' => $params['auth_token'],
        ));
		
    }
	
    public function postMainData($payload)
    {
	$params = $payload['trial_completion_details'];
        $stepsConfig = $params['steps_for_trial_completion'];
        
        $configArray = array();
        $stepsArray = array();
        $isFound = false;
        foreach($stepsConfig as $each) {
            array_push($stepsArray, $each['step']);
            $configArray[$each['step']] = $each['label'];
        }
        
        $products = $payload['products'];
        
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

        if(empty($payload['trial_completion_details']) || 
            empty($stepsConfig) || !$isFound) {
            return;
        }
        
        $crmResponse = CrmResponse::all();
        
        if(
            empty($crmResponse['success'])
        ) {
            return;
        }
        
        $params['category']              = $configArray[$selectedProduct];
        $params['parent_order_id']       = $crmResponse['orderId'];
        $params['parent_campaign_id']    = $payload['products']['codebaseCampaignId'];
        $params['customerId']            = $crmResponse['customerId'];
        $params['main_order_gateway_id'] = $crmResponse['gatewayId'];
        unset($params['only_gateway'],$params['upsell_gw_id'][$payload['meta.stepId']],
                $params['upsell_order_id'][$payload['meta.stepId']]);
        $newInstance = Config::extensionsConfig('LenderLBP.enable_new_instance');


        if($payload['meta.crmType'] == 'konnektive') {
            //check flag is set in session and import upsell method 
            $kk_iu_flag = $params['Post_KK_IU_Flag'];
            if(!strcmp($params['method'], 'importUpsell') && !empty($kk_iu_flag)) {
                $params['split_charge_id'] = $kk_iu_flag;
            }


            $gatewayId = $this->checkOrderGateWayId($crmResponse['orderId']);
            $params['main_order_gateway_id'] = $gatewayId;
            
            // $crmResponse update to main order
            CrmResponse::update($crmResponse);
            
            if(!$newInstance) {
                $url = sprintf(
                        '%s/konnektive-split-charge/', Registry::system('systemConstants.201CLICKS_URL')
                );
            } else {
                $url = self::REMOTE_URL."/konnektive/";
            }
        } else {
            if(!$newInstance) {
                $url = sprintf(
                        '%s/insureship-load-balance/', Registry::system('systemConstants.201CLICKS_URL')
                );
            } else {
                $url = self::REMOTE_URL."/limelight/";
            }
        }

        $response = Http::post($url, http_build_query($params), array(
                    'auth-token' => $params['auth_token'],
        ));
		
    }
    
    private function getTransactionDetails($orderId)
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

}
