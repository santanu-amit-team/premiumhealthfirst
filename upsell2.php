<?php
require (dirname(__FILE__) . '/library/app.php');

App::run(array(
    'config_id' => 5,
    'step'      => 3,
    'tpl'       => 'upsell2.tpl',
    'go_to'     => 'thank-you.php',
    'tpl_vars'  => array(),
    'version'   => 'desktop',
    'pageType'  => 'upsellPage2',
));
