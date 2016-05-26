<?php

if(!defined('ABSPATH')){
    // use $wpdb directly
    require_once( dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php' );
}
require(__DIR__ . '/Paystack.php');
global $wpdb;

define('PAYSTACK_RECURRENT_BILLING_TABLE', $wpdb->prefix . "paystack_recurrent_billing");
define('PAYSTACK_RECURRENT_BILLING_CODES_TABLE', $wpdb->prefix . "paystack_recurrent_billing_codes");
define('PAYSTACK_RECURRENT_BILLING_DB_VERSION', "1.0");

function paystack_recurrent_billing_verify_short_code($atts)
{
    $toret = new stdClass();
    
    $toret->buttontext = (is_array($atts) && array_key_exists('buttontext', $atts)) ? $atts['buttontext'] : "Subscribe";
    $toret->target = (is_array($atts) && array_key_exists('target', $atts)) ? $atts['target'] : null;
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

function paystack_recurrent_billing_get_public_key()
{
    $options = get_option( 'paystack_recurrent_billing_settings', array('paystack_recurrent_billing_public_key'=>""));
    return trim($options['paystack_recurrent_billing_public_key']);
}

function paystack_recurrent_billing_get_alert_emails()
{
    $options = get_option( 'paystack_recurrent_billing_settings', array('paystack_recurrent_billing_alert_emails'=>""));
    return trim($options['paystack_recurrent_billing_alert_emails']);
}

function paystack_recurrent_billing_get_alert_email_sender_name()
{
    $options = get_option( 'paystack_recurrent_billing_settings', array('paystack_recurrent_billing_get_alert_email_sender_name'=>"Paystack Recurrent Billing on " . get_bloginfo( 'name', 'display' )));
    return trim($options['paystack_recurrent_billing_get_alert_email_sender_name']);
}

function paystack_recurrent_billing_get_alert_email_sender()
{
    $options = get_option( 'paystack_recurrent_billing_settings', array('paystack_recurrent_billing_alert_email_sender'=>get_bloginfo( 'admin_email', 'display' )));
    return trim($options['paystack_recurrent_billing_alert_email_sender']);
}

function paystack_recurrent_billing_get_secret_key()
{
    $options = get_option( 'paystack_recurrent_billing_settings', array('paystack_recurrent_billing_secret_key'=>""));
    return trim($options['paystack_recurrent_billing_secret_key']);
}

function paystack_recurrent_billing_form($atts)
{
    $att = paystack_recurrent_billing_verify_short_code($atts);

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
            target: \''.$att->target.'\',
            message: \''.addslashes($att->message).'\'
        };
        paystackHandler = PaystackPop.setup({
          key: \''.paystack_recurrent_billing_get_public_key().'\',
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
            $.get( callbackurl ).fail(function() {
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

function paystack_recurrent_billing_action_links($links) {
  /* Add link to settings page under woo-commerce */
  $links[] = '<a href="' . esc_url(get_admin_url(null, 'options-general.php?page=paystack_recurrent_billing')) . '">Settings</a>';
  /* Add link to settings page on paystack dashboard */
  $links[] = '<a target="_blank" href="https://dashboard.paystack.co/#/settings/developer" target="_blank">Paystack Dashboard</a>';
  return $links;
}

function paystack_recurrent_billing_start_session() {
    if(!session_id()) {
        session_start();
    }
}

function paystack_recurrent_billing_add_admin_menu()
{

    add_options_page('Paystack Recurrent Billing', 'Paystack Recurrent Billing', 'manage_options', 'paystack_recurrent_billing', 'paystack_recurrent_billing_options_page');

}


function paystack_recurrent_billing_settings_init()
{

    register_setting('paystack_recurrent_billing_pluginPage', 'paystack_recurrent_billing_settings');

    add_settings_section(
        'paystack_recurrent_billing_pluginPage_section',
        __('', 'paystack_recurrent_billing'),
        'paystack_recurrent_billing_settings_section_callback',
        'paystack_recurrent_billing_pluginPage'
    );

    add_settings_field(
        'paystack_recurrent_billing_alert_email_sender',
        __('Enter an email to use as sender for alerts', 'paystack_recurrent_billing'),
        'paystack_recurrent_billing_alert_email_sender_render',
        'paystack_recurrent_billing_pluginPage',
        'paystack_recurrent_billing_pluginPage_section'
    );

    add_settings_field(
        'paystack_recurrent_billing_alert_email_sender_name',
        __('Enter a name to use as sender for alerts', 'paystack_recurrent_billing'),
        'paystack_recurrent_billing_alert_email_sender_name_render',
        'paystack_recurrent_billing_pluginPage',
        'paystack_recurrent_billing_pluginPage_section'
    );

    add_settings_field(
        'paystack_recurrent_billing_alert_emails',
        __('Enter (an) email(s) to be alerted for every event separated by a comma', 'paystack_recurrent_billing'),
        'paystack_recurrent_billing_alert_emails_render',
        'paystack_recurrent_billing_pluginPage',
        'paystack_recurrent_billing_pluginPage_section'
    );

    add_settings_field(
        'paystack_recurrent_billing_secret_key',
        __('Enter your paystack secret key', 'paystack_recurrent_billing'),
        'paystack_recurrent_billing_secret_key_render',
        'paystack_recurrent_billing_pluginPage',
        'paystack_recurrent_billing_pluginPage_section'
    );

    add_settings_field(
        'paystack_recurrent_billing_public_key',
        __('Enter your paystack public key', 'paystack_recurrent_billing'),
        'paystack_recurrent_billing_public_key_render',
        'paystack_recurrent_billing_pluginPage',
        'paystack_recurrent_billing_pluginPage_section'
    );


}

function paystack_recurrent_billing_public_key_render()
{

    $options = get_option('paystack_recurrent_billing_settings');
    ?>
    <input type='text' name='paystack_recurrent_billing_settings[paystack_recurrent_billing_public_key]' 
    value='<?php echo $options['paystack_recurrent_billing_public_key']; ?>' size="50">
    <br><i>Obtain from: <a target="_blank" href="https://dashboard.paystack.co/#/settings/developer" 
    target="_blank">Paystack Dashboard</a></i>
    <?php

}

function paystack_recurrent_billing_secret_key_render()
{

    $options = get_option('paystack_recurrent_billing_settings');
    ?>
    <input type='text' name='paystack_recurrent_billing_settings[paystack_recurrent_billing_secret_key]' 
    value='<?php echo $options['paystack_recurrent_billing_secret_key']; ?>' size="50">
    <br><i>Obtain from: <a target="_blank" href="https://dashboard.paystack.co/#/settings/developer" 
    target="_blank">Paystack Dashboard</a></i>
    <?php

}

function paystack_recurrent_billing_alert_email_sender_render()
{

    $options = get_option('paystack_recurrent_billing_settings');
    ?>
    <input type='text' name='paystack_recurrent_billing_settings[paystack_recurrent_billing_alert_email_sender]' 
    value='<?php echo $options['paystack_recurrent_billing_alert_email_sender']; ?>' size="50">
    <br><i>Be sure to enter a valid Email</i>
    <?php

}

function paystack_recurrent_billing_alert_email_sender_name_render()
{

    $options = get_option('paystack_recurrent_billing_settings');
    ?>
    <input type='text' name='paystack_recurrent_billing_settings[paystack_recurrent_billing_alert_email_sender_name]' 
    value='<?php echo $options['paystack_recurrent_billing_alert_email_sender_name']; ?>' size="50">
    <br><i>Be sure to enter a name</i>
    <?php

}

function paystack_recurrent_billing_alert_emails_render()
{

    $options = get_option('paystack_recurrent_billing_settings');
    ?>
    <input type='text' name='paystack_recurrent_billing_settings[paystack_recurrent_billing_alert_emails]' 
    value='<?php echo $options['paystack_recurrent_billing_alert_emails']; ?>' size="50">
    <br><i>Be sure to enter valid emails separated by a comma</i>
    <?php

}


function paystack_recurrent_billing_settings_section_callback()
{

    echo __('
   <div class="wrap">
   <div class="postbox-container og_left_col">
        <div id="poststuff">
            <div class="postbox">
                <div style="
    padding: 20px;
">
                <h3 id="export">Export Data</h3>
                <p>Export Data: <a href="'.wp_nonce_url(plugins_url( 'links/export.php', __DIR__ ), 'export_csv', 'link_clicked').'" 
                target="_blank">CSV</a>&nbsp;&nbsp;<a href="'.wp_nonce_url(plugins_url( 'links/export.php', __DIR__ ), 'export_json', 'link_clicked').'"
                target="_blank">JSON</a></p>
                <h3 id="settings">Setup Instructions</h3>
                
    <ol>
    <li>Set your Live WebHook URL here: <a target="_blank" 
    href="https://dashboard.paystack.co/#/settings/developer" target="_blank">Paystack
    Dashboard</a> to: <pre>'.plugins_url( 'links/webhook.php', __DIR__ ).'</pre></li>
    <li>Configure the plugin by filling the Alert Emails, Paystack Secret Key and Paystack Public Key fields below.</li>
    <li>Include the shortcode: <b>[paystackrecurrentbilling 
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
    <p>e.g. <b>[paystackrecurrentbilling target="10000" message="Thanks for subscribing!" plancode="PLN_xxx"]</b>', 
    'paystack_recurrent_billing</p></li>
    </ol>
            </div>
        </div>
    </div>
    </div>
    </div>
    
    ');

}


function paystack_recurrent_billing_options_page()
{

    ?>
    <form action='options.php' method='post'>
        
        <h2>Paystack Recurrent Billing</h2>
        
        <?php
        settings_fields('paystack_recurrent_billing_pluginPage');
        do_settings_sections('paystack_recurrent_billing_pluginPage');
        submit_button();
        ?>
        
    </form>
    <?php

}

function paystack_recurrent_billing_install () {

    $installed_ver = get_option( "paystack_recurrent_billing_db_version" );

    if ( $installed_ver != PAYSTACK_RECURRENT_BILLING_DB_VERSION ) {

        $sql = "CREATE TABLE `".PAYSTACK_RECURRENT_BILLING_TABLE."` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
        
        CREATE TABLE `".PAYSTACK_RECURRENT_BILLING_CODES_TABLE."` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `subscriptioncode` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
          `customercode` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
          `plancode` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
          `used` TINYINT(1) NOT NULL DEFAULT 0,
          `whensubscribed` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
        ";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        update_option( "paystack_recurrent_billing_db_version", PAYSTACK_RECURRENT_BILLING_DB_VERSION );
    }
}

function paystack_recurrent_billing_get_ip_address() {
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

function paystack_recurrent_billing_alert_them ($subject, $message){
    $emails = explode(',' , paystack_recurrent_billing_get_alert_emails());
    $has_invalid_email = false;
    foreach($emails as $em){
        if(!filter_var($em, FILTER_VALIDATE_EMAIL)){
            $has_invalid_email = true;
        }
    }

    $headers = "MIME-Version: 1.0\r\n";
    $headers.= "From: =?utf-8?Q?" . quoted_printable_encode(
            ( paystack_recurrent_billing_get_alert_email_sender_name() ?: "Paystack Recurrent Billing Plugin")
        ) . "?= <".
            ( 
                filter_var(paystack_recurrent_billing_get_alert_email_sender(), FILTER_VALIDATE_EMAIL) ? 
                    paystack_recurrent_billing_get_alert_email_sender() : 'support@paystack.com'.">\r\n";
    $headers.= "Content-Type: text/plain;charset=utf-8\r\n";
    $headers.= "X-Mailer: PHP/" . phpversion();

    if(!$has_invalid_email){
        mail(  paystack_recurrent_billing_get_alert_emails(), '[Paystack Recurrent Billing] '.$subject, $message, $headers);
    }
}

function paystack_recurrent_billing_add_subscriber ($subscriber){
    global $wpdb;
    paystack_recurrent_billing_alert_them('A new subscriber!','Hi,

Just a heads up about a new subscriber to your plan: '.$subscriber->payments[0]->plan_name."

Name: {$subscriber->firstname} {$subscriber->lastname}
Email: {$subscriber->email}
Delivery Address: {$subscriber->deliveryaddress}
".($subscriber->debt ? "To Balance: {$subscriber->debt}" : "")."

Thanks!
        " );
    $wpdb->insert( 
        PAYSTACK_RECURRENT_BILLING_TABLE, 
        array( 
            'firstname' => $subscriber->firstname, 
            'lastname' => $subscriber->lastname, 
            'email' => $subscriber->email, 
            'phone' => $subscriber->phone, 
            'deliveryaddress' => $subscriber->deliveryaddress, 
            'subscriptioncode' => $subscriber->subscriptioncode, 
            'debt' => $subscriber->debt, 
            'payments' => json_encode($subscriber->payments), 
            'ip' => paystack_recurrent_billing_get_ip_address(), 
        ) 
    );
}

function paystack_recurrent_billing_add_invoice_payment ($evt){
    global $wpdb;
    // only add successful payments
    if(strtolower($evt->data->status) != 'success'){
        return;
    }

    $subscriber = paystack_recurrent_billing_get_subscriber_by_code($evt);
    if(!$subscriber){
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
    $tocomp = $evt->data->transaction->reference;
    foreach($payments as $p){
        // if the payment had an invoice code, it was an event, else a transaction
        if(($p->invoice_code ? ($p->data->transaction->reference == $tocomp) : ($p->data->reference == $tocomp) )){
            // trying to add same reference twice.
            return;
        }
    }
    $payments[] = $evt;
    $disableit = false;
    $olddebt = $subscriber->debt;
    $subscriber->debt = $subscriber->debt ? ($subscriber->debt - ($evt->data->amount/100)) : null;

    if($olddebt && ($subscriber->debt<=0)){
        // debt fulfilled
        $paystack = new Paystack(paystack_recurrent_billing_get_secret_key());
        $subscriptiondata = $paystack->subscription($evt->data->subscription->subscription_code);
        // disable subscription at Paystack
        $disabled = $paystack->subscription->disable(['code'=>$subscriptiondata->data->subscription_code, 'token'=>$subscriptiondata->data->email_token ]);
        paystack_recurrent_billing_alert_them('A subscriber\'s payment has been completed!','Hi,

Just a heads up about a subscriber to your plan: '.$subscriber->payments[0]->plan_name." who has completed their payment.

Name: {$subscriber->firstname} {$subscriber->lastname}
Email: {$subscriber->email}
Delivery Address: {$subscriber->deliveryaddress}

Thanks!" );
    }
    $subscriber->payments = json_encode($payments);

    // update subscriber info
    $wpdb->update( 
        PAYSTACK_RECURRENT_BILLING_TABLE, 
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

function paystack_recurrent_billing_get_subscription_code($plancode, $customercode){
    global $wpdb;
    $subscriptioncode = $wpdb->get_var( 
        $wpdb->prepare(
            'SELECT `subscriptioncode` FROM `'.PAYSTACK_RECURRENT_BILLING_CODES_TABLE.'` 
            WHERE `plancode` = %s AND `customercode` = %s AND `used`=0',
            $plancode, $customercode
        )
    );
    // update subscriber info
    $wpdb->update( 
        PAYSTACK_RECURRENT_BILLING_CODES_TABLE, 
        array( 
            'used' => 1
        ), 
        array( 'subscriptioncode' => $subscriptioncode ), 
        array( 
            '%s'
        ), 
        array( '%s' ) 
    );
    return $subscriptioncode;
}

function paystack_recurrent_billing_get_subscriber_by_code ($evt){
    // get subscriber by code
    $subcode = $evt->data->subscription_code ? : $evt->data->subscription->subscription_code;
    global $wpdb;
    $subscriber = $wpdb->get_row( 
        $wpdb->prepare(
            'SELECT * FROM `'.PAYSTACK_RECURRENT_BILLING_TABLE.'` WHERE `subscriptioncode` = %s',
            $subcode
        ),
        OBJECT
    );
    if(!$subscriber){
        //subscriber not found
        paystack_recurrent_billing_alert_them('Subscriber Not found!','Hi,

Just a heads up about a new subscriber to your plan: '.$evt->data->plan->name." was not found in the database.
This was probably due to network connectivity issues.
Here's their details so you may follow up:

Name: {$evt->data->customer->first_name} {$evt->data->customer->first_name}
Email: {$evt->data->customer->email}
Phone: {$evt->data->customer->phone}

Thanks!
        " );
        return;
    }
    return $subscriber;
}

function paystack_recurrent_billing_get_all_subscribers (){
    // get subscribers
    global $wpdb;
    return $wpdb->get_results( 
            'SELECT * FROM `'.PAYSTACK_RECURRENT_BILLING_TABLE.'` ORDER BY id DESC'
    );
}

function paystack_recurrent_billing_get_subscriber_by_email_no_code ($evt, $notify=false){
    // get subscriber by email who has no subscription code
    global $wpdb;
    $subscriber = $wpdb->get_row( 
        $wpdb->prepare(
            'SELECT * FROM `'.PAYSTACK_RECURRENT_BILLING_TABLE.'` WHERE `email` = %s AND  and `subscription_code` IS NULL',
            $evt->data->customer->email
        ),
        OBJECT
    );
    if(!$subscriber && $notify){
        //subscriber not found
        paystack_recurrent_billing_alert_them('Subscriber Not found!','Hi,

Just a heads up about a new subscriber to your plan: '.$evt->data->plan->name." who was not found in the database.
This was probably due to network connectivity issues.
Here's their details so you may follow up:

Name: {$evt->data->customer->first_name} {$evt->data->customer->first_name}
Email: {$evt->data->customer->email}
Phone: {$evt->data->customer->phone}

Thanks!" );
        return;
    }
    return $subscriber;
}

function paystack_recurrent_billing_check_debt_and_notify ($evt){
    global $wpdb;
    $subscriber = paystack_recurrent_billing_get_subscriber_by_code($evt);
    if(!$subscriber){
        return;
    }

    paystack_recurrent_billing_alert_them('A subscriber\'s subscription has been disabled!','Hi,

Just a heads up about a subscriber to your plan: '.$subscriber->payments[0]->plan_name." 
who has disabled their subscription.

Name: {$subscriber->firstname} {$subscriber->lastname}
Email: {$subscriber->email}
Delivery Address: {$subscriber->deliveryaddress}
".($subscriber->debt ? "To Balance: {$subscriber->debt}" : "")."

Thanks!" );
}


function paystack_recurrent_billing_update_subscription_code ($evt){
    global $wpdb;
    $wpdb->insert( 
        PAYSTACK_RECURRENT_BILLING_CODES_TABLE, 
        array( 
            'plancode' => $evt->data->plan->plan_code, 
            'customercode' => $evt->data->customer->customer_code, 
            'subscriptioncode' => $evt->data->subscription_code
        ) 
    );

    $subscriber = paystack_recurrent_billing_get_subscriber_by_email_no_code($evt);
    if($subscriber){
        // update subscriber info
        $wpdb->update( 
            PAYSTACK_RECURRENT_BILLING_TABLE, 
            array( 
                'subscriptioncode' => $evt->subscription_code
            ), 
            array( 'id' => $subscriber->id ), 
            array( 
                '%s'
            ), 
            array( '%d' ) 
        );
        return true;
    }
}

function paystack_recurrent_billing_update_db_check() {
    if ( get_site_option( 'paystack_recurrent_billing_db_version' ) != PAYSTACK_RECURRENT_BILLING_DB_VERSION ) {
        paystack_recurrent_billing_install();
    }
}
