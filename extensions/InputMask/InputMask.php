<?php

namespace Extension\InputMask;

use Application\Config;

class InputMask
{
    public function inputMaskLoadData()
    {

        $config = Config::extensionsConfig('InputMask');

        if (empty($config['credit_card_place_holder_active'])) {
            $config['credit_card_place_holder_active'] = false;
        }

        echo sprintf(
            "\n<script>var input_mask_data = %s;</script>\n",
            json_encode($config)
        );

    }
}
