<!DOCTYPE html>

<html class="no-js" ng-app="stepOne">

    <!--<![endif]-->

    <head>

    <?php include 'general/__header__.tpl'; ?>

<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no" />

<link rel="shortcut icon" href="<?= $path['images'] ?>/logo.png?5221549" type="image/x-icon"> 





<link rel="stylesheet" href="<?= $path['css'] ?>/modal.css">



    <link rel="stylesheet" href="<?= $path['css'] ?>/default.css?12345">






        <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,700" rel="stylesheet">



        <link rel="stylesheet" href="<?= $path['css'] ?>/global.css?123">

        <link rel="stylesheet" href="<?= $path['css'] ?>/upsell.css?12345">
    <link rel="icon" type="image/png" href="<?= $path['images']; ?>/favicon.png">
        <style>

            .progress .part div:last-child, .progress .bar { background: #a42a5b; }



            .logo { background-image: url('<?= $path['images'] ?>/logo.png?5221549'); }

            .bottle { background-image: url('<?= $path['images'] ?>/upsell_image.png?5221549'); }

            .bottle-info p.line-4 strong { color: #ffa425; }

            button.submit { background: #ffa425;} 

            span.month {    

                font-size: 15px;

                font-weight: 500;

            }

            button.submit {

                background: #f2491a;

            }

            

            .decline {

                text-align: center;

                margin-top: 10px;

                width: 100%;

                /*position: absolute;*/

                left: 0;

            }

            

           .decline a  {

                color: #828080;

                font-size: 10px;

            }
            footer {
    margin-top: 0px;
}
.footer {
 
    margin: 0px auto 20px auto;
   
}
            

            @media only screen and (max-width: 1000px) {

                button.submit {

                    font-size: 25px;

                    padding: 11px 22px;

                }

                

                .decline {

                    margin-top: 10px;

                }

            }

            .page-checkout, .page-upsell1
{
    background-position: top left;
    }

        </style>

    </head>

    

    <body class='device-desktop slug-special-o4 page-upsell1 flow-keto-v4'>

        <?php perform_body_tag_open_actions();

            include '../fetch-data.php'; ?>

        <div style='position: absolute; overflow: hidden; width: 1px; height: 1px;'>

    </div>        <header>

            <div class="container">

                <div class="logo"></div>

                <div class="seals">

                    <img src="<?= $path['images'] ?>/checkout-seals.png" alt="">

                </div>

            </div>

        </header>

        <main>

            <div class="container">                

                
<form name="is-upsell" class="is-upsell" accept-charset="utf-8" enctype="application/x-www-form-urlencoded;charset=utf-8" style="display: none;">

                   <input type="hidden" name="limelight_charset" id="limelight_charset" value="utf-8" />

                    <input type="hidden" name="campaigns[4][id]" value="11">

                </form>

                <div id="upsell-box">

                    <div class="progress clearfix">

                        <div class='bar'></div>

                        <div class="part">

                            <div>Confirm Order</div>

                            <div>1</div>

                        </div>

                        <div class="part">

                            <div>Special Offer</div>

                            <div>2</div>

                        </div>

                        <div class="part grey">

                            <div>Order Summary</div>

                            <div>3</div>

                        </div>

                    </div>

                    <div class="clear"></div>

                    

                    <div class="center top">

                                                <h2>WAIT! YOUR ORDER IS NOT COMPLETE</h2>

                        <p>Customers that purchased <strong><?= $get_data['productName']['step1'] ?></strong> also purchased <strong><?= $get_data['productName']['step2'] ?></strong></p>

                    </div>

                    <div class="coupon-container clearfix" onclick="$('[name=is-upsell]').submit();">

                        <img src="<?= $path['images'] ?>/scissors.png" class='scissors'>

                        <div class="bottle">&nbsp;</div>



                        <div class="bottle-info">



                            <p class="line-1">Limited Offer - Only <span style="color: red;text-decoration: underline;">19</span> Remaining</p>



                            <p class="line-2">MAXIMIZE YOUR RESULTS</p>



                            <p class="line-3">with<br><strong><?= $get_data['productName']['step2'] ?></strong></p>

                            <p class="line-4">Add a <strong>SPECIAL</strong> bottle<br>just pay</p>

                            <p class="line-5"><span class='upsell-msrp'></span>  $<?= $get_data['productPrice']['step2_master'][0]->product_price; ?> </p>

                        </div>

                    </div>

                    <div class="center">

                        <button onclick="$('[name=is-upsell]').submit();" type="button" class="submit" style="width: auto; display: inline-block;">COMPLETE CHECKOUT</button>

                    </div>

                    <p class="center">

                        <br>

                        We Care About Your Privacy<br>

                        

                        <img src="<?= $path['images'] ?>/secure.png" alt="">

                        <br>

                        <img src="<?= $path['images'] ?>/benefits.png" alt="">

                        <br>

                        <img src="<?= $path['images'] ?>/footer-logos.png" alt="">

                        

                    </p>
                    <p>*Due to limited inventory levels on any given day, we must limit trial sales to 250 maximum per day. Representations regarding the efficacy and safety of <?= $get_data['productName']['step1'] ?> have not been evaluated by the Food and Drug Administration. The FDA only evaluates foods and drugs, not supplements like these products. These products are not intended to diagnose, prevent, treat, or cure any disease.<br><br> This product has not been evaluated by the FDA. This product is not intended to diagnose, treat, cure, or prevent any disease. This product is intended to be used in conjunction with a healthy diet and regular exercise. Consult your physician before starting any diet, exercise program, and taking any diet pill to avoid any health issues.</p>

                    <p class="decline">

                        <a href="<?= get_no_thank_you_link() ?>">No Thanks, I decline this offer</a>

                    </p>

                        

                </div>

                

                <div style='position: absolute; overflow: hidden; width: 1px; height: 1px;'>

    </div>





<footer>

  <div class="footer">

        <p class="copyright">

            &copy; <?= date('Y') ;?> <?= $get_data['productName']['step1'] ?> &mdash; All rights reserved.

        </p>

        <p class="customerservice">

            Customer Service: 866-529-5649 </p>

        <p class="footerlinks">

          <?php include 'footer.tpl';  ?>

        </p>

    </div>

</footer>




                       

            </div>

            <p id="loading-indicator" style="display:none;"></p>

            



        </main>

        <?php

        include 'general/__scripts__.tpl';

        include 'general/__analytics__.tpl';

        perform_body_tag_close_actions();

        ?>



       
    </body>

</html>

