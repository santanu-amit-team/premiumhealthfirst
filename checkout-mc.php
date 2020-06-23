<?php
require_once ('library' . DIRECTORY_SEPARATOR . 'app.php');

App::run(array(
    'config_id' => 3,
    'step'      => 1,
    'tpl'       => 'checkout-mc.tpl',
    'go_to'     => 'upsell1-mc.php',
    'version'   => 'desktop',
    'tpl_vars'  => array(),
    'pageType'  => 'checkoutPage',
));
