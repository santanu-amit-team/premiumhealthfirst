<?php
use Application\Helper\Provider; /* Fetch Order details on thank you page*/
use Application\Config; /* FETCH ADMIN PRODUCT DETAILS */
$orders = Provider::orderView(
	array(
		1 => $steps[1]['orderId'], // Order Id,
		2 => $steps[2]['orderId'], //Order Id
		)
	);

//print_r($orders);
?>