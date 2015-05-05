<?php

require_once 'MCAPI.class.php';
require_once 'config.inc.php'; //contains apikey
require_once 'mc_functions.php';
require_once 'eventsmail.inc.php'; // contains event mail texts

$listid='6fdaf01860';

$api = new MCAPI($apikey);


$ret=$api->campaigns();
if ($api->errorCode){
  echo "Error - Code=".$api->errorCode." Msg=".$api->errorMessage."\n";
} else {
  if(sizeof($ret['data'])) {
    foreach ($ret['data'] as $camp) {
      if($camp['from_email'] === 'andrew.minich@redrockapps.com') {
        echo "id=".$camp['id']."\n";
        echo "\t".$camp['status']." | ".$camp['title']." | ".$camp['subject']."\n";
//        if(substr($camp['title'],0,9) === '201504080') {
//          $try=$api->campaignDelete($camp['id']);
//          if ($api->errorCode){
//            echo "Error - Code=".$api->errorCode." Msg=".$api->errorMessage."\n";
//          } else {
//            echo "id=".$camp['id']." - Deleted\n";
//          }
//        }
      }
    }
  }
}

echo "before:".sizeof($ret['data'])."\n";


//$try=$api->campaignDelete('6a8313b96e');
//$try=$api->campaignPause('6a8313b96e');
//$try=$api->campaignSendTest('8afe0e0149', array('jamini29@gmail.com'),'html');
//$try=$api->campaignSendNow('8afe0e0149');
$try=$api->folders();
if ($api->errorCode){
  echo "Error - Code=".$api->errorCode." Msg=".$api->errorMessage."\n";
} else {
  var_dump($try);
  print_r($try);
}

$ret=$api->campaigns();
if ($api->errorCode){
  echo "Error - Code=".$api->errorCode." Msg=".$api->errorMessage."\n";
} else {
  echo "after:".sizeof($ret['data'])."\n";
}
