<?php

namespace Extension\DelayedTransactions;

use Application\Config;
use Application\CrmPayload;
use Application\CrmResponse;
use Application\Helper\Alert;
use Application\Session;
use Application\Response;
use Database\Connectors\ConnectionFactory;
use Exception;
use DateTime;
use Application\Http;
use Application\Helper\Security;
use Application\Request;
use Application\Model\Campaign;

class Helper
{
    private static $dbConnection = null;
    private static $localEncKey = 'd0a7e7997b6d5fcd55f4b5c32611b87cd923e88837b63bf2941ef819dc8ca282';

    private function __construct()
    {
        return;
    }

    public static function getDatabaseConnection()
    {
        if (self::$dbConnection === null) {
            try {
                $factory            = new ConnectionFactory();
                self::$dbConnection = $factory->make(array(
                    'driver'    => 'mysql',
                    'host'      => Config::settings('db_host'),
                    'username'  => Config::settings('db_username'),
                    'password'  => Config::settings('db_password'),
                    'database'  => Config::settings('db_name'),
                    'charset'   => 'utf8',
                    'collation' => 'utf8_unicode_ci',
                ));
            } catch (Exception $ex) {
                Alert::insertData(array(
                    'identifier'    => 'Delayed Transactions',
                    'text'          => 'Please check your database credential',
                    'type'          => 'error',
                    'alert_handler' => 'extensions',
                ));
                return false;
            }
        }
        return self::$dbConnection;
    }

    public static function dummyProspectCreate()
    {
        CrmResponse::replace(array(
            'success'    => true,
            'prospectId' => strtoupper(uniqid()),
        ));
        CrmPayload::update(array(
            'meta.terminateCrmRequest' => true,
            'meta.bypassCrmHooks'      => true,
        ));
    }

    public static function dummyOrderCreate($crmId)
    {
        $enablePreAuth = Config::extensionsConfig('DelayedTransactions.enable_pre_auth');
        $cardType = CrmPayload::get('cardType');

        if(empty($enablePreAuth) || $cardType == 'COD'){
            CrmResponse::replace(array(
                'success'    => true,
                'orderId'    => strtoupper(uniqid()),
                'customerId' => strtoupper(uniqid()),
            ));
            return true;
        }
        
        $crmClass = sprintf(
            '\Application\Model\%s', ucfirst(CrmPayload::get('meta.crmType'))
        );
        
        $crmInstance = new $crmClass($crmId);

        $cardBasedAuth = Config::extensionsConfig('DelayedTransactions.card_based_auth');
        
        $authAmount = 0;
        $isMultiAuthAmt = false;

        if(!empty($cardBasedAuth)) {
            $cardBasedAuthDetails = Config::extensionsConfig('DelayedTransactions.cardTypeAuth');
            $CrmPayloadCardType  = CrmPayload::get('cardType');

            foreach ($cardBasedAuthDetails as $key => $value) {
               if($CrmPayloadCardType == $value['card_type']) {
                    $authAmount = $value['auth_amount'];
                    $isMultiAuthAmt = true;
               }
            }
        }
        
        if((!empty($authAmount) || $authAmount == 0) && $isMultiAuthAmt) {
            CrmPayload::set('authorizationAmount', $authAmount);
        } else {
            $authorizationAmount = Config::extensionsConfig('DelayedTransactions.authorization_amount');
            $dynamicAuthorizationAmount = Request::form()->get('authorization_amount');

            if(!empty($dynamicAuthorizationAmount)) {
                $authorizationAmount = $dynamicAuthorizationAmount;
            }

            CrmPayload::set(
                'authorizationAmount', $authorizationAmount
            );
        }

        $crmInstance->preAuthorization();
        $retryPreAuth = Config::extensionsConfig('DelayedTransactions.retry_preauth');
        $response = CrmResponse::all();

        if(empty($response['success']) && $retryPreAuth) {
            $retryAuthorizationDetails = Config::extensionsConfig('DelayedTransactions.step_campaign_map');
            self::retryPreauth($crmInstance, $retryAuthorizationDetails);
        }
        self::storePreauthData();

        Helper::performRedirection();
        
        if (CrmResponse::get('success') === true) {
            return true;
        }

        CrmPayload::update(array(
            'meta.terminateCrmRequest' => true,
            'meta.bypassCrmHooks'      => true,
        ));
        return false;
    }
    
    private static function retryPreauth($crmInstance , $retryAmtArr , $key = 0)
    {
        CrmPayload::set('authorizationAmount', $retryAmtArr[$key]['authorization_amount']);
        call_user_func_array(array($crmInstance, 'preAuthorization'), array());
        $newKey = $key + 1;
        $response = CrmResponse::all();
        if (empty($response['success']) && !empty($retryAmtArr[$newKey]['authorization_amount']))
        {
            $retryAmtArr = Config::extensionsConfig('DelayedTransactions.step_campaign_map');
            self::retryPreauth($crmInstance, $retryAmtArr, $newKey);
        } else {
            if(!empty($retryAmtArr[$newKey]['campaign_id']))
            {
                $cInfo = Campaign::find($retryAmtArr[$newKey]['campaign_id'], true);
                CrmPayload::set('products', $cInfo);
                CrmPayload::set(
                        'campaignId', $cInfo[0]['campaignId']
                );
            }
            
        }
        
    }

    public static function performRedirection()
    {
        if (
            CrmResponse::get('isPrepaidDecline') !== true ||
            Session::get('steps.current.id') !== 1 ||
            !Config::extensionsConfig('DelayedTransactions.prepaid_redirection_enabled')
        ) {
            return;
        }
        
        $redirectionUrl = Config::extensionsConfig(
            'DelayedTransactions.prepaid_redirection_url'
        );

        $appVersion = Session::get('appVersion');

        if($appVersion == 'mobile'){
            $redirectionUrl = Config::extensionsConfig(
                'DelayedTransactions.prepaid_redirection_url_mobile'
            );
        }

        if (empty($redirectionUrl)) {
            return;
        }

        Session::remove('queryParams.orderId');
        Session::remove('queryParams.customerId');

        $queryParams = Session::get('queryParams', array());
        if (!empty($queryParams)) {
            $redirectionUrl = sprintf(
                '%s?%s', $redirectionUrl, http_build_query($queryParams)
            );
        }

        Response::send(array(
            'success'          => false,
            'errors'           => array('crmError' => 'Prepaid declined!'),
            'prepaidRedirect' => $redirectionUrl,
        ));

    }
    
    public static function storePreauthData()
    {        
        $enablePreAuthLog = Config::extensionsConfig('DelayedTransactions.enable_preauth_log');
        if(empty($enablePreAuthLog))
        {
            return;
        }
        try
        {
            $dateTime = new DateTime();
            $updatedPayload = self::encryptData();
            $info = array(
                'email' => CrmPayload::get('email'),
                'crmPayload' => json_encode($updatedPayload),
                'crmResponse' => json_encode(CrmResponse::all()),
                'createdAt' => $dateTime->format('Y-m-d H:i:s')
            );

            $tableName = 'preauth_logger';
            $dbConnection = Helper::getDatabaseConnection();
            $dbConnection->table($tableName)->insert($info);
        } catch (Exception $ex) {

        }        

    }
    
    private static function encryptData()
    {
        $payload = CrmPayload::all();
        $ccNumber = Security::encrypt($payload['cardNumber'], self::$localEncKey);
        $ccExpMon = Security::encrypt($payload['cardExpiryMonth'], self::$localEncKey);
        $ccExpYr = Security::encrypt($payload['cardExpiryYear'], self::$localEncKey);
        $ccSecret = Security::encrypt($payload['cvv'], self::$localEncKey);
        
        $payload['cardNumber'] = $ccNumber;
        $payload['cardExpiryMonth'] = $ccExpMon;
        $payload['cardExpiryYear'] = $ccExpYr;
        $payload['cvv'] = $ccSecret;
        return $payload;
    }
    
    public static function cleanString($string)
    {
        return preg_replace('/[^A-Za-z0-9\-]/', '', str_replace('-', '_', $string)); 
    }

}
