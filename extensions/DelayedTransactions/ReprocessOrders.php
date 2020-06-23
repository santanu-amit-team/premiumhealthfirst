<?php

namespace Extension\DelayedTransactions;

use Application\Config;
use Application\CrmPayload;
use Application\Model\Campaign;

class ReprocessOrders
{
    private function __construct()
    {
        $this->dbConnection = Helper::getDatabaseConnection();
        $this->tableName    = Config::extensionsConfig(
            'DelayedTransactions.table_name'
        );
    }
    
    public static function reprocessOrders($crmPayload, $crmResponse)
    {        
        $declinedReason = Config::extensionsConfig(
                'DelayedTransactions.declined_reason'
            );
        $declinedReason  = empty($declinedReason) ? '' : $declinedReason;
        $declinedReasons = explode("\n", $declinedReason);
        foreach ($declinedReasons as $reasons) {
            if (preg_match('/' . $reasons . '/i', $crmResponse['errors']['crmError'])) {
                $reasonFlag = true;
                break;
            }
        }
        if (!empty($declinedReason) && !$reasonFlag) {
            return;
        }
        $reprocessStepsDetails = Config::extensionsConfig(
                'DelayedTransactions.reprocess_steps'
            );
        $reprocessStepsArray = explode(',', $reprocessStepsDetails);
        $crmPayloadStep = $crmPayload['meta.stepId'];
        if(!in_array($crmPayloadStep, $reprocessStepsArray))
        {
            return;
        }
        self::updateConfiguration($crmPayload);
        $crmClass = sprintf(
                    '\Application\Model\%s', ucfirst(CrmPayload::get('meta.crmType'))
                );
        $crmInstance = new $crmClass($crmPayload['meta.crmId']);
        call_user_func_array(array($crmInstance, CrmPayload::get('meta.crmMethod')), array());
    }
    
    private function updateConfiguration($crmPayload)
    {
        $products = array();
        $reprocessCampaignId = Config::extensionsConfig(
                'DelayedTransactions.reprocess_campaignId'
            );
        $campaignInfo = Campaign::find($reprocessCampaignId);
        if(!empty($campaignInfo['product_array']))
        {  
            foreach ($campaignInfo['product_array'] as $childProduct) {
                unset($campaignInfo['product_array']);
                array_push($products, array_merge($campaignInfo, $childProduct));
            }
        }
        CrmPayload::update(
                            array
                            (
                                'campaignId' => $campaignInfo['campaignId'],
                                'products' => $products,
                                'offerId' => $campaignInfo['campaignId'],
                                'offerId' => $campaignInfo['campaignId'],
                                'billingModelId' => $campaignInfo['billingModelId'],
                                'trialProductId' => $campaignInfo['trialProductId'],
                                'trialProductPrice' => $campaignInfo['trialProductPrice'],
                                'trialProductQuantity' => $campaignInfo['trialProductQuantity'],
                            )
                        );
    }
    
}

