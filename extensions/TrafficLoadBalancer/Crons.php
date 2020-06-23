<?php

namespace Extension\TrafficLoadBalancer;

class Crons
{

    public function cleanupLbCaches()
    {
        $cacheLifeTime = 10 * 60; // in seconds;

        $cacheFolder = STORAGE_DIR . DS . '.lbcache';
        if (!file_exists($cacheFolder) || !is_writable($cacheFolder)) {
            echo "\nLoad Balancer Cache Folder not exists / writable.\n";
            return;
        }
        $cacheFiles = scandir($cacheFolder, SCANDIR_SORT_NONE);
        if ($cacheFiles === false || count($cacheFiles) === 2) {
            echo "\nLoad Balancer Cache Folder is empty.\n\n";
            return;
        }

        echo "\nCleaning Load Balancer Caches...\n\n";

        $currentTimestamp = time();

        foreach ($cacheFiles as $cacheFile) {
            $filePath = $cacheFolder . DS . $cacheFile;
            if (!is_file($filePath)) {
                continue;
            }
            if (!is_writable($filePath)) {
                echo $cacheFile . "\t---\tNot Writable.\n";
                continue;
            }
            $cacheFileLifeTime = $currentTimestamp - filemtime($filePath);
            if ($cacheFileLifeTime > $cacheLifeTime) {
                unlink($filePath);
                echo $cacheFile . "\t---\tRemoved.\n";
            }
        }

        echo "\nLoad Balancer Caches Cleanup Completed.\n\n";

    }

}
