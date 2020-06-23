<?php

namespace Extension\TrafficLoadBalancer;

use Application\Config;
use Application\CrmPayload;
use Application\CrmResponse;
use Application\Helper\Provider;
use Application\Logger;
use Application\Request;
use Application\Response;
use Application\Session;
use Database\Connectors\ConnectionFactory;
use DateTime;
use Exception;
use Application\Model\Configuration;

class Scrapper
{

    private $config, $tableName, $scrapperStepId, $rule, $dbConnection,$configId;

    const V2_SCRAPPER_RESET_LIMIT = 100;
    public static $scrapSpecificScrapMethod;

    public $scrapperTypes = array(
        'enable_default_settings',
        'enable_card_scrapper',
        'enable_affiliate_orderfilter'
    );

    public function __construct()
    {
        $this->config['enable'] = $this->isScrapEnabled();
        $this->tableName = 'scrapper';
        $currentStepId = Session::get('steps.current.id');
        if((Config::extensionsConfig('TrafficLoadBalancer.default_settings.enable_remote'))){
            $this->scrapperStepId    = $currentStepId > 1 ? 2 : 1;
        }else{
            $this->scrapperStepId = $currentStepId;
        }
        $this->rule = array();
        $this->dbConnection = null;
        $this->pageType = Session::get('steps.current.pageType');
        $this->disableOrderCount = (Config::extensionsConfig('TrafficLoadBalancer.enable_daily_limit') && 
                !empty(Config::extensionsConfig('TrafficLoadBalancer.disable_orderfilter_count'))) ? (int) Config::extensionsConfig('TrafficLoadBalancer.disable_orderfilter_count') : false;
        $this->fileName = BASE_DIR . DS . 'extensions/TrafficLoadBalancer/OrderFilter.txt';

        /* Properties for card specific scrap & 
         * short interval based scrap
         */
        $this->V2ScrapperTableName = 'v2loadbalancer';
        $this->v2ScrapperDb = 'trafficlb.sqlite';
        $this->v2ScrapperDbConnection = null;
        $this->currentStepIdV2Scrapper = $currentStepId;
        $this->possibleCards = Settings::$possibleCards;
        $this->reqCardtype = null;
        $this->cardSpcScrap = false;
        $this->cardScrapPercentage = false;
        $this->configId = Session::get('steps.current.configId');
        Scrapper::$scrapSpecificScrapMethod = Config::extensionsConfig('TrafficLoadBalancer.'
                    . 'scrapping_method');
    }

    public function isScrapEnabled()
    {
        foreach ($this->scrapperTypes as $key => $value)
        {
            if (Config::extensionsConfig('TrafficLoadBalancer.' . $value)){
                return true;
                break;
            }
        }
        return false;
    }

    public function initialize()
    {
        if (!empty($_COOKIE['skipCount']))
        {
            return;
        }

        if (Session::has(
                        sprintf('extensions.trafficLoadBalancer.%d', $this->scrapperStepId)
                ))
        {
            Response::send(array(
                'success' => true,
                'message' => 'Already evaluated for this step.',
            ));
        }

        if (!empty(Config::extensionsConfig('TrafficLoadBalancer.default_settings.enable_remote')))
        {
            $settings = Settings::getRemote();
           
        }
        else
        {
            $settings = Settings::getLocal();
        }
       
        if (empty($settings['percentage']) && empty($settings['card_details']))
        {
            Response::send(array(
                'success' => false,
                'errors' => array(
                    'settings' => 'No percentage is defined. Please check your settings.',
                ),
            ));
        }

        Logger::write('Scrapper Settings', $settings);
        if(!empty(Config::extensionsConfig('TrafficLoadBalancer.default_settings.enable_remote'))){
            if ((int) $settings['percentage'][1] === (int) $settings['percentage'][2]) {
                $settings['percentage'][2] = 0;
            }
        }else{
            foreach (array(2,3,4,5) as $value)
            {
                if ((int) $settings['percentage'][1] === (int) $settings['percentage'][$value])
                {
                    $settings['percentage'][$value] = 0;
                }
            }
        }
        

        $this->config = $settings;

        $this->dbConnection = $this->getDatabaseConnection();

        $this->rule = $this->getCurrentStepRule();

        if (empty($this->rule))
        {
            Response::send(array(
                'success' => false,
                'errors' => array(
                    'rule' => 'No rule found or generated for this step.',
                ),
            ));
        }

        $isScrapped = $this->isScrapped();

        Session::set(
                sprintf('extensions.trafficLoadBalancer.%d', $this->scrapperStepId), array(
                'scrapped' => $isScrapped,
                'ruleId' => $this->rule['id'],
                'committed' => false,
                )
        );
        Session::set('steps.meta.isScrapFlow', $isScrapped);

        Response::send(array('success' => true));
    }

    /*
     * To check the card scrap percentage 
     * is exists for any one card or not.
     

    private function checkCardScrapElgibility($param)
    {
        $isExistcardScrapPercentage = false;
        if (empty($param))
        {
            return;
        }
        foreach ($this->possibleCards as $value)
        {
            if (!empty($param['card_details'][$value][1]) ||
                    !empty($param['card_details'][$value][2]))
            {
                $isExistcardScrapPercentage = true;
            }
        }
        return $isExistcardScrapPercentage;
    }*/

    private function isScrapped()
    {
        /* Scrap for short interval logic */
        if (Config::extensionsConfig('TrafficLoadBalancer.enable_v2_scrapper') &&
                $this->scrapperStepId > 1)
        {
            if (Session::get('extensions.trafficLoadBalancer.1.scrapped'))
            {
                $this->v2ScrapperDbConnection = $this->getV2ScrapperDbConnection();
                $this->setV2ScrappedCountDetails();
                return true;
            }
        }

        if ($this->scrapperStepId > 1)
        {
            if (Session::get('extensions.trafficLoadBalancer.1.scrapped'))
            {
                return true;
            }
        }
        
        $scrappingMethod = empty(Scrapper::$scrapSpecificScrapMethod) ? 'random' : Scrapper::$scrapSpecificScrapMethod;
        if(empty($scrappingMethod)){
              Response::send(array(
                'success' => false,
                'errors' => array(
                    'rule' => 'No scrapping method found.',
                ),
            ));
        }
        switch ($scrappingMethod)
        {
            case 'timestamp':return $this->determineByTimestampScrapper();
            case 'flat':return $this->determineByFlatScrapper();
            case 'random':
            default:return $this->determineByRandomScrapper();
        }
    }
    
    /*private function selectSpecificScrapperMethod()
    {
        print_r(Request::form()->all());
        print_r(Session::get('affiliates', array()));die;
    }*/

    protected function determineByFlatScrapper()
    {
        if (!Config::extensionsConfig('TrafficLoadBalancer.default_settings.enable_remote') && 
                !empty($this->reqCardtype) && array_key_exists($this->reqCardtype, $this->rule['card_details']) &&
                empty($this->rule['card_details'][$this->reqCardtype][$this->configId]))
        {
            return false;
        }
        if(Config::extensionsConfig('TrafficLoadBalancer.default_settings.enable_remote') && 
                !empty($this->reqCardtype) && array_key_exists($this->reqCardtype, $this->rule['card_details'] )
                && empty($this->rule['card_details'][$this->reqCardtype][$this->scrapperStepId])){
                return false;
        }
        $this->rule['percentage'] = empty($this->cardScrapPercentage) ? $this->rule['percentage'] :
                $this->cardScrapPercentage;

        $upperLimit = (int) $this->getUpperLimit($this->rule['percentage']);
        if ((int) $this->rule['percentage'] === 0)
        {
            return false;
        }

        $scrappedStep = (int) ceil($upperLimit / $this->rule['percentage']);
        for ($step = $scrappedStep; $step <= 100; $step = $step + $scrappedStep)
        {
            if ((int) $this->rule['hitsCount'] === $step - 1)
            {
                return true;
            }
        }
        return false;
    }

    protected function determineByTimestampScrapper()
    {

        $startTime = Config::extensionsConfig('TrafficLoadBalancer.scheduler.start_time');
        $endTime = Config::extensionsConfig('TrafficLoadBalancer.scheduler.end_time');

        $startDateTime = DateTime::createFromFormat('H:i', $startTime);
        $endDateTime = DateTime::createFromFormat('H:i', $endTime);
        $currentDateTime = new DateTime();

        if (
                $startDateTime <= $currentDateTime &&
                $endDateTime >= $currentDateTime
        )
        {
            return true;
        }

        return false;
    }

    protected function determineByRandomScrapper()
    {
        if (empty($this->rule['percentage']) && empty($this->cardScrapPercentage))
        {
            return false;
        }
        if (!Config::extensionsConfig('TrafficLoadBalancer.default_settings.enable_remote') && 
                !empty($this->reqCardtype) && array_key_exists($this->reqCardtype, $this->rule['card_details']) &&
                empty($this->rule['card_details'][$this->reqCardtype][$this->configId]))
        {
            return false;
        }
        if(Config::extensionsConfig('TrafficLoadBalancer.default_settings.enable_remote') && 
                !empty($this->reqCardtype) && array_key_exists($this->reqCardtype, $this->rule['card_details'] )
                && empty($this->rule['card_details'][$this->reqCardtype][$this->scrapperStepId])){
                return false;
        }
        $this->rule['percentage'] = empty($this->cardScrapPercentage) ? $this->rule['percentage'] :
                $this->cardScrapPercentage;
        
        $scrappedCount = $this->rule['scrappedCount'];
        $hitsCount = $this->rule['hitsCount'];
        if(Config::extensionsConfig('TrafficLoadBalancer.default_settings.enable_remote') ||
                    Config::extensionsConfig('TrafficLoadBalancer.enable_card_scrapper')){
            
            $scrappedCount = $this->rule['card_details'][$this->reqCardtype]['details']['scrappedCount'];
            $hitsCount = $this->rule['card_details'][$this->reqCardtype]['details']['hitsCount'];
        }

        /* Scrap for short interval logic */
        if (Config::extensionsConfig('TrafficLoadBalancer.enable_v2_scrapper'))
        {
            $this->v2ScrapperDbConnection = $this->getV2ScrapperDbConnection();
            return $this->determineV2Scrapper();
        }
        $upperLimit = $this->getUpperLimit($this->rule['percentage']);
        Logger::write('Upper Limit', $upperLimit);
        $maxScrapLimit = (float) (($upperLimit * $this->rule['percentage']) / 100.00);
        Logger::write('Max Scrap Limit', $maxScrapLimit);
        $pendingScrap = (float) ($maxScrapLimit - $scrappedCount);
        Logger::write('Pending Scrap', $pendingScrap);
        $currentCounter = (float) $hitsCount;
        Logger::write('Current Counter', $currentCounter);
        $pr = (float) ($pendingScrap / ($upperLimit - $currentCounter));
        Logger::write('Probability', $pr);

        if (((float) mt_rand(1, $upperLimit) / (float) $upperLimit) <= $pr)
        {
            return true;
        }
        else
        {

            return false;
        }
    }

    protected function getUpperLimit($percentage)
    {

        $scrappingMethod = Config::extensionsConfig('TrafficLoadBalancer.scrapping_method');
        switch ($scrappingMethod)
        {
            case 'timestamp':return 100;
            case 'flat':return 100;
            case 'random':
            default:break;
        }

        if ((int) $percentage === 100 || (int) $percentage === 0)
        {
            return 100;
        }
        $number2 = 100 - (int) $percentage;
        $number1 = (int) $percentage;
        $gcd = $this->gcd($number1, $number2);
        return (int) ($number1 / $gcd) + (int) ($number2 / $gcd);
    }

    protected function gcd($number1, $number2)
    {
        while ($number2)
        {
            $temp = $number2;
            $number2 = $number1 % $number2;
            $number1 = $temp;
        }
        return $number1;
    }

    public function incrementHit()
    {
        /** return for card specific scrap * */
        if (Session::has('extensions.trafficLoadBalancer.' . $this->scrapperStepId . '.cardScrapEval'))
        {
            $isCardScrap = Session::get('extensions.trafficLoadBalancer.' . $this->scrapperStepId . '.cardScrapEval');
            if ($isCardScrap)
            {
                return;
            }
        }

        if (!empty($this->disableOrderCount) && !empty($_COOKIE['skipCount']))
        {
            if (CrmResponse::has('orderId') !== true)
            {
                try
                {
                    $fp = fopen($this->fileName, 'r');
                    $contents = fread($fp, filesize($this->fileName));
                    fclose($fp);
                    if (!empty($contents))
                    {
                        $data = explode(',', $contents);
                        $countPerDay = $data[0];
                        $currentDateTime = new DateTime();
                        $currentDate = strtotime($currentDateTime->format('Y-m-d'));
                        $this->increaseOrderFilterFile($this->fileName, $currentDate, $countPerDay - 1);
                    }
                }
                catch (Exception $ex)
                {
                    throw ($ex);
                }
            }
        }

        try
        {
            if (empty($this->config['enable']))
            {
                return;
            }

            if (Session::get(
                            sprintf(
                                    'extensions.trafficLoadBalancer.%d.committed', $this->scrapperStepId
                            )
                    ) === true)
            {
                return;
            }
            if (CrmResponse::has('orderId') !== true)
            {
                return;
            }

            $currentLoadBalancer = Session::get(
                            sprintf('extensions.trafficLoadBalancer.%d', $this->scrapperStepId)
            );

            if (!empty($currentLoadBalancer['committed']))
            {
                return;
            }

            $this->dbConnection = $this->getDatabaseConnection();

            $this->rule = $this->getRuleById($currentLoadBalancer['ruleId']);

            if (empty($this->rule))
            {
                return;
            }

            $upperLimit = $this->getUpperLimit($this->rule['percentage']);

            $data = array(
                'hitsCount' => ($this->rule['hitsCount'] + 1) % $upperLimit,
                'hits' => $this->rule['hits'] + 1,
            );

            $isDisablePrepaidOrderFiler = Config::extensionsConfig('TrafficLoadBalancer.default_settings.disable_prepaid_orderfilter');

            if($isDisablePrepaidOrderFiler && Session::has('steps.meta.isPrepaidFlow')) {
                $data = array(
                    'hitsCount' => ($this->rule['hitsCount']) % $upperLimit,
                    'hits'      => $this->rule['hits'],
                );
            }

            if (
                    $currentLoadBalancer['scrapped'] && !$isDisablePrepaidOrderFiler && Session::has('steps.meta.isPrepaidFlow') ||
                    $currentLoadBalancer['scrapped'] && !Session::has('steps.meta.isPrepaidFlow')
            )
            {
                $data['scrappedCount'] = $this->rule['scrappedCount'] + 1;
                $data['scrapped'] = $this->rule['scrapped'] + 1;
            }

            if ($data['hitsCount'] === 0)
            {
                $data['scrappedCount'] = 0;
            }

            $this->dbConnection->table($this->tableName)
                    ->where('id', $currentLoadBalancer['ruleId'])
                    ->update($data);

            $currentLoadBalancer['committed'] = true;
            Session::set(
                    sprintf(
                            'extensions.trafficLoadBalancer.%d', $this->scrapperStepId
                    ), $currentLoadBalancer
            );
        }
        catch (Exception $ex)
        {
            
        }
    }

    private function getCurrentStepRule($id = null)
    {
        $rule = $this->dbConnection->table($this->tableName)
                ->select('id', 'percentage', 'card_details')
                ->where('scrapStep', $this->scrapperStepId)
                ->where($this->config['affiliates'])
                ->first();
       
        if ($rule !== null && is_array($rule))
        {
            $rule['percentage'] = (int) $rule['percentage'];
        }

        if ($rule === null)
        {
            $this->insertRule(array_merge(
                            array(
                'percentage' => $this->config['percentage'][$this->scrapperStepId],
                'card_details' => empty($this->config['card_details']) ? null : json_encode($this->config['card_details']),
                            ), $this->config['affiliates']));
        }
        else if (
                $rule['percentage'] !== $this->config['percentage'][$this->scrapperStepId])
        {
            $this->updateRule($rule['id'], array(
                'scrappedCount' => 0,
                'hitsCount' => 0,
                'percentage' => $this->config['percentage'][$this->scrapperStepId]
            ));
        }
        if (!empty($this->config['card_details']))
        {
             if (!empty(Config::extensionsConfig('TrafficLoadBalancer.default_settings.enable_remote'))){
                 
                $cardSpecificchanges = $this->getChangesCardSpecifDetailsRemote($this->config['card_details'],
                         json_decode($rule['card_details'], true));
             }else{
                $cardSpecificchanges = $this->getChangesCardSpecifDetailsLocal($this->config['card_details'],
                        json_decode($rule['card_details'], true));
             }
            if (!empty($cardSpecificchanges))
            {
                $new = array_replace($this->config['card_details'], $cardSpecificchanges);
                $this->updateRule($rule['id'], array(
                    'card_details' => json_encode($new)
                ));
            }
        }

        $rule = $this->dbConnection->table($this->tableName)
                ->select(
                        'id', 'scrappedCount', 'hitsCount', 'percentage', 'scrapped', 'hits', 'card_details'
                )
                ->where('scrapStep', $this->scrapperStepId)
                ->where($this->config['affiliates'])
                ->first();
        $rule['percentage'] = (int) $rule['percentage'];

        $rule['card_details'] = empty($rule['card_details']) ? null : json_decode($rule['card_details'], true);
       

        return $rule;
    }
    
    private function getChangesCardSpecifDetailsRemote($current,$existing)
    {

        if(empty($current)){
                return false;
        }
        $cardDetsails = array();
        foreach($this->possibleCards as $val){
                if(empty($existing)){
                        $cardDetsails[$val][$this->scrapperStepId] = $current[$val][$this->scrapperStepId];
                        $cardDetsails[$val][2] = $current[$val][2];
                        $cardDetsails[$val]['details']['scrappedCount'] = 0;
                        $cardDetsails[$val]['details']['hitsCount'] = 0;
                        $cardDetsails[$val]['details']['hits'] = 0;
                        $cardDetsails[$val]['details']['scrapped'] = 0;
                }
                if(!empty($existing) && $existing[$val][$this->scrapperStepId] != $current[$val][$this->scrapperStepId]){
                        $cardDetsails[$val][$this->scrapperStepId] = $current[$val][$this->scrapperStepId];
                        $cardDetsails[$val][2] = $current[$val][2];
                        $cardDetsails[$val]['details']['scrappedCount'] = 0;
                        $cardDetsails[$val]['details']['hitsCount'] = 0;
                }
        }

        return $cardDetsails;
    }

    private function getChangesCardSpecifDetailsLocal($current, $existing)
    {
        if (empty($current))
        {
            return false;
        }
        $cardDetsails = array();
        foreach ($this->possibleCards as $val)
        {
            if (empty($existing))
            {
                $cardDetsails[$val][$this->configId] = $current[$val][$this->configId];
                $cardDetsails[$val]['details']['scrappedCount'] = 0;
                $cardDetsails[$val]['details']['hitsCount'] = 0;
                $cardDetsails[$val]['details']['hits'] = 0;
                $cardDetsails[$val]['details']['scrapped'] = 0;
            }
            if (!empty($existing) && $existing[$val][$this->configId] != $current[$val][$this->configId])
            {
                $cardDetsails[$val][$this->configId] = $current[$val][$this->configId];
                $cardDetsails[$val]['details']['scrappedCount'] = 0;
                $cardDetsails[$val]['details']['hitsCount'] = 0;
            }
        }

        return $cardDetsails;
    }

    private function removeExtraForMatch($param)
    {
        foreach (array_keys($param) as $val)
        {
            if (array_key_exists('details', $param[$val]))
                unset($param[$val]['details']);
        }
        return $param;
    }

    private function getRuleById($id)
    {
        return $this->dbConnection->table($this->tableName)
                        ->select(
                                'id', 'scrappedCount', 'hitsCount', 'percentage', 'scrapped', 'hits', 'card_details'
                        )->find($id);
    }

    private function updateRule($id, $data = array())
    {
        $this->dbConnection->table($this->tableName)
                ->where('id', $id)->update($data);
    }

    private function insertRule($data = array())
    {
        $affiliates = Settings::initializeAffiliates();
        $rule = array_merge(array(
            'scrappedCount' => 0,
            'hitsCount' => 0,
            'scrapped' => 0,
            'hits' => 0,
            'percentage' => 0,
            'scrapStep' => $this->scrapperStepId,
                ), $data);
        $this->dbConnection->table($this->tableName)->insert($rule);
    }

    protected function loadBalance()
    {
        return in_array(
                $this->rule['hitsCount'], explode(',', $this->rule['randomNumbers'])
        );
    }

    private function getDatabaseConnection()
    {
        $factory = new ConnectionFactory();
        return $factory->make(array(
                    'driver' => 'sqlite',
                    'database' => STORAGE_DIR . DS . 'trafficlb.sqlite',
        ));
    }

    public function injectScript()
    {
        if (empty($this->config['enable']))
        {
            return;
        }

        if (!$this->isEligibleForAdvancedActions())
        {
            return;
        }

        if($this->isOldFrameworkVersion())
        {
            echo Provider::asyncScript(
                AJAX_PATH . 'extensions/trafficloadbalancer/initialize'
            );

            $ajax_path = Request::getOfferPath() . AJAX_PATH . 'extensions/trafficloadbalancer/place';
        }
        else{
            echo Provider::asyncScript(
                    AJAX_PATH . 'extensions/checktraffic/initialize'
            );

            $ajax_path = Request::getOfferPath() . AJAX_PATH . 'extensions/checktraffic/place';
        }

        echo <<<EOF
        <script type="text/javascript">
            $(function(){

                setTimeout(function(){
                    $.get("$ajax_path", function( data ) {
                        if(data == null) return;
                
                        if(typeof data == 'string') {
                            data = JSON.parse(data);
                        }
                
                        data.forEach(function(el) {
                            
                            if(el.type == 'head') {
                                $('head').append(el.content);
                            }
    
                            if(el.type == 'top') {
                                $('body').prepend(el.content);
                            }
    
                            if(el.type == 'bottom') {
                                $('body').append(el.content);
                            }
                        });
                    });
                }, 500);
                
            });
            
        </script>
EOF;
    }

    public function place() {
        $isScrapFlow = Session::get('steps.meta.isScrapFlow');
        if(!$isScrapFlow) {
            $GeneralPixelToFire = Session::get('GeneralPixelToFire');
            echo empty($GeneralPixelToFire) ? null : json_encode(Session::get('GeneralPixelToFire'));
            Session::remove('GeneralPixelToFire');
        }
    }

    public function isDisableOrderFilter()
    {
        $disableOrderCount = Config::extensionsConfig('TrafficLoadBalancer.disable_orderfilter_count');
        if (empty($this->disableOrderCount) || 
                empty($disableOrderCount) ||
                !empty($_COOKIE['skipCount'])
            )
        {
            return;
        }
        try
        {
            $fp = fopen($this->fileName, 'r');
            $contents = fread($fp, filesize($this->fileName));
            fclose($fp);
            if (!empty($contents))
            {
                $data = explode(',', $contents);
                $countPerDay = $data[0];
                $timeStamp = $data[1];
                $currentDateTime = new DateTime();
                $currentDate = strtotime($currentDateTime->format('Y-m-d'));
                if ($currentDate != $timeStamp)
                {
                    $this->increaseOrderFilterFile($this->fileName, $currentDate, 1);
                    setcookie("skipCount", true, time() + (86400 * 20));
                }
                else
                {
                    if ($countPerDay < $disableOrderCount)
                    {
                        $this->increaseOrderFilterFile($this->fileName, $currentDate, $countPerDay + 1);
                        setcookie("skipCount", true, time() + (86400 * 20));
                    }
                }
            }
        }
        catch (Exception $ex)
        {
            throw ($ex);
        }
    }

    public function increaseOrderFilterFile($fileName, $currentDate, $count)
    {
        try
        {
            $fp = fopen($fileName, 'r+');
            flock($fp, LOCK_EX);
            fwrite($fp, $count . ',' . $currentDate);
            flock($fp, LOCK_UN);
            fclose($fp);
        }
        catch (Exception $ex)
        {
            print_r($ex);
            throw ($ex);
        }
    }

    public function isEligibleForAdvancedActions()
    {
        $allowedConfig = Config::extensionsConfig('TrafficLoadBalancer.allowed_config');
        $currentConfig = Session::get('steps.current.configId');
        if (!empty($allowedConfig) && !empty($currentConfig))
        {
            $configs = explode(',', $allowedConfig);
            if (!in_array($currentConfig, $configs))
            {
                return false;
            }
        }

        return true;
    }

    public function switchMethod()
    {
        if (
                Session::get(
                        'extensions.trafficLoadBalancer.1.scrapped'
                ) === true ||
                Request::attributes()->get('action') !== 'upsell' ||
                CrmPayload::get('meta.crmMethod') === 'newOrder' ||
            Session::get('steps.meta.isScrapFlow') !== true ||
            Session::get('extensions.trafficLoadBalancer.orderFilter')
        )
        {
            return;
        }
        CrmPayload::remove('previousOrderId');
        CrmPayload::remove('customerId');
        CrmPayload::set('meta.crmMethod', 'newOrder');
    }

    /**
     * if step was scrapped then it will work 
     * to add the count details. That means 
     * when step2 will forcefully scrapped.
     */
    private function setV2ScrappedCountDetails()
    {
        try
        {
            $this->v2ScrapperDbConnection = $this->getV2ScrapperDbConnection();
            $data['loadbalancer_id'] = $this->rule['id'];
            $rule = $this->v2ScrapperDbConnection->table($this->V2ScrapperTableName)
                            ->select('*')->where('loadbalancer_id', $data['loadbalancer_id'])->first();
            if (empty($rule))
            {
                $data['scrapcount'] = 1;
                $data['normalcount'] = 0;
                $data['ordercount'] = 1;
                $data['isV2Scrap'] = 1;
            }
            else
            {
                $data['scrapcount'] = $rule['scrapcount'] + 1;
                $data['normalcount'] = $rule['normalcount'];
                $data['ordercount'] = $rule['ordercount'] + 1;
                $data['id'] = $rule['id'];
                $data['isV2Scrap'] = 1;
            }
            Session::set('steps.' . $this->currentStepIdV2Scrapper . '.scrapper', $data);
        }
        catch (\Exception $ex)
        {
            
        }
    }

    /**
     * For short interval based random scrap it will use.
     * @return boolean
     */
    private function determineV2Scrapper()
    {
        if (!empty($this->reqCardtype) && array_key_exists($this->reqCardtype, $this->rule['card_details']) && empty($this->rule['card_details'][$this->reqCardtype][$this->scrapperStepId]))
        {
            return false;
        }

        $data = array();
        $this->rule['percentage'] = empty($this->cardScrapPercentage) ? $this->rule['percentage'] :
                $this->cardScrapPercentage;
        $scrapPercentage = $this->rule['percentage'];
        $normalPercentage = 100 - round($scrapPercentage);
        $data['loadbalancer_id'] = $this->rule['id'];

        $rule = $this->v2ScrapperDbConnection->table($this->V2ScrapperTableName)
                        ->select('*')->where('loadbalancer_id', $data['loadbalancer_id'])->first();
        if (empty($rule))
        {
            $randoms = array('scrap', 'normal');
            $randKey = ($scrapPercentage == 100) ? 0 : array_rand($randoms);
            $data['scrapcount'] = ($randoms[$randKey] == "scrap") ? 1 : 0;
            $data['normalcount'] = ($randoms[$randKey] == "normal") ? 1 : 0;
            $data['ordercount'] = 1;
            //$data['campaign'] = ($randoms[$randKey] == "scrap") ? 1 : 0;
            $data['isV2Scrap'] = ($randoms[$randKey] == "scrap") ? 1 : 0;
            Session::set('steps.' . $this->currentStepIdV2Scrapper . '.scrapper', $data);
            return empty($data['scrapcount']) ? false : true;
        }
        $data['scrapcount'] = $rule['scrapcount'];
        $data['normalcount'] = $rule['normalcount'];
        $data['ordercount'] = $rule['ordercount'];
        $isV2Scrap = false;
        if (round(($data['scrapcount'] / $data['ordercount']) * 100) < $scrapPercentage ||
                $scrapPercentage == 100)
        {
            //$data['campaign'] = 1;
            $data['scrapcount'] += 1;
            $isV2Scrap = true;
        }
        else
        {
            $campaign = 0;
            $data['normalcount'] += 1;
        }
        $data['ordercount'] += 1;
        $data['isV2Scrap'] = $isV2Scrap;
        $data['id'] = $rule['id'];
        Session::set('steps.' . $this->currentStepIdV2Scrapper . '.scrapper', $data);
        return $isV2Scrap;
    }

    /*
     * For short interval based scrap after placed 
     * successful order it will increase desired count.
     */

    public function setScrapperDetails()
    {
        try
        {
            /* Scrap for short interval logic */
            if (CrmResponse::get('success') && CrmResponse::has('orderId') &&
                    Config::extensionsConfig('TrafficLoadBalancer.enable_v2_scrapper') &&
                    Session::has('steps.' . $this->currentStepIdV2Scrapper . '.scrapper')
            )
            {
                $this->v2ScrapperDbConnection = $this->getV2ScrapperDbConnection();

                if (Session::has('steps.' . $this->currentStepIdV2Scrapper . '.scrapper'))
                {
                    $v2ScrapperSesData = Session::get('steps.' . $this->currentStepIdV2Scrapper . '.scrapper');
                    if (!empty($v2ScrapperSesData['id']))
                    {
                        $id = $v2ScrapperSesData['id'];
                        unset($v2ScrapperSesData['id'], $v2ScrapperSesData['isV2Scrap']);
                        if ((int) $v2ScrapperSesData['ordercount'] > self::V2_SCRAPPER_RESET_LIMIT)
                        {
                            $this->resetV2ScrapperData($id);
                        }
                        else
                        {
                            $this->v2ScrapperDbConnection->table($this->V2ScrapperTableName)
                                    ->where('id', $id)
                                    ->update($v2ScrapperSesData);
                        }
                    }
                    else
                    {
                        unset($v2ScrapperSesData['isV2Scrap']);
                        $this->v2ScrapperDbConnection->table($this->V2ScrapperTableName)->insert($v2ScrapperSesData);
                    }
                }
            }
        }
        catch (\Exception $ex)
        {
            
        }
        return;
    }

    private function resetV2ScrapperData($id)
    {
        $this->v2ScrapperDbConnection->table($this->V2ScrapperTableName)
                ->where('id', $id)
                ->update(
                        array(
                            'scrapcount' => 0,
                            'normalcount' => 0,
                            'ordercount' => 0
                        )
        );
        return true;
    }

    private function getV2ScrapperDbConnection()
    {
        $factory = new ConnectionFactory();
        return $factory->make(array(
                    'driver' => 'sqlite',
                    'database' => STORAGE_DIR . DS . $this->v2ScrapperDb,
        ));
    }

    public function setCardSpecificScrap()
    {
        $cardType = Request::form()->get('creditCardType');
        $this->reqCardtype = empty($cardType) ? Session::get('customer.cardType') : $cardType;
        if(
                empty($this->reqCardtype) || 
                (
                    (!Config::extensionsConfig('TrafficLoadBalancer.default_settings.enable_card_based_from_remote') ||
                    !Config::extensionsConfig('TrafficLoadBalancer.default_settings.enable_remote')) && 
                    !Config::extensionsConfig('TrafficLoadBalancer.enable_card_scrapper')
                )
        )
        {
            return;
        }
       
        /*
          if(empty(CrmPayload::get('cardType')) ||
          CrmPayload::get('meta.isSplitOrder') ||
          !Config::extensionsConfig('TrafficLoadBalancer.enable_card_scrapper')){
          return;
          } */
        $this->dbConnection = $this->getDatabaseConnection();

        /* $this->reqCardtype = CrmPayload::get('cardType'); */
        if (!empty($_COOKIE['skipCount']))
        {
            return;
        }

        if (Session::has(
                        sprintf('extensions.trafficLoadBalancer.%d.cardScrapEval', $this->scrapperStepId)
                ))
        {
            return;
        }
        
        $cardSpecificPercentage = 0;
        if (!empty(Config::extensionsConfig('TrafficLoadBalancer.default_settings.enable_remote')))
        {
            $settings = Settings::getRemote();
            $cardSpecificPercentage = empty($settings['card_details'][$this->reqCardtype][$this->scrapperStepId]) ? 0 : 
                    (int) $settings['card_details'][$this->reqCardtype][$this->scrapperStepId];
        }
        else
        {
            $settings = Settings::getLocal();
            $cardSpecificPercentage = empty($settings['card_details'][$this->reqCardtype][$this->configId]) ? 0 : 
                    (int) $settings['card_details'][$this->reqCardtype][$this->configId];
            
        }
        
        if (empty($settings['card_details']))
        {
            return;
        }

        $isStep1CardScrap = false;
        if ($this->scrapperStepId > 1)
        {
            $isStep1CardScrap = Session::get('extensions.trafficLoadBalancer.1.scrapped');
        }

        $prevStepScrap = empty(Session::has('extensions.trafficLoadBalancer.1.cardScrapEval')) ? false :
                Session::get('extensions.trafficLoadBalancer.1.cardScrapEval');
        
        /*$cardSpecificPercentage = 0;
        if (!empty($settings['card_details'][$this->reqCardtype][$this->configId]))
        {
            $cardSpecificPercentage = $settings['card_details'][$this->reqCardtype][$this->configId];
        }*/

        Logger::write('Scrapper Settings', $settings);

        if (!empty(Config::extensionsConfig('TrafficLoadBalancer.default_settings.enable_remote')))
        {
            if ((int) $settings['card_details'][$this->reqCardtype][1] === 
                    (int) $settings['card_details'][$this->reqCardtype][2])
            {
                $settings['card_details'][$this->reqCardtype][2] = 0;
            }
        }else{
            foreach (array(2,3,4,5) as $value)
            {
                if ((int) $settings['percentage'][1] === (int) $settings['percentage'][$value])
                {
                    $settings['percentage'][$value] = 0;
                }
            }
        }

        $this->config = $settings;

        $this->dbConnection = $this->getDatabaseConnection();

        $this->rule = $this->getCurrentStepRule();

        if (empty($this->rule))
        {
            return;
        }
        $this->cardScrapPercentage = (int) $cardSpecificPercentage;
        Scrapper::$scrapSpecificScrapMethod = Config::extensionsConfig('TrafficLoadBalancer.'
                    . 'scrapping_method');
        $isScrapped = $this->isScrapped();
        Session::set(
                sprintf('extensions.trafficLoadBalancer.%d', $this->scrapperStepId), array(
            'scrapped' => $isScrapped,
            'ruleId' => $this->rule['id'],
            'cardScrapCommmited' => false,
            'cardScrapEval' => true
                )
        );
        Session::set('steps.meta.isScrapFlow', $isScrapped);
        return;
    }

    public function incrementHitCardScrap()
    {
        $isCardScrapEval = Session::get('extensions.trafficLoadBalancer.' . $this->scrapperStepId . '.cardScrapEval');
        if(!$isCardScrapEval   || 
                (
                    (!Config::extensionsConfig('TrafficLoadBalancer.default_settings.enable_card_based_from_remote') ||
                    !Config::extensionsConfig('TrafficLoadBalancer.default_settings.enable_remote')) && 
                    !Config::extensionsConfig('TrafficLoadBalancer.enable_card_scrapper')
                )
        )
            return;

        if (!empty($this->disableOrderCount) && !empty($_COOKIE['skipCount']))
        {
            if (CrmResponse::has('orderId') !== true)
            {
                try
                {
                    $fp = fopen($this->fileName, 'r');
                    $contents = fread($fp, filesize($this->fileName));
                    fclose($fp);
                    if (!empty($contents))
                    {
                        $data = explode(',', $contents);
                        $countPerDay = $data[0];
                        $currentDateTime = new DateTime();
                        $currentDate = strtotime($currentDateTime->format('Y-m-d'));
                        $this->increaseOrderFilterFile($this->fileName, $currentDate, $countPerDay - 1);
                    }
                }
                catch (Exception $ex)
                {
                    throw ($ex);
                }
            }
        }

        try
        {

            if (empty($this->config['enable']))
            {
                return;
            }

            if (Session::get(
                            sprintf(
                                    'extensions.trafficLoadBalancer.%d.cardScrapCommmited', $this->scrapperStepId
                            )
                    ) === true)
            {
                return;
            }
            if (CrmResponse::has('orderId') !== true)
            {
                return;
            }

            $currentLoadBalancer = Session::get(
                            sprintf('extensions.trafficLoadBalancer.%d', $this->scrapperStepId)
            );
            $this->dbConnection = $this->getDatabaseConnection();
            $this->rule = $this->getRuleById($currentLoadBalancer['ruleId']);
            $cardScrapData = empty($this->rule['card_details']) ? false : json_decode($this->rule['card_details'], true);

            if (empty($cardScrapData))
            {
                return;
            }
            $this->reqCardtype = CrmPayload::get('cardType');
            $isStep1CardScrap = false;
            if ($this->scrapperStepId > 1)
            {
                $isStep1CardScrap = Session::get('extensions.trafficLoadBalancer.1.scrapped');
            }

            $cardSpecificPercentage = 0;
            if (!empty(Config::extensionsConfig('TrafficLoadBalancer.default_settings.enable_remote'))){
                if(!empty((int)$cardScrapData[$this->reqCardtype][$this->scrapperStepId])){
                            $cardSpecificPercentage = (int) $cardScrapData[$this->reqCardtype][$this->scrapperStepId];
                }
            }else{
                if (!empty((int) $cardScrapData[$this->reqCardtype][$this->configId]))
                {
                    $cardSpecificPercentage = (int) $cardScrapData[$this->reqCardtype][$this->configId];
                }
            }

            $cardUpperLimit = $this->getUpperLimit($cardSpecificPercentage);

            $modifiedData = $cardScrapData;
            $modifiedData[$this->reqCardtype]['details']['hitsCount'] = ((int) $modifiedData[$this->reqCardtype]['details']['hitsCount'] + 1) % $cardUpperLimit;
            $modifiedData[$this->reqCardtype]['details']['hits'] = (int) $modifiedData[$this->reqCardtype]['details']['hits'] + 1;

            $upperLimit = $this->getUpperLimit($this->rule['percentage']);
            $data = array(
                'hitsCount' => ($this->rule['hitsCount'] + 1) % $upperLimit,
                'hits' => $this->rule['hits'] + 1,
            );

            $isDisablePrepaidOrderFiler = Config::extensionsConfig('TrafficLoadBalancer.default_settings.disable_prepaid_orderfilter');

            if($isDisablePrepaidOrderFiler && Session::has('steps.meta.isPrepaidFlow')) {
            $data = array(
                'hitsCount' => ($this->rule['hitsCount']) % $upperLimit,
                'hits'      => $this->rule['hits'],
            );
            $modifiedData[$this->reqCardtype]['details']['hitsCount'] =
                        ((int) $modifiedData[$this->reqCardtype]['details']['hitsCount']) % $cardUpperLimit;
            $modifiedData[$this->reqCardtype]['details']['hits'] = 
                        (int) $modifiedData[$this->reqCardtype]['details']['hits'];
        }

            if (
                    $currentLoadBalancer['scrapped'] && !$isDisablePrepaidOrderFiler && Session::has('steps.meta.isPrepaidFlow') ||
                    $currentLoadBalancer['scrapped'] && !Session::has('steps.meta.isPrepaidFlow')
            )
            {

                $data['scrappedCount'] = $this->rule['scrappedCount'] + 1;
                $data['scrapped'] = $this->rule['scrapped'] + 1;

                $modifiedData[$this->reqCardtype]['details']['scrappedCount'] = (int) $modifiedData[$this->reqCardtype]['details']['scrappedCount'] + 1;

                $modifiedData[$this->reqCardtype]['details']['scrapped'] = (int) $modifiedData[$this->reqCardtype]['details']['scrapped'] + 1;
            }

            if ($data['hitsCount'] === 0)
            {
                $data['scrappedCount'] = 0;
            }

            if ($modifiedData[$this->reqCardtype]['details']['hitsCount'] === 0)
            {
                $modifiedData[$this->reqCardtype]['details']['scrappedCount'] = 0;
            }
            $data['card_details'] = json_encode($modifiedData);
            $this->dbConnection->table($this->tableName)
                    ->where('id', $currentLoadBalancer['ruleId'])
                    ->update($data);
            $currentLoadBalancer['cardScrapCommmited'] = true;
            $currentLoadBalancer['committed'] = true;
            Session::set(
                    sprintf(
                            'extensions.trafficLoadBalancer.%d', $this->scrapperStepId
                    ), $currentLoadBalancer
            );
        }
        catch (Exception $ex)
        {
            
        }
    }
    
    public function incrementHitCardScrapForDelay()
    {
        if (
            Request::attributes()->get('action') === 'prospect'
        ) {
            return;
        }
        $configuration = new Configuration(CrmPayload::get('meta.configId'));
        if(!$configuration->getEnableDelay())
        {
            return false;
        }

        $isCardScrapEval = Session::get('extensions.trafficLoadBalancer.' . $this->scrapperStepId . '.cardScrapEval');
        if(!$isCardScrapEval   || 
                (
                    (!Config::extensionsConfig('TrafficLoadBalancer.default_settings.enable_card_based_from_remote') ||
                    !Config::extensionsConfig('TrafficLoadBalancer.default_settings.enable_remote')) && 
                    !Config::extensionsConfig('TrafficLoadBalancer.enable_card_scrapper')
                )
        )
            return;

        if (!empty($this->disableOrderCount) && !empty($_COOKIE['skipCount']))
        {
            try
            {
                $fp = fopen($this->fileName, 'r');
                $contents = fread($fp, filesize($this->fileName));
                fclose($fp);
                if (!empty($contents))
                {
                    $data = explode(',', $contents);
                    $countPerDay = $data[0];
                    $currentDateTime = new DateTime();
                    $currentDate = strtotime($currentDateTime->format('Y-m-d'));
                    $this->increaseOrderFilterFile($this->fileName, $currentDate, $countPerDay - 1);
                }
            }
            catch (Exception $ex)
            {
                throw ($ex);
            }
        }

        try
        {

            if (empty($this->config['enable']))
            {
                return;
            }

            $currentLoadBalancer = Session::get(
                            sprintf('extensions.trafficLoadBalancer.%d', $this->scrapperStepId)
            );
            $this->dbConnection = $this->getDatabaseConnection();
            $this->rule = $this->getRuleById($currentLoadBalancer['ruleId']);
            $cardScrapData = empty($this->rule['card_details']) ? false : json_decode($this->rule['card_details'], true);
 
            if (empty($cardScrapData))
            {
                return;
            }
            $this->reqCardtype = CrmPayload::get('cardType');
            $isStep1CardScrap = false;
            if ($this->scrapperStepId > 1)
            {
                $isStep1CardScrap = Session::get('extensions.trafficLoadBalancer.1.scrapped');
            }

            $cardSpecificPercentage = 0;
            if (!empty(Config::extensionsConfig('TrafficLoadBalancer.default_settings.enable_remote'))){
                if(!empty((int)$cardScrapData[$this->reqCardtype][$this->scrapperStepId])){
                            $cardSpecificPercentage = (int) $cardScrapData[$this->reqCardtype][$this->scrapperStepId];
                }
            }else{
                if (!empty((int) $cardScrapData[$this->reqCardtype][$this->configId]))
                {
                    $cardSpecificPercentage = (int) $cardScrapData[$this->reqCardtype][$this->configId];
                }
            }

            $cardUpperLimit = $this->getUpperLimit($cardSpecificPercentage);

            $modifiedData = $cardScrapData;
            $modifiedData[$this->reqCardtype]['details']['hitsCount'] = ((int) $modifiedData[$this->reqCardtype]['details']['hitsCount'] + 1) % $cardUpperLimit;
            $modifiedData[$this->reqCardtype]['details']['hits'] = (int) $modifiedData[$this->reqCardtype]['details']['hits'] + 1;

            $upperLimit = $this->getUpperLimit($this->rule['percentage']);
            $data = array(
                'hitsCount' => ($this->rule['hitsCount'] + 1) % $upperLimit,
                'hits' => $this->rule['hits'] + 1,
            );

            $isDisablePrepaidOrderFiler = Config::extensionsConfig('TrafficLoadBalancer.default_settings.disable_prepaid_orderfilter');

            if (
                    $currentLoadBalancer['scrapped'] && !$isDisablePrepaidOrderFiler && Session::has('steps.meta.isPrepaidFlow') ||
                    $currentLoadBalancer['scrapped'] && !Session::has('steps.meta.isPrepaidFlow')
            )
            {

                $data['scrappedCount'] = $this->rule['scrappedCount'] + 1;
                $data['scrapped'] = $this->rule['scrapped'] + 1;

                $modifiedData[$this->reqCardtype]['details']['scrappedCount'] = (int) $modifiedData[$this->reqCardtype]['details']['scrappedCount'] + 1;

                $modifiedData[$this->reqCardtype]['details']['scrapped'] = (int) $modifiedData[$this->reqCardtype]['details']['scrapped'] + 1;
            }

            if ($data['hitsCount'] === 0)
            {
                $data['scrappedCount'] = 0;
            }

            if ($modifiedData[$this->reqCardtype]['details']['hitsCount'] === 0)
            {
                $modifiedData[$this->reqCardtype]['details']['scrappedCount'] = 0;
            }
            $data['card_details'] = json_encode($modifiedData);
            CrmPayload::set('card_details' , $data);
            CrmPayload::set('ruleId' , $currentLoadBalancer['ruleId']);

            $currentLoadBalancer['cardScrapCommmited'] = true;
            $currentLoadBalancer['committed'] = true;
            
            Session::set(
                    sprintf(
                            'extensions.trafficLoadBalancer.%d', $this->scrapperStepId
                    ), $currentLoadBalancer
            );
        }
        catch (Exception $ex)
        {
            
        }
    }
    
    public function postRemoteData()
    {
        if (
            Request::attributes()->get('action') == 'prospect'
        ) {
            return;
        }
        
        if (!empty($_COOKIE['skipCount'])) {
            return;
        }
        
        $isSplitOrder  = CrmPayload::get('meta.isSplitOrder');
        $currentStepId = Session::get('steps.current.id');
        
        if (
            CrmResponse::has('orderId') !== true || 
            $isSplitOrder || 
            $currentStepId > 2
            ) {
            return;
        }
        
        $settings = Settings::postRemoteData();

    }
    
    public function isOldFrameworkVersion()
    {
        $currentVersion = \Application\Registry::system('systemConstants.version');
        $changedVersion = 6;

        $parsedCurrentVersion = explode('.', $currentVersion);
        if($changedVersion > $parsedCurrentVersion[1])
        {
            return true;
        }
        return false;
    }

}
