<?php

namespace Extension\LimelightUtilPack;

use Application\Config;
use Application\CrmPayload;
use Application\Model\Configuration;
use Application\Model\Campaign;
use Application\Session;

class LimelightTracking
{

    public function __construct()
    {
        $this->pageType      = Session::get('steps.current.pageType');
    }

    public function injectLLScript()
    {
        if($this->pageType === 'thankyouPage'){
            return;
        }
        $gaType                 = Config::extensionsConfig('LimelightUtilPack.ga_code_type');
        $limelightTracking      = Config::extensionsConfig('LimelightUtilPack.limelight_tracking');
        if($gaType == 'custom' || empty($limelightTracking))
        {
            return;
        }
        $appKey     = Config::extensionsConfig('LimelightUtilPack.app_key');
        $gaCode     = Config::extensionsConfig('LimelightUtilPack.ga_code');
        $configId   = (int) Session::get('steps.current.configId');
        $configuration = new Configuration($configId);
        $localCampaignId = $configuration->getCampaignIds();
        $campaignId = Campaign::find($localCampaignId[0], true);
        $script     = @file_get_contents(
            __DIR__ . DS . "TrackingScript.txt"
        );
        $toReplace = array("/gaCode/","/appKey/","/campaignId/");
        $replace   = array($gaCode, $appKey, $campaignId[0]['campaignId']);
        $trackingScript = preg_replace($toReplace, $replace,  $script);
        echo $trackingScript;
    }

    public function captureCrmPayload()
    {
        $utm_values = array(
            'utm_medium',
            'utm_source',
            'utm_campaign',
            'utm_term',
            'utm_content',
            'device_category',
        );

        foreach ($utm_values as $utm_key => $utm_val) {
                CrmPayload::update(array(
                    $utm_val => $_COOKIE[$utm_val]
                )
            );
        }

    }

}
