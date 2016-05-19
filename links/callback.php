<?php

require(dirname(__DIR__) . '/library/common.php');

// all form submissions are sent here via AJAX 
// or a redirect if AJAX fails
// along with trxref so we can load payment details
$getObj = json_decode(json_encode($_GET));
if(!($getObj->trxref && $getObj->subscriber)){
    // only a get with subscriber and trxref is welcome
    die();
}

// verify transaction
$paystack = new Paystack(paystack_subscribe_pay_get_secret_key());
$trx = $paystack->transaction->verify(['reference'=>$getObj->trxref]);
if(!$trx->status){
    // only a successful API call is welcome
    die('status false');
}
$tx = $trx->data;
if(!(strtolower($tx->status) === 'success')){
    // only a successful transaction is welcome
    die('success not');
}

$tx->plan_name = $paystack->plan($tx->plan)->data->name;

// add transaction details to subscriber object
$subscriber = new stdClass();
$subscriber->firstname = $getObj->subscriber->firstname; 
$subscriber->lastname = $getObj->subscriber->lastname; 
$subscriber->email = $getObj->subscriber->email; 
$subscriber->deliveryaddress = $getObj->subscriber->deliveryaddress; 
$subscriber->phone = $getObj->subscriber->phone; 
$subscriber->debt = $getObj->cost ? ($getObj->cost - ($tx->amount/100)) : null;
$subscriber->payments = [$tx];

paystack_subscribe_pay_add_subscriber($subscriber);

echo "Subscription Successful.";