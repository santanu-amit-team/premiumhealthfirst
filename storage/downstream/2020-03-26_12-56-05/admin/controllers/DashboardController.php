<?php

namespace Admin\Controller;

use Admin\Library\ResetSettings;
use Application\Helper\Alert;
use Application\Helper\Provider;
use Application\Helper\Security;
use Application\Model\Limelight;
use Application\Registry;
use Application\Request;
use Exception;
use Application\Hook\Crons;
use Admin\Controller\SettingsController;
use Application\Config;
use Application\Session;

class DashboardController
{
    public function allAlerts()
    {
        return Alert::getData();
    }

    public function updateAlert()
    {
        $data = Request::form()->all();
        return Alert::updateData($data['id']);
    }

    public function quickLaunchers()
    {
        $extensionLaunchers           = Registry::extension('quick_launchers');
        $extensionLaunchersStructured = array();

        if (!empty($extensionLaunchers)) {
            foreach ($extensionLaunchers as $extension => $launchers) {
                foreach ($launchers as $launcher) {
                    $launcher['handler'] = Request::getOfferUrl() . AJAX_PATH . 'extensions/'
                    .
                    strtolower($extension)
                        .
                        '/'
                        .
                        $launcher['handler'];

                    $extensionLaunchersStructured[] = $launcher;
                }
            }
        }

        return $extensionLaunchersStructured;
    }

    public function resetSettings()
    {
        if (Request::form()->get('confirm') !== 'yes') {
            return;
        }
        $domain = Provider::removeSubDomain(
            trim(Request::getHttpHost(), '/')
        );

        try {
            ResetSettings::resetStorage();
            Security::registerDomain($domain);
        } catch (Exception $ex) {
            return array(
                'success'       => false,
                'data'          => array(),
                'error_message' => $ex->getMessage(),
            );
        }

        return array(
            'success' => true,
            'message' => 'Installation completed',
        );
    }

    public function registerDomain()
    {
        if (Request::form()->get('confirm') !== 'yes') {
            return;
        }
        $domain = Provider::removeSubDomain(
            trim(Request::getHttpHost(), '/')
        );
        Security::registerDomain($domain);
        return array(
            'success' => true,
            'message' => 'Notification disabled permanently.',
        );
    }

    public function getCronRunningStatus()
    {
        $isCronRunning  = true;
        $cronStatusFile = sprintf('%s%s.cron_running_status', STORAGE_DIR, DS);

        if (!file_exists($cronStatusFile)) {
            $isCronRunning = false;
        } elseif ((time() - filemtime($cronStatusFile)) > 30 * 60) {
            $isCronRunning = false;
        };

        $data = array(
            'identifier' => 'Cron Error',
            'text'       => "Cron job is not set, some of the important features of this CodeBase might not run properly",
            'type'       => 'error',
        );

        if (!$isCronRunning) {
            Alert::insertData($data);
        }else{
            Alert::removeData($data);
        }

        return array(
            'success'       => true,
            'isCronRunning' => $isCronRunning,
        );

    }

    public function checkDomainSwitch()
    {
        $domain = Provider::removeSubDomain(
            trim(Request::getHttpHost(), '/')
        );
        
        try{
            $settingsInstance = new SettingsController();
            $settingsData = $settingsInstance->all();
            if(!empty($settingsData['data'][0]['domain']) && $domain !== $settingsData['data'][0]['domain']){
                $settingsData['data'][0]['encryption_key'] = "";
                $settingsData['data'][0]['gateway_switcher_id'] = "";
                $settingsInstance->removeWrongInstance($settingsData['data'][0]);

            }
        }catch(Exception $ex){

        }

        $domainSwitched = Security::isDomainChanged($domain);

        $data = array(
            'identifier' => 'Installation Incomplete!',
            'text'       => "This CodeBase didn't go through an installation process, which is crucial in terms of performance/security",
            'type'       => 'error',
        );

        if ($domainSwitched) {
            Alert::insertData($data);
        } else {
            Alert::removeData($data);
        }

        return array(
            'success'  => true,
            'switched' => $domainSwitched,
        );
    }
    
    public function checkDevModeStatus()
    {
        $cron = new Crons();
        $cron->disableDevelopmentMode();

        return array(
            'success'  => true,
            'message' => 'Dev mode stauts verified.',
        );
    }
    
    public function updateTrackingID()
    {
        
        $maxTimeLimit = 12 * 60 * 60; // In seconds
        $fileName     = STORAGE_DIR . DS . '.remote_tracking';

        if (!file_exists($fileName)) {
            
            $res = $this->saveTrackingID();
            if($res)
             {
                 touch($fileName);
                 return array(
                    'success'  => true,
                    'message' => 'Tracking ID updated successfully.',
                 );
             }
             else{
                 return array(
                    'success'  => false,
                    'message' => 'Something went wrong.',
                 );
             }
        }

        $currentTime    = time();
        $fileModifiedAt = filemtime($fileName);
        
        if (($currentTime - $fileModifiedAt) > $maxTimeLimit) {
             $res = $this->saveTrackingID();
             
             if($res)
             {
                 touch($fileName);
                 return array(
                    'success'  => true,
                    'message' => 'Tracking ID updated successfully.',
                 );
             }
             else{
                 return array(
                    'success'  => false,
                    'message' => 'Something went wrong.',
                 );
             }
             
        }
        else{
            return array(
                'success'  => true,
                'message' => 'Already updated.',
             );
        }

    }
    
    private function saveTrackingID()
    {
        try
        {
            $settings = new SettingsController();
            $data = $settings->edit(1, true);
            return $data['success'];
        } catch (Exception $ex) {
            return false;
        }
    }

    public function feedback()
    {
        $subject = Request::get('subject');
        $description = Request::get('description');
        try
        {
            if(!strlen($subject) && !strlen($description))
                return array(
                    'success' => false,
                    'data' => null,
                    'error_message' => 'Subject or Description can not be blank.',
                );

            $offerUrl = sprintf('%s/', rtrim(Request::getOfferUrl(), '/'));
            $to_email = 'subhendu.mondal@codeclouds.in';
            $subject = 'Unify Feedback | ' . trim($subject);
            $message = '<html><body style="background: #f0f3f2; padding: 10px; border-radius: 5px;">';
            $message .= '<h1 style="color:#3e4444; font-size:14px;">Url: ' . $offerUrl . ' </h1>';
            $message .= '<p style="color:#3b3a30;font-size:12px;"> ' . trim($description) . ' </p>';
            $message .= '</body></html>';
            $headers  = "From: Unify Dashboard < subhendu.mondal@codeclouds.in >" . PHP_EOL ;
            $headers .= "Reply-To: subhendu.mondal@codeclouds.in \r\n";
            $headers .= "CC: soumyajit.maity@codeclouds.in\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";


            if(mail($to_email,$subject,$message,$headers))
                return array(
                    'success' => true,
                    'data' => Request::get('all'),
                    'success_message' => 'Thank you for your feedback.'
                );
            else 
                return array(
                    'success' => false,
                    'data' => null,
                    'error_message' => 'Something went wrong, please try again later.',
                );
        }
        catch (Exception $ex)
        {
            return array(
                'success' => false,
                'data' => '',
                'error_message' => $ex->getMessage(),
            );
        }
    }

    public function purge()
    {
        $check = $this->checkExtensions('JsMinifier');

        if($check['success'] && $check['extensionCouponsActive']) {

            $minifire = new \Extension\JsMinifier\Compiler;

            $result = $minifire->execute();
        }
        else {
            $result = array(
                'success' => false,
                'error_message' => 'Js Minifier Extension not found.',
            );
        }

        return $result;
    }

    public function checkExtensions($extentionName = '')
    {
       
        $extentionName = strlen($extentionName) ? $extentionName : Request::get('extention');
       
        $result = array(
            'success' => true,
            'extensionCouponsActive' => false,
        );
        $extensions = Config::extensions();

        foreach ($extensions as $extension)
        {
            if ($extension['extension_slug'] !== $extentionName)
            {
                continue;
            }
            if ($extension['active'] === true)
            {
                $result['extensionCouponsActive'] = true;
            }
            break;
        }


        return $result;
    }

    public function checkPermission($slug)
    {
        $urlpermission = new UrlPermissionController();

        return $urlpermission->isValid($slug);
    }
    
    public function getDocumentation()
    {
        try{
         $mdDocs = array(
             'dashboard'=>'dashboard',
             'campaigns'=>'campaigns',
             'campaign-manager'=>'campaigns',
             'configurations'=>'configurations',
             'configuration-manager'=>'configurations',
             'settings'=>'settings',
             'cms'=>'cms',
             'crms'=>'crm',
             'crms-manager'=>'crm',
             'users'=>'users',
             'user-manager'=>'users',
             'pixels'=>'pixels',
             'pixel-setup'=>'pixels',
             'auto-responder'=>'auto-responder',
             'autoresponder-manager'=>'auto-responder',
             'scheduler'=>'scheduler',
             'cron-manager'=>'scheduler',
             'affiliates'=>'affiliates',
             'affiliate-manager'=>'affiliates',
             'crons'=>'scheduler',
             'extensions'=>'extensions',
             'extension-catalogue'=>'extensions',
             'routing'=>'mid-routing',
             'routing-manager'=>'mid-routing',
        );
         $slug = Request::form()->get('data')['slug'];
        // echo $mdDocs[$slug];die;
        if(empty($mdDocs[$slug]))
          throw new Exception('Not found');
       
        
        
        $url = 'https://framework.unify.to/unify_help_doc/sections/'.
                $mdDocs[$slug].'.md';
        $docs = file_get_contents($url);
        $docs = $this->relatedMDChunk($slug,$docs);
        if(empty($docs))
            throw new Exception('Not found');
       
         return array(
                'success' => true,
                'data' =>  $docs,
            );
         
        }catch(Exception $ex){
            return array(
                'success' => false,
                'data' => '**404 Not found.**',
                'error_message' => $ex->getMessage(),
            );
        }
    }
    
    private function relatedMDChunk($slug,$param)
    {
        if(!in_array($slug, array('campaign-manager',
            'configuration-manager','crms-manager',
            'user-manager','pixel-setup',
            'autoresponder-manager','affiliate-manager','cron-manager', 'extension-catalogue','routing-manager')))
                return $param;
        
        $keywords = preg_split("/##\sAdd/i", $param);
        return '## Add'.end($keywords);
    }


    public function getFeedbackDetails() {

        $userType = Session::get('userType');

        if (!strcmp($userType, 'developer')) {
            $userTypeCaption = 'developer';
            $userIdentification = Session::get('googleEmail');
        }
        else {
            $userTypeCaption = UsersController::userTypeToString($userType);
            $userIdentification = Session::get('username');
        }

        try {
            $ip = Request::getClientIP();
            $response = json_decode(file_get_contents("http://ipinfo.io/{$ip}/json"));
            $details = array(
                $response->city, $response->region, $response->country
            );
            $details = array_filter($details);
        }
        catch(Exception $e) {
            //$details = null;
        }

        $response = array(
            'UserType' => $userTypeCaption,
            'Identification' => $userIdentification,
            'OfferUrl' => Request::getOfferUrl(),
            'IP' => Request::getClientIP(),
            'Location' => !empty($details) ? implode(',', $details) : 'Not Found'
        );

        return array(
            'success' => true,
            'message' => $response
        );
    }

    public function getFrameworkVersion() {

        $currentVersion = Registry::system('systemConstants.version');
        $availableVersion = file_get_contents("https://framework.unify.to/extension-lists/framework.version.json");
        $availableVersion = json_decode($availableVersion, TRUE);
        return array(
            'success' => true,
            'data' => array(
                'currentVersion' => $currentVersion,
                'availableVersion' => $availableVersion['version'],
                'isNewVersionAvailable' => version_compare($currentVersion, $availableVersion['version']) < 0
            )
        );
    }
}
