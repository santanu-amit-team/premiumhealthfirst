<!DOCTYPE html>
<?php include 'fetch-data.php';  ?>
<html lang="en">
   <head>
      <?php require_once 'general/__header__.tpl' ?>
      <meta charset="utf-8">
     <title></title>
      
      <link href="<?= $path['css']; ?>/style-index.css" rel="stylesheet" type="text/css"/>
      <link href="<?= $path['css']; ?>/css/up-style.css" rel="stylesheet" type="text/css"/>
     
      <link href="<?= $path['css']; ?>/css/bootstrap.css" rel="stylesheet"/>
      <link href="<?= $path['css']; ?>/css/animate.css" rel="stylesheet"/>
      <link rel="stylesheet" href="<?= $path['css']; ?>/css/all.css"/>
      <link rel="stylesheet" href="<?= $path['css']; ?>/css/kform.css"/>
      <!-- <link rel="stylesheet" href="<?= $path['css']; ?>/css/kprofile.css"/> -->
      <link rel="stylesheet" href="<?= $path['css']; ?>/css/kcart.css"/>
     
      <style>
         .sf-submit-loader-container {display: none;position: fixed;width: 100vw;height: 100vh;top: 0;left: 0;background: rgba(0, 0, 0, 0.2);}.sf-submit-loader-content {margin: auto;text-align: center;}.sf-submit-loader-text {font-size: 26px;margin-bottom: 6px;color: rgba(0,0,0,0.52);letter-spacing: 1.2px;}.lds-dual-ring {display: inline-block;width: 64px;height: 64px;}.lds-dual-ring:after {content: " ";display: block;width: 46px;height: 46px;margin: 1px;border-radius: 50%;border: 5px solid #fff;border-color: #fff transparent #fff transparent;animation: lds-dual-ring 1.2s linear infinite;}@keyframes lds-dual-ring {0% {  transform: rotate(0deg);}100% {  transform: rotate(360deg);}}
         .sq-input {height: 40px;box-sizing: border-box;border: 1px solid rgba(0,0,0,0.4);background-color: white;display: inline-block;-webkit-transition: border-color .1s ease-in-out;   -moz-transition: border-color .1s ease-in-out; -ms-transition: border-color .1s ease-in-out; transition: border-color .1s ease-in-out;}.sq-input--focus {border: 1px solid rgb(57, 142, 231);}.sq-input--error {border: 1px solid #E02F2F;}
         ion-icon{visibility:hidden;}.c5911{width:80%;margin-bottom:0px;}.c6157{width:100px;}.c6642{width:300px;}.c6887{width:170px;height:45px;}.c6933{font-size:12px;}.c123365{padding-top:10px;padding-right:10px;padding-bottom:10px;padding-left:10px;text-align:center;}.c1190{width:7%;text-align:left;float:none;font-weight:700;}.c1178{float:right;margin-top:0px;margin-right:4%;margin-bottom:0px;margin-left:0px;}.warnWrap{width:92%;text-align:center;float:none;}.warnWrap.c1178{width:100%;}.btn-custom{backface-visibility:visible !important;}.pulse{animation-name:pulse;-webkit-animation-name:pulse;animation-duration:1.5s;-webkit-animation-duration:1.5s;animation-iteration-count:infinite;-webkit-animation-iteration-count:infinite;}.btn{color:#fff !important;background:#d91e18 !important;}.btn:hover{color:#000 !important;background:#FFEB3B !important;}.hero.hero2{background-image:url(https://cdn.subscribefunnels.com/d6333932-4a63-48e8-b466-59b32878c024/hero-bgFla_OPT01.jpg);background-repeat:no-repeat;background-position:center top;background-size:cover;background-attachment:local;}@keyframes pulse{0%{transform:scale(0.9);opacity:0.9;}50%{transform:scale(1);opacity:1;}100%{transform:scale(0.9);opacity:0.9;}}@media screen and (max-width: 600px){.hero{background-image:url(https://cdn.subscribefunnels.com/d6333932-4a63-48e8-b466-59b32878c024/hero-bg3Fla2.jpg) !important;background-repeat:no-repeat;background-size:800px;background-position:top center !important;undefined:undefined;}}
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
      <!-- <title>Try <?= $get_data['productName']['step1']; ?> - Melts Fat Instantly</title> -->
      <section id="ieiml" class="hero">
         <div class="alert-row animated slideInDown delay-2s" >
            <div class="container">
               <div class="row">
                  <div role="alert" class="alert alert-danger">
                     <div class="c123365">
                        <div id="i0n16x-2" data-gjs-type="Timer" data-gjs-sf-timer-seconds="300" class="warnWrap c1178">
                           <strong>WARNING:</strong> Due to extremely high social
                           media demand for our offers with free bottles, there is limited supply
                           of <?= $get_data['productName']['step1']; ?> in stock as of <div class="time" id="time" style="display:inline;"><script type="text/javascript">getDate(0)</script></div>!

                           <input class="formats" type="text" id="format" value="DD MMMM dd, yyyy"><span class="todays-date"></span>
                        <div style="display:inline;"> Offer Expires In <span id="clock"></span></div>
                           
                        </div>
                     </div>
                  </div>
               </div>
            </div>
         </div>
         <div class="container">
            <div class="container">
               <div class="row lp-form-align">
                  <div class="lp-form formTop" id="header">
                     <div id="top" class="lp-form-title">
                        Tell us where to send your package!
                     </div>
                     <div class="lp-form-title-arrow"></div>
                   

                     <form method="post" action="ajax.php?method=new_prospect" name="prospect_form1" accept-charset="utf-8" enctype="application/x-www-form-urlencoded;charset=utf-8">
            <input type="hidden" name="limelight_charset" id="limelight_charset" value="utf-8" />



                        <input type="text" name="firstName" placeholder="First Name" class="required form-control" value="" data-error-message="Please enter your first name!" />

                        <input type="text" name="lastName" placeholder="Last Name" class="required form-control" value="" data-error-message="Please enter your last name!" />

                        <input type="text" name="email" placeholder="Email Address" class="required form-control" value="" data-validate="email" data-error-message="Please enter a valid email id!" />

                        <input type="tel" name="phone" placeholder="Phone" class="required form-control" data-validate="phone" data-min-length="14" data-max-length="15" value="" data-error-message="Please enter a valid contact number!" maxlength="14" />

                        <input type="tel" name="shippingZip" placeholder="Zip Code" class="required form-control" value="" data-error-message="Please enter a valid zip code!" maxlength="5" />


                        <input type="text" name="shippingAddress1" placeholder="Your Address" class="required form-control" value="" data-error-message="Please enter your address!" />


                        <input type="text" name="shippingCity" placeholder="Your City" class="required form-control" value="" data-error-message="Please enter your city!" />

                        <select name="shippingCountry" style="display: none;" class="required form-control" data-selected="" data-error-message="Please select your country!">
                        <option value="">Select Country</option>
                       </select>

                       <input type="text" name="shippingState" placeholder="Your State" class="required form-control" data-selected="" data-error-message="Please select your state!" readonly />
                        

                        <button type="submit" id="" class="btn btn-custom btn-lg btn-block infinite kform_submitBtn pulse">
                           Rush my order
                           <div>Order your package today!</div>
                        </button>
                        <input type="hidden" name="country" value="US"/>
                     </form>
                     <div class="lp-form-verified">
                        <center><img src="<?= $path['images']; ?>/images/verified_ab29d0ab8b4851bebc51c9f2a71bf70d.png" class="img-fluid"/></center>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </section>
      <section id="about-2">
         <div class="container">
            <div class="row">
               <div class="col-lg-6 my-auto">
                  <h2><span>Revolutionary Break-through!</span><br/>Why does it have Scientists, Doctors and Celebrities Buzzing?<br/></h2>
                  <p><b>The most talked about weight loss product is finally here!</b>
                     A powerful fat burning ketone, BHB has been modified to produce a
                     instant fat burning solution the natural way. Beta-hydroxybutyrate is
                     the first substrate that kicks the metabolic state of ketosis into
                     action. If you take it, BHB is able to start processing in your body
                     resulting in energy and greatly speed up weight loss by putting your
                     body into ketosis. This one BHB Supplement is a revolutionary
                     breakthrough that has the Media in a frenzy!
                  </p>
                  <p><b><?= $get_data['productName']['step1']; ?> with BHB is here to stay
                     because of the insurmountable success people are having losing up to 1lb
                     of fat per day!</b>
                  </p>
               </div>
               <div class="col-lg-6 text-center"><!-- <img src="<?= $path['images']; ?>/images/mag_coverFla_OPT01.png" class="img-fluid"/> -->
                  <img src="<?= $path['images']; ?>/images/prodMainFla.png" class="img-fluid" style="width: 250px;"/></div>
            </div>
         </div>
      </section>
      <section class="before-and-after">
         <div class="container">
            <div class="row">
               <div class="col-lg-6">
               </div>
               <div class="col-lg-6">
                  <center>
                     <img src="<?= $path['images']; ?>/images/theproof.png" class="c5911"/>
                     <h2>Join the Thousands who are already losing up to 1lb per day!<br/></h2>
                  </center>
                  <img src="<?= $path['images']; ?>/images/beforeandafter.jpg" class="img-fluid"/>
                  <center> <a href="javascript:bookmarkscroll.scrollTo('header')"><button type="button" class="btn btn-small btn-custom btn-lg mt-5 animated infinite pulse orderNw">Order NOW!</button></a></center>
               </div>
            </div>
         </div>
      </section>
      <section class="how-does-it-work">
         <div class="container">
            <div class="">
               <h1>How Does it work? </h1>
               <h2>Ketosis Forces your body to Burn Fat for Energy instead of Carbs.</h2>
            </div>
            <div class="row mt-5">
               <div class="col-lg-5">
                  <img src="<?= $path['images']; ?>/images/baddiet.jpg" class="img-fluid"/>
                  <h2 class="mt-5">WHY YOUR DIETS FAIL...</h2>
                  <p>Currently with the massive load of cabohydrates in our
                     foods, our bodies are conditioned to burn carbs for energy instead of
                     fat. Because it is an easier energy source for the body to use up.
                  </p>
                  <h2 class="mt-5">The Problem:</h2>
                  <p>1. Fat stores on the body as carbs are burned as an easy energy fuel. Essentialy we gain more weight year after year.</p>
                  <p>2. Carbs are not the body’s ideal source of energy therefore we are
                     usually left feeling tired, stressed and drained at the end of each day.
                  </p>
               </div>
               <div class="col-lg-2 my-auto">
                  <center><img src="<?= $path['images']; ?>/images/vs.png" class="c6157"/></center>
               </div>
               <div class="col-lg-5">
                  <img src="<?= $path['images']; ?>/images/gooddiet.jpg" class="img-fluid"/>
                  <h2 class="mt-5">WHY KETO WORKS!</h2>
                  <p>Ketosis is the state where your body is actually burning
                     fat for energy instead of carbs. Ketosis is extremely hard to obtain on
                     your own and takes weeks to accomplish. <?= $get_data['productName']['step1']; ?> actually
                     helps your body achieve ketosis fast and helps you burn fat for energy
                     instead of carbs!
                  </p>
                  <h2 class="mt-5">The Solution:</h2>
                  <p>1. When your body is in ketosis, you are actually burning stored fat for energy and not carbs!</p>
                  <p>2. Fat IS the body’s ideal source of energy and when you
                     are in ketosis you experience energy and mental clarity like never
                     before and of course very rapid weight loss.
                  </p>
               </div>
            </div>
         </div>
      </section>
      <section class="lp-para">
         <div class="container">
            <div class="row text-center lp-para-spacing justify-content-center">
               <div class="col-lg-6 my-auto"><img src="<?= $path['images']; ?>/images/3btlFla.png" class="img-fluid"/></div>
               <div class="col-lg-5 my-auto">
                  <h1><span>BURN FAT</span><br/>INSTEAD OF CARBS</h1>
                  <h3>with <?= $get_data['productName']['step1']; ?>!</h3>
                  <br/> <a href="javascript:bookmarkscroll.scrollTo('header')"><button type="button" class="btn btn-custom btn-lg animated infinite pulse orderNw">RUSH MY ORDER!</button></a>
               </div>
            </div>
         </div>
      </section>
      <section class="key-features">
         <div class="container">
            <div class="row">
               <div class="col-md-8 order-md-2 ml-auto my-auto gza">
                  <h2><span>WHAT DO YOU GET?</span> </h2>
                  <h3>The 30 day ketosis supplement that is Sweeping the Nation!</h3>
                  <p class="mt-5"><?= $get_data['productName']['step1']; ?> contains
                     Beta-hydroxybutyrate. BHB is the first substrate that kicks the
                     metabolic state of ketosis into action. Revisiting the scenario from
                     before, if you either take supplemental forms or if your body is making
                     beta-hydroxybutyrate, it is able to start processing in your body
                     resulting in energy.
                  </p>
                  <p>Beta-hydroxybutyrate floats around in your blood, and importantly,
                     can cross different important barriers to be able to be turned into
                     energy at all times. One of the most important areas where this happens
                     is in the brain. The blood-brain barrier (BBB) is usually a very tightly
                     regulated interface, but since BHB is such a rock star and so
                     hydrophilic, your brain knows to let it in so it can bring energy to the
                     party at any time. This is one of the main reasons why increased BHB
                     levels lead to heightened mental acuity.*
                  </p>
                  <p> Get slim, healthy, and confident again with our
                     unique <?= $get_data['productName']['step1']; ?> supplement. Ideal for both men and women, <?= $get_data['productName']['step1']; ?> is a dynamic and powerful ketosis dietary
                     supplement that will assist weight loss, promote abdominal fat burn, and
                     support better digestion and sleep.*
                  </p>
                  <p></p>
                  <ul>
                     <li>Lose Weight*</li>
                     <li>Burn Fat in Trouble Areas*</li>
                     <li>Get into Ketosis Fast!*</li>
                     <li> Burn Fat for Energy (without the jitters)!*</li>
                     <li> Better Brain Health!*</li>
                     <li> Faster Recovery from Exercise!*</li>
                     <li> Maintain Lean Muscle!*</li>
                  </ul>
                  <p></p>
               </div>
               <div class="col-md-4 order-md-1 featured-pouch-image align-content-center my-auto gza"><img src="<?= $path['images']; ?>/images/prodMainFla.png" class="featurette-image img-fluid mx-auto c6642"/></div>
            </div>
         </div>
         <center><br/>
            <a href="javascript:bookmarkscroll.scrollTo('header')"><button type="button" id="kformSubmit-2-2" class="btn btn-custom btn-small btn-lg kform_submitBtn animated infinite pulse orderNw" >RUSH MY ORDER!</button></a></center>
      </section>
      <section class="testimonials mx-auto">
         <div class="container">
            <h2>Behind the Hype!<br/><span>Newest Reviews</span> </h2>
            <div class="row mx-auto">
               <div class="col-lg-6 mx-auto"><img src="<?= $path['images']; ?>/images/fb_comments_1ControlX_469cf322736b80a4e334b8b30d6df7a9.jpg" class="img-fluid"/></div>
               <div class="col-lg-6 mx-auto"><img src="<?= $path['images']; ?>/images/fb_comments_2ControlX_5621472fd6846cc54d156d1829aee8d5.jpg" class="img-fluid"/></div>
            </div>
         </div>
      </section>

      <div id="exitpopup-overlay" class="exitpopup-overlay">
            <div id="exit_pop" class="exitpop-content">
                <a href="<?= get_exit_pop_url('step1', 1); ?>">
                    <img src="<?= $path['assets_images'] ?>/downsell.jpg" />
                </a>
            </div>
        </div>
      <!-- Footer -->
      <footer class="footer-bs">
         <div class="container">
            <div class="row">
               <div class="col-md-6 footer-brand my-auto gza">
                  <h2><img src="<?= $path['images']; ?>/images/loloFla_OPT01.png" class="c6887"/></h2>
                  <p>These statements have not been evaluated by the food
                     and drug administration (FDA). These products are not intended to
                     diagnose, treat, cure or prevent any disease.
                  </p>
                  <p>© <?= date("Y"); ?> <?= $get_data['productName']['step1']; ?>, All rights reserved</p>
               </div>
               <div class="col-md-6 footer-social my-auto">
                  <p class="c6933">

                     <?php include 'footer.tpl';  ?>

                    <!--  [ <a draggable="true" data-highlightable="1" href="page-terms" target="_blank">Terms & Conditions</a> ] [ <a draggable="true" data-highlightable="1" href="page-privacy" target="_blank">Privacy Policy</a> ] [ <a draggable="true" data-highlightable="1" href="page-contact" target="_blank">Contact Us</a> ]  | Toll Free +1 (833) 619-0619  -->
                  </p>
               </div>
            </div>
         </div>
      </footer>
<!-- <div id="idleModal" class="popupmod" style="display:none;">

        <div class="pop-con">
        
         <img src="<?= $path['images'] ?>/idlemodalimage_comp.png" style="display: inline-block;" id="ExitPop" border="0" onclick="window.location.href='discount.php<?= make_query_string() ?>'">
         </div> 

    </div> -->

      <p id="loading-indicator" style="display:none;">Processing...</p>
    <p id="crm-response-container" style="display:none;">Limelight messages will appear here...</p>

      <?php require_once 'general/__scripts__.tpl' ?>
        <?php require_once 'general/__analytics__.tpl' ?>
        <?php perform_body_tag_close_actions(); ?>

<script src="<?= $path['js'] ?>/bookmarkscroll.js"></script>
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


            /* Numbers only */
$("[name=phone], [name=shippingZip]").keypress(function (e) {
   if ((e.which >= 33 && e.which <= 47) || (e.which >= 58 && e.which <= 126)) {
                       return false;
             }
   });

 </script>

   </body>
</html>