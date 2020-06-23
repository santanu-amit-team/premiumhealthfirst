<?php

namespace Extension\LimelightUtilPack;

use Application\Config;
use Application\CrmPayload;
use Application\CrmResponse;
use Application\Http;
use Application\Model\Campaign;
use Application\Model\Configuration;
use Application\Request;
use Application\Session;
use Exception;

class TransactionSelect
{

    public function __construct()
    {
        $this->isEnablePixelFire         = $this->config['enable_pixel_fire'];
    }

    public function updatePixelfire()
    {
        $reorderResponse = CrmResponse::all();
        $isTransactionSelectDecline = Session::get('failed_screening_decline');
        if ($reorderResponse['success'] && !$this->isEnablePixelFire && $isTransactionSelectDecline) {
            Session::set('steps.meta.skipPixelFire', true);
        }

    }

}
