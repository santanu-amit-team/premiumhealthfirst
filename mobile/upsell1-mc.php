<?php

require_once (dirname(__DIR__) . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'app.php');

App::run(array(
	'config_id' => 4,
	'step' => 2,
	'tpl' => 'upsell1-mc.tpl',
	'go_to' => 'thank-you.php',
	'tpl_vars' => array(),
	'version' => 'mobile',
	'pageType' => 'upsellPage1',
));
