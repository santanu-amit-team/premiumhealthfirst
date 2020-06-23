<?php

namespace Extension\TrafficLoadBalancer;

use Application\Request;
use Exception;
use Database\Connectors\ConnectionFactory;

class Actions
{

    public function save()
    {
        Setup::tableExists();
        $this->createProductOrderFilter();
        $this->setV2ScrapperDetails();

        if (!extension_loaded('pdo_sqlite') && !extension_loaded('pdo_sqlite'))
        {
            throw new Exception("Sqlite PDO extension is not installed.");
        }

        $this->defaultConfigValidation();

        if (!Request::form()->get('enable_schedule'))
        {
            return;
        }
        $schedulerSettings = Request::form()->get('scheduler');
        foreach ($schedulerSettings as $key => $value)
        {
            $startTime = $value['start_time'];
            $endTime = $value['end_time'];
            if (empty($startTime) || empty($endTime))
            {
                throw new Exception('Start and End time can\'t be empty');
                break;
            }

            if (
                    !$this->isValidTimeFormat($endTime) ||
                    !$this->isValidTimeFormat($startTime)
            )
            {
                throw new Exception('Enter valid Start and End time');
                break;
            }
        }
    }

    private function defaultConfigValidation()
    {
        $defaultSettings = Request::form()->get('default_settings');
        
        if (empty(Request::form()->get('enable_default_settings'))  || 
                !empty($defaultSettings['enable_remote']))
            return;
        $defaultSteps = array(
            'step1', 'step2', 'step3', 'step4', 'step5'
        );

        
        foreach ($defaultSteps as $value)
        {
            if (empty($defaultSettings['step1']) ||
                    (!empty($defaultSettings[$value]) &&
                    (int) $defaultSettings[$value] < (int) $defaultSettings['step1']
                    )
            )
            {
                if($defaultSettings[$value] == '0' && $defaultSettings['step1'] == '0') {
                    break;
                } else {
                    throw new Exception('Step1 percentage should not greater than other steps!');
                    break;
                }                
            }
        }
    }

    private function isValidTimeFormat($timeString)
    {
        if (preg_match('/^\d{2}:\d{2}$/', $timeString))
        {
            if (
                    preg_match(
                            "/(2[0-3]|[0][0-9]|1[0-9]):([0-5][0-9])/", $timeString
                    )
            )
            {
                return true;
            }
        }
        return false;
    }

    private function UniqueRandomNumbersWithinRange($min, $max, $quantity)
    {
        $numbers = range($min, $max);
        shuffle($numbers);
        return array_slice($numbers, 0, $quantity);
    }

    private function createProductOrderFilter()
    {
        $enableProductOrderFilter = Request::form()->get('enable_product_orderfilter');
        if (!$enableProductOrderFilter)
        {
            return;
        }
        
        $productFilterConfig = Request::form()->get('productFilter');
        $product_orderfilter_configuration = '';
        $product_orderfilter_configuration_flat = '';
        $product_orderfilter_campaignid = '';
        foreach ($productFilterConfig as $key => $value)
        {
            if(array_key_exists('percentage', $value)){
                $product_orderfilter_configuration .= $value['productID'].'|'.$value['percentage']."\n";
            }
            if(array_key_exists('count_interval', $value) && array_key_exists('number_of_orderfilter', $value) && 
                    array_key_exists('number_of_non_orderfilter', $value)){
                
                $product_orderfilter_configuration_flat .= $value['count_interval'].'|'.
                    $value['number_of_orderfilter'].'|'.$value['number_of_non_orderfilter']."\n";
            }
            $product_orderfilter_campaignid .= $value['productID']."\n";
        }
        
        $product_orderfilter_configuration = rtrim($product_orderfilter_configuration,"\n");
        $product_orderfilter_configuration_flat = rtrim($product_orderfilter_configuration_flat,"\n");
        $product_orderfilter_campaignid = rtrim($product_orderfilter_campaignid,"\n");
        
        Request::form()->set('product_orderfilter_configuration',
                $product_orderfilter_configuration);
        Request::form()->set('product_orderfilter_configuration_flat',
                $product_orderfilter_configuration_flat);
        Request::form()->set('product_orderfilter_campaignid',
                $product_orderfilter_campaignid);
        Request::form()->set('product_orderfilter_scrapping_method',
                Request::form()->get('scrapping_method'));
        
        $productOrderFilterConfig = Request::form()->get('product_orderfilter_configuration');
        $productOrderFilterMethod = Request::form()->get('product_orderfilter_scrapping_method');
        $productOrderFilterCampiagnId = Request::form()->get('product_orderfilter_campaignid');
        $productOrderFilterConfigurationFlat = Request::form()->get('product_orderfilter_configuration_flat');
        $fileName = BASE_DIR . DS . 'storage/productOrderFilter';
        $jsonArray = array();
        if ($productOrderFilterMethod == 'flat')
        {
            $campaignArray = preg_split("/\\r\\n|\\r|\\n/", $productOrderFilterCampiagnId);
            $configArray = preg_split("/\\r\\n|\\r|\\n/", $productOrderFilterConfigurationFlat);
            foreach ($configArray as $key => $val)
            {
                $productOrderFilterInfo = explode('|', $val);
                $randomNo = $this->flatLogic($productOrderFilterInfo);
                $jsonArray = $this->prepareData($jsonArray, $campaignArray[$key], $fileName, $randomNo);
            }
            $this->insertData($jsonArray, $fileName);
        }
        else
        {
            $configs = preg_split("/\\r\\n|\\r|\\n/", $productOrderFilterConfig);
            foreach ($configs as $val)
            {
                $productRange = explode('|', $val);
                $randomNo = $this->UniqueRandomNumbersWithinRange(1, 100, $productRange[1]);
                $jsonArray = $this->prepareData($jsonArray, $productRange[0], $fileName, $randomNo);
            }
            $this->insertData($jsonArray, $fileName);
        }
    }

    private function prepareData($jsonArray, $val, $fileName, $randomNo)
    {
        $productArray = array();
        if (!file_exists($fileName))
        {
            $productArray[$val]['count'] = 1;
        }
        else
        {
            $fp = fopen($fileName, 'r');
            $contents = fread($fp, filesize($fileName));
            fclose($fp);
            if ($contents)
            {
                $data = json_decode($contents, true);
                foreach ($data as $key => $value)
                {
                    if (array_key_exists($val, $value))
                    {
                        $count = $value[$val]['count'];
                        $productArray[$val]['count'] = $count;
                        break;
                    }
                }
            }
        }
        $productArray[$val]['random_numbers'] = $randomNo;
        array_push($jsonArray, $productArray);
        return $jsonArray;
    }

    private function insertData($jsonArray, $fileName)
    {
        $jsonData = json_encode($jsonArray);

        try
        {
            $fp = fopen($fileName, 'r+');
            file_put_contents($fileName, $jsonData);
            fclose($fp);
        }
        catch (Exception $ex)
        {
            throw ($ex);
        }
    }

    private function flatLogic($productOrderFilterInfo)
    {
        $flatArray = array();
        $orderFilterInterval = $productOrderFilterInfo[0];
        $orderFilterCount = $productOrderFilterInfo[1];
        $nonOrderFilterCount = $productOrderFilterInfo[2];
        for ($i = 1, $j = 1; $i <= 100; $i++)
        {
            if (($i % $orderFilterInterval != 0) && ($j <= $orderFilterCount))
            {
                array_push($flatArray, $i);
                $j++;
            }
            elseif ($i % $orderFilterInterval == 0)
            {
                $j = 1;
            }
            else
            {
                $j++;
            }
        }
        return $flatArray;
    }

    private function setV2ScrapperDetails()
    {
        if (Request::form()->get('enable_v2_scrapper') && trim(Request::form()->get('scrapping_method') != "random"))
        {
            throw new Exception('For v2 scrapper you should choose random order filter option');
        }
        $enable_v2_scrapper = Request::form()->get('enable_v2_scrapper');
        if (trim(Request::form()->get('scrapping_method') == "random") &&
                !empty($enable_v2_scrapper))
        {

            $this->v2LogsFilePath = STORAGE_DIR . DS . 'trafficlb.sqlite';

            if (!file_exists($this->v2LogsFilePath))
            {
                file_put_contents($this->v2LogsFilePath, '');
            }

            if (!is_writable($this->v2LogsFilePath))
            {
                throw new Exception(
                sprintf("File %s couldn't be created.", $this->v2LogsFilePath)
                );
            }
            try
            {
                $sql = 'CREATE TABLE IF NOT EXISTS `v2loadbalancer` (
                                `id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                                `scrapcount` INTEGER NOT NULL,
                                `normalcount` INTEGER NOT NULL,
                                `ordercount` INTEGER NOT NULL,
                                `loadbalancer_id` INTEGER NOT NULL
                                )';
                $this->getDatabaseConnection()->query($sql);
            }
            catch (Exception $ex)
            {
                
                throw new Exception("Table could not be created for V2 order filter functionality");
            }
        }
    }

    private function getDatabaseConnection()
    {
        $factory = new ConnectionFactory();
        return $factory->make(array(
                    'driver' => 'sqlite',
                    'database' => STORAGE_DIR . DS . 'trafficlb.sqlite',
        ));
    }

}
