<?php

namespace Extension\TrafficLoadBalancer;

use Application\Config;
use Application\Request;
use Application\Session;
use Application\Model\Campaign;
use Application\CrmPayload;
use Application\Model\Configuration;

class productScrapper
{

    public function __construct()
    {
        $this->pageType = Session::get('steps.current.pageType');
        $this->stepId   = Session::get('steps.current.id');
        $this->fileName = BASE_DIR . DS . 'storage/productOrderFilter';
    }

    public function scrapFlow()
    {
        if ($this->pageType == 'leadPage' || $this->pageType == 'thankyouPage') {
            return;
        }

        $enableProductOrderFilter = Config::extensionsConfig('TrafficLoadBalancer.enable_product_orderfilter');

        if (!$enableProductOrderFilter) {
            return;
        }

        if ($this->pageType == 'checkoutPage') {
            try
            {
                $fp       = fopen($this->fileName, 'r');
                $contents = fread($fp, filesize($this->fileName));
                fclose($fp);
                if ($contents) {
                    $contentsArray = json_decode($contents, true);
                    Session::set('extensions.trafficLoadBalancer.productData', $contentsArray);
                }
            } catch (Exception $ex) {
                throw ($ex);
            }

            $productData             = Config::extensionsConfig('TrafficLoadBalancer.product_orderfilter_configuration');
            $productOrderFilterMethod = Config::extensionsConfig('TrafficLoadBalancer.product_orderfilter_scrapping_method');
            if ($productOrderFilterMethod == 'flat') {
                $productData = Config::extensionsConfig('TrafficLoadBalancer.product_orderfilter_campaignid');
            }

            $configs = preg_split("/\\r\\n|\\r|\\n/", $productData);

            $formData = Request::form()->all();
           
            if (array_key_exists('campaigns', $formData)) {
                $formCampaign = $formData['campaigns'][1]['id'];
                
                if(empty($formCampaign)) return;
                
                $formProductIds = $this->prepareFromProducts(Campaign::find($formCampaign));
            }
            else {
                
                $currentConfigId = (int) Session::get('steps.current.configId');
                $configuration = new Configuration($currentConfigId);
                $campaignIds = $configuration->getCampaignIds();
                $formProductIds = $this->prepareFromProducts(Campaign::find($campaignIds[0]));
            }
           
            foreach ($configs as $val) {
                $productArray = array();
                if ($productOrderFilterMethod == 'flat') {
                    //$campaignId = $val;
                    $productId = $val;
                } else {
                    $productData = explode('|', $val);
                    //$campaignId  = $productData[0];
                    $productId = $productData[0];
                }
                /*if (!empty($formCampaign) && $formCampaign == $campaignId) {
                    Session::set('extensions.trafficLoadBalancer.campaignMatch', true);
                    Session::set('extensions.trafficLoadBalancer.campaign', $campaignId);
                    break;
                }*/
                
                if(!empty($formProductIds) && !empty($productId) &&
                        in_array($productId, $formProductIds)){
                    Session::set('extensions.trafficLoadBalancer.productMatch', true);
                    Session::set('extensions.trafficLoadBalancer.product', $productId);
                    break;
                    
                }
            }

           // $campaignMatch = Session::get('extensions.trafficLoadBalancer.campaignMatch');
             $productMatch = Session::get('extensions.trafficLoadBalancer.productMatch');

            /*if (!$campaignMatch) {
                return;
            }*/
            if (!$productMatch) {
                return;
            }

            $orderCount = Session::get('extensions.trafficLoadBalancer.productData');
            $product   = Session::get('extensions.trafficLoadBalancer.product');

            foreach ($orderCount as $key => $value) {
                if (array_key_exists($product, $value)) {
                    $data = $value[$product];
                    Session::set('extensions.trafficLoadBalancer.count', $data['count']);
                    if (in_array($data['count'], $data['random_numbers'])) {
                        Session::set('extensions.trafficLoadBalancer.orderFilter', true);
                        break;
                    }
                }
            }
        }

        $isScrap       = Session::get('extensions.trafficLoadBalancer.orderFilter');
        $productMatch = Session::get('extensions.trafficLoadBalancer.productMatch');

        if ($productMatch) {
            if ($isScrap) {
                Session::set('steps.meta.isScrapFlow', true);
                Session::set('extensions.trafficLoadBalancer.' . $this->stepId . '.scrapped', 0);
                Session::set('extensions.trafficLoadBalancer.' . $this->stepId . '.committed', 1);
            } else {
                Session::set('steps.meta.isScrapFlow', false);
                Session::set('extensions.trafficLoadBalancer.' . $this->stepId . '.scrapped', 0);
                Session::set('extensions.trafficLoadBalancer.' . $this->stepId . '.committed', 1);
            }
        }

    }
    
    public function prepareFromProducts($param)
    {
        if(empty($param['product_array'])) return;
        $products = array();
        foreach ($param['product_array'] as $key => $value)
        {
            array_push($products, $value['productId']);
        }
        return $products;
    }

    public function incrementHit()
    {
        $enableProductOrderFilter = Config::extensionsConfig('TrafficLoadBalancer.enable_product_orderfilter');

        if (!$enableProductOrderFilter) {
            return;
        }

        if ($this->stepId == '2') {

            if (Session::has('extensions.trafficLoadBalancer.productData')) {
                $productData = Session::get('extensions.trafficLoadBalancer.productData');
                $product     = Session::get('extensions.trafficLoadBalancer.product');
                $count        = Session::get('extensions.trafficLoadBalancer.count');

                foreach ($productData as $key => $value) {
                    if (array_key_exists($product, $value)) {
                        $productData[$key][$product]['count'] = $count + 1;
                        if ($productData[$key][$product]['count'] > 100) {
                            $productData[$key][$product]['count'] = 1;
                        }
                        break;
                    }
                }

                $jsonData = json_encode($productData);

                $fp = fopen($this->fileName, 'r+');
                flock($fp, LOCK_EX);
                $contents = fread($fp, filesize($this->fileName));

                if ($contents) {
                    file_put_contents($this->fileName, $jsonData);
                }
            }
        }
    }

}
