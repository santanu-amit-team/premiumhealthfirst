<?php 
require_once ('library' . DIRECTORY_SEPARATOR . 'app.php');

use Application\Config; /* FETCH ADMIN PRODUCT DETAILS */
use Application\Session; /* FETCH CUSTOMER DETAILS */

$campaignDetails = Config::campaigns();
$settingDetails = Config::settings();
$configurationSettings = Config::configurations();

$get_data= array(
	'siteDetails'=>array(
		'corpAddress'=>$settingDetails['corporate_address'],
		'returnAddress'=>$settingDetails['return_address'],
		'phone'=>$settingDetails['customer_service_number'],
		'email'=>$settingDetails['customer_support_email'],
		'url'=>$settingDetails['domain'],
		'siteTitle'=>$configurationSettings[1]['site_title'],
	),
	'productName'=>array(
		'step1'=>'Active Level Nutrition Keto',
		'step2'=>'Active Level Nutrition Coffee',
		'step3'=>'Active Level Nutrition Probiotic'
	),
	'productPrice'=>array(
		'step1'=> json_decode($campaignDetails[1]['product_array']),
		'step1_master'=> json_decode($campaignDetails[7]['product_array']),
		'step2'=> json_decode($campaignDetails[4]['product_array']),
		'step2_master'=> json_decode($campaignDetails[10]['product_array']),
		'step3'=> '29.99',
	),
	'shippingPrice'=>array(
		'step1'=> $campaignDetails[1]['shipping_price'],
		'step1_master'=> $campaignDetails[7]['shipping_price'],
		'step2'=> $campaignDetails[4]['shipping_price'],
		'step2_master'=> $campaignDetails[10]['shipping_price'],
		'step3'=> $campaignDetails[17]['shipping_price'],
		),
);



$product_data = array(

	'1'=>array(
			'name'=>'Active Level Nutrition Keto',
			'desc'=>'',
			// 'img'=>'image-1.png',
			'img'=>'mag_cover.png'
		),
	'2'=>array(
			'name'=>'Active Level Nutrition Coffee',
			'desc'=>'',
			'img'=>'image-2.png',
		),
		'3'=>array(
				'name'=>'Active Level Nutrition Probiotic',
				'desc'=>'',
				'img'=>'image-3.png',
			),

);

?>