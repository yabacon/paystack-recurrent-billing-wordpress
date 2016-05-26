<?php
if ((strtoupper($_SERVER['REQUEST_METHOD']) != 'POST' ) || !array_key_exists('HTTP_X_PAYSTACK_SIGNATURE', $_SERVER) ) {
    // only a post with paystack signature header gets our attention
    exit();
}
define( 'SHORTINIT', true );
require(dirname(__DIR__) . '/library/common.php');

// Retrieve the request's body and parse it as JSON
$input = @file_get_contents("php://input");
$event = json_decode($input);

if(!$_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] || ($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] !== hash_hmac('sha512', $input, paystack_recurrent_billing_get_secret_key()))){
  // Someone is trying funny things
  // silently forget
  exit();
}

switch($event->event){
    // subscription.create should update the record for the subscriber's email 
    // with the subscriptioncode
    case 'subscription.create':
        paystack_recurrent_billing_update_subscription_code($event);
        break;

    // subscription.disable should alert them of what happened and 
    // send the outstanding balance
    case 'subscription.disable':
        paystack_recurrent_billing_check_debt_and_notify($event);
        break;

    // invoice.create and invoice.update should update the record for the subscriber's email 
    // and subscriptioncode with the new payment received if paid is true
    case 'invoice.create':
    case 'invoice.update':
        $event->data->paid && paystack_recurrent_billing_add_invoice_payment($event);
        break;

}

// Do something with $event
http_response_code(200);
exit();
