<?php

namespace Extension\LimelightUtilPack;

use Application\Config;
use Application\CrmPayload;
use Application\Http;
use Application\Request;


class DisableNote
{
    public function removeNote()
    {
        if (
                CrmPayload::get('meta.crmType') !== 'limelight' ||
                Config::extensionsConfig('LimelightUtilPack.remove_note') !== true
        )
        {
            return;
        }

        CrmPayload::remove('userIsAt');
        CrmPayload::remove('userAgent');
    }

    public function removeOfferUrlFromNote()
    {
        if (
                CrmPayload::get('meta.crmType') !== 'limelight' ||
                Config::extensionsConfig('LimelightUtilPack.remove_offer_url_from_note') !== true
        )
        {
            return;
        }

        CrmPayload::remove('userIsAt');
    }

    public function encryptNote()
    {
        if (
                CrmPayload::get('meta.crmType') !== 'limelight' ||
                Config::extensionsConfig('LimelightUtilPack.encrypt_note') !== true
        )
        {
            return;
        }
        $crmPayload = Http::getOptions();
        $crmArray = array();
        $isJson = false;
        
        if (
            strpos($crmPayload[10002], 'almost20.com') !== false ||
            strpos($crmPayload[10002], '201clicks.com') !== false
        ) {
            return;
        }
        
        if (!empty($crmPayload[10015]))
        {
            if(!is_array($crmPayload[10015]) && !$this->isJson($crmPayload[10015]))
            {               
                parse_str($crmPayload[10015], $crmPayload[10015]);
            }
            elseif($this->isJson($crmPayload[10015]))
            {
                $crmPayload[10015] = json_decode($crmPayload[10015], true);
                $isJson = true;
            }
            foreach ($crmPayload[10015] as $key => $val)
            {
                if ($key == 'notes')
                {
                    $notes = base64_encode($crmPayload[10015][$key]);
                    $crmPayload[10015][$key] = wordwrap($notes, 50, "\n", true);
                }
                $crmArray[$key] = $crmPayload[10015][$key];
            }
            
            if($isJson && !empty($crmArray))
            {
                $crmArray = json_encode($crmArray);
            }
            
            Http::updateOptions(array('10015' => $crmArray));
        }
    }
    
    public function isJson($string) {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }
    
    public function removeNoteForProspect()
    {
        if (
                CrmPayload::get('meta.crmType') === 'limelight' &&
                !Config::extensionsConfig('LimelightUtilPack.prospect_note') &&
                Request::attributes()->get('action') === 'prospect'
        )
        {
            CrmPayload::remove('userIsAt');
            CrmPayload::remove('userAgent');
        }
    }
    
    public function removeNoteForOrder()
    {
        if (
                CrmPayload::get('meta.crmType') === 'limelight' &&
                !Config::extensionsConfig('LimelightUtilPack.order_note') &&
                Request::attributes()->get('action') !== 'prospect'
        )
        {
            CrmPayload::remove('userIsAt');
            CrmPayload::remove('userAgent');
        }
    }

}
