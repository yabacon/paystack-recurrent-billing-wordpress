<?php

require(dirname(__DIR__) . '/library/common.php');

// format = csv or json, default csv
$format = array_key_exists('json', $_GET) ? 'json' : 'csv';
// get a dump of data in subscribers table
$subscribers = paystack_recurrent_billing_get_all_subscribers();

if($format==='json'){
    header('Content-Type: application/json');
    header('Content-disposition: attachment; filename="subscribers.json"');
    foreach($subscribers as $s){
        $s->payments = json_decode($s->payments);
    }
    echo json_encode($subscribers);
}
if($format==='csv'){
    header('Content-Type: text/csv');
    header('Content-disposition: attachment; filename="subscribers.csv"');
    $str = '';
    $row = [];
    $colnames_added = false;
    foreach($subscribers as $s){
        $row = [];
        $vars = get_object_vars($s);
        if(!$colnames_added){
            $colnames = array_keys($vars);
            // first row is col names
            foreach($colnames as $c){
                $row[] = '\'' . str_replace('\'', "\\".'\'', $c) . '\'';
            }
            // add row
            $str .= implode(',', $row);
            $row = [];
            $colnames_added = true;
        }
        foreach($vars as $v){
            $row[] = '\'' . str_replace('\'', "\\".'\'', $v) . '\'';
        }
        $str .= "\n" . implode(',', $row);
        $row = [];
    }
    echo $str;
}

