<?php include '../fetch-data.php';  ?>

<html lang="en" class="gr__ultrafastketoboost_com">

<head>

    <?php include 'general/__header__.tpl'; ?>
    
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  
    <!-- Bootstrap core CSS -->
    <link href="<?= $path['css']; ?>/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css?family=Open+Sans:400,600,700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css?family=Oswald:300,400,500,600,700&display=swap" rel="stylesheet">
    <!-- Custom styles for this template -->
    <link rel="stylesheet" href="<?= $path['css']; ?>/all.css">
    <link href="<?= $path['css']; ?>/style.css" rel="stylesheet">
    <link href="<?= $path['css']; ?>/checkout.css" rel="stylesheet">
    <link href="<?= $path['css']; ?>/animate.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?= $path['images']; ?>/favicon.png">

    <style type="text/css">
        span.im-caret {
            -webkit-animation: 1s blink step-end infinite;
            animation: 1s blink step-end infinite;
        }
        
        @keyframes blink {
            from,
            to {
                border-right-color: black;
            }
            50% {
                border-right-color: transparent;
            }
        }
        
        @-webkit-keyframes blink {
            from,
            to {
                border-right-color: black;
            }
            50% {
                border-right-color: transparent;
            }
        }
        
        span.im-static {
            color: grey;
        }
        
        div.im-colormask {
            display: inline-block;
            border-style: inset;
            border-width: 2px;
            -webkit-appearance: textfield;
            -moz-appearance: textfield;
            appearance: textfield;
        }
        
        div.im-colormask > input {
            position: absolute;
            display: inline-block;
            background-color: transparent;
            color: transparent;
            -webkit-appearance: caret;
            -moz-appearance: caret;
            appearance: caret;
            border-style: none;
            left: 0;
            /*calculated*/
        }
        
        div.im-colormask > input:focus {
            outline: none;
        }
        
        div.im-colormask > input::-moz-selection {
            background: none;
        }
        
        div.im-colormask > input::selection {
            background: none;
        }
        
        div.im-colormask > input::-moz-selection {
            background: none;
        }
        
        div.im-colormask > div {
            color: black;
            display: inline-block;
            width: 100px;
            /*calculated*/
        }
    </style>
    <template>
        <style>
            :host {
                display: inline-block;
                width: 1em;
                height: 1em;
                contain: strict;
                -webkit-box-sizing: content-box!important;
                box-sizing: content-box!important
            }
            
            .icon-inner,
            svg {
                display: block;
                fill: currentColor;
                stroke: currentColor;
                height: 100%;
                width: 100%
            }
            
            :host(.flip-rtl) .icon-inner {
                -webkit-transform: scaleX(-1);
                transform: scaleX(-1)
            }
            
            :host(.icon-small) {
                font-size: 18px!important
            }
            
            :host(.icon-large) {
                font-size: 32px!important
            }
            
            :host(.ion-color) {
                color: var(--ion-color-base)!important
            }
            
            :host(.ion-color-primary) {
                --ion-color-base: var(--ion-color-primary, #3880ff)
            }
            
            :host(.ion-color-secondary) {
                --ion-color-base: var(--ion-color-secondary, #0cd1e8)
            }
            
            :host(.ion-color-tertiary) {
                --ion-color-base: var(--ion-color-tertiary, #f4a942)
            }
            
            :host(.ion-color-success) {
                --ion-color-base: var(--ion-color-success, #10dc60)
            }
            
            :host(.ion-color-warning) {
                --ion-color-base: var(--ion-color-warning, #ffce00)
            }
            
            :host(.ion-color-danger) {
                --ion-color-base: var(--ion-color-danger, #f14141)
            }
            
            :host(.ion-color-light) {
                --ion-color-base: var(--ion-color-light, #f4f5f8)
            }
            
            :host(.ion-color-medium) {
                --ion-color-base: var(--ion-color-medium, #989aa2)
            }
            
            :host(.ion-color-dark) {
                --ion-color-base: var(--ion-color-dark, #222428)
            }
        </style>
    </template>
    <script type="text/javascript">
         function getDate(days) {
         var dayNames = new Array("Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday");
         var monthNames = new Array("January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December");
         var now = new Date();
         now.setDate(now.getDate() + days);
         var nowString = dayNames[now.getDay()] + ", " + monthNames[now.getMonth()] + " " + now.getDate() + ", " + now.getFullYear();
         document.write(nowString);
       }
      </script>

</head>

<body data-gr-c-s-loaded="true">
<?php perform_body_tag_open_actions(); ?>
    <section class="checkout-header">
        <div class="container">
            <div class="d-flex justify-content-between bd-highlight mb-3">
                <div class="p-2 bd-highlight"><img src="<?= $path['images']; ?>/logo-white.png" style="width: 120px;" class="img-fluid">
                </div>
                <div class="p-2 bd-highlight">
                    <img src="<?= $path['images']; ?>/steps.png" style="width:300px;" class="img-fluid mt-2">

                </div>
                <div class="p-2 bd-highlight">
                    <img src="<?= $path['images']; ?>/checkout.png" style="width: 130px;" class="img-fluid mt-2">
                </div>
            </div>
        </div>
    </section>
    <section class="section-checkout">

        <div class="container checkout-form">
         
           <form method="post" action="ajax.php?method=new_order_prospect" name="checkout_form" accept-charset="utf-8" enctype="application/x-www-form-urlencoded;charset=utf-8">
            <input type="hidden" name="limelight_charset" id="limelight_charset" value="utf-8" />
            <input type="radio" style="display: none;" name="billingSameAsShipping" value="yes" checked="checked" />
            <input type="radio" style="display: none;" name="billingSameAsShipping" value="no" />
            <input type="hidden" name="campaigns[3][id]" value="8">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="product-container">
                            <h2>Our Special Offer Just For You!</h2>
                            <h3>A limited time promo code has been applied to your cart.</h3>
                            <p>Current Availability:</p>
                            <div class="progress progress-bar-custom">
                                <div class="progress-bar  progress-bar-striped bg-warning progress-bar-animated" role="progressbar" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100" style="width: 20%"></div>
                            </div>
                            <div class="stock-tag"><strong>&nbsp;&nbsp;Low Stock!</strong></div>
                            <p class="disabled">Special Discount Expires In <span id="clock"><strong>This is about to expire! HURRY!</strong></span></p>
                            <div class="product">

                    <div class="left-ch">

                        <div class="bottle"><img src="<?= $path['images']; ?>/bottle.png"></div>

                    </div>

                    <div class="right-ch">

                        <h3><?= $get_data['productName']['step1']; ?></h3>

                        <div class="supply">30 Day Supply /14 days Day Trial</div>

                        <ul>

                            <li>Price:<span>$<?= $get_data['productPrice']['step1_master'][0]->product_price ?></span></li>

                           
                         
                            <li>Shipping &amp; Handling:<span>$<?= $get_data['shippingPrice']['step1_master']; ?></span></li>

                            <!-- <li>Discount:<span class='red'>-$5.00</span></li> -->

                            <li>Total:<span><strong>$<?= $get_data['productPrice']['step1_master'][0]->product_price ?></strong></span></li>

                        </ul>

                    </div>

                    <div class="clearfix"></div>

                </div>

                <div class="big-arrow">

                    CONFIRM YOUR EXCLUSIVE TRIAL NOW!

                    <div>LIMITED QUANTITIES AVAILABLE</div>

                    

                </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="scroll2" style="visibility: hidden;">&nbsp;</div>
                        <div class="row">
                            <div class="lp-form-title2 form-title2 text-center">
                                Final Step
                                <p>Payment information</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="myform2">
                                <div class="row">
                                    <div class="col-lg-4">
                                        <strong>Ship To:</strong>
                                    </div>
                                    <div class="col-lg-8 text-left">
                                        <?= $customer['firstName']; ?> <?= $customer['lastName']; ?>
                                        <br> <?= $customer['shippingAddress1']; ?>
                                        <br> <?= $customer['shippingCity']; ?>, <?= $customer['shippingState']; ?> <?= $customer['shippingZip']; ?>
                                        <br>
                                    </div>
                                </div>
                                <hr>
                                <p class="text-center">
                                    Enjoy <strong>FREE SHIPPING</strong> with your order
                                    <br> Your order will arrive by <strong><script type="text/javascript">getDate(5)</script></strong>
                                </p>
                                <hr>
                                <center><img src="<?= $path['images']; ?>/accepted_c22e0.png"></center>
                                <div class="signup-form-inputs">
                <select name="creditCardType" style="display: none;" class="form-control" data-deselect="false" data-error-message="Please select valid card type!">
                    <option value="">Card Type</option>
                    <?php foreach($config['allowed_card_types'] as $key=>$value): ?>
                    <option value="<?= $key ?>"><?= ucfirst($value) ?></option>
                    <?php endforeach ?>
                </select>
                                    <label class="input-title">Card #</label>
                                    <div class="input-group mb-3">
                                           <input type="tel" name="creditCardNumber" class="required form-control" maxlength="16" data-error-message="Please enter a valid credit card number!" placeholder="Credit Card Number"/>

                                    </div>
                                    <div class="form-row">
                                        <div class="col-md-7">
                                            <label class="input-title">Exp Month</label>
                                            <select name="expmonth" class="required form-control" data-error-message="Please select a valid expiry month!">

                    <?php get_months(); ?>
                </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="input-title">Exp Year</label>
                                            <select name="expyear" class="required form-control" data-error-message="Please select a valid expiry year!">

                    <?php get_years(); ?>
                </select>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="input-title">CVC Code</label>
                                            <input type="tel" name="CVV" class="required form-control" data-validate="cvv" maxlength="3" data-error-message="Please enter a valid CVV code!" placeholder="CVV"/>
                                            <div class="invalid-feedback">Please provide a valid CVC.</div>
                                            <span style="font-size:9px">Code On Back Of Card<br> <a href="javascript:void(0)" onclick="openNewWindow('../cvv.php','modal')">(what's CVC?)</a></span>
                                        </div>
                                    </div>
                                    <br>
                                   
                                    <button type="submit" class="btn btn-checkout btn-custom btn-lg btn-block animated infinite pulse" name="complete" formnovalidate="">Complete Order
                                        <div>Safe &amp; Secure Transaction</div>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </section>

    <footer class="footer-bs">
        <div class="container">
            <div class="row">
                <div class="col-md-6 footer-brand my-auto">
                    <h2><img src="<?= $path['images']; ?>/logo-white.png" style="width:100px;"></h2>
                    <p>These statements have not been evaluated by the food and drug administration (FDA). These products are not intended to diagnose, treat, cure or prevent any disease.</p>
                    <p>© <?= date("Y"); ?> <?= $get_data['siteDetails']['siteTitle']; ?>, All rights reserved</p>
                </div>

                <div class="col-md-6 footer-social my-auto">
                    <p style="font-size:12px;">
                    <?php include 'footer.tpl';  ?>
                </div>
            </div>
        </div>
    </footer>



 <p id="loading-indicator" style="display:none;">Processing...</p>
    <p id="crm-response-container" style="display:none;">Limelight messages will appear here...</p>

 <?php
        include 'general/__scripts__.tpl';
        include 'general/__analytics__.tpl';
        perform_body_tag_close_actions();
        ?>

<script type="text/javascript">
            function startTimer(duration, display) {
                var timer = duration, minutes, seconds;
                setInterval(function () {
                    minutes = parseInt(timer / 60, 10);
                    seconds = parseInt(timer % 60, 10);

                    minutes = minutes < 10 ? "0" + minutes : minutes;
                    seconds = seconds < 10 ? "0" + seconds : seconds;

                    display.textContent = minutes + ":" + seconds;

                    if (--timer < 0) {
                        timer = duration;
                    }
                }, 1000);
            }

            window.onload = function () {
                var fiveMinutes = 60 * 5,
                    display = document.querySelector('#clock');
                startTimer(fiveMinutes, display);
            };


        </script>
</body>

</html>