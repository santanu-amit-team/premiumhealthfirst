<?php

namespace Application\Hook;

use Admin\Controller\SettingsController;
use Application\Helper\Security;
use Application\Config;
use Application\Request;
use Admin\Controller\ExtensionsController as Extensions;

class Crons
{

    public function disableDevelopmentMode()
    {
        $maxTimeLimit = 3 * 60 * 60; // In seconds
        $fileName     = STORAGE_DIR . DS . '.development_mode';

        if (!file_exists($fileName)) {
            touch($fileName);
            return;
        }

        $currentTime    = time();
        $fileModifiedAt = filemtime($fileName);

        if (($currentTime - $fileModifiedAt) > $maxTimeLimit) {
            $settiongController = new SettingsController();
            $settiongController->updateDevMode(1, 0);
        }

    }
    
    public function disableExtensionsIfLicenseKeyExpired(){
        $validLicense = Security::isValidLicenseKey(Config::settings('domain'), Config::settings('license_key'), Config::settings('unify_authentication_key'));
        if (!$validLicense) {
            // Disbaled the all extensions
            $extension = new Extensions('');
            $allExtension = $extension->installedExtensions();

            if($allExtension['success']) {
                $allExtension = $allExtension['data'];
                foreach ($allExtension as $eachExtension) {
                    
                    foreach($eachExtension as $key => $value) {
                        Request::form()->set($key, $value);
                        if(!strcmp($key, 'active'))
                            Request::form()->set($key, false);
                    }
                    $extension->edit($eachExtension['id'], true);
                }
                echo "All Extension has been deactivated due to license key expired.";
            }
            
        }        
    }
}