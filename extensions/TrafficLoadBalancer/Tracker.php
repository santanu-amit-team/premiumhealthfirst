<?php

namespace Extension\TrafficLoadBalancer;

use Application\Config;
use Application\Http;
use Application\Registry;
use Database\Connectors\ConnectionFactory;
use Lazer\Classes\Database;
use Application\Request;

class Tracker
{
    const API_ENDPOINT = 'https://apps.almost20.com/api';

    public function __construct()
    {
        $this->offerId      = Config::settings('push_track_id');
        $this->tableName    = 'scrapper';
        $this->dbConnection = $this->getDatabaseConnection();
    }

    public function pushTrackingData()
    {
        echo '<pre>';

        $this->getDatabaseConnection();

        $postData = array(
            'initial' => $this->getScrapStepEntries(1),
            'upsell'  => $this->getScrapStepEntries(2),
        );

        print_r($postData);

        $gateWaySwitcherId = Config::settings('gateway_switcher_id');
        
        if(empty($gateWaySwitcherId))
        {
            return;
        }

        $apiEndpoint = rtrim(Registry::system('systemConstants.201CLICKS_URL'), '/api');

        $url = sprintf(
                '%s/scrapper-stats/%s/', $apiEndpoint, $gateWaySwitcherId
        );
        
        $response = Http::post($url, http_build_query($postData));
        print_r($response);        
        
        if (!empty($response['curlError'])) {            
                    return;
        }

        $this->dbConnection->table($this->tableName)
                ->update(array(
                'hits'     => 0,
                    'scrapped' => 0,
        ));

        echo '</pre>';
    }

    private function getScrapStepEntries($scrapStep = 1)
    {

        $entries = $this->dbConnection
                ->table($this->tableName)
                ->where('scrapStep', $scrapStep)
                ->where('hits', '>', 0)
                ->get();

        $data = array(
            'unique_id' => $this->offerId,
            'clicks'    => 0,
            'scrap'     => 0,
        );

        foreach ($entries as $entry) {

            if (
                    empty($entry['afId']) && empty($entry['affId']) &&
                    empty($entry['sId']) && empty($entry['c1']) &&
                    empty($entry['c2']) && empty($entry['c3']) &&
                    empty($entry['c4']) && empty($entry['c5']) &&
                    empty($entry['aId']) && empty($entry['opt']) &&
                    empty($entry['clickId'])
            ) {
                echo "All empty";
                $data['clicks'] = $entry['hits'];
                $data['scrap']  = $entry['scrapped'];
            }

            if (empty($entry['afId']) && !empty($entry['affId'])) {
                $entry['afId'] = $entry['affId'];
            } elseif (!empty($entry['aId'])) {
                $entry['afId'] = $entry['aId'];
            }

            if (
                    (!empty($entry['afId']) || !empty($entry['affId'])) &&
                    empty($entry['sid']) && empty($entry['c1']) &&
                    empty($entry['c2']) && empty($entry['c3']) &&
                    empty($entry['c4']) && empty($entry['c5']) &&
                    empty($entry['aId']) && empty($entry['opt']) &&
                    empty($entry['clickId'])
            ) {
                $data['affiliate'][] = array(
                    'aff_unique_id' => $entry['afId'],
                    'clicks'        => $entry['hits'],
                    'scrap'         => $entry['scrapped'],
                );
            }

            foreach (array('sId', 'c1', 'c2', 'c3', 'c4', 'c5', 'opt', 'clickId') as $sub) {
                if (empty($entry[$sub]) && !empty($entry[strtoupper($sub)])) {
                    $entry[$sub] = $entry[strtoupper($sub)];
                }
                if (!empty($entry[$sub])) {
                    $data['sub_affiliate'][] = array(
                        'sub_unique_aff_id' => $entry[$sub],
                        'parent_id'         => @$entry['afId'] ? $entry['afId'] : 0,
                        'clicks'            => $entry['hits'],
                        'scrap'             => $entry['scrapped'],
                    );
                }
            }
        }

        return $data;

    }

    private function getDatabaseConnection()
    {
        $factory = new ConnectionFactory();
        return $factory->make(array(
            'driver'   => 'sqlite',
                    'database' => STORAGE_DIR . DS . 'trafficlb.sqlite',
        ));
    }

    public function getTrackingID()
    {
        $offerUrl = sprintf('%s/', rtrim(Request::getOfferUrl(), '/'));
        $gateWaySwitcherId = Config::settings('gateway_switcher_id');
        if(empty($gateWaySwitcherId))
        {
            echo '\n\n Instance ID is missing. Please check your settings \n\n';
            return;
        }
        $queryParams = array(
            'offer_url' => $offerUrl,
            'conf_scrap_count' => 0,
        );
        $queryString = http_build_query($queryParams);
        $apiEndpoint = rtrim(Registry::system('systemConstants.201CLICKS_URL'), '/api');
        $url = sprintf(
                '%s/scrapper/%s/?%s', $apiEndpoint, $gateWaySwitcherId, $queryString
        );
        $response = json_decode(Http::get($url), true);
        $data = json_decode($response['data'], true);
        $validatedUniqueID = $data['unique_id'];

        $row = Database::table('settings')->find(1);
        $rowRec = json_decode($row->scrapper);

        if (empty($validatedUniqueID))
        {
            echo '\n\n Remote data not found \n\n';
            return;
        }

        if (empty($rowRec->push_track_id))
        {
            $rowRec->push_track_id = $validatedUniqueID;
            $row->scrapper = json_encode($rowRec);
            $row->save();
            echo "\n\n Saved new data \n\n";
        }
        else
        {
            if (
                    !empty($rowRec->push_track_id) &&
                    $validatedUniqueID != $rowRec->push_track_id
            )
            {
                $rowRec->push_track_id = $validatedUniqueID;
                $row->scrapper = json_encode($rowRec);
                $row->save();
                echo "\n\n Saved new remote data \n\n";
            }
        }
    }

}
