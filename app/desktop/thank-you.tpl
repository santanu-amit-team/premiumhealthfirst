<?php include 'fetch-data.php'; ?>
<?php include 'crm-response.php'; ?>

<!DOCTYPE html>

<html lang="en">

    <head>

        <?php include 'general/__header__.tpl'; ?>

<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no" />

<link rel="shortcut icon" href="<?= $path['images'] ?>/logo.png?5221549" type="image/x-icon"> 





<link rel="stylesheet" href="<?= $path['css'] ?>/modal.css">



    <link rel="stylesheet" href="<?= $path['css'] ?>/default.css?12345">

    <link rel="icon" type="image/png" href="<?= $path['images']; ?>/favicon.png">


<script type='text/javascript'>

    function getDate(days) 

    {

        var dayNames = new Array("Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday");

        var monthNames = new Array("January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December");

        var now = new Date();

        now.setDate(now.getDate() + days);

        var nowString = monthNames[now.getMonth()] + " " + now.getDate() + ", " + now.getFullYear();

        document.write(nowString);

    }

</script>



        

        <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,700" rel="stylesheet">



        <link rel="stylesheet" href="<?= $path['css'] ?>/global.css">

        <link rel="stylesheet" href="<?= $path['css'] ?>/receipt.css">

    </head>



    <body class='device-desktop slug-special-o4 page-thank-you flow-keto-v4'>

        <?php perform_body_tag_open_actions(); ?>

        <div style='position: absolute; overflow: hidden; width: 1px; height: 1px;'>

    </div>        <main>

            <div class='container'>

                <h1>Thank You For Your Order!</h1>

                <h2>Your orders are currently being processed and will ship promptly.</h2>

                <h3>Orders Placed: <script>getDate(0)</script></h3>
                <h3>Order No.:  <?= $steps['1']['orderId'] ?></h3>

                <div id='address'>

                    <div id="shipping">

                        <p><strong>Shipping Info:</strong></p>

                        <p class="info"><?= $customer['firstName'] ?> <?= $customer['lastName'] ?></p>

                        <p class="info"><?= $customer['shippingAddress1'] ?></p>

                        <p class="info"><?= $customer['shippingCity'] ?>, <?= $customer['shippingState'] ?> <?= $customer['shippingZip'] ?></p>

                        <p class='info'><?= $customer['shippingCountry'] ?><br/></p>

                    </div>

                    <div id="billing">

                        <p><strong>Billing Info:</strong></p>

                        <?php if($customer['billingFirstName']!=''){ ?>

                            <p class="info"><?= $customer['firstName'] ?> <?= $customer['lastName'] ?></p>

                            <p class="info"><?= $customer['shippingAddress1'] ?></p>

                            <p class="info"><?= $customer['shippingCity'] ?>, <?= $customer['shippingState'] ?> <?= $customer['shippingZip'] ?></p>

                            <p class='info'><?= $customer['shippingCountry'] ?><br/></p>

                        <?php } else { ?>

                            <p class="info"><?= $customer['firstName'] ?> <?= $customer['lastName'] ?></p>

                            <p class="info"><?= $customer['shippingAddress1'] ?></p>

                            <p class="info"><?= $customer['shippingCity'] ?>, <?= $customer['shippingState'] ?> <?= $customer['shippingZip'] ?></p>

                            <p class='info'><?= $customer['shippingCountry'] ?><br/></p>

                        <?php } ?>

                    </div>

                    <div class='clear'></div>

                </div>





            <div id="receipt" class='clearfix'>



                <?php 
                $i = 0;
                $productPrice = 0;
                $shippingPrice = 0;
                $total = 0;
                foreach ($steps as $key => $step) {

                    $i++;

                    if(!empty($step['orderId'])){
                        $productPrice += $step['products'][0]['productPrice'];
                        $shippingPrice += $step['products'][0]['shippingPrice'];

                        $total += $step['products'][0]['productPrice'] + $step['products'][0]['shippingPrice'];
                        
                        $product_details = $product_data[$key];

                ?>

                <div class="bottleanddescription step-1">

                    <div id="rbottles" class="ib bottlewrap">

                        <img class="imagebox" src="<?= $path['images'] ?>/<?= $product_details['img']?>" id="bottle_1089" alt="">

                    </div>

                    <div class="descriptioncontain ib">

                        <?= $product_details['name']; ?>

                        <p class="description">
                           <!-- <?= $product_details['desc'] ?> -->
                        </p>

                         <p class="details">

                          Price:   $<?= $step['products'][0]['productPrice'] ?>

                        </p>                       

                        <p class="details">

                        Shipping:     $<?=$step['products'][0]['shippingPrice']  ?>
                        </p>
                    
                    </div>

                </div>

                <?php } } ?>





                

                <div class="btop"></div>

                <div id="subtotal">

                    <table>

                        <tr>

                            <td>

                                <p class="description totals">Item(s) Subtotal:</td>

                            <td>

                                <p class="rprice totals">$<?= number_format($productPrice,2); ?></td>

                        </tr>

                        <tr>

                            <td>

                                <p class="description totals">Shipping &amp; Handling&nbsp;&nbsp;</p>

                            </td>

                            <td>

                                <p class="rprice totals">$<?= number_format(($shippingPrice),2); ?> </p>

                            </td>

                        </tr>

                        <tr class="bold">

                            <td>

                                <p class="description totals">Total</p>

                            </td>

                            <td>

                                <p class="rprice totals">$<?= number_format(($total),2); ?></p>

                            </td>

                        </tr>

                    </table>

                </div>

            </div>

            <div class='clear'></div>

        </main>

            

        <div style='position: absolute; overflow: hidden; width: 1px; height: 1px;'>

    </div>





<footer>

    *Due to limited inventory levels on any given day, we must limit trial sales to 250 maximum per day. Representations regarding the efficacy and safety of <?= $get_data['siteDetails']['siteTitle'] ?> have not been evaluated by the Food and Drug Administration. The FDA only evaluates foods and drugs, not supplements like these products. These products are not intended to diagnose, prevent, treat, or cure any disease.<br><br> This product has not been evaluated by the FDA. This product is not intended to diagnose, treat, cure, or prevent any disease. This product is intended to be used in conjunction with a healthy diet and regular exercise. Consult your physician before starting any diet, exercise program, and taking any diet pill to avoid any health issues.

    <br><br>

    <div class="footer">

        <p class="copyright">

            &copy; <?= date('Y'); ?> <?= $get_data['siteDetails']['siteTitle'] ?> &mdash; All rights reserved.

        </p>

        <p class="customerservice">

            Customer Service: <a href="tel:866-529-5649"><?= $get_data['siteDetails']['phone'] ?></a> </p>

        <p class="footerlinks">

            <?php include 'footer.tpl';  ?>

        </p>

    </div>

</footer>




    <?php include 'general/__scripts__.tpl'; ?>

    <?php include 'general/__analytics__.tpl'; ?>

    <?php perform_body_tag_close_actions(); ?>

<script type="text/javascript">
    history.pushState({}, null, appLocation.pathname + appLocation.search);
</script>

    </body>

</html>

