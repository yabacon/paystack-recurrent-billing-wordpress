<?php

if(!defined('ABSPATH')){
    // use $wpdb directly
    define( 'SHORTINIT', true );
    require_once( dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php' );
}
require(__DIR__ . '/Paystack.php');
global $wpdb;

define('PAYSTACK_SUBSCRIBE_PAY_TABLE', $wpdb->prefix . "paystack_subscribe_pay");
define('PAYSTACK_SUBSCRIBE_PAY_DB_VERSION', "1.1");

function paystack_subscribe_pay_verify_short_code($atts)
{
    $toret = new stdClass();
    
    $toret->buttontext = (is_array($atts) && array_key_exists('buttontext', $atts)) ? $atts['buttontext'] : "Subscribe";
    $toret->target = (is_array($atts) && array_key_exists('target', $atts)) ? $atts['target'] : 0;
    $toret->plancode = (is_array($atts) && array_key_exists('plancode', $atts)) ? $atts['plancode'] : false;
    $toret->message = (is_array($atts) && array_key_exists('message', $atts)) ? $atts['message'] : false;

    // cost must be a valid float or not at all
    if ($toret->target && !floatval($toret->target)) {
        $toret->error = 'Target is optional but must be a valid number if provided';
    }

    // plancode is required
    if (!$toret->plancode) {
        $toret->error = 'Plan Code is required.';
    }
    
    return $toret;
}

function paystack_subscribe_pay_get_public_key()
{
    $options = get_option( 'paystack_subscribe_pay_settings', array('paystack_subscribe_pay_public_key'=>""));
    return $options['paystack_subscribe_pay_public_key'];
}

function paystack_subscribe_pay_get_alert_emails()
{
    $options = get_option( 'paystack_subscribe_pay_settings', array('paystack_subscribe_pay_alert_emails'=>""));
    return $options['paystack_subscribe_pay_alert_emails'];
}

function paystack_subscribe_pay_get_secret_key()
{
    $options = get_option( 'paystack_subscribe_pay_settings', array('paystack_subscribe_pay_secret_key'=>""));
    return $options['paystack_subscribe_pay_secret_key'];
}

function paystack_subscribe_pay_form($atts)
{
    $att = paystack_subscribe_pay_verify_short_code($atts);

    if($att->error){
        return $att->error;
    }
    
    $form = '<script>
// load jQuery 1.12.3 if not loaded
(typeof $ === \'undefined\') && document.write("<scr" + "ipt type=\"text\/javascript\" src=\"https:\/\/code.jquery.com\/jquery-1.12.3.min.js\"><\/scr" + "ipt>");
</script>
<script>
// conditionally add hidden=display_none
var $span = $(\'<span class="bs hidden"></span>\');
var $bs = $(\'body\').append($span).find(\'.bs\');

if ($bs.css(\'display\') !== \'none\') {
    $("head").append(\'<style>.hidden{display:none;}<\/style>\');
}
$bs.remove();
</script>

<form id="payment-form" class="trans col-sm-5 cnter-block animated fadeInUp">
  <div class="form-group payment-form firstname">
    <label for="">First Name</label>
    <input id="payment-firstname" type="text" class="form-control" placeholder="Your first name" autocomplete="off">
    <span class="help-block hidden">* Please use a valid first name</span>
  </div>
  <div class="form-group payment-form lastname">
    <label for="">Last Name</label>
    <input id="payment-lastname" type="text" class="form-control" placeholder="Your last name" autocomplete="off">
    <span class="help-block hidden">* Please use a valid last name</span>
  </div>
  <div class="form-group payment-form email">
    <label for="">Email Address</label>
    <input id="payment-email" type="email" class="form-control" placeholder="Your email address" autocomplete="off">
    <span class="help-block hidden">* Please use a valid email address</span>
  </div>
  <div class="form-group payment-form phone">
    <label for="">Phone Number</label>
    <input id="payment-phone" type="tel" class="form-control" placeholder="Your phone number" autocomplete="off">
    <span class="help-block hidden">* Please use a valid phone number</span>
  </div>
  <div class="form-group payment-form deliveryaddress">
    <label for="">Delivery Address</label>
    <textarea id="payment-deliveryaddress" class="form-control textarea" 
        placeholder="Your Delivery address. Include all information that can help us locate you." autocomplete="off"></textarea>
    <span class="help-block hidden">* Please enter a valid address</span>
  </div>
  <div class="form-group text-right call-to-action">
    <button id="payment-btn" class="btn btn-block green-btn large-btn trans" style="margin: 0">'.htmlspecialchars($att->buttontext).'</button>
  </div>
  <script src="https://js.paystack.co/v1/inline.js"></script>
</form>
<div id="success-message" class="row animated fadeInUp hidden col-md-12">
  <div class="call-to-action text-center mb">
    <span class="fa fa-check pay-thanks"></span>
    <h3>Successful!</h3>
    <div class="col-md-8 col-md-offset-2">
      <p class="demo-success">Your transaction reference is <b id="trans-ref"></b>. '.
      ($att->message ? $att->message : 'You will also get a confirmation message in your email box.') . '</p>
    </div>
  </div>
</div>
<script>
    $(function () {
      var invalid_email=true;
      var invalid_firstname=true;
      var invalid_lastname=true;
      var invalid_deliveryaddress=true;
      var invalid_phone=true;
      var paystackHandler;

      function validateEmail(email) {
        var re = /^([\w-]+(?:\.[\w-]+)*)@((?:[\w-]+\.)*\w[\w-]{0,66})\.([a-z]{2,6}(?:\.[a-z]{2})?)$/i;
        return re.test(email);
      }
      
      function validateNotBlank(str){
        return !(!str || !str.length || !str.trim().length);
      }

      var typingTimer;
      $(\'#payment-email\').on(\'keyup\', function () {
        clearTimeout(typingTimer);
        var email = $(this).val().trim();
        var parent = $(this).parent();
        var email_helper = $(this).siblings(\'.help-block\');
        typingTimer = setTimeout(function () {
          if (validateEmail(email)) {
            if (invalid_email) {
              parent.removeClass(\'has-error\');
              email_helper.addClass(\'hidden\');
              invalid_email = false;
            }
          } else {
            if (!invalid_email) {
              parent.addClass(\'has-error\');
              email_helper.removeClass(\'hidden\')
              invalid_email = true;
            }
          }
        }, 500);
      });

      $(\'#payment-firstname\').on(\'keyup\', function () {
        clearTimeout(typingTimer);
        var firstname = $(this).val().trim();
        var parent = $(this).parent();
        var firstname_helper = $(this).siblings(\'.help-block\');
        typingTimer = setTimeout(function () {
          if (validateNotBlank(firstname)) {
            if (invalid_firstname) {
              parent.removeClass(\'has-error\');
              firstname_helper.addClass(\'hidden\');
              invalid_firstname = false;
            }
          } else {
            if (!invalid_firstname) {
              parent.addClass(\'has-error\');
              firstname_helper.removeClass(\'hidden\')
              invalid_firstname = true;
            }
          }
        }, 500);
      });

      $(\'#payment-phone\').on(\'keyup\', function () {
        clearTimeout(typingTimer);
        var phone = $(this).val().trim();
        var parent = $(this).parent();
        var phone_helper = $(this).siblings(\'.help-block\');
        typingTimer = setTimeout(function () {
          if (validateNotBlank(phone)) {
            if (invalid_phone) {
              parent.removeClass(\'has-error\');
              phone_helper.addClass(\'hidden\');
              invalid_phone = false;
            }
          } else {
            if (!invalid_phone) {
              parent.addClass(\'has-error\');
              phone_helper.removeClass(\'hidden\')
              invalid_phone = true;
            }
          }
        }, 500);
      });

      $(\'#payment-deliveryaddress\').on(\'keyup\', function () {
        clearTimeout(typingTimer);
        var deliveryaddress = $(this).val().trim();
        var parent = $(this).parent();
        var deliveryaddress_helper = $(this).siblings(\'.help-block\');
        typingTimer = setTimeout(function () {
          if (validateNotBlank(deliveryaddress)) {
            if (invalid_deliveryaddress) {
              parent.removeClass(\'has-error\');
              deliveryaddress_helper.addClass(\'hidden\');
              invalid_deliveryaddress = false;
            }
          } else {
            if (!invalid_deliveryaddress) {
              parent.addClass(\'has-error\');
              deliveryaddress_helper.removeClass(\'hidden\')
              invalid_deliveryaddress = true;
            }
          }
        }, 500);
      });

      $(\'#payment-lastname\').on(\'keyup\', function () {
        clearTimeout(typingTimer);
        var lastname = $(this).val().trim();
        var parent = $(this).parent();
        var lastname_helper = $(this).siblings(\'.help-block\');
        typingTimer = setTimeout(function () {
          if (validateNotBlank(lastname)) {
            if (invalid_lastname) {
              parent.removeClass(\'has-error\');
              lastname_helper.addClass(\'hidden\');
              invalid_lastname = false;
            }
          } else {
            if (!invalid_lastname) {
              parent.addClass(\'has-error\');
              lastname_helper.removeClass(\'hidden\')
              invalid_lastname = true;
            }
          }
        }, 500);
      });

      $(\'#payment-btn\').click(function (e) {
        e.preventDefault();

        var payment_btn = $(this);
        var subscriber = {};
        subscriber.email = $(\'#payment-email\').val().trim();
        subscriber.firstname = $(\'#payment-firstname\').val().trim();
        subscriber.lastname = $(\'#payment-lastname\').val().trim();
        subscriber.phone = $(\'#payment-phone\').val().trim();
        subscriber.deliveryaddress = $(\'#payment-deliveryaddress\').val().trim();

        if (!validateEmail(subscriber.email)) {
          $(\'.payment-form.email\').addClass(\'has-error\');
          $(\'.email .help-block\').removeClass(\'hidden\');
          return;
        }

        if (!validateNotBlank(subscriber.firstname)) {
          $(\'.payment-form.firstname\').addClass(\'has-error\');
          $(\'.firstname .help-block\').removeClass(\'hidden\');
          return;
        }

        if (!validateNotBlank(subscriber.lastname)) {
          $(\'.payment-form.lastname\').addClass(\'has-error\');
          $(\'.lastname .help-block\').removeClass(\'hidden\');
          return;
        }

        if (!validateNotBlank(subscriber.deliveryaddress)) {
          $(\'.payment-form.deliveryaddress\').addClass(\'has-error\');
          $(\'.deliveryaddress .help-block\').removeClass(\'hidden\');
          return;
        }

        if (!validateNotBlank(subscriber.phone)) {
          $(\'.payment-form.phone\').addClass(\'has-error\');
          $(\'.phone .help-block\').removeClass(\'hidden\');
          return;
        }
        
        serialize = function(obj, prefix) {
          var str = [];
          for(var p in obj) {
            if (obj.hasOwnProperty(p)) {
              var k = prefix ? prefix + "[" + p + "]" : p, v = obj[p];
              str.push(typeof v == "object" ?
                serialize(v, k) :
                encodeURIComponent(k) + "=" + encodeURIComponent(v));
            }
          }
          return str.join("&");
        }

        var sendthis = {
            subscriber: subscriber,
            cost: '.$att->target.'
        };
        paystackHandler = PaystackPop.setup({
          key: \''.paystack_subscribe_pay_get_public_key().'\',
          email: subscriber.email,
          first_name: subscriber.firstname,
          last_name: subscriber.lastname,
          plan: \''.$att->plancode.'\',
          metadata: serialize(sendthis),
          onClose: function () {
            payment_btn.attr(\'disabled\', false).html(\''.htmlspecialchars($att->buttontext).'\');
          },
          callback: function (response) {
            // use AJAX to verify payment and add subscriber
            // ignore result
            // can ignore because event will also fire from Paystack
            sendthis.trxref = response.trxref;
            var callbackurl = \''.
                plugins_url( 'links/callback.php', __DIR__ ) .
                '?\' + serialize(sendthis);
            $.get(  ).fail(function() {
                // redirect if AJAX fails
                $("#success-message").removeClass(\'hidden\').
                    find(\'#trans-ref\').text(response.trxref+ \'. Please wait while you are redirected... \');
                window.location = callbackurl;
              });
            $("#payment-form, .demo-talk").addClass(\'hidden\');
            $("#success-message").removeClass(\'hidden\').find(\'#trans-ref\').text(response.trxref);
          }
        })
        paystackHandler.openIframe();
        payment_btn.attr(\'disabled\', true).html(\'<i class="fa fa-spinner fa-spin"></i>\');
      })

    })
</script>
';

    return $form;
}

function paystack_subscribe_pay_action_links($links) {
  /* Add link to settings page under woo-commerce */
  $links[] = '<a href="' . esc_url(get_admin_url(null, 'options-general.php?page=paystack_subscribe_pay')) . '">Settings</a>';
  /* Add link to settings page on paystack dashboard */
  $links[] = '<a target="_blank" href="https://dashboard.paystack.co/#/settings/developer" target="_blank">Paystack Dashboard</a>';
  return $links;
}

function paystack_subscribe_pay_start_session() {
    if(!session_id()) {
        session_start();
    }
}

function paystack_subscribe_pay_add_admin_menu()
{

    add_options_page('Paystack Subscribe Pay', 'Paystack Subscribe Pay', 'manage_options', 'paystack_subscribe_pay', 'paystack_subscribe_pay_options_page');

}


function paystack_subscribe_pay_settings_init()
{

    register_setting('paystack_subscribe_pay_pluginPage', 'paystack_subscribe_pay_settings');

    add_settings_section(
        'paystack_subscribe_pay_pluginPage_section',
        __('', 'paystack_subscribe_pay'),
        'paystack_subscribe_pay_settings_section_callback',
        'paystack_subscribe_pay_pluginPage'
    );

    add_settings_field(
        'paystack_subscribe_pay_alert_emails',
        __('Enter (an) email(s) to be alerted for every event separated by a comma', 'paystack_subscribe_pay'),
        'paystack_subscribe_pay_alert_emails_render',
        'paystack_subscribe_pay_pluginPage',
        'paystack_subscribe_pay_pluginPage_section'
    );

    add_settings_field(
        'paystack_subscribe_pay_secret_key',
        __('Enter your paystack secret key', 'paystack_subscribe_pay'),
        'paystack_subscribe_pay_secret_key_render',
        'paystack_subscribe_pay_pluginPage',
        'paystack_subscribe_pay_pluginPage_section'
    );

    add_settings_field(
        'paystack_subscribe_pay_public_key',
        __('Enter your paystack public key', 'paystack_subscribe_pay'),
        'paystack_subscribe_pay_public_key_render',
        'paystack_subscribe_pay_pluginPage',
        'paystack_subscribe_pay_pluginPage_section'
    );


}

function paystack_subscribe_pay_public_key_render()
{

    $options = get_option('paystack_subscribe_pay_settings');
    ?>
    <input type='text' name='paystack_subscribe_pay_settings[paystack_subscribe_pay_public_key]' 
    value='<?php echo $options['paystack_subscribe_pay_public_key']; ?>' size="50">
    <br><i>Obtain from: <a target="_blank" href="https://dashboard.paystack.co/#/settings/developer" 
    target="_blank">Paystack Dashboard</a></i>
    <?php

}

function paystack_subscribe_pay_secret_key_render()
{

    $options = get_option('paystack_subscribe_pay_settings');
    ?>
    <input type='text' name='paystack_subscribe_pay_settings[paystack_subscribe_pay_secret_key]' 
    value='<?php echo $options['paystack_subscribe_pay_secret_key']; ?>' size="50">
    <br><i>Obtain from: <a target="_blank" href="https://dashboard.paystack.co/#/settings/developer" 
    target="_blank">Paystack Dashboard</a></i>
    <?php

}


function paystack_subscribe_pay_alert_emails_render()
{

    $options = get_option('paystack_subscribe_pay_settings');
    ?>
    <input type='text' name='paystack_subscribe_pay_settings[paystack_subscribe_pay_alert_emails]' 
    value='<?php echo $options['paystack_subscribe_pay_alert_emails']; ?>' size="50">
    <br><i>Be sure to enter valid emails separated by a comma</i>
    <?php

}


function paystack_subscribe_pay_settings_section_callback()
{

    echo __('
   <div class="wrap">
   <div class="postbox-container og_left_col">
        <div id="poststuff">
            <div class="postbox">
                <div style="
    padding: 20px;
">
                <h3 id="settings">Setup Instructions</h3>
                
    <ol>
    <li>Set your Live WebHook URL here: <a target="_blank" 
    href="https://dashboard.paystack.co/#/settings/developer" target="_blank">Paystack
    Dashboard</a> to: <pre>'.plugins_url( 'links/webhook.php', __DIR__ ).'</pre></li>
    <li>Configure the plugin by filling the Alert Emails, Paystack Secret Key and Paystack Public Key fields below.</li>
    <li>Include the shortcode: <b>[paystacksubscribepay 
    target="<i>NGN_AMT</i>" message="<i>MESSAGE</i>" plancode="<i>PLAN_CODE</i>"]</b> 
    in the page where you want the subscription form displayed.
    <p style="text-align:justify">Replace <i>PLAN_CODE</i> with the code for the plan you have created
    here: <a target="_blank" href="https://dashboard.paystack.co/#/plans">https://dashboard.paystack.co/#/plans</a> 
    If you will be setting a target, be sure to make the target a multiple of the plan cost. This is a
    required field.
    <p style="text-align:justify">Replace <i>NGN_AMT</i> with the target amount in naira to be charged in total (all digits, no commas). 
    Note that the cost is optional and the subscription will continue indefinitely if not provided.
    <p style="text-align:justify">Replace <i>MESSAGE</i> with the message you want to display to your visitor after a successful subscription 
    Note that the message is optional and a default of <b>You will also get a confirmation message in the mail.</b> will be displayed.
    <p>e.g. <b>[paystacksubscribepay target="10000" message="Thanks for subscribing!" plancode="PLN_xxx"]</b>', 
    'paystack_subscribe_pay</p></li>
    </ol>
            </div>
        </div>
    </div>
    </div>
    </div>
    
    ');

}


function paystack_subscribe_pay_options_page()
{

    ?>
    <form action='options.php' method='post'>
        
        <h2>Paystack Subscribe Pay</h2>
        
        <?php
        settings_fields('paystack_subscribe_pay_pluginPage');
        do_settings_sections('paystack_subscribe_pay_pluginPage');
        submit_button();
        ?>
        
    </form>
    <?php

}

function paystack_subscribe_pay_install () {

    $installed_ver = get_option( "paystack_subscribe_pay_db_version" );

    if ( $installed_ver != PAYSTACK_SUBSCRIBE_PAY_DB_VERSION ) {

        $sql = "CREATE TABLE `".PAYSTACK_SUBSCRIBE_PAY_TABLE."` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `firstname` varchar(200) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
          `lastname` varchar(200) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
          `email` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
          `phone` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
          `subscriptioncode` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
          `deliveryaddress` text COLLATE utf8_unicode_ci DEFAULT NULL,
          `debt` DECIMAL(13, 2) NULL,
          `payments` longtext COLLATE utf8_unicode_ci,
          `whensubscribed` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          `internalnotes` text COLLATE utf8_unicode_ci,
          `ip` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        update_option( "paystack_subscribe_pay_db_version", PAYSTACK_SUBSCRIBE_PAY_DB_VERSION );
    }
}

function paystack_subscribe_pay_get_ip_address() {
    foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip); // just to be safe

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
}

function paystack_subscribe_pay_add_subscriber ($subscriber){
    global $wpdb;
    
    $emails = explode(',' , paystack_subscribe_pay_get_alert_emails());
    $has_invalid_email = false;
    foreach($emails as $em){
        if(!filter_var($em, FILTER_VALIDATE_EMAIL)){
            $has_invalid_email = true;
        }
    }
    if(!$has_invalid_email){
        mail(  paystack_subscribe_pay_get_alert_emails(), '[Paystack Subscribe Pay] A new subscriber!', 'Hi,

Just a heads up about a new subscriber to your plan: '.$subscriber->payments[0]->plan_name."

Name: {$subscriber->firstname} {$subscriber->lastname}
Email: {$subscriber->email}
Delivery Address: {$subscriber->deliveryaddress}
".($subscriber->debt ? "To Balance: {$subscriber->debt}" : "")."

Thanks!
        " );
    }
    
    $wpdb->insert( 
        PAYSTACK_SUBSCRIBE_PAY_TABLE, 
        array( 
            'firstname' => $subscriber->firstname, 
            'lastname' => $subscriber->lastname, 
            'email' => $subscriber->email, 
            'phone' => $subscriber->phone, 
            'deliveryaddress' => $subscriber->deliveryaddress, 
            'debt' => $subscriber->debt, 
            'payments' => json_encode($subscriber->payments), 
            'ip' => paystack_subscribe_pay_get_ip_address(), 
        ) 
    );
}

function paystack_subscribe_pay_add_payment ($evt){
    // only add successful payments
    if(strtolower($evt->status) != 'success'){
        return;
    }
    $is_event = $evt->invoice_code ? true : false;
    // get subscriber by email
    global $wpdb;
    $subscriber = $wpdb->get_row( 
        $wpdb->prepare(
            'SELECT * FROM `'.PAYSTACK_SUBSCRIBE_PAY_TABLE.'` WHERE `email` = %s',
            $evt->customer->email
        ),
        OBJECT
    );
    if(!$subscriber){
        //subscriber not found
        return;
    }

    // update by decoding json of payments and adding this
    // payment if no ref matches
    $payments = json_decode($subscriber->payments);
    // payment should be an array of objects
    if(!is_array($payments)){
        //payments not found
        return;
    }
    $tocomp = $is_event ? $evt->reference : $evt->transaction->reference;
    foreach($payments as $p){
        // if the payment had an invoice code, it was an event, else a transaction
        if(($p->invoice_code ? ($p->transaction->reference == $tocomp) : ($p->reference == $tocomp) )){
            // trying to add same reference twice.
            return;
        }
    }
    $payments[] = $evt;
    $subscriber->debt = $subscriber->debt - ($evt->amount/100);
    $subscriber->payments = json_encode($payments);
    
    // update subscriber info
    $wpdb->update( 
        PAYSTACK_SUBSCRIBE_PAY_TABLE, 
        array( 
            'payments' => $subscriber->payments,
            'debt' => $subscriber->debt
        ), 
        array( 'id' => $subscriber->id ), 
        array( 
            '%s',
            '%f'
        ), 
        array( '%d' ) 
    );
    return true;
}

function paystack_subscribe_pay_update_db_check() {
    if ( get_site_option( 'paystack_subscribe_pay_db_version' ) != PAYSTACK_SUBSCRIBE_PAY_DB_VERSION ) {
        paystack_subscribe_pay_install();
    }
}