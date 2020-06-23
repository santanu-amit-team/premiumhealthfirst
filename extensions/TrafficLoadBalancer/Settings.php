<?php

namespace Extension\TrafficLoadBalancer;

use Application\Config;
use Application\Http;
use Application\Registry;
use Application\Request;
use Application\Session;
use Lazer\Classes\Database;

class Settings
{

    private function __construct()
    {
        return;
    }

    public static $possibleCards = array('visa', 'master', 'discover', 'amex', 'diners', 'jcb');

    public static function getLocal()
    {
        $percentage = array(1 => 0, 2 => 0);
        $cardPercentage = array();
        if(!empty(Config::extensionsConfig('TrafficLoadBalancer.enable_default_settings'))){
            Scrapper::$scrapSpecificScrapMethod = Config::extensionsConfig('TrafficLoadBalancer.default_settings.'
                    . 'scrapping_method');
            $percentage[1] = (int) Config::extensionsConfig('TrafficLoadBalancer.default_settings.step1');
            $percentage[2] = (int) Config::extensionsConfig('TrafficLoadBalancer.default_settings.step2');
            $percentage[3] = (int) Config::extensionsConfig('TrafficLoadBalancer.default_settings.step3');
            $percentage[4] = (int) Config::extensionsConfig('TrafficLoadBalancer.default_settings.step4');
            $percentage[5] = (int) Config::extensionsConfig('TrafficLoadBalancer.default_settings.step5');
        }
//        print_r(Config::advanced());die;
        if (!empty(Config::extensionsConfig('TrafficLoadBalancer.enable_card_scrapper')))
        {
            $cardPercentage = self::localCardPercentagePrepare(Config::extensionsConfig('TrafficLoadBalancer.cardFilter'));
        }
        
        $affiliates = array_replace(
                self::initializeAffiliates(), Session::get('affiliates', array())
        );
        $affiliateSettings = Database::table('affiliates')->findAll()->asArray();
        $mappedAffiliates = self::mapAffiliateKeys($affiliates);
        $selectedAffiliates = self::initializeAffiliates();       
        $enableAdvancedAffiliateLogic = Config::extensionsConfig('TrafficLoadBalancer.enable_affiliate_orderfilter');
//        print_r($affiliateSettings);die;
        foreach ($affiliateSettings as $affiliateSetting)
        { 
            $matched = true;$affiliateKey = '';
            foreach ($mappedAffiliates as $key => $value)
            {
                if ($affiliateSetting[$key] !== $value)
                {
                    $matched = false;
                    break;
                }
                 $affiliateKey = $value;
            }
            
            if ($matched)
            {
                $affiliatePercentage = self::getAffiliatePercenatge($affiliateSetting);
               
                if(!empty(Config::extensionsConfig('TrafficLoadBalancer.enable_affiliate_orderfilter'))){
                    Scrapper::$scrapSpecificScrapMethod = Config::extensionsConfig('TrafficLoadBalancer.'
                    . 'scrapping_method');
                    $percentage[1] = empty($affiliatePercentage['step1']) ? 0 : (int) $affiliatePercentage['step1'];
                    $percentage[2] = empty($affiliatePercentage['step2']) ? 0 : (int) $affiliatePercentage['step2'];
                    $percentage[3] = empty($affiliatePercentage['step3']) ? 0 : (int) $affiliatePercentage['step3'];
                    $percentage[4] = empty($affiliatePercentage['step4']) ? 0 : (int) $affiliatePercentage['step4'];
                    $percentage[5] = empty($affiliatePercentage['step5']) ? 0 : (int) $affiliatePercentage['step5'];
                }
               
               
                $selectedAffiliates = $affiliates;
                break;
            }
        }
        
        
        if (count(array_filter($selectedAffiliates)) == 0 && $enableAdvancedAffiliateLogic)
        {
            $updatedAffilitesLogic = self::advancedAffilitesLogic();
            $percentage = $updatedAffilitesLogic['percentage'];
            $selectedAffiliates = $updatedAffilitesLogic['affiliates'];
            $cardPercentage = $updatedAffilitesLogic['cardPercentage'];
        }

        return array(
            'percentage' => $percentage, 'affiliates' => $selectedAffiliates,
            'card_details' => empty($cardPercentage) ? null : $cardPercentage
        );
    }
    
    private static function getAffiliatePercenatge($affiliateSettings){
        $affiliateScrapperSettings = Config::extensionsConfig('TrafficLoadBalancer.affiliateFilter');
        if(empty($affiliateScrapperSettings))
            return false;
        $percentage = array();
        foreach ($affiliateScrapperSettings as $val){
            if($val['affiliate'] == $affiliateSettings['id']){
                $percentage['step1'] = $val['step1'];
                $percentage['step2'] = $val['step2'];
                $percentage['step3'] = $val['step3'];
                $percentage['step4'] = $val['step4'];
                $percentage['step5'] = $val['step5'];
            }
        }
        return empty($percentage) ? false : $percentage;
    }

    private static function localCardPercentagePrepare($param)
    {
        //print_r($param);die;
        $cardPercentage = array();
        foreach (self::$possibleCards as $val)
        {
            //$cardPercentage[$val][0] = 0;
            foreach ($param as $value)
            {
                if($val == $value['card_type']){
                    //unset($cardPercentage[$val]);
                    $cardPercentage[$val][$value['card_filter_config']] = 
                             $value['card_percentage'];
                    
                }
            }
           /* $cardKeyStep1 = "card_" . $val . "_scrap1";
            $cardKeyStep2 = "card_" . $val . "_scrap2";
            $cardPercentage[$val][1] = empty($param[$cardKeyStep1]) ? 0 :
                    (int) $param[$cardKeyStep1];
            $cardPercentage[$val][2] = empty($param[$cardKeyStep2]) ? 0 :
                    (int) $param[$cardKeyStep2];*/

            $cardPercentage[$val]['details']['scrappedCount'] = 0;
            $cardPercentage[$val]['details']['hitsCount'] = 0;
            $cardPercentage[$val]['details']['scrapped'] = 0;
            $cardPercentage[$val]['details']['hits'] = 0;
        }

        return $cardPercentage;
    }

    private static function advancedAffilitesLogic()
    {
        $percentage = array(1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0);
        $cardPercentage = array();
        if(!empty(Config::extensionsConfig('TrafficLoadBalancer.enable_default_settings'))){
            Scrapper::$scrapSpecificScrapMethod = Config::extensionsConfig('TrafficLoadBalancer.default_settings.'
                    . 'scrapping_method');
            $percentage[1] = (int) Config::extensionsConfig('TrafficLoadBalancer.default_settings.step1');
            $percentage[2] = (int) Config::extensionsConfig('TrafficLoadBalancer.default_settings.step2');
            $percentage[3] = (int) Config::extensionsConfig('TrafficLoadBalancer.default_settings.step3');
            $percentage[4] = (int) Config::extensionsConfig('TrafficLoadBalancer.default_settings.step4');
            $percentage[5] = (int) Config::extensionsConfig('TrafficLoadBalancer.default_settings.step5');
        }
        
       
        $affiliates = array_replace(
                self::initializeAffiliates(), Session::get('affiliates', array())
        );
        $affiliateSettings = Database::table('affiliates')->findAll()->asArray();
        $mappedAffiliates = self::mapAffiliateKeys($affiliates);
        $selectedAffiliates = self::initializeAffiliates();
        $matchedArr = array();
        $lastMatched = array();

        foreach ($affiliateSettings as $k => $affiliateSetting)
        {
            $matched = true;
            foreach ($mappedAffiliates as $key => $value)
            {
                if (!empty($affiliateSetting[$key]) && !empty($value) && $affiliateSetting[$key] == $value)
                {
                    $lastMatched[$key] = $affiliateSetting['id'];
                }
            }
        }

        if (array_key_exists('affid', $lastMatched) || array_key_exists('afid', $lastMatched))
        {
            $v = array_count_values($lastMatched);
            $maxs = array_keys($v, max($v));
            $matchKey = $maxs[0] - 1;
           
            
            $affiliatePercentage = self::getAffiliatePercenatge($affiliateSettings[$matchKey]);
             Scrapper::$scrapSpecificScrapMethod = Config::extensionsConfig('TrafficLoadBalancer.'
                    . 'scrapping_method');   
            $percentage[1] = empty($affiliatePercentage['step1']) ? 0 : (int) $affiliatePercentage['step1'];
            $percentage[2] = empty($affiliatePercentage['step2']) ? 0 : (int) $affiliatePercentage['step2'];
            $percentage[3] = empty($affiliatePercentage['step3']) ? 0 : (int) $affiliatePercentage['step3'];
            $percentage[4] = empty($affiliatePercentage['step4']) ? 0 : (int) $affiliatePercentage['step4'];
            $percentage[5] = empty($affiliatePercentage['step5']) ? 0 : (int) $affiliatePercentage['step5'];
            
            if (!empty($affiliateSettings[$matchKey]['enable_card_specific_filter']))
            {
                $cardPercentage = self::localCardPercentagePrepare($affiliateSettings[$matchKey]);
            }
            $selectedAffiliates = $affiliates;
        }

        return array(
            'percentage' => $percentage, 'affiliates' => $selectedAffiliates,
            'cardPercentage' => $cardPercentage
        );
    }

    public static function getRemote()
    {

        $offerUrl = sprintf('%s/', rtrim(Request::getOfferUrl(), '/'));
        $affiliates = self::mapAffiliateKeys(Session::get('affiliates', array()));
        
        $enableAffiliateMapping = Config::extensionsConfig('TrafficLoadBalancer.enable_affiliate_mapping');
        if ($enableAffiliateMapping)
        {
            $affiliateMappingConfiguration = Config::extensionsConfig('TrafficLoadBalancer.affiliates');
            foreach ($affiliateMappingConfiguration as $value)
            {
                if (array_key_exists($value['aff_param'], $affiliates))
                {
                    $requestParams = Session::get('queryParams.' . strtoupper($value['mapped_param']));
                    if (!empty($requestParams))
                    {
                        $affiliates[$value['aff_param']] = $requestParams . '.' . $affiliates[$value['aff_param']];
                    }
                }
            }
        }
        
        $gateWaySwitcherId = Config::settings('gateway_switcher_id');
        $queryParams = array(
            'offer_url' => $offerUrl,
            'conf_scrap_count' => 0,
        );

        if (!empty($affiliates))
        {
            $queryParams = array_replace($queryParams, $affiliates);
            $subaffiliatePost = Config::extensionsConfig('TrafficLoadBalancer.subaffiliate_post');
            if (!$subaffiliatePost)
            {
                unset($queryParams['c1']);
                unset($queryParams['c2']);
                unset($queryParams['c3']);
                unset($queryParams['c4']);
                unset($queryParams['c5']);
                unset($queryParams['sId']);
            }
        }
        $queryString = http_build_query($queryParams);

        $apiEndpoint = rtrim(Registry::system('systemConstants.201CLICKS_URL'), '/api');

        $url = sprintf(
                '%s/scrapper/%s/?%s', $apiEndpoint, $gateWaySwitcherId, $queryString
        );

        $response = self::getResponse($url);      
        $settings = empty($response['data']) ? array() : json_decode($response['data'], true);
  
        $percentage = array(
            1 => empty($settings['step1_scrap_value']) ? 0 : (int) $settings['step1_scrap_value'],
            2 => empty($settings['upsell_scrap_value']) ? 0 : (int) $settings['upsell_scrap_value'],
        );

        $cardData = array();
        if (!empty($settings['sub_affiliate']))
        {
            $subaffData = end($settings['sub_affiliate']);
            $cardData = empty($subaffData['card']) ? array() : $subaffData['card'];
        }
        else if (!empty($settings['affiliate']))
        {
            $cardData = empty($settings['affiliate']['card']) ? array() : $settings['affiliate']['card'];
        }
        else
        {
            $cardData = empty($settings['card']) ? array() : $settings['card'];
        }

        $cardPercentage = array();
        if (!empty($cardData))
        {

            $cardPercentage = self::cardPercentagePrepare($cardData);
        }

        $affiliates = self::initializeAffiliates();

        $prepaid = !empty($settings['prepaid_check']);

        if (!empty($settings['affiliate']))
        {
            if (
                    !empty($settings['affiliate']['key']) &&
                    !empty($settings['affiliate']['aff_unique_id'])
            )
            {
                if (strtolower($settings['affiliate']['key']) === 'affid')
                {
                    $affiliates['affId'] = $settings['affiliate']['aff_unique_id'];
                }
                if (strtolower($settings['affiliate']['key']) === 'afid')
                {
                    $affiliates['afId'] = $settings['affiliate']['aff_unique_id'];
                }
            }
            $percentage[1] = (int) $settings['affiliate']['step1_scrap_value'];
            $percentage[2] = (int) $settings['affiliate']['upsell_scrap_value'];
            $prepaid = !empty($settings['affiliate']['prepaid_check']);
        }

        if (!empty($settings['affiliate']) && !empty($settings['sub_affiliate']))
        {
            $deepestSubAffiliate = end($settings['sub_affiliate']);
            $percentage[1] = (int) $deepestSubAffiliate['step1_scrap_value'];
            $percentage[2] = (int) $deepestSubAffiliate['upsell_scrap_value'];
            $prepaid = $deepestSubAffiliate['prepaid_check'];

            foreach ($settings['sub_affiliate'] as $subAffiliate)
            {
                if (
                        !empty($subAffiliate['key']) &&
                        !empty($subAffiliate['sub_unique_aff_id'])
                )
                {
                    if (strtolower($subAffiliate['key']) === 'sid')
                    {
                        $affiliates['sId'] = $subAffiliate['sub_unique_aff_id'];
                        continue;
                    }
                    if (strtolower($subAffiliate['key']) === 'click_id')
                    {
                        $affiliates['clickId'] = $subAffiliate['sub_unique_aff_id'];
                        continue;
                    }
                    $affiliates[$subAffiliate['key']] = $subAffiliate['sub_unique_aff_id'];
                }
            }
        }
       
        return array(
            'percentage' => $percentage,
            'affiliates' => $affiliates,
            'prepaid' => $prepaid,
            'card_details' => empty($cardPercentage) ? null : $cardPercentage
        );
    }

    private static function cardPercentagePrepare($settings)
    {
        $cardPercentage = array();
        foreach (self::$possibleCards as $val)
        {

            $cardPercentage[$val][1] = empty($settings[$val]['step1_scrap_value']) ? 0 :
                    (int) $settings[$val]['step1_scrap_value'];
            $cardPercentage[$val][2] = empty($settings[$val]['upsell_scrap_value']) ? 0 :
                    (int) $settings[$val]['upsell_scrap_value'];

            $cardPercentage[$val]['details']['scrappedCount'] = 0;
            $cardPercentage[$val]['details']['hitsCount'] = 0;
            $cardPercentage[$val]['details']['scrapped'] = 0;
            $cardPercentage[$val]['details']['hits'] = 0;
        }

        return $cardPercentage;
    }

    private static function getResponse($url)
    {
        $lbCacheFolder = STORAGE_DIR . DS . '.lbcache';
        $lbCacheWriteable = is_writeable($lbCacheFolder);
        if (!$lbCacheWriteable)
        {
            return json_decode(Http::get($url), true);
        }
        $cacheFileName = $lbCacheFolder . DS . md5($url);
        if (!file_exists($cacheFileName))
        {
            $response = Http::get($url);
            file_put_contents($cacheFileName, $response);
            return json_decode($response, true);
        }
        $lastCachingTime = filemtime($cacheFileName);
        $currentTime = time();
        if (($currentTime - $lastCachingTime) < 1 * 60)
        {
            $response = file_get_contents($cacheFileName);
        }
        else
        {
            $response = Http::get($url);
            file_put_contents($cacheFileName, $response);
        }
        return json_decode($response, true);
    }

    public static function initializeAffiliates()
    {
        return array(
            'afId' => '', 'affId' => '', 'sId' => '', 'c1' => '', 'c2' => '',
            'c3' => '', 'c4' => '', 'c5' => '', 'aId' => '', 'opt' => '',
            'clickId' => '',
        );
    }

    private static function mapAffiliateKeys($affiliates)
    {
        $mapping = array(
            'afId' => 'afid', 'affId' => 'affid', 'sId' => 'sid', 'aId' => 'aid',
            'clickId' => 'click_id',
        );
        $newAffiliates = array();
        foreach ($affiliates as $key => $value)
        {
            if (array_key_exists($key, $mapping))
            {
                $newAffiliates[$mapping[$key]] = $value;
                continue;
            }
            $newAffiliates[$key] = $value;
        }
        return $newAffiliates;
    }
    
    public static function postRemoteData()
    {

        $offerUrl   = sprintf('%s/', rtrim(Request::getOfferUrl(), '/'));
        $affiliates = self::mapAffiliateKeys(Session::get('affiliates', array()));
        
        $enableAffiliateMapping = Config::extensionsConfig('TrafficLoadBalancer.enable_affiliate_mapping');
        if ($enableAffiliateMapping)
        {
            $affiliateMappingConfiguration = Config::extensionsConfig('TrafficLoadBalancer.affiliates');
            foreach ($affiliateMappingConfiguration as $value)
            {
                if (array_key_exists($value['aff_param'], $affiliates))
                {
                    $requestParams = Session::get('queryParams.' . strtoupper($value['mapped_param']));
                    if (!empty($requestParams))
                    {
                        $affiliates[$value['aff_param']] = $requestParams . '.' . $affiliates[$value['aff_param']];
                    }
                }
            }
        }
        
        $gateWaySwitcherId = Config::settings('gateway_switcher_id');
        $queryParams       = array(
            'offer_url'        => $offerUrl,
            'conf_scrap_count' => 0,
        );

        if (!empty($affiliates)) {
            $queryParams = array_replace($queryParams, $affiliates);
             $subaffiliatePost = Config::extensionsConfig('TrafficLoadBalancer.subaffiliate_post');            
            if(!$subaffiliatePost)
            {
                unset($queryParams['c1']);
                unset($queryParams['c2']);
                unset($queryParams['c3']);
                unset($queryParams['c4']);
                unset($queryParams['c5']);
                unset($queryParams['sId']);
            }
        }
        $queryString = http_build_query($queryParams);

        $apiEndpoint = rtrim(Registry::system('systemConstants.201CLICKS_URL'), '/api');

        $currentStepId = Session::get('steps.current.id');

        if($currentStepId == 1) {
            $queryString2 = $queryString.'&convert=yes&sales=yes';
        } else {
            $queryString2 = $queryString.'&convert=yes';
        }

        $url = sprintf(
            '%s/scrapper/%s/?%s', $apiEndpoint, $gateWaySwitcherId, $queryString2
        );

        $response = self::getResponse($url);
        $settings = empty($response['data']) ? array() : json_decode($response['data'], true);
        return $settings;
    }

}
