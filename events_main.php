<?php

$options=getopt("d:s:");
$appIdSet = isset($options['s']) ? $options['s'] : 'wlr';

define("ROOTDIR", dirname(__FILE__));
chdir(ROOTDIR);

$ini=parse_ini_file("events_main.ini", true);
$DEBUG=$ini['global']['debug'];
$DEV=$ini['global']['development'];

require_once 'inc_common.php';
require_once 'inc_mc_functions.php';
require_once 'inc_mc_api_1_3.php';
require_once 'inc_locales_events_texts.php'; // contains event mail texts

$daysago=$ini['global']['daysago'];
$olddaysago=$ini['global']['olddaysago'];


$mc_list_id=$ini['mailchimp']['list_id'];
$mc_template_id=$ini['mailchimp']['template_id'];
$mc_folder_id=$ini['mailchimp']['folder_id'];




$itogo=array();
$itogo['scriptstartts']=time();

$mc_api = new MCAPI($ini['mailchimp']['apikey']);

$db1ini=$ini["mysql1"];
$db1 = new mysqli($db1ini['host'], $db1ini['username'], $db1ini['password'], $db1ini['db'], $db1ini['port']);
if ($db1->connect_errno) { die("Failed to connect to db1: " . $mysqli->connect_error); }

$db2 = new SQLite3($ini['tranningdb']['file']);

$cbini=$ini["couchdb"];
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
//$fbmails=get_fbusermail($db1, array_keys($advert_keys));
$fbmails=get_fbusermail_uniq($db1, array_keys($advert_keys)); // get from uniq email created by fbEmails_sync2speedUpSearch.php
$db1->close();

$itogo['num_advert_keys_1']=count($advert_keys_1);
$itogo['num_advert_keys_2']=count($advert_keys_2);
$itogo['num_advert_keys_3']=count($advert_keys_3);
$itogo['num_advert_keys']=count($advert_keys);
// get possible for garbage collector free memory
unset($advert_keys_1, $advert_keys_2, $advert_keys_3);

if($DEBUG) echo "Try to get profiles_by_advert_keys\n";
$merged=get_channel_profiles_by_advert_keys($cb, array_keys($advert_keys));

if($DEBUG) echo "Try to get trainings_for_channel\n";
$channel_trainings=get_trainings_for_channel($cb, array_keys($merged),'production');

if($DEBUG) echo "Try to get emails_by_customchannel\n";
$cbemails=get_emails_by_customchannel($cb, array_keys($merged));

if($DEBUG) echo "Try to merge profiles, emails, trainings\n";
$itogo['num_channel_profiles']=count($merged);
foreach($merged as $cc => $merged_cc_item) {
  foreach($merged_cc_item as $aid => $merged_cc_aid_item) {
    // assign emails from couchbase 'emailuser' doc.type
    $merged[$cc][$aid]['cbEmail']=isset($cbemails[$cc][$aid]) ? $cbemails[$cc][$aid]['email'] : '';
    // assign emails from db1 'fbusers'
    $merged[$cc][$aid]['dbEmail']=isset($fbmails[$merged_cc_aid_item['advertIdentificator']]) ? $fbmails[$merged_cc_aid_item['advertIdentificator']]['email'] : '';
    // assign adverts scope (olddaysago, daysago, last)
    $merged[$cc][$aid]['daysago']=isset($advert_keys[$merged_cc_aid_item['advertIdentificator']]) ? $advert_keys[$merged_cc_aid_item['advertIdentificator']]['daysago'] : 0;
    // assign trainings
    if(isset($channel_trainings[$cc][$aid])) $merged[$cc][$aid]['traininglogs']=$channel_trainings[$cc][$aid];
  }
  // unknown appid case
  if(isset($channel_trainings[$cc]['unknown'])) {
    foreach($merged[$cc] as $aid => $merged_cc_aid_item) {
      $merged[$cc]['unknown']['fbEmail']=($merged[$cc][$aid]['fbEmail']!=='') ?
              $merged[$cc][$aid]['fbEmail'] : (
                (isset($merged[$cc]['unknown']['fbEmail']) and $merged[$cc]['unknown']['fbEmail']!=='') ? $merged[$cc]['unknown']['fbEmail'] : ''
              );
      $merged[$cc]['unknown']['cbEmail']=($merged[$cc][$aid]['cbEmail']!=='') ?
              $merged[$cc][$aid]['cbEmail'] : (
                (isset($merged[$cc]['unknown']['cbEmail']) and $merged[$cc]['unknown']['cbEmail']!=='') ? $merged[$cc]['unknown']['cbEmail'] : ''
              );
      $merged[$cc]['unknown']['dbEmail']=($merged[$cc][$aid]['dbEmail']!=='') ?
              $merged[$cc][$aid]['dbEmail'] : (
                (isset($merged[$cc]['unknown']['dbEmail']) and $merged[$cc]['unknown']['dbEmail']!=='') ? $merged[$cc]['unknown']['dbEmail'] : ''
              );
      $merged[$cc]['unknown']['lang']=($merged[$cc][$aid]['lang']!=='en') ?
              $merged[$cc][$aid]['lang'] : (
                (isset($merged[$cc]['unknown']['lang']) and $merged[$cc]['unknown']['lang']!=='en') ? $merged[$cc]['unknown']['lang'] : 'en'
              );
          // assign adverts scope (olddaysago, daysago, last)
     $merged[$cc]['unknown']['daysago']=!isset($merged[$cc]['unknown']['daysago']) ? $merged[$cc][$aid]['daysago'] : (
             ($merged[$cc]['unknown']['daysago']!=='last' and $merged[$cc]['unknown']['daysago'] < $merged[$cc][$aid]['daysago']) ?
             $merged[$cc][$aid]['daysago'] :
             $merged[$cc]['unknown']['daysago']
             );
    }
    $merged[$cc]['unknown']['traininglogs']=$channel_trainings[$cc]['unknown'];
  }
}
// make possible for garbage collector to free memory
unset($advert_keys, $cbemails, $fbmails, $channel_trainings);

$test_emails=array(
//    'andrew.minich@redrockapps.com',
    'jamini29@gmail.com',
//    'inf.kalevich@gmail.com',
//    'igor.pomaz@redrockapps.com',
//    'lp@redrockapps.com',
//    'ekaterina.sazonova@redrockapps.com',
//    '17luba@tut.by',
);

$itogo['agocount']=array();
foreach($merged as $cc => $merged_cc_item) {
  foreach($merged_cc_item as $aid => $merged_cc_aid_item) {
    if(!isset($itogo['agocount'][$merged[$cc][$aid]['daysago']])) $itogo['agocount'][$merged[$cc][$aid]['daysago']]=1;
    else $itogo['agocount'][$merged[$cc][$aid]['daysago']]++;
  }
}
$itigo['appidcount']=array();
$itogo2catched=array();
foreach($merged as $cc => $merged_cc_item) {
  foreach($merged_cc_item as $aid => $merged_cc_aid_item) {
    if(in_array($merged_cc_aid_item['fbEmail'], $test_emails)) print_r($merged_cc_aid_item);
    if(!isset($itigo['appidcount'][$aid])) $itigo['appidcount'][$aid]=1; else $itigo['appidcount'][$aid]++;
    if(in_array($aid, $ini['appIdSet'][$appIdSet]) or $aid==='unknown') { // if in array of searched appId set or appId unknown
      switch($merged_cc_aid_item['daysago']) {
        case $olddaysago :
        // event 5
          // !!! may be check - if finished plan ?
          $itogo2catched[$cc][$aid]=array('event' => 5, 'debugdata'=>$merged_cc_aid_item);
        break;
        case $daysago :
        // event 1, 2, 3, 4
          if(!isset($merged_cc_aid_item['traininglogs'])) { // no trainings found
          // event 1
            $itogo2catched[$cc][$aid]=array('event' => 1, 'debugdata'=>$merged_cc_aid_item);
          } else {
          // event 2, 3, 4
            $itogo2catched[$cc][$aid]=array('event' => get_ago_event($merged_cc_aid_item['traininglogs']), 'debugdata'=>$merged_cc_aid_item);
          }
        break;
        case 'last' :
        // event 6, 7, 8, 9
          if(isset($merged_cc_aid_item['traininglogs'])) {
            // event 9 - has just one training log for this customChannel/appId pair
            if(count($merged_cc_aid_item['traininglogs']) == 1) {
              // event 9 - training was last day ago
              if($merged_cc_aid_item['traininglogs'][0]['timeStamp'] >= $itogo['start_ts_lastday'] and
                 $merged_cc_aid_item['traininglogs'][0]['timeStamp'] < $itogo['start_ts_lastday']+(24*60*60)) {
                $itogo2catched[$cc][$aid]=array('event' => 9, 'debugdata'=>$merged_cc_aid_item);
              } else $itogo2catched[$cc][$aid]=array('event' => 999);
            } else {
              // has more then one training log
              $itogo2catched[$cc][$aid]=array('event' => get_last_event($merged_cc_aid_item['traininglogs'], $itogo['start_ts_lastday']), 'debugdata'=>$merged_cc_aid_item);
            }
          }
        break;
      }
    }
  }
}

foreach($itogo2catched as $cc => $itogo2catched_cc_item) {
  foreach($itogo2catched_cc_item as $aid => $itogo2catched_cc_aid_item) {
    if(!in_array($itogo2catched_cc_aid_item['event'], array(1,2,3,4,5,6,7,8,9))) continue;
    if($merged[$cc][$aid]['fbEmail'] !== '') {
      $itogo2catched[$cc][$aid]['fbEmail']=trim($merged[$cc][$aid]['fbEmail']);
    }
    if($merged[$cc][$aid]['cbEmail'] !== '') {
      $itogo2catched[$cc][$aid]['cbEmail']=trim($merged[$cc][$aid]['cbEmail']);
    }
    if($merged[$cc][$aid]['dbEmail'] !== '') {
      $itogo2catched[$cc][$aid]['dbEmail']=trim($merged[$cc][$aid]['dbEmail']);
    }
    $itogo2catched[$cc][$aid]['lang']=$merged[$cc][$aid]['lang'];
  }
}

// stats
$itogo['byappid']=array();
foreach($itogo2catched as $cc => $itogo2catched_cc_item) {
  foreach($itogo2catched_cc_item as $aid => $itogo2catched_cc_aid_item) {
    if(!in_array($itogo2catched_cc_aid_item['event'], array(1,2,3,4,5,6,7,8,9))) continue;
    if(!isset($itogo['byappid'][$aid][$itogo2catched_cc_aid_item['event']])) $itogo['byappid'][$aid][$itogo2catched_cc_aid_item['event']]=1;
    else $itogo['byappid'][$aid][$itogo2catched_cc_aid_item['event']]++;
    if(!isset($itogo['event'.$itogo2catched_cc_aid_item['event'].'catched'])) $itogo['event'.$itogo2catched_cc_aid_item['event'].'catched']=1;
    else $itogo['event'.$itogo2catched_cc_aid_item['event'].'catched']++;
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

$uniq_emails=array();
foreach($itogo2catched as $cc => $itogo2catched_cc_item) {
  foreach($itogo2catched_cc_item as $aid => $itogo2catched_cc_aid_item) {
    if(!in_array($itogo2catched_cc_aid_item['event'], array(1,2,3,4,5,6,7,8,9))) continue;
    if(!isset($itogo2catched_cc_aid_item['fbEmail']) and !isset($itogo2catched_cc_aid_item['cbEmail']) and !isset($itogo2catched_cc_aid_item['dbEmail'])) continue;
    
    if(isset($itogo2catched_cc_aid_item['fbEmail']) and
             !in_array($itogo2catched_cc_aid_item['fbEmail'], $uniq_emails) and
             chk_email_valid($itogo2catched_cc_aid_item['fbEmail'])) {
      array_push($uniq_emails, $itogo2catched_cc_aid_item['fbEmail']);
      array_push($emails_prepared['Event'.$itogo2catched_cc_aid_item['event']],
                 array('EMAIL' => $itogo2catched_cc_aid_item['fbEmail'], 'mc_language' => $itogo2catched_cc_aid_item['lang'], 'from'=>'fbEmail', 'appId'=>$aid,
                       'debugdata'=>($DEBUG) ? $itogo2catched_cc_aid_item['debugdata'] : '',));
    }
    if(isset($itogo2catched_cc_aid_item['cbEmail']) and
             !in_array($itogo2catched_cc_aid_item['cbEmail'], $uniq_emails) and
             chk_email_valid($itogo2catched_cc_aid_item['cbEmail'])) {
      array_push($uniq_emails, $itogo2catched_cc_aid_item['cbEmail']);
      array_push($emails_prepared['Event'.$itogo2catched_cc_aid_item['event']],
                 array('EMAIL' => $itogo2catched_cc_aid_item['cbEmail'], 'mc_language' => $itogo2catched_cc_aid_item['lang'], 'from'=>'cbEmail', 'appId'=>$aid,
                       'debugdata'=>($DEBUG) ? $itogo2catched_cc_aid_item['debugdata'] : '',));
    }
    if(isset($itogo2catched_cc_aid_item['dbEmail']) and
             !in_array($itogo2catched_cc_aid_item['dbEmail'], $uniq_emails) and
             chk_email_valid($itogo2catched_cc_aid_item['dbEmail'])) {
      array_push($uniq_emails, $itogo2catched_cc_aid_item['dbEmail']);
      array_push($emails_prepared['Event'.$itogo2catched_cc_aid_item['event']],
                 array('EMAIL' => $itogo2catched_cc_aid_item['dbEmail'], 'mc_language' => $itogo2catched_cc_aid_item['lang'], 'from'=>'dbEmail', 'appId'=>$aid,
                       'debugdata'=>($DEBUG) ? $itogo2catched_cc_aid_item['debugdata'] : '',));
    }
  }
}

foreach (array_keys($emails_prepared) as $event)
  $itogo[strtolower($event).'hasemail']=count($emails_prepared[$event]);

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

$rotate_event=array('Event1','Event2','Event3','Event4','Event5','Event6','Event7','Event8','Event9');

// just for testing purposes - filter just emails for real sending
$filter_lang=array('ru','it');
foreach($rotate_event as $event_name)
  foreach ($emails_prepared[$event_name] as $key => $itemval)
    if(!in_array($emails_prepared[$event_name][$key]['mc_language'],$filter_lang)) unset($emails_prepared[$event_name][$key]); // unset all but in $filter_lang

dump2file('log/print_r_dump.txt', print_r($emails_prepared, true));
dump2file('log/var_export_dump.txt', var_export($emails_prepared, true));

//print_r($emails_prepared);

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
  if($DEBUG) echo "Event '".$event_name."' segment match ".$itogo[strtolower($event_name).'segment']." members\n";
  if($itogo[strtolower($event_name).'segment']) {
    $itogo[strtolower($event_name).'campaign_id']=0;
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
$db1 = new mysqli($db1ini['host'], $db1ini['username'], $db1ini['password'], $db1ini['db'], $db1ini['port']);
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
//
$db1->close();
//
$itogo['scriptstopts']=time();
print_r($itogo);

echo "spend time: ".gmdate("H:i:s", $itogo['scriptstopts']-$itogo['scriptstartts'])."\n";

