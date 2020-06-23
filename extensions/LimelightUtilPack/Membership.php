<?php

namespace Extension\LimelightUtilPack;

use Application\Config;
use Application\Session;
use Application\Model\Configuration;
use Application\Http;

class Membership
{

    protected $curlResponse, $curlPostData = array();

    public function __construct()
    {
        $this->pageType = Session::get('steps.current.pageType');
        $this->crmType = Session::get('crmType');
        $this->activate = Config::extensionsConfig('LimelightUtilPack.membership_service');
    }

    public function orderUpdateRecurring($params = array())
    {

        if (!$this->checkExtensionStatus('LimelightUtilPack') || !$this->activate)
        {
            return;
        }

        if (Session::get('crmType', 'unknown') !== 'limelight' ||
                (empty($params['order_id']) || empty($params['status'])))
        {
            return;
        }

        $this->curlPostData['order_id'] = $params['order_id'];
        $this->curlPostData['status'] = $params['status'];
        $this->curlPostData['method'] = 'order_update_recurring';

        return $this->callAPI();
    }

    public function orderUpdate($params = array())
    {

        if (!$this->checkExtensionStatus('LimelightUtilPack') || !$this->activate)
        {
            return;
        }

        if ((empty($params['order_ids']) || empty($params['actions'])))
        {
            return;
        }

        $this->curlPostData['order_ids'] = $params['order_ids'];
        $this->curlPostData['actions'] = $params['actions'];
        $this->curlPostData['values'] = $params['values'];
        $this->curlPostData['method'] = 'order_update';
        $configID = !empty($params['configID']) ? $params['configID'] : '';

        return $this->callAPI($configID);
    }

    public function viewProspect($params = array())
    {

        if (!$this->checkExtensionStatus('LimelightUtilPack') || !$this->activate)
        {
            return;
        }

        if (empty($params['prospect_id']))
        {
            return;
        }

        $this->curlPostData['prospect_id'] = $params['prospect_id'];
        $this->curlPostData['method'] = 'prospect_view';
        $configID = !empty($params['configID']) ? $params['configID'] : '';

        return $this->callAPI($configID);
    }

    public function findProspects($params = array())
    {

        if (!$this->checkExtensionStatus('LimelightUtilPack') || !$this->activate)
        {
            return;
        }

        if (empty($params['campaign_id']))
        {
            return;
        }

        if (empty($params['start_date']))
        {
            $params['start_date'] = date('m/d/Y', strtotime('-3 Months'));
        }

        if (empty($params['end_date']))
        {
            $params['end_date'] = date('m/d/Y');
        }

        $this->curlPostData['campaign_id'] = $params['campaign_id'];
        $this->curlPostData['start_date'] = $params['start_date'];
        $this->curlPostData['end_date'] = $params['end_date'];
        $this->curlPostData['criteria'] = @$params['criteria'];
        $this->curlPostData['search_type'] = 'any';
        $this->curlPostData['return_type'] = 'prospect_view';
        $this->curlPostData['method'] = 'prospect_find';
        $configID = !empty($params['configID']) ? $params['configID'] : '';

        return $this->callAPI($configID);
    }
    
    public function viewCustomer($params = array())
    {

        if (!$this->checkExtensionStatus('LimelightUtilPack') || !$this->activate)
        {
            return;
        }

        if (empty($params['customer_id']))
        {
            return;
        }

        $this->curlPostData['customer_id'] = $params['customer_id'];
        $this->curlPostData['method'] = 'customer_view';
        $configID = !empty($params['configID']) ? $params['configID'] : '';

        return $this->callAPI($configID);
    }

    public function findCustomer($params = array())
    {

        if (!$this->checkExtensionStatus('LimelightUtilPack') || !$this->activate)
        {
            return;
        }

        if (empty($params['campaign_id']))
        {
            return;
        }

        if (empty($params['start_date']))
        {
            $params['start_date'] = date('m/d/Y', strtotime('-3 Months'));
        }

        if (empty($params['end_date']))
        {
            $params['end_date'] = date('m/d/Y');
        }

        $this->curlPostData['campaign_id'] = $params['campaign_id'];
        $this->curlPostData['start_date'] = $params['start_date'];
        $this->curlPostData['end_date'] = $params['end_date'];
        $this->curlPostData['criteria'] = @$params['criteria'];
        $this->curlPostData['search_type'] = 'any';
        $this->curlPostData['return_type'] = 'customer_view';
        $this->curlPostData['method'] = 'customer_find';
        $configID = !empty($params['configID']) ? $params['configID'] : '';

        return $this->callAPI($configID);
    }

    private function callAPI($configID = null)
    {
        $result = array();

        if (empty($configID))
        {
            $prevConfigId = Session::get('steps.previous.configId');

            if (!empty($prevConfigId))
            {
                $configId = $prevConfigId;
            }
            else
            {
                $configId = Session::get('steps.current.configId');
            }
        }
        else
        {
            $configId = $configID;
        }
        $this->configuration = new Configuration($configId);

        $crmInfo = $this->configuration->getCrm();

        $this->curlPostData['username'] = $crmInfo['username'];
        $this->curlPostData['password'] = $crmInfo['password'];

        $url = $crmInfo['endpoint'] . "/admin/membership.php";
        $this->curlResponse = Http::post($url, http_build_query($this->curlPostData));

        parse_str($this->curlResponse, $result);
        return $result;
    }

    private function checkExtensionStatus($extensionName)
    {
        $extensions = Config::extensions();
        $isExtensionActive = false;
        if (!empty($extensions))
        {
            foreach ($extensions as $extension)
            {
                if ($extension['extension_slug'] == $extensionName)
                {
                    $isExtensionActive = $extension['active'];
                    break;
                }
            }
        }
        return $isExtensionActive;
    }

    public function prospectUpdate($params = array())
    {

        if (!$this->checkExtensionStatus('LimelightUtilPack') || !$this->activate)
        {
            return;
        }

        if ((empty($params['prospect_ids']) || empty($params['actions'])))
        {
            return;
        }

        $this->curlPostData['prospect_ids'] = $params['prospect_ids'];
        $this->curlPostData['actions'] = $params['actions'];
        $this->curlPostData['values'] = $params['values'];
        $this->curlPostData['method'] = 'prospect_update';
        
        $configID = !empty($params['configID']) ? $params['configID'] : '';

        return $this->callAPI($configID);
    }
    
    public function viewOrder($params = array())
    {

        if (!$this->checkExtensionStatus('LimelightUtilPack') || !$this->activate)
        {
            return;
        }

        if (empty($params['order_id']))
        {
            return;
        }

        $this->curlPostData['order_id'] = $params['order_id'];
        $this->curlPostData['method'] = 'order_view';
        $configID = !empty($params['configID']) ? $params['configID'] : '';

        return $this->callAPI($configID);
    }
    
    public function findOrders($params = array())
    {

        if (!$this->checkExtensionStatus('LimelightUtilPack') || !$this->activate)
        {
            return;
        }

        if (empty($params['campaign_id']))
        {
            return;
        }

        if (empty($params['start_date']))
        {
            $params['start_date'] = date('m/d/Y', strtotime('-3 Months'));
        }

        if (empty($params['end_date']))
        {
            $params['end_date'] = date('m/d/Y');
        }


        if (empty($params['end_date']))
        {
            $params['end_date'] = date('m/d/Y');
        }

        if (!empty($params['product_ids']))
        {
            $this->curlPostData['product_ids'] = $params['product_ids'];
        }

        $this->curlPostData['campaign_id'] = $params['campaign_id'];
        $this->curlPostData['start_date'] = $params['start_date'];
        $this->curlPostData['end_date'] = $params['end_date'];
        $this->curlPostData['criteria'] = @$params['criteria'];
        $this->curlPostData['search_type'] = 'any';
        $this->curlPostData['return_type'] = 'order_view';
        $this->curlPostData['method'] = 'order_find';
        $configID = !empty($params['configID']) ? $params['configID'] : '';

        return $this->callAPI($configID);
    }
    
    public function voidOrder($params = array())
    {

        if (!$this->checkExtensionStatus('LimelightUtilPack') || !$this->activate)
        {
            return;
        }

        if (empty($params['order_id']))
        {
            return;
        }

        $this->curlPostData['order_id'] = $params['order_id'];
        $this->curlPostData['method'] = 'order_void';
        $configID = !empty($params['configID']) ? $params['configID'] : '';

        return $this->callAPI($configID);
    }

}
