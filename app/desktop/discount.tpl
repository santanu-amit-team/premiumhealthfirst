<?php include 'fetch-data.php';  ?>
<!DOCTYPE html>
<html lang="en">
   <head>

      <?php include 'general/__header__.tpl'; ?>
      <meta charset="utf-8">
      <title></title>
      <link rel="icon" href="data:,">
      <link href="<?= $path['css']; ?>/css/bootyRepo.css" rel="stylesheet" type="text/css"/>
      <link href="<?= $path['css']; ?>/css/appRepo.css" rel="stylesheet" type="text/css" id="isub"/>
      <link href="<?= $path['css']; ?>/css/checkout2repo.css" rel="stylesheet" type="text/css"/>
     
      <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no"/>
      <style>
         .sf-submit-loader-container {display: none;position: fixed;width: 100vw;height: 100vh;top: 0;left: 0;background: rgba(0, 0, 0, 0.2);}.sf-submit-loader-content {margin: auto;text-align: center;}.sf-submit-loader-text {font-size: 26px;margin-bottom: 6px;color: rgba(0,0,0,0.52);letter-spacing: 1.2px;}.lds-dual-ring {display: inline-block;width: 64px;height: 64px;}.lds-dual-ring:after {content: " ";display: block;width: 46px;height: 46px;margin: 1px;border-radius: 50%;border: 5px solid #fff;border-color: #fff transparent #fff transparent;animation: lds-dual-ring 1.2s linear infinite;}@keyframes lds-dual-ring {0% {  transform: rotate(0deg);}100% {  transform: rotate(360deg);}}
         .sq-input {height: 40px;box-sizing: border-box;border: 1px solid rgba(0,0,0,0.4);background-color: white;display: inline-block;-webkit-transition: border-color .1s ease-in-out;   -moz-transition: border-color .1s ease-in-out; -ms-transition: border-color .1s ease-in-out; transition: border-color .1s ease-in-out;}.sq-input--focus {border: 1px solid rgb(57, 142, 231);}.sq-input--error {border: 1px solid #E02F2F;}
         #insyj{background:none;}#i3bwf{font-weight:bold;}#imwvi{margin:auto;}#ip6bk{display:none;}#iqb3ke{clear:both;}#ixj7mc{position:relative;}#ii28bc{padding:10px 0;}#iztr7h{border:4px solid #1e9705;}#iotvsq{background:#1e9705;}#iafcdk{text-align:left;}#i0fkgz{font-size:18px;}#i80kc9{display:none;}#igqbj5{text-align:right;}#i5o7jj{color:green;font-weight:bold;display:none;}#ihfrrw{display:none;}#ExpYear{margin-left:3px;}#i4xbh5{font:10px Arial;width:100%;}#iwloap{font-size:12px;color:#666;text-align:center;}.txt-ctr{text-align:center;}.hidden{display:none;}
      </style>

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
   <body>
      
      <?php perform_body_tag_open_actions(); ?>
      <div id="insyj">
         <img src="<?= $path['images']; ?>/images/loloOpt.png" id="ExitPop" border="0" class="disNone"/>
         <section class="container style1">
            <div id="banner" class="style2">
               <img alt="logo" src="<?= $path['images']; ?>/images/flashKetoLogoReverse.png" class="logo style3"/><img alt="" id="mobile-banner" src="<?= $path['images']; ?>/images/mobile-banner2.jpg"/>
               <div id="steps">
                  <ul>
                     <li class="step-1">
                        <div class="step">
                           <p>1</p>
                        </div>
                        <p class="step-name">SHIPPING INFO</p>
                     </li>
                     <li class="step-2">
                        <div class="step style4">
                           <p>2</p>
                        </div>
                        <p class="step-name">FINISH ORDER</p>
                     </li>
                     <li class="step-3">
                        <div class="step">
                           <p>
                              3
                           </p>
                        </div>
                        <p class="step-name">SUMMARY</p>
                     </li>
                  </ul>
                  <div id="steps line">
                     <div id="slider" class="style5"></div>
                  </div>
               </div>
               <div class="demographic">
                  <p class="style6"><span class="bl">Internet Exclusive Offer</span><br/>Available to US Residents Only</p>
                  <img alt="flag" src="<?= $path['images']; ?>/images/flag2.png" class="flag"/>
               </div>
            </div>
            <div class="row">
               <div class="col-sm-8">
                  <div class="content-left">
                     <div class="viewing style7">
                        <p>
                           <span class="randonCls">
                              <span id="random-num-73255438" data-gjs-type="RandomNumber" data-gjs-sf-randnum-min="3" data-gjs-sf-randnum-max="26" class="random-number-block">
                                 <span id="randomNum-container-73255438"></span>
                              </span>
                           </span>
                           Others are viewing this offer right now - 
                           <strong>
                              <span id="stopwatch">
                                 <span id="timer-9135250" data-gjs-type="Timer" data-gjs-sf-timer-seconds="300">
                                     <span id="clock"></span>
                                 </span>
                              </span>
                           </strong>
                        </p>
                     </div>
                     <div id="i3bwf" class="choice"><span class="color3 style8">Great Job!</span> You're taking your first step towards a better health.
                        Act now so you don't miss out on this offer!
                     </div>
                     <div class="small-text">
                        <div class="style9">
                           Current Availability: <strong class="color1">LOW STOCK!</strong> <br/> Sell-out Risk: <strong class="color1">HIGH</strong>
                        </div>
                        Your order is scheduled to arrive by
                        <span class="color2"><strong><script type="text/javascript">getDate(5)</script></strong></span><br/>
                     </div>
                     <br/>
                  </div>
                  <div class="product-image"><img src="<?= $path['images']; ?>/images/prodMainFla.png" alt="The Dietary Lab Keto" id="imwvi" class="product1 img-responsive"/></div>
                  <div class="product">
                     <p class="large-text"><strong> <?= $get_data['productName']['step1']; ?> </strong></p>
                     <p class="small-text">Advanced Weight Loss Formula</p>
                     <!-- <p class="small-text">60 capsules</p> -->
                     <table width="100%">
                        <tbody>
                           <tr>
                              <td><strong>Price:</strong></td>
                              <td align="right" class="color3">$0.00</td>
                           </tr>
                           <tr>
                              <td>Shipping & Handling</td>
                              <td align="right" id="i2q2w"><span class="ship_price price_total">$1.99</span></td>
                           </tr>
                           <tr class="noborder">
                              <td>Total</td>
                              <td align="right">
                                 <div class="full"><span id="price_total" class="price_total">$1.99</span></div>
                                 <div class="discounted">
                                    $
                                 </div>
                              </td>
                           </tr>
                        </tbody>
                     </table>
                     <p id="ip6bk" class="mc-popup"><strong>ATTENTION: </strong>Use a Visa Card to get $15.00 discount</p>
                     <div class="billing-postal-logos"><img src="<?= $path['images']; ?>/images/billing-postal-logos2.png" alt="postal logos" height="42" width="319" id="i6c57"/></div>
                  </div>
                  <div id="iqb3ke">
                  </div>
                  <div id="ixj7mc"><img src="<?= $path['images']; ?>/images/chek_arrow2.png" id="ii28bc" class="img-responsive center-block"/></div>
               </div>
               <div id="orderNow" class="col-sm-4">
                  <!--form area--><a name="order"></a><img src="<?= $path['images']; ?>/images/seals-m2.png" width="240" height="79" alt="" class="img-responsive center-block mobile"/>
                  <div id="iztr7h" class="formBox center-block">
                     <div id="iotvsq" class="formTop"><img src="<?= $path['images']; ?>/images/checkout-first-step2.png" width="303" height="87" alt="" class="img-responsive center-block"/></div>
                     <div class="formBg">
                        <!-- form fields-->
                       <form method="post" action="ajax.php?method=downsell1" name="downsell_form1" accept-charset="utf-8" enctype="application/x-www-form-urlencoded;charset=utf-8">

                           <input type="hidden" name="limelight_charset" id="limelight_charset" value="utf-8" />
                               
                              <input type="hidden" name="campaigns[1][id]" value="1" id="dynamic_product">
                           <div class="formContent">
                             <div class="form-group">
                              <input type="text" name="firstName" placeholder="First Name" class="required form-control" value="" data-error-message="Please enter your first name!" />
                             </div>
                        <div class="form-group">
                        <input type="text" name="lastName" placeholder="Last Name" class="required form-control" value="" data-error-message="Please enter your last name!" />
                       </div>
                       
                       <div class="form-group">
                        <input type="text" name="email" placeholder="Email Address" class="required form-control" value="" data-validate="email" data-error-message="Please enter a valid email id!" />
                        </div>
                        
                        <div class="form-group">
                        <input type="tel" name="phone" placeholder="Phone" class="required form-control" data-validate="phone" data-min-length="10" data-max-length="15" value="" data-error-message="Please enter a valid contact number!" maxlength="14" />
                        </div>
                        
                        <div class="form-group">
                        <input type="tel" name="shippingZip" placeholder="Zip Code" class="required form-control" value="" data-error-message="Please enter a valid zip code!" maxlength="5" />
                       </div>

                       <div class="form-group">

                        <input type="text" name="shippingAddress1" placeholder="Your Address" class="required form-control" value="" data-error-message="Please enter your address!" />
                       </div>

                       <div class="form-group">
                        <input type="text" name="shippingCity" placeholder="Your City" class="required form-control" value="" data-error-message="Please enter your city!" />
                       </div>
                      
                      <div class="form-group">
                        <select name="shippingCountry" style="display: none;" class="required form-control" data-selected="" data-error-message="Please select your country!">
                        <option value="">Select Country</option>
                       </select>

                       <input type="text" name="shippingState" placeholder="Your State" class="required form-control" data-selected="" data-error-message="Please select your state!" readonly />
                    </div>
                        
                              <div for="address" id="iafcdk" class="control-label">Is your Billing Address the same as your Shipping Address?</div>
                              <div class="radio">
                                 <div id="i0fkgz" class="row">
                                    <div class="col-xs-4"> </div>
                                    <div class="col-xs-3">
                                      <input type="radio" name="billingSameAsShipping" value="yes" checked="checked" /> Yes
                                    </div>
                                    <div class="col-xs-3">
                                       <input type="radio" name="billingSameAsShipping" value="no" /> No
                                    </div>
                                 </div>
                                 <!-- <div id="i80kc9" class="billing hidden"> -->
                                    <div class="billingaddress billing-info" style="display:none;">
                                    <h2>Billing Address</h2>
                                    <div class="form-group">
                                       <div for="firstname">First Name*:</div>
                                       
                                       <input type="text" name="billingFirstName" placeholder="Billing First Name" class="form-control input-sm" data-error-message="Please enter your billing first name!" />
                                    </div>
                                    <div class="form-group">
                                       <div for="lastname">Last Name:*</div>
                                      
                                       <input type="text" name="billingLastName" placeholder="Billing Last Name" class="form-control input-sm" data-error-message="Please enter your billing last name!" />
                                    </div>
                                    <div class="form-group">
                                       <div for="address">Address:*</div>
                                       
                                       <input type="text" name="billingAddress1" placeholder="Billing Address" class="form-control input-sm" data-error-message="Please enter your billing address!" />
                                    </div>
                                    <div class="form-group">
                                       <div for="city">Zip Code:*</div>
                                       
                                       <input type="tel" name="billingZip" placeholder="Billing Zip Code" class="form-control input-sm" data-error-message="Please enter a valid billing zip code!" onkeyup="javascript: this.value = this.value.replace(/[^0-9]/g,'');" maxlength="5"/>
                                    </div>
                                    <div class="form-group">
                                       <div for="city">City:*</div>
                                       
                                       <input type="text" name="billingCity" placeholder="Billing City" class="form-control input-sm" data-error-message="Please enter your billing city!" />
                                    </div>
                                    <div class="form-group">
                                       <div for="state">Country:*</div>

                                        <select name="billingCountry" class="form-control input-sm" data-error-message="Please select your billing country!">
                                                <option value="">Select Country</option>
                                                </select>
                                      
                                    </div>
                                    <div class="form-group">
                                       <div for="state">State:*</div>
                                       
                                           <input type="text" name="billingState" placeholder="Billing State" class="form-control input-sm" data-error-message="Please enter your billing state!" />
                                       </select>
                                    </div>
                                 </div>
                                 <h2>Pay with Credit or Debit Card</h2>
                                 <div id="igqbj5" class="col-xs-5">We Accept</div>
                                 <div class="col-xs-7"><img src="<?= $path['images']; ?>/images/visa-mc-disc2.png" width="100%" height="auto" id="irlh71"/></div>
                                 <br/><br/>
                                 <p id="i5o7jj" class="blinkVisa">EXCLUSIVE LIMITED TIME DISCOUNT FOR<br/> <span class="pulse">VISA & DISCOVER</span> CARDHOLDERS ONLY!</p>
                                 <p id="ihfrrw" class="mc-popup"><strong>ATTENTION: </strong>Use a Visa Card to get $15.00 discount</p>
                                  <div style="display: none;">Card Type*</div>

                                    <select name="creditCardType" id="cc_type" class="required form-control" data-deselect="false" data-error-message="Please select valid card type!" style="display: none;">

                             <option value="">Card Type</option>

                             <?php foreach($config['allowed_card_types'] as $key=>$value): ?>

                             <option value="<?= $key ?>"><?= ucfirst($value) ?></option>

                             <?php endforeach ?>

                         </select>


                                 <div><i class="fa fa-lock"></i> Credit Card #*</div>
                                <input type="tel" name="creditCardNumber" class="required masked form-control" maxlength="16" data-error-message="Please enter a valid credit card number!" placeholder="Credit Card Number" />

                                 <div>Exp Date*</div><br/>
                                 <div class="col-xs-6 nopad">

                                    <select name="expmonth" class="required form-control" data-error-message="Please select a valid expiry month!">
                                            <?php get_months(); ?>
                                        </select>
                                    <!-- <select name="cardMonth" id="ExpMonth" alt="Exp Month" data-error-message="Please select a valid expiry month!" data-threeds="month" required="" class="form-control required">
                                       <option value="">Month</option>
                                      
                                    </select> -->
                                 </div>
                                 <div class="col-xs-6 nopad">

                                    <select name="expyear" class="required form-control" data-error-message="Please select a valid expiry year!">
                                            <?php get_years(); ?>
                                            </select>
                                    <!-- <select alt="Exp Year" name="cardYear" id="ExpYear" data-error-message="Please select a valid expiry year!" data-threeds="year" required="" class="form-control required">
                                       <option value="">Year</option>
                                      
                                    </select> -->
                                 </div>
                                 <div class="clearfix"></div>
                                 <div>CVV*</div><br/>
                                 <div class="col-sm-6 nopad">
                                    <input type="tel" name="CVV" class="required form-control" data-validate="cvv" maxlength="3" data-error-message="Please enter a valid CVV code!" onkeyup="javascript: this.value = this.value.replace(/[^0-9]/g,'');" maxlength="3" placeholder="CVV" />
                                 </div>
                                 <div class="col-sm-6">
                                    <a href="javascript:void(0);" onclick="javascript:openNewWindow('cvv.html','modal');" class="fancybox" style="font:10px Arial;">What's This?</a><br/><br/></div>
                                 <div class="clearfix"> </div>
                                 <p class="click-below">
                                 </p>
                                 <div id="rushtop">
                                    <input type="image" src="<?= $path['images']; ?>/images/rush-my-order2.png" onclick="PreventExitSplash=true;" class="img-responsive center-block"/>
                                    <div id="ijvdts" class="txt-ctr">  </div>
                                 </div>
                              </div>
                              <!--/form fields-->
                           </div>
                        </form>
                     </div>
                     <!--/form area-->
                     <center><img src="<?= $path['images']; ?>/images/secure2.png"/></center>
                     <center><img src="<?= $path['images']; ?>/images/order-secureicons32.jpg"/></center>
                  </div>
               </div>
            </div>
         </section>
         <section class="container">
            <div class="row">
               <div class="col-sm-12">
                  <br/>
                  <div id="disclaimers">
                     <p>
                     </p>
                     <center><br/> -->
                        <?= $get_data['productName']['step1']; ?> is committed to maintaining the highest quality products and the utmost integrity in business practices. All products sold on this website are certified by Good Manufacturing Practices (GMP), which is the highest standard of testing in the supplement industry.
                     </center>
                     <p></p>
                     <p>
                     </p>
                     <center>Notice: The products and information found on tryflashketo.com are not intended to replace professional medical advice or treatment. These statements have not been evaluated by the Food and Drug Administration. These products are not intended to diagnose, treat, cure or prevent any disease. Individual results may vary.<br/><br/>
                        © Copyright 2020 <?= $get_data['productName']['step1']; ?>.
                     </center>
                     <p></p>
                  </div>
                  <p id="iwloap" class="disclaimer_m"><br/>
                     <?php include 'footer.tpl';  ?>
                     Disclaimer: These products have not been evaluated by the Food and Drug Administration. This product is not intended to diagnose, treat, cure or prevent any disease.
                  </p>
               </div>
            </div>
         </section>
         <p id="loading-indicator" style="display:none;">Processing...</p>
    <p id="crm-response-container" style="display:none;">Limelight messages will appear here...</p>
      </div>
     
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

            var tryCount = 0;
              $("#cc_type").change(function(){


                   var cc_type = $('#cc_type').val();
                   if(cc_type=='master'){
                     tryCount = tryCount + 1;
                     
                      $("#dynamic_product").val(23);
                    }
                    else{

                        $("#dynamic_product").val(20);
                    }

            });
              history.pushState({}, null, appLocation.pathname + appLocation.search);

        </script>
   </body>
</html>