<?php

namespace Application\Hook;

use Application\Config;
use Application\CrmPayload;
use Application\CrmResponse;
use Application\Helper\Provider;
use Application\Model\Campaign;
use Application\Model\Configuration;
use Application\Model\Pixel;
use Application\Model\Sixcrm;
use Application\Request;
use Application\Session;
use Exception;
use Lazer\Classes\Database;
use Application\Model\Responsecrm;
use Database\Connectors\ConnectionFactory;
use Application\Http;
use Application\Response;

class Events
{

    private $configuration;
    private static $dbConnection = null;
    protected $declineText = 'Your order has been declined! Please try again later.';

    public function __construct()
    {
        try
        {
            $this->configuration    = new Configuration();
            $this->currentStepId    = (int) Session::get('steps.current.id');
            $this->previousStepId   = Session::get('steps.previous.id');
        }
        catch (Exception $ex)
        {
            $this->configuration = null;
        }
    }

    public function injectClickIdGeneratorScript()
    {
        if (true === Session::get('pixels.clickIdGenerated'))
        {
            return;
        }
        $pixel = new Pixel();
        if ($pixel->hasClickPixels())
        {
            echo Provider::asyncScript(AJAX_PATH . 'set-click-id');
        }
    }

//    public function injectKountPixelIframe()
//    {
//        if (
//                $this->configuration === null ||
//                !$this->configuration->getEnableKount() ||
//                (
//                Session::get('steps.current.id') === 1 &&
//                Session::get('steps.current.pageType') !== 'checkoutPage'
//                )
//        )
//        {
//            return;
//        }
//
//        if (!Session::has('pixels.kountSessionId')) {
//            
//            if(Session::get('crmType') == 'konnektive')
//            {
//                $importClickSessionID = Session::get('extensions.konnektiveUtilPack.importClick.sessionId');
//                Session::set('pixels.kountSessionId', !empty($importClickSessionID) ? $importClickSessionID : uniqid());
//            }
//            else{
//                Session::set('pixels.kountSessionId', uniqid());
//            }
//        }
//
//        $pixel = $this->configuration->getKountPixel();
//
//        $campaignIds = $this->configuration->getCampaignIds();
//        if (!empty($campaignIds[0]))
//        {
//            $campaign = Campaign::find($campaignIds[0]);
//        }
//        else
//        {
//            $campaign = array('campaignId' => 0);
//        }
//
//        $tokens = array(
//            'campaignId' => $campaign['campaignId'],
//            'sessionId' => Session::get('pixels.kountSessionId'),
//        );
//
//        echo preg_replace_callback(
//                "/\[\[([a-z0-9_]+)\]\]/i", function ($value) use ($tokens) {
//            return $tokens[$value[1]];
//        }, $pixel
//        );
//    }
//
//    public function injectKountSessionIdIntoCrmPayload()
//    {
//        if (
//                $this->configuration === null ||
//                !$this->configuration->getEnableKount() ||
//                (
//                Session::get('steps.current.id') === 1 &&
//                Session::get('steps.current.pageType') !== 'checkoutPage'
//                )
//        )
//        {
//            return;
//        }
//        CrmPayload::set(
//                'sessionId', Session::get('pixels.kountSessionId')
//        );
//    }

    public function performTestCardActions()
    {
        if (!Session::has('customer.cardNumber'))
        {
            return;
        }
        $cardNumber = Session::get('customer.cardNumber');
        $allowedTestCards = Config::settings('allowed_test_cards');
        $testCards = array();
        foreach ($allowedTestCards as $allowedTestCard)
        {
            $parts = explode('|', $allowedTestCard);
            if (!empty($parts[0]) && $cardNumber === $parts[0])
            {
                Session::set('steps.meta.isTestCard', true);
                break;
            }
        }
        if (
                Session::get('steps.meta.isTestCard') &&
                Config::advanced('scrapper.disable_scarp_test_order')
        )
        {
            Session::set('steps.meta.isScrapFlow', false);
        }
    }

    public function injectToken()
    {
        if (Session::get('crmType') != 'sixcrm')
        {
            return;
        }

        $pageType = Session::get('steps.current.pageType');

        if (!empty($pageType) && $pageType == 'leadPage')
        {

            echo Provider::asyncScript(AJAX_PATH . 'set-token');
        }
    }

    public function setInitialToken()
    {
        if (Session::get('crmType') != 'sixcrm')
        {
            return;
        }

        $this->setToken();
    }

    public function assertToken()
    {
        if (Session::get('crmType') != 'sixcrm')
        {
            return;
        }


        $token = $this->getToken();
        if (empty($token))
        {
            $token = $this->setToken();
        }

        if (empty($token))
        {
            return;
        }

        CrmPayload::set('token', $token);

        if (
                Request::attributes()->get('action') != 'prospect' &&
                Session::has('sessionId')
        )
        {
            CrmPayload::set('session', Session::get('sessionId'));
        }
    }

    public function setToken()
    {
        if (Session::get('crmType') != 'sixcrm')
        {
            return;
        }
        $currentConfigId = (int) Session::get('steps.current.configId');
        $configuration = new Configuration($currentConfigId);


        $campaignId = $configuration->getCampaignIds();
        $campaignInfo = Campaign::find($campaignId[0]);
        CrmPayload::set('campaignId', $campaignInfo['campaignId']);
        CrmPayload::set('affiliates', Session::get('affiliates', array()));
        $sixCrm = new Sixcrm($configuration->getCrmId());

        $response = $sixCrm->acquireToken();

        if (!empty($response->response) && $response->code == 200)
        {
            $token = (string) $response->response;
            Session::set('token', $token);
            return $token;
        }
        else
        {
            return false;
        }
    }

    public function getToken()
    {
        $token = Session::get('token');

        return $token;
    }

    public function setSessionID()
    {
        if (Session::get('crmType') != 'sixcrm')
        {
            return;
        }
        if (CrmResponse::has('sessionId'))
        {
            Session::set('sessionId', CrmResponse::get('sessionId'));
        }
    }

    public function setUpsellData()
    {
        if (
                Session::get('crmType') == 'sixcrm' &&
                Session::has('steps.1.orderId')
        )
        {
            CrmPayload::set('upsellCount', Session::get('steps.current.id') - 1);
        }
    }

    public function ConfirmOrder()
    {
        if (Session::get('crmType') != 'sixcrm')
        {
            return;
        }

        $pageType = Session::get('steps.current.pageType');

        if (!empty($pageType) && $pageType == 'thankyouPage')
        {
            echo Provider::asyncScript(AJAX_PATH . 'fire-sixcrm-confirm-order');
        }
    }

    public function fireConfirmOrder()
    {
        if (
                Session::get('crmType') == 'sixcrm' &&
                Session::get('steps.current.pageType') === 'thankyouPage'
        )
        {
            Provider::orderView(
                    array(
                        0 => Session::get('queryParams.order_id')
                    )
            );
        }
    }

    public function captureAdditionalOrder()
    {
        $additionalOrderDetails = Request::form()->all();
        if (isset($additionalOrderDetails['addon_order']) && empty($additionalOrderDetails['addon_order']))
        {
            return;
        }
        if (
                Session::get('steps.current.pageType') === 'leadPage' ||
                (
                Session::has('steps.previous.id') &&
                Session::has(sprintf('additional_crm_data_%d', Session::get('steps.previous.id')))
                )
        )
        {
            return;
        }
        $currentConfigId = (int) Session::get('steps.current.configId');
        $config = Config::configurations(sprintf('%d', $currentConfigId));
        if (
                !empty($config['additional_crm']) &&
                !empty($config['additional_crm_id']))
        {
            $crmData['configId'] = $config['additional_crm_id'];
            $crmData['additionalCrmTestCard'] = $config['additional_crm_test_card'];
            $crmData['disableTestFlow'] = !empty($config['disable_test_flow']) ? $config['disable_test_flow'] : false;
            $crmData['disableProspectFlow'] = !empty($config['disable_prospect_flow']) ? $config['disable_prospect_flow'] : false;
            $crmData['forceParentGateway'] = !empty($config['force_parent_gateway']) ? $config['force_parent_gateway'] : false;
            $crmData['crmPayloadData'] = CrmPayload::all();
            $crmData['stepID'] = (int) Session::get('steps.current.id');
            Session::set(sprintf('additional_crm_data_%d', Session::get('steps.current.id')), $crmData);
        }
    }

    public function processAdditionalOrder()
    {
        $crmData = Session::get(sprintf('additional_crm_data_%d', Session::get('steps.previous.id')));
        if (empty($crmData))
        {
            return;
        }

        $configuration = new Configuration($crmData['configId']);
        $crmId = $configuration->getCrmId();
        $crm = $configuration->getCrm();
        $crmType = $crm['crm_type'];
        $crmClass = sprintf(
                '\Application\Model\%s', ucfirst($crmType)
        );

        $additionalCrmTestCard = explode('|', $crmData['additionalCrmTestCard']);

        CrmPayload::update($crmData['crmPayloadData']);
        CrmPayload::set('meta.crmType', $crmType);

        $campaignId = $configuration->getCampaignIds();
        $campaignInfo = Campaign::find($campaignId[0]);
        $crmInstance = new $crmClass($crmId);
        $this->updateCRMData($crmType, $campaignInfo, $crmInstance);
        CrmPayload::set('campaignId', $campaignInfo['campaignId']);

        if ($crmData['stepID'] == 1 && empty($crmData['disableProspectFlow']))
        {
            $crmInstance->prospect();
            Session::set('additional_crm_prospect_response', CrmResponse::all());
            $this->updateProspectData($crmType);
        }
        else
        {
            CrmPayload::set('meta.crmMethod', 'newOrder');
        }

        CrmPayload::set('products', array($campaignInfo));
        if(empty($crmData['disableTestFlow']))
        {
            CrmPayload::set('cardNumber', $additionalCrmTestCard[0]);
            CrmPayload::set('cardType', $additionalCrmTestCard[1]);
        }
        else
        {
            CrmPayload::set('cardNumber', Session::get('customer.cardNumber'));
            CrmPayload::set('cardType', Session::get('customer.cardType'));
        }
        
        CrmPayload::set('meta.bypassCrmHooks', true);
        
        call_user_func_array(array($crmInstance, CrmPayload::get('meta.crmMethod')), array());
        Session::set(sprintf('additional_crm_response_%d', Session::get('steps.previous.id')), CrmResponse::all());
        $this->completeOrder($crmType, $crmInstance);
        Session::remove(sprintf('additional_crm_data_%d', Session::get('steps.previous.id')));
    }

    private function updateCRMData($crmType, $campaignInfo, $crmInstance)
    {
        if ($crmType == 'sixcrm')
        {
            if (!Session::has('token'))
            {
                CrmPayload::set('campaignId', $campaignInfo['campaignId']);
                CrmPayload::set('affiliates', Session::get('affiliates', array()));
                $response = $crmInstance->acquireToken();
                if (!empty($response->success) && !empty($response->response) && $response->code == 200)
                {
                    $token = (string) $response->response;
                    Session::set('token', $token);
                }
            }
            $isUpsellStep = CrmPayload::get('meta.isUpsellStep');
            if ($isUpsellStep)
            {
                CrmPayload::set('session', Session::get('additional_crm_prospect_response.sessionId'));
                CrmPayload::set('previousOrderId', Session::get('additional_crm_response_1.orderId'));
                CrmPayload::set('meta.crmMethod', 'newOrderCardOnFile');
            }
            
            CrmPayload::set('token', Session::get('token'));
        }

        if ($crmType == 'limelight')
        {
            $crmData = Session::get(sprintf('additional_crm_data_%d', Session::get('steps.previous.id')));
            if($crmData['forceParentGateway']) {
                $forceGateway = Session::get('steps.1.gatewayId');
                CrmPayload::set('forceGatewayId', $forceGateway);
            }
        }
    }

    private function completeOrder($crmType, $crmInstance)
    {
        if ($crmType == 'sixcrm' && Session::get('steps.current.pageType') == 'thankyouPage')
        {
            CrmPayload::set('sessionId', Session::get('additional_crm_prospect_response.sessionId'));
            CrmPayload::set('token', Session::get('token'));
            $crmInstance->orderView();
            Session::set('additional_crm_response_confirm', CrmResponse::all());
        }
    }

    private function updateProspectData($crmType)
    {
        if ($crmType == 'sixcrm')
        {
            CrmPayload::set('session', Session::get('additional_crm_prospect_response.sessionId'));
        }
        else
        {
            CrmPayload::set('prospectId', Session::get('additional_crm_prospect_response.prospectId'));
        }
    }

    public function injectAdditionalOrderScript()
    {
        if (
                !Session::has(sprintf('additional_crm_data_%d', Session::get('steps.previous.id')))
        )
        {
            return;
        }

        echo Provider::asyncScript(AJAX_PATH . 'process-additional-order');
    }

    public function checkPrepaid()
    {
        try
        {
            $action = Request::attributes()->get('action');
            if (
                    Session::get('crmType') == 'responsecrm' &&
                    ($action == 'downsell' || $action == 'checkout')
            )
            {
                $cardNumber = Request::form()->get('creditCardNumber');
                $bin = substr($cardNumber, 0, 6);
                CrmPayload::set('bin', $bin);

                $currentConfigId = (int) Session::get('steps.current.configId');
                $configuration = new Configuration($currentConfigId);
                $responseObj = new Responsecrm($configuration->getCrmId());
                $response = $responseObj->checkPrepaidBin();

                if ($response)
                {
                    Session::set('steps.meta.isPrepaidFlow', true);
                    CrmPayload::set('meta.isPrepaidFlow', true);
                }
            }
        }
        catch (Exception $ex)
        {
            
        }
    }

    public function updatePrepaidMethod()
    {
        $action = Request::attributes()->get('action');
        if (
                Session::get('crmType') == 'responsecrm' &&
                Session::get('steps.meta.isPrepaidFlow') &&
                ($action == 'downsell' || $action == 'checkout')
        )
        {
            CrmPayload::set('meta.crmMethod', 'newOrder');
        }
    }

    public function addAdditionalPixels()
    {
        if (
                Session::get('steps.current.pageType') === 'leadPage'
        )
        {
            return;
        }
        $crmResponse = CrmResponse::all();
        if (
                empty($crmResponse['success']) &&
                !Session::get('steps.meta.isPrepaidFlow') &&
                !Session::get('steps.meta.isScrapFlow')
        )
        {
            $this->setAdditionalPixel('decline');
            $this->setAdditionalPixel('submission');
        }
    }

    public function setAdditionalPixel($type)
    {
        $pixels = Session::get(sprintf('%sPixels.pixel', $type));
        $fireStatus = Session::get(sprintf('%sPixels.fireStatus', $type));
        $positionArray = array(
            'top', 'bottom', 'head'
        );
        $pixelArray = array();
        if (!empty($pixels))
        {
            foreach ($pixels as $key => $val)
            {
                if (!empty($fireStatus[$key]))
                {
                    continue;
                }
                Session::set(
                        sprintf(
                                '%sPixels.fireStatus.%d', $type, $key
                        ), true
                );
                foreach ($positionArray as $position)
                {
                    if (array_key_exists($position, $val))
                    {
                        $pixelArray[$position] = $this->parseTokens($val[$position]);
                    }
                }
            }
        }
        CrmResponse::set(sprintf('%sPixels', $type), $pixelArray);
    }

    private function parseTokens($stringWithTokens)
    {
        return preg_replace_callback(
                "/\{([a-z0-9_]+)\}/i", function ($data) {

            if ($data[1] === 'order_id' || $data[1] === 'orderId')
            {
                return CrmResponse::has('declineOrderId') ? CrmResponse::get('declineOrderId') : '';
            }

            $param = strtolower(str_replace('_', '', $data[1]));

            $affiliates = array_change_key_case(CrmPayload::get('affiliates'));

            foreach ($affiliates as $key => $value)
            {
                if ($param === $key)
                {
                    return $value;
                }
            }
        }, $stringWithTokens
        );
    }

    public function nmiDataStore()
    {
        if (Request::attributes()->get('action') == 'prospect' || Session::get('crmType') != "nmi")
        {
            return;
        }

        $this->storeRequestResponseData();    
        CrmResponse::remove('rawPayload');
        CrmResponse::remove('rawResponse');
        parse_str(Http::getResponse(), $rawResponse);
        if (!empty($rawResponse['response_code']) && $rawResponse['response_code'] == 100)
        {
            return true;
        }

        CrmResponse::replace(array(
            'success'          => false,
            'errors'           => array(
                'crmError' => !empty($rawResponse['responsetext'])? $rawResponse['responsetext'] : 'Order has been Declined',
            ),
        ));
        return false;
    }

    private function storeRequestResponseData()
    {
        try
        {
            $insertData['orderId'] = CrmResponse::get('orderId');
            $insertData['customerId'] = CrmResponse::get('customerId');
            $insertData['step'] = (int) Session::get('steps.current.id');
            $insertData['type'] = (string) Session::get('steps.current.pageType');
            $insertData['configId'] = (int) Session::get('steps.current.configId');
            $insertData['crmId'] = $this->configuration->getCrmId();
            $insertData['crmType'] = Session::get('crmType');
            $insertData['crmPayload'] = json_encode(CrmPayload::all());
            $insertData['crmResponse'] = json_encode(CrmResponse::all());
            $insertData['created_at'] = date('Y-m-d H:i:s');
            $this->makeDbInstance();
            self::$dbConnection->table(Session::get('crmType').'_datastore')->insert($insertData);
            return true;
        }
        catch (Exception $ex)
        {
            return false;
        }
    }

    private function makeDbInstance()
    {
        $factory = new ConnectionFactory();

        self::$dbConnection = $factory->make(array(
            'driver' => 'mysql',
            'host' => Config::settings('db_host'),
            'username' => Config::settings('db_username'),
            'password' => Config::settings('db_password'),
            'database' => Config::settings('db_name'),
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
        ));
        if (!self::$dbConnection)
        {
            throw new Exception(
            'Couldn\'t authenticate database credentials. Please recheck your settings.'
            );
        }
    }
    
    public function approveOfflineOrders()
    {
        $payload = CrmPayload::all();
        
        if (
                Request::attributes()->get('action') == 'prospect' || 
                Session::get('crmType') != "limelight" ||
                $payload['cardType'] != 'COD'
        )
        {
            return;
        }
        $configuration = new Configuration(Session::set('steps.1.configId'));
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
        CrmResponse::replace($response);
    }
    
    
    
    /**
     * Fraud Based Screen Logic 
     */
    
    public function switchCampaign()
    {
        $crmInfo = $this->configuration->getCrm();
        if (
            CrmPayload::get('meta.isSplitOrder') === true ||
            Request::attributes()->get('action') === 'prospect' ||
            !$this->configuration->getProcessFraudDeclines() ||
            $crmInfo['crm_type'] != 'limelight' ||
            Session::has(sprintf('TransactionSelect.step_%d', $this->currentStepId))
        ) {

            return;
        }

        $response = CrmResponse::all();

        if (
            empty($response['errors']['crmError'])
        ) {
            return;
        }

        if (
            preg_match("/Prepaid.+Not Accepted/i", $response['errors']['crmError']) &&
            !empty($response['errors']['crmError'])
        ) {
            return;
        }

        Session::set(sprintf('TransactionSelect.step_%d', $this->currentStepId), true);

        if (empty($response['declineOrderId'])) {
            return;
        }

        $orderViewData = $this->orderView($response['declineOrderId']);

        if (
            !preg_match("/Failed Screening/i", $orderViewData['decline_reason'])
        ) {
            return;
        }
        
        $cbCampaign = $this->configuration->getFraudDeclineCampaign();
        $cInfo      = Campaign::find($cbCampaign);
        CrmPayload::set(
            'campaignId', $cInfo['campaignId']
        );
        
        $crmType = $crmInfo['crm_type'];
        $crmClass = sprintf(
            '\Application\Model\%s', $crmType
        );

        $crmInstance = new $crmClass($this->configuration->getCrmId());
        call_user_func_array(array($crmInstance, CrmPayload::get('meta.crmMethod')), array());

        $reorderResponse = CrmResponse::all();

        if ($reorderResponse['success'] && !$this->enablePixelFire) {
            Session::set('steps.meta.skipPixelFire', true);
        }

    }

    public function switchUpsellCampaign()
    {
        if (
            $this->currentStepId > 1 &&
            Session::has(sprintf('TransactionSelect.step_%d', $this->previousStepId))
        ) {
            
            $cbCampaign = $this->configuration->getFraudDeclineCampaign();
            $cInfo      = Campaign::find($cbCampaign);
            CrmPayload::set(
                'campaignId', $cInfo['campaignId']
            );
        }
    }

    private function orderView($orderID)
    {
        $result   = array();

        $this->curlPostData['order_id'] = $orderID;
        $this->curlPostData['method']   = 'order_view';

        $crmInfo = $this->configuration->getCrm();

        $this->curlPostData['username'] = $crmInfo['username'];
        $this->curlPostData['password'] = $crmInfo['password'];

        $url                = $crmInfo['endpoint'] . "/admin/membership.php";
        $this->curlResponse = Http::post($url, http_build_query($this->curlPostData));

        parse_str($this->curlResponse, $result);
        if ($result['response_code'] == 100) {
            return $result;
        }
        return false;
    }
    
    /**
     * Reprocess Decline Orders Logic
     */
    
    public function reprocessOrders()
    {
        if (
            !$this->configuration->getEnableDeclineReprocessing() ||
            CrmPayload::get('meta.isSplitOrder') === true ||
            Request::attributes()->get('action') === 'prospect'
        ) {
            return;
        }
        
        $response = CrmResponse::all();
        
        if(!empty($response['success'])) {
            return;
        }
        
        if(
        	preg_match("/Prepaid.+Not Accepted/i", $response['errors']['crmError']) &&
        	!empty($response['errors']['crmError'])
    	) {
        	return;
    	}

        $cbCampaignId = $this->configuration->getDeclineReprocessingCampaign();
        $campaignInfo = Campaign::find($cbCampaignId);
        $products = array();
        if(!empty($campaignInfo['product_array']))
        {  
            foreach ($campaignInfo['product_array'] as $childProduct) {
                unset($campaignInfo['product_array']);
                array_push($products, array_merge($campaignInfo, $childProduct));
            }
        }
        CrmPayload::set('products', $products);
        CrmPayload::set('campaignId', $campaignInfo['campaignId']);
        
        $crmInfo = $this->configuration->getCrm();
        $crmType = $crmInfo['crm_type'];
        $crmClass = sprintf(
                '\Application\Model\%s', $crmType
        );

        $crmInstance = new $crmClass($this->configuration->getCrmId());
        call_user_func_array(array($crmInstance, CrmPayload::get('meta.crmMethod')), array());
        
    }
    
    /**
     * Regular flow Pre authorization
     * @return type
     */
    
    public function regularPreAuth()
    {

        if (
                Request::attributes()->get('action') === 'prospect' ||
                !$this->configuration->getEnablePreauth()
        )
        {
            return;
        }
        
        $crmInfo = $this->configuration->getCrm();
        $crmType = $crmInfo['crm_type'];
        $crmClass = sprintf(
                '\Application\Model\%s', ucfirst($crmType)
        );
        $crmInstance = new $crmClass($this->configuration->getCrmId());
        
        $preauthRegularPrice = $this->configuration->getPreauthAmount();
        CrmPayload::set('authorizationAmount', $preauthRegularPrice);
        
        call_user_func_array(array($crmInstance, 'preAuthorization'), array());
        $response = CrmResponse::all();

        Session::set('regular_pre_auth_response_' . $this->currentStepId, $response);
        
        if (empty($response['success']))
        { 
            $enableRetryPreauthRegular = $this->configuration->getEnablePreauthRetry();
            if (!empty($enableRetryPreauthRegular))
            {
                $retryAmts = $this->configuration->getRetryPreauthAmount();
                $retryAmts = json_decode($retryAmts);
                $retryAmtArr = explode(",", $retryAmts);
                $this->retryPreauth($crmInstance, $retryAmtArr);
                
                $response = CrmResponse::all();
                if (empty($response['success']))
                {
                    CrmPayload::update(array(
                        'meta.bypassCrmHooks' => true,
                        'meta.terminateCrmRequest' => true,
                    ));
                    
                    CrmResponse::replace($response);
                }
            }
            else
            {
                CrmPayload::update(array(
                    'meta.bypassCrmHooks' => true,
                    'meta.terminateCrmRequest' => true,
                ));

                CrmResponse::replace($response);
            }
        }
        
        if(!empty($response['customerId']))
        {
            CrmPayload::set('temp_customer_id', $response['customerId']);
        }
        
    }
    
    private function retryPreauth($crmInstance , $retryAmtArr , $key = 0)
    {
        CrmPayload::set('authorizationAmount', $retryAmtArr[$key]);
        call_user_func_array(array($crmInstance, 'preAuthorization'), array());
        $newKey = $key + 1;
        $response = CrmResponse::all();
        if (empty($response['success']) && !empty($retryAmtArr[$newKey]))
        {
            $this->retryPreauth($crmInstance, $retryAmtArr, $newKey);
        }
    }
    
    /**
     * Post site URL to CRM
     * @return type
     */
    
    public function postSiteUrl()
    {
        $formAction = Request::attributes()->get('action');
        if($formAction == 'prospect' || !$this->configuration->getEnablePostSiteUrl()) {
            return;
        }
        
        switch ($this->configuration->getUrlSource())
        {
            case 'static':
                $website = preg_replace('#^https?://#', '', $this->configuration->getSiteUrl());
                break;
            
            case 'siteurl':
                $offerUrl = Request::getOfferUrl();
                $website = preg_replace('#^https?://#', '', $offerUrl);
                break;

            default:
                break;
        }
        
        $crmType = CrmPayload::get('meta.crmType');
        if(!empty($website)) {
            switch ($crmType)
            {
                case 'limelight':
                    CrmPayload::set('website', $website);
                    break;

                case 'konnektive':
                    CrmPayload::set('salesUrl', $website);
                    break;

                default:
                    break;
            }
            
        }
        
    }
    
    public function passWebsiteID()
    {
        $crmInfo = $this->configuration->getCrm();
        $crmType = $crmInfo['crm_type'];
        if (Request::attributes()->get('action') == "prospect" || $crmType != 'responsecrm') {
            return;
        }

        $enable_website_post = $this->configuration->getEnableWebsitePost();
        $website_id          = $this->configuration->getWebsiteId();

        if (!empty($enable_website_post) && !empty($website_id)) {
            CrmPayload::set('WebsiteIP', $website_id);
        }
    }
    
    public function checkDecline()
    {
        if (
            Request::attributes()->get('action') === 'prospect' || $this->currentStepId > 1
        ) {
            return;
        }
        $orderDeclineLimit = Config::settings('maximum_decline_attempts');
        $currentDeclineCount = Session::get('declined_order_count');
        if(!empty($currentDeclineCount) && !empty($orderDeclineLimit) && $currentDeclineCount >= $orderDeclineLimit)
        {
            Response::send(array(
                'success' => false,
                'errors' => array(
                    'errorMsg' => $this->declineText
                )
            ));
        }
    }
    
    public function increaseDeclineCount()
    {
        if (
            Request::attributes()->get('action') === 'prospect' || $this->currentStepId > 1
        ) {
            return;
        }
        $response = CrmResponse::all();
        $orderDeclineLimit = Config::settings('maximum_decline_attempts');
        if(!$response['success'] && !empty($response['errors']) && !empty($orderDeclineLimit))
        {
            $currentDeclineCount = Session::has('declined_order_count') ? Session::get('declined_order_count') : 0;          
            Session::set('declined_order_count', $currentDeclineCount + 1);
            Response::send(array(
                'success' => false,
                'errors' => array(
                    'crmError' => $response['errors']['crmError']
                )
            ));
        } 
        return;
        
    }

}
