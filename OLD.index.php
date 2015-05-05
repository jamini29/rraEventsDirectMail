<?php

$options=getopt("d:s:");
$appIdSet = isset($options['s']) ? $options['s'] : 'wlr';

define("ROOTDIR", dirname(__FILE__));
chdir(ROOTDIR);

$ini=parse_ini_file("config.ini", true);
$DEBUG=$ini['global']['debug'];
$DEV=$ini['global']['development'];

require_once 'functions.php';
require_once 'MCAPI.class.php';
//require_once 'config.inc.php'; //contains apikey
require_once 'mc_functions.php';
require_once 'eventsmail.inc.php'; // contains event mail texts


$daysago=$ini['global']['daysago'];
$olddaysago=$ini['global']['olddaysago'];


$mc_list_id=$ini['mailchimp']['list_id'];
$mc_template_id=$ini['mailchimp']['template_id'];
$mc_folder_id=$ini['mailchimp']['folder_id'];




$itogo=array();
$itogo['scriptstartts']=time();

$mc_api = new MCAPI($ini['mailchimp']['apikey']);

$db1ini=$ini["mysql1".($DEV ? "_dev" : "")];
$db1 = new mysqli($db1ini['host'], $db1ini['username'], $db1ini['password'], $db1ini['db']);
if ($db1->connect_errno) { die("Failed to connect to db1: " . $mysqli->connect_error); }

$db2 = new SQLite3($ini['tranningdb']['file']);

$cbini=$ini["couchdb".($DEV ? "_dev" : "")];
$cb = new Couchbase($cbini['hostname'].":".$cbini['port'], $cbini['username'], $cbini['password'], $cbini['database']);


$itogo=array_merge($itogo, get_db_startdaytime($db1,$daysago,$olddaysago));

$trainings=get_training_plans($db2);

if($DEBUG) echo "Try to get advert keys 1\n";
//now -> dummy, prduction -> call get_advert_keys for long time ago - 3-4 weeks
//$advert_keys_1=array();
$advert_keys_1=get_advert_keys($db1, $ini['appIdSet'][$appIdSet], $olddaysago);
if($DEBUG) echo "Try to get advert keys 2\n";
//$advert_keys_2=array();
$advert_keys_2=get_advert_keys($db1, $ini['appIdSet'][$appIdSet], $daysago);
if($DEBUG) echo "Try to get advert keys 3\n";
//$advert_keys_3=array();
$advert_keys_3=get_advert_keys($db1, $ini['appIdSet'][$appIdSet], 'last');

$advert_keys=add_newer_advert_keys(add_newer_advert_keys($advert_keys_1,$advert_keys_2), $advert_keys_3);
if($DEBUG) echo "Try to get fbemails\n";
//$fbmails=array();
$fbmails=get_fbusermail($db1, array_keys($advert_keys));
$db1->close();

$itogo['num_advert_keys_1']=count($advert_keys_1);
$itogo['num_advert_keys_2']=count($advert_keys_2);
$itogo['num_advert_keys_3']=count($advert_keys_3);
$itogo['num_advert_keys']=count($advert_keys);

if($DEBUG) echo "Try to get profiles_by_advert_keys\n";
$channel_profiles=get_profiles_by_advert_keys($cb, array_keys($advert_keys));
if($DEBUG) echo "Try to get trainings_for_channel\n";
$channel_trainings=get_trainings_for_channel($cb, array_keys($channel_profiles));
if($DEBUG) echo "Try to get emails_by_customchannel\n";
//$cbemails=array();
$cbemails=get_emails_by_customchannel($cb, array_keys($channel_profiles));

$itogo['num_channel_profiles']=count($channel_profiles);
$itogo['num_channel_trainings']=count($channel_trainings);

$test_emails=array(
    'andrew.minich@redrockapps.com',
    'jamini29@gmail.com',
    'inf.kalevich@gmail.com',
    'igor.pomaz@redrockapps.com',
);

$itogo2catched=array();
foreach ($channel_profiles as $customchannel => $channel_item) {
  if(in_array($channel_item['fbEmail'], $test_emails)) print_r($channel_item);
  switch($advert_keys[$channel_item['advertIdentificator']]['daysago']) {
    case $olddaysago :
    // event 5
      // !!! may be check - if finished plan ?
      $itogo2catched[$customchannel]=array('event' => 5);
    break;
    case $daysago :
    // event 1, 2, 3, 4
      if(!array_key_exists($customchannel, $channel_trainings)) {
      // event 1
        $itogo2catched[$customchannel]=array('event' => 1);
      } else {
      // event 2, 3, 4
        $itogo2catched[$customchannel]=array('event' => get_ago_event($channel_trainings[$customchannel],$trainings));
      }
    break;
    case 'last' :
    // event 6, 7, 8, 9
      if(array_key_exists($customchannel, $channel_trainings)) {
        if(count($channel_trainings[$customchannel]) == 1) {
          // has just one training log
          $itogo2catched[$customchannel]=array('event' => 9);
        } else {
          // has more then one training log
          $itogo2catched[$customchannel]=array('event' => get_last_event($channel_trainings[$customchannel],$trainings,$itogo['start_ts_lastday'],$cb));
        }
      }
    break;
  }
}

foreach($itogo2catched as $customchannel => $item) {
  if(!in_array($item['event'], array(1,2,3,4,5,6,7,8,9))) continue;
  
  if(array_key_exists($channel_profiles[$customchannel]['advertIdentificator'], $fbmails)) {
    $itogo2catched[$customchannel]['fbdb']=trim($fbmails[$channel_profiles[$customchannel]['advertIdentificator']]['fbemail']);
  }
  if(isset($channel_profiles[$customchannel]['fbEmail'])) {
    $itogo2catched[$customchannel]['fbcb']=trim($channel_profiles[$customchannel]['fbEmail']);
  }
  if(array_key_exists($customchannel, $cbemails)) {
    $itogo2catched[$customchannel]['emailcb']=trim($cbemails[$customchannel]);
  }
  
  if(isset($itogo2catched[$customchannel]['fbdb']) or isset($itogo2catched[$customchannel]['fbcb']) or isset($itogo2catched[$customchannel]['emailcb'])) {
    $itogo2catched[$customchannel]['lang']=isset($channel_profiles[$customchannel]) ? get_lang($channel_profiles[$customchannel]) : 'en';
  }
}


foreach($itogo2catched as $item) {
  if(!in_array($item['event'], array(1,2,3,4,5,6,7,8,9))) continue;  
  if(!isset($itogo['event'.$item['event'].'catched'])) {
    $itogo['event'.$item['event'].'catched']=1;
  } else {
    $itogo['event'.$item['event'].'catched']++;
  }
}

$emails_prepared=array(
    'Event1' => array(),
    'Event2' => array(),
    'Event3' => array(),
    'Event4' => array(),
    'Event5' => array(),
    'Event6' => array(),
    'Event7' => array(),
    'Event8' => array(),
    'Event9' => array(),
    );
//'EMAIL' => '', 'mc_language' => 'en'
$uniq_emails=array();
foreach($itogo2catched as $item) {
  if(!in_array($item['event'], array(1,2,3,4,5,6,7,8,9))) continue;
  if(!isset($item['fbdb']) and !isset($item['fbcb']) and !isset($item['emailcb'])) continue;
  if(isset($item['fbdb']) and !in_array($item['fbdb'], $uniq_emails) and chk_email_valid($item['fbdb'])) {
    array_push($uniq_emails, $item['fbdb']);
    array_push($emails_prepared['Event'.$item['event']], array('EMAIL' => $item['fbdb'], 'mc_language' => $item['lang'], 'from'=>'fbdb'));
  }
  if(isset($item['fbcb']) and !in_array($item['fbcb'], $uniq_emails) and chk_email_valid($item['fbcb'])) {
    array_push($uniq_emails, $item['fbcb']);
    array_push($emails_prepared['Event'.$item['event']], array('EMAIL' => $item['fbcb'], 'mc_language' => $item['lang'], 'from'=>'fbcb'));
  }
  if(isset($item['emailcb']) and !in_array($item['emailcb'], $uniq_emails) and chk_email_valid($item['emailcb'])) {
    array_push($uniq_emails, $item['emailcb']);
    array_push($emails_prepared['Event'.$item['event']], array('EMAIL' => $item['emailcb'], 'mc_language' => $item['lang'], 'from'=>'emailcb'));
  }
}

foreach (array_keys($emails_prepared) as $event) {
  $itogo[strtolower($event).'hasemail']=count($emails_prepared[$event]);
  // just for testing - remove
  //foreach($emails_prepared[$event] as $num => $item) { echo $num."\t".$event."|".$item['mc_language']."|".$item['EMAIL']."|".$item['from']."\n"; }
}

$membertypes=array('subscribed',
                   'unsubscribed',
//                   'cleaned',
//                   'updated',
                  );
// get all members from mailcimp list
$members=array();
foreach($membertypes as $type) {
  $members[$type]=get_all_listmembers($mc_api, $mc_list_id, $type);
}

// clear all group and customsubj info for existing mailchimp list members
$retclear=array();
foreach($membertypes as $type) {
  $itogo[$type.'_init']=count($members[$type]);
  if(count($members[$type])) $retclear[$type]=reset_all_listmembers($mc_api, $mc_list_id, $members[$type]);
}

foreach($membertypes as $type) {
  if(isset($retclear[$type]) and $retclear[$type]['success']) {
    if($DEBUG) echo "Group '".$type."' cleared success: ".$retclear[$type]['return']['update_count']." errors: ".$retclear[$type]['return']['error_count']."\n";
  }
}

//$grlist=array(
////    array('EMAIL' => 'jamini29@gmail.com', 'mc_language' => 'zh'),
////    array('EMAIL' => 'jamini@bn.by', 'mc_language' => 'de'),
//    array('EMAIL' => 'andrew.minich@redrockapps.com', 'mc_language' => 'en'),
////    array('EMAIL' => 'lp@redrockapps.com', 'mc_language' => 'en'),
////    array('EMAIL' => 'ekaterina.sazonova@redrockapps.com', 'FNAME' => 'Ekaterina Sazonova', 'mc_language' => 'ru'),
//);
//

$rotate_event=array('Event1','Event2','Event3','Event4','Event5','Event6','Event7','Event8','Event9');

// just for testing purposes - filter just test emails for real sending
foreach($rotate_event as $event_name) {
  foreach ($emails_prepared[$event_name] as $key => $itemval) {
    if(!in_array($emails_prepared[$event_name][$key]['EMAIL'], $test_emails)) {
      unset($emails_prepared[$event_name][$key]);
    }
  }
}

print_r($emails_prepared);

// here will be rotating for groups from list emails prepared for sending events
// if event array is not empty - set group call for this event
foreach($rotate_event as $event_name) {
  if(count($emails_prepared[$event_name])) {
    $retsetgr=set_listmembers_group($mc_api, $mc_list_id, $emails_prepared[$event_name], $event_name);
    if($retsetgr['success']) {
      if($DEBUG) echo "Group '".$event_name."' added: ".$retsetgr['return']['add_count']." updated: ".$retsetgr['return']['update_count']." errors: ".$retsetgr['return']['error_count']."\n";
    }
  }
}

$titleprefix=gmdate('YmdHis');
foreach($rotate_event as $event_name) {
  $itogo[strtolower($event_name).'segment']=chk_segment_multilang($mc_api, $mc_list_id, $event_name);
  if($itogo[strtolower($event_name).'segment']) {
    $itogo[strtolower($event_name).'campaign_id']=0;
    if($DEBUG) echo "Event '".$event_name."' segment match ".$itogo[strtolower($event_name).'segment']." members\n";
    $itogo[strtolower($event_name).'campaign_id']=create_campaign_multilang($mc_api, $mc_list_id, $event_name, $titleprefix, $mc_template_id, $mc_folder_id);
    if($DEBUG) echo "Event '".$event_name."' campaign_id=".$itogo[strtolower($event_name).'campaign_id']." created\n";
    if(isset($itogo[strtolower($event_name).'campaign_id']) and isset($itogo[strtolower($event_name).'campaign_id'])!=0) {
      $sendsuccess=send_campaign_now($mc_api, $itogo[strtolower($event_name).'campaign_id']);
      if($DEBUG and $sendsuccess) echo "Event '".$event_name."' campaign_id=".$itogo[strtolower($event_name).'campaign_id']." was sent\n";
    }
  }
}

// log results into db
// first step - log itogo
$db1 = new mysqli($db1ini['host'], $db1ini['username'], $db1ini['password'], $db1ini['db']);
if ($db1->connect_errno) { die("Failed to connect to db1: " . $mysqli->connect_error); }
$logid=put_events_log($db1, $itogo);
// now - log all mail to send data for feature use and dublicate sending skips
foreach($rotate_event as $event_name) {
// unremark when mailchimp enables
  if(isset($itogo[strtolower($event_name).'campaign_id']) and $itogo[strtolower($event_name).'campaign_id']!=0) {
    if(count($emails_prepared[$event_name])) {
      $emailsret=put_emails2send($db1, $logid, $emails_prepared[$event_name], $event_name);
    }
  }
}

$db1->close();

$itogo['scriptstopts']=time();
print_r($itogo);

echo "spend time: ".gmdate("H:i:s", $itogo['scriptstopts']-$itogo['scriptstartts'])."\n";
