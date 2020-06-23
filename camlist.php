<?php
$str = file_get_contents('storage/admin/campaigns.data.json');

$json = json_decode($str, true);

//echo '<pre>' . print_r($json, true) . '</pre>';

// foreach ($json as $key => $value) {
// 	$value['id'] = $key + 1;
// 	$raw[] = $value;
// }
// // $rawjson = json_encode($raw);
// // echo '<pre>' . print_r($rawjson, true) . '</pre>';
?>

<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<title>Campaigns List</title>
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" crossorigin="anonymous">
</head>

<body>
	<table class="table table-bordered table-striped w-75 my-3" align="center">
		<tr>
			<th width="5%">Id</th>
			<th>Label</th>
			<th width="11%">Campaign Id</th>
			<th width="11%">Product Id</th>
			<th width="11%">Product Price</th>
			<th width="11%">Shipping Id</th>
			<th width="11%">Shipping Price</th>
			<th width="5%">Type</th>
			<th width="11%">Linked Ids</th>
		</tr>
		<?php
		foreach ($json as $key => $value) {
			$productObj = json_decode($value['product_array'], true)[0];
			?>
			<tr>
				<td><?= $value['id'] ?></td>
				<td><?= $value['campaign_label'] ?></td>
				<td><?= $value['campaign_id'] ?></td>
				<td><?= $productObj['product_id'] ?></td>
				<td><?= $productObj['product_price'] ?></td>
				<td><?= $value['shipping_id'] ?></td>
				<td><?= $value['shipping_price'] ?></td>
				<td><?= $value['campaign_type'] ?></td>
				<td>PP[<?= $value['prepaid_campaign_id'] ?>], OF [<?= $value['scrap_campaign_id'] ?>]</td>
			</tr>
		<?php
		}
		?>
	</table>
</body>

</html>