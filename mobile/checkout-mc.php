<?php

require_once (dirname(__DIR__) . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'app.php');

App::run(array(
	'config_id' => 3,
	'step' => 1,
	'tpl' => 'checkout-mc.tpl',
	'go_to' => 'upsell1-mc.php',
	'version' => 'mobile',
	'tpl_vars' => array(),
	'pageType' => 'checkoutPage',
));
