<?php

namespace Extension\DelayedTransactions;

use Application\Config;
use Application\CrmPayload;
use Application\CrmResponse;
use Application\Model\Campaign;
use Application\Model\Configuration;
use Application\Session;
use Application\Request;
use DateTime;
use Exception;

class AdvancedDelay
{

    public function __construct()
    {
        $this->currentStepId   = (int) Session::get('steps.current.id');
        $this->currentConfigId = (int) Session::get('steps.current.configId');
        $this->parentOrderId   = Session::get('steps.1.orderId');
        $this->customerId      = Session::get('steps.1.customerId');
        $this->tableName       = Config::extensionsConfig('DelayedTransactions.table_name');
        $this->advancedDelay   = Config::extensionsConfig('DelayedTransactions.advanced_delay');
        $this->ignoreSplit     = Config::extensionsConfig('DelayedTransactions.ignore_split_charge_for_advance_flow');
        $dateTime              = new DateTime();
        $this->currentDateTime = $dateTime->format('Y-m-d H:i:s');

        try {
            $this->configuration = new Configuration();
        } catch (Exception $ex) {
            $this->configuration = null;
        }
    }

    public function passThroughDelayModule()
    {

        if (!$this->advancedDelay || Request::attributes()->get('action') == "prospect") {
            return;
        }
        
        $updateStepsCsv = Config::extensionsConfig(
            'DelayedTransactions.update_steps'
        );
        $updateStepsCsv = empty($updateStepsCsv) ? '' : $updateStepsCsv;
        $updateSteps    = array_map(function ($value) {
            return (int) $value;
        }, explode(',', $updateStepsCsv));

        if (!in_array($this->currentStepId, $updateSteps)) {
            return;
        }

        $this->dbConnection = Helper::getDatabaseConnection();

        if (!$this->dbConnection) {
            return;
        }

        try {
            $result = $this->dbConnection->table($this->tableName)->where('parentOrderId', '=', $this->parentOrderId)->get();
        } catch (Exception $ex) {
            echo $ex->getMessage();die;
        }

        $upgradeCrmData = CrmPayload::get('products');

        foreach ($upgradeCrmData as $key => $value) {
            $campaignId        = $value['codebaseCampaignId'];
            $config            = Config::campaigns($campaignId);
            $prepaidCampaignId = !empty($config['prepaid_campaign_id']) ? $config['prepaid_campaign_id'] : '';
            if (!empty($prepaidCampaignId)) {
                $prepaidData = Config::campaigns((string) $prepaidCampaignId);
            }
        }

        $upgrade['campaignId']        = $upgradeCrmData[0]['campaignId'];
        $upgrade['productId']         = $upgradeCrmData[0]['productId'];
        $upgrade['productQuantity']   = $upgradeCrmData[0]['productQuantity'];
        $upgrade['price']             = $upgradeCrmData[0]['productPrice'];
        $upgrade['shippingId']        = $upgradeCrmData[0]['shippingId'];
        $upgrade['shipPrice']         = $upgradeCrmData[0]['shippingPrice'];
        $upgrade['offerId']           = !empty($upgradeCrmData[0]['offerId']) ? $upgradeCrmData[0]['offerId'] : '';
        $upgrade['billingModelId']    = !empty($upgradeCrmData[0]['billingModelId']) ? $upgradeCrmData[0]['billingModelId'] : '';
        $upgrade['trialProductId']    = !empty($upgradeCrmData[0]['trialProductId']) ? $upgradeCrmData[0]['trialProductId'] : '';
        $upgrade['trialProductPrice'] = !empty($upgradeCrmData[0]['trialProductPrice']) ? $upgradeCrmData[0]['trialProductPrice'] : '';

        if (!empty($prepaidData)) {
            $prepaidProductData = json_decode($prepaidData['product_array'], true);
            
            $upgrade['prepaid']['campaignId']        = $prepaidData['campaign_id'];
            $upgrade['prepaid']['productId']         = $prepaidProductData[0]['product_id'];
            $upgrade['prepaid']['productQuantity']   = $prepaidProductData[0]['product_quantity'];
            $upgrade['prepaid']['price']             = $prepaidProductData[0]['product_price'];
            $upgrade['prepaid']['shippingId']        = $prepaidData['shipping_id'];
            $upgrade['prepaid']['shipPrice']         = $prepaidData['shipping_price'];
            $upgrade['prepaid']['offerId']           = !empty($prepaidData['offer_id']) ? $prepaidData['offer_id'] : '';
            $upgrade['prepaid']['billingModelId']    = !empty($prepaidData['billing_model_id']) ? $prepaidData['billing_model_id'] : '';
            $upgrade['prepaid']['trialProductId']    = !empty($prepaidData['trial_product_id']) ? $prepaidData['trial_product_id'] : '';
            $upgrade['prepaid']['trialProductPrice'] = !empty($prepaidData['trial_product_price']) ? $prepaidData['trial_product_price'] : '';
        }

        $this->updateCrmDetails($result, $upgrade);
        
        $query = $this->dbConnection->table($this->tableName);
        $query->where('parentOrderId', '=', $this->parentOrderId);
        $query->update(array(
            'processing'  => 0,
            'processedAt' => null,
        ));

        Session::set(
            sprintf('extensions.advancedDelay.upgradeSteps.%d', $this->currentStepId), true
        );

        if ($this->ignoreSplit) {
            $query = $this->dbConnection->table($this->tableName);
            $query->where('parentOrderId', '=', $this->parentOrderId)
                ->where('type', '=', 'split');
            $query->update(array(
                'processedAt' => $this->currentDateTime,
            ));
        }

        CrmPayload::set('meta.bypassCrmHooks', true);
        CrmPayload::set('meta.terminateCrmRequest', true);
        CrmResponse::replace(array(
            'success'    => true,
            'orderId'    => $this->parentOrderId,
            'customerId' => $this->customerId,
        ));

    }

    public function processCurrentOrders()
    {
        if (!$this->advancedDelay) {
            return;
        }

        $orderProcessingCsv = Config::extensionsConfig(
            'DelayedTransactions.order_processing_steps'
        );
        
        $orderProcessingCsv = empty($orderProcessingCsv) ? '' : $orderProcessingCsv;
        $orderProcessingSteps    = array_map(function ($value) {
            return (int) $value;
        }, explode(',', $orderProcessingCsv));

        if (!in_array($this->currentStepId, $orderProcessingSteps)) {
            return;
        }

        if (Session::has('extensions.delayedTransactions.reprocessDeclinedOrder')) {
            return;
        }

        Session::set('extensions.delayedTransactions.reprocessDeclinedOrder', true);

        $crons = new Crons();
        $crons->processOrdersWithParentOrderId($this->parentOrderId);

        $response = CrmResponse::all();

        Session::set('extensions.delayedTransactions.advancedDelay.response', $response);

        if (CrmResponse::get('success')) {
            $pixels = CrmResponse::get('htmlPixel');
            Session::set('extensions.delayedTransactions.pixels', 
                    empty($pixels) ? null :  array_shift($pixels));
        }

        $this->dbConnection = Helper::getDatabaseConnection();

        if (!$this->dbConnection) {
            return;
        }

        try {
            $result = $this->dbConnection->table($this->tableName)->where('parentOrderId', '=', $this->parentOrderId)->get();
        } catch (Exception $ex) {
            return;
        }

        $orderDetails = json_decode($result[0]['crmResponse']);
        $orderPayload = json_decode($result[0]['crmPayload']);
        $orderId      = !empty($orderDetails->orderId) ? $orderDetails->orderId : '';
        $customerId   = !empty($orderDetails->customerId) ? $orderDetails->customerId : '';

        if (!empty($orderId) && !empty($customerId)) {
            Session::update(array(
                'queryParams.order_id'    => $orderId,
                'queryParams.customer_id' => $customerId,
            ));
            $isPrepaidFlow = CrmResponse::get('isPrepaidFlow');
            if($isPrepaidFlow) {
                Session::set('steps.meta.isPrepaidFlow', true);
            }

            $delayedSteps = Session::get('extensions.delayedTransactions.steps');
            foreach ($delayedSteps as $stepId => $delayedStep) {
                if (!empty($delayedStep['main'])) {
                    Session::update(array(
                        sprintf('steps.%d.orderId', $stepId)    => $orderId,
                        sprintf('steps.%d.customerId', $stepId) => $customerId,
                    ));
                }
            }
        }

        if (!empty($orderDetails) && $orderDetails->success) {
            return;
        }

        $reprocessDeclinedOrder = Config::extensionsConfig(
            'DelayedTransactions.reprocess_decline'
        );

        if ($reprocessDeclinedOrder) {

            $declinedReason = Config::extensionsConfig(
                'DelayedTransactions.declined_reason'
            );
            $declinedReason  = empty($declinedReason) ? '' : $declinedReason;
            $declinedReasons = explode("\n", $declinedReason);

            foreach ($declinedReasons as $reasons) {
                if (preg_match('/' . $reasons . '/i', $orderDetails->errors->crmError)) {
                    $reasonFlag = true;
                    break;
                }
            }

            if (!empty($declinedReason) && !$reasonFlag) {
                return;
            }

            $reprocessCampaignId = Config::extensionsConfig(
                'DelayedTransactions.reprocess_campaignId'
            );

            if (empty($reprocessCampaignId) && !empty($orderPayload->reprocessCampaignId)) {
                $reprocessCampaignId = $orderPayload->reprocessCampaignId;
            }

            if (!empty($reprocessDeclinedOrder)) {
                $campaignId   = $this->configuration->getCampaignIds();
                $campaignInfo = Campaign::find($reprocessCampaignId, true);

                $upgrade['campaignId']        = $campaignInfo[0]['campaignId'];
                $upgrade['productId']         = $campaignInfo[0]['productId'];
                $upgrade['price']             = $campaignInfo[0]['productPrice'];
                $upgrade['shippingId']        = $campaignInfo[0]['shippingId'];
                $upgrade['shipPrice']         = $campaignInfo[0]['shippingPrice'];
                $upgrade['offerId']           = !empty($campaignInfo[0]['offerId']) ? $campaignInfo[0]['offerId'] : '';
                $upgrade['billingModelId']    = !empty($campaignInfo[0]['billingModelId']) ? $campaignInfo[0]['billingModelId'] : '';
                $upgrade['trialProductId']    = !empty($campaignInfo[0]['trialProductId']) ? $campaignInfo[0]['trialProductId'] : '';
                $upgrade['trialProductPrice'] = !empty($campaignInfo[0]['trialProductPrice']) ? $campaignInfo[0]['trialProductPrice'] : '';

                $this->updateCrmDetails($result, $upgrade);

                $crons = new Crons();
                $crons->processOrdersWithParentOrderId($this->parentOrderId);
                $response = CrmResponse::all();
                Session::set('extensions.delayedTransactions.advancedDelay.response', $response);
            }

        }

    }

    private function updateCrmDetails($result, $upgrade)
    {

        if (!empty($result)) {
            foreach ($result as $k => $v) {
                if (!empty($v['crmPayload'])) {
                    $crmPayload = json_decode($v['crmPayload'], true);
                    if (!$crmPayload['meta.isSplitOrder']) {
                        $crmPayload['products'][0]['campaignId']                     = $upgrade['campaignId'];
                        $crmPayload['campaignId']                                    = $upgrade['campaignId'];
                        $crmPayload['products'][0]['productId']                      = $upgrade['productId'];
                        $crmPayload['products'][0]['productQuantity']                = $upgrade['productQuantity'];
                        $crmPayload['products'][0]['productPrice']                   = $upgrade['price'];
                        $crmPayload['products'][0]['shippingId']                     = $upgrade['shippingId'];
                        $crmPayload['products'][0]['shippingPrice']                  = $upgrade['shipPrice'];
                        $crmPayload['products'][0]['offerId']                        = !empty($upgrade['offerId']) ? $upgrade['offerId'] : '';
                        $crmPayload['products'][0]['billingModelId']                 = !empty($upgrade['billingModelId']) ? $upgrade['billingModelId'] : '';
                        $crmPayload['products'][0]['trialProductId']                 = !empty($upgrade['trialProductId']) ? $upgrade['trialProductId'] : '';
                        $crmPayload['products'][0]['trialProductPrice']              = !empty($upgrade['trialProductPrice']) ? $upgrade['trialProductPrice'] : '';
                        $crmPayload['prepaidConfig']['products'][0]['campaignId']    = $upgrade['prepaid']['campaignId'];
                        $crmPayload['prepaidConfig']['products'][0]['productId']     = $upgrade['prepaid']['productId'];
                        $crmPayload['prepaidConfig']['products'][0]['productQuantity']= $upgrade['prepaid']['productQuantity'];
                        $crmPayload['prepaidConfig']['products'][0]['productPrice']  = $upgrade['prepaid']['price'];
                        $crmPayload['prepaidConfig']['products'][0]['shippingId']    = $upgrade['prepaid']['shippingId'];
                        $crmPayload['prepaidConfig']['products'][0]['shippingPrice'] = $upgrade['prepaid']['shipPrice'];

                        $crmPayload['prepaidConfig']['products'][0]['offerId']           = !empty($upgrade['prepaid']['offerId']) ? $upgrade['prepaid']['offerId'] : '';
                        $crmPayload['prepaidConfig']['products'][0]['billingModelId']    = !empty($upgrade['prepaid']['billingModelId']) ? $upgrade['prepaid']['billingModelId'] : '';
                        $crmPayload['prepaidConfig']['products'][0]['trialProductId']    = !empty($upgrade['prepaid']['trialProductId']) ? $upgrade['prepaid']['trialProductId'] : '';
                        $crmPayload['prepaidConfig']['products'][0]['trialProductPrice'] = !empty($upgrade['prepaid']['trialProductPrice']) ? $upgrade['prepaid']['trialProductPrice'] : '';

                        $crmPayloadJson = json_encode($crmPayload);
                        $query          = $this->dbConnection->table($this->tableName);
                        $query->where('parentOrderId', '=', $this->parentOrderId)
                            ->where('id', '=', $v['id']);
                        $query->update(array(
                            'crmPayload' => $crmPayloadJson,
                        ));
                    }

                }
            }
        }
    }

}
