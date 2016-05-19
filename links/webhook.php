<?php

require(dirname(__DIR__) . '/library/common.php');

// Retrieve the request's body and parse it as JSON
$input = @file_get_contents("php://input");
$event = json_decode($input);
$paystack_signature = '';
$prepend = 'invalid';

// check if there's a X-Paystack-Signature
if(array_key_exists('HTTP_X_PAYSTACK_SIGNATURE', $_SERVER)){
  $paystack_signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'];
}

define('PAYSTACK_SECRET_KEY', 'sk_live_d7e94a1ada7088115b1f1642efe1a337f82fa631');

// echo hash_hmac('sha512', $input, PAYSTACK_SECRET_KEY); // SHA512 = 7
if($paystack_signature === hash_hmac('sha512', $input, PAYSTACK_SECRET_KEY)){
  $prepend = 'valid';
}
file_put_contents ($prepend . '_' . date('YmdHis').'_'.count(scandir(__DIR__)).'.body.json',  $input );
file_put_contents ($prepend . '_' . date('YmdHis').'_'.count(scandir(__DIR__)).'.head.json',  json_encode(getallheaders()) );

// Do something with $event
http_response_code(200);
exit();

// subscription.create should update the record for the subscriber's email 
// with the subscriptioncode

// subscription.disable should alert the email if what happened and 
// send the outstanding balance

// invoice.create should update the record for the subscriber's email 
// and subscriptioncode with the new payment received if paid is true

// invoice.update should update the record for the subscriber's email 
// and subscriptioncode with the new payment received if paid is true
