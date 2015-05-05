<?php


function get_db_startdaytime($link,$daysago,$olddaysago) {
  $query="select ".
          "DATE_FORMAT(NOW(),'%Y-%m-%d %H:%i:%s') as start_time, ".
          "DATE_FORMAT(NOW(),'%Y-%m-%d 00:00:00') as start_time_day, ".
          "DATE_SUB(DATE_FORMAT(NOW(),'%Y-%m-%d 00:00:00'), INTERVAL 1 DAY) as start_time_lastday, ".
          "DATE_SUB(DATE_FORMAT(NOW(),'%Y-%m-%d 00:00:00'), INTERVAL ".$daysago." DAY) as start_time_daysago, ".
          "DATE_SUB(DATE_FORMAT(NOW(),'%Y-%m-%d 00:00:00'), INTERVAL ".$olddaysago." DAY) as start_time_olddaysago, ".
          "UNIX_TIMESTAMP(DATE_SUB(DATE_FORMAT(NOW(),'%Y-%m-%d 00:00:00'), INTERVAL 1 DAY)) as start_ts_lastday ";
          
  if(!$result=$link->query($query)){
    die('Query error ['.$link->error.']');
  }
  $ret=$result->fetch_assoc();
  if(isset($result)) $result->free();
  return $ret;
}

function get_advert_keys($link, $appidset, $daysago) {
  $advert_keys=array();
  if(is_numeric($daysago)) {
    $query="select `idfa`,`lastlogintime` from `idfausers` ".
              "where `appId` in ('".implode("','", $appidset)."') ".
              "and (`lastlogintime` between ".
                "DATE_SUB(DATE_FORMAT(NOW(),'%Y-%m-%d 00:00:00'), INTERVAL ".$daysago." DAY) ".
                "and ".
                "DATE_SUB(DATE_FORMAT(NOW(),'%Y-%m-%d 23:59:59'), INTERVAL ".$daysago." DAY) ".
              ") ";
  } elseif($daysago === 'last') {
    $query="select `idfa`,`lastlogintime` from `idfausers` ".
              "where `appId` in ('".implode("','", $appidset)."') ".
              "and (`lastlogintime` > DATE_SUB(DATE_FORMAT(NOW(),'%Y-%m-%d 00:00:00'), INTERVAL 1 DAY) ".
              ") ";
  }
  if(!$result=$link->query($query)){
    die('Query error ['.$link->error.']');
  }
  while($row=$result->fetch_assoc()){
    $advert_keys[$row['idfa']]=array(
        'lastlogintime' => $row['lastlogintime'],
        'daysago' => $daysago,
        );
    //array_push($advert_keys, $row['idfa']);
  }
  if(isset($result)) $result->free();
  return $advert_keys;
}


function get_fbusermail($link, $keys) {
  $ret=array();
  $query="select distinct `idfa`, `email`, `name` from `fbusers` where `email`!='' and `idfa`!=''";
  if(!$result=$link->query($query)){
    die('Query error ['.$link->error.']');
  }
  if($result->num_rows) while($row=$result->fetch_assoc()) {
    if(in_array($row['idfa'], $keys)) {
      $ret[$row['idfa']]=array(
          'fbemail' => $row['email'],
          'fbname' => $row['name'],
          );
    }
  }
  if(isset($result)) $result->free();
  return $ret;
}

function add_newer_advert_keys($ad1,$ad2) {
  foreach(array_keys($ad2) as $key) {
      $ad1[$key]=$ad2[$key];
  }
  return $ad1;
}

function get_profiles_by_advert_keys($cblink, $adverts) {
  global $DEBUG;
  $channel_results=array();
  $chunk_size=100;
  $i=0;
  foreach (array_chunk($adverts, $chunk_size) as $advert_keys_chunk) {
    $cb_result = $cblink->view('directemail', 'advertIdentificator', array('keys' => $advert_keys_chunk));
    if($DEBUG) echo "advertIdentificator chunk #".$i++."\n";
    if(isset($cb_result, $cb_result['total_rows'], $cb_result['rows'])) {
      if($DEBUG) echo "advertIdentificator total_rows=".$cb_result['total_rows']." advertIdentificator rows=".count($cb_result['rows'])."\n";
      foreach($cb_result['rows'] as &$res_item) {
        $rapp_profile_type=array();
        $rapp_profile_type['doc_id'] = $res_item['id'];
        $rapp_profile_type['advertIdentificator'] = $res_item['key'];
        if(isset($res_item['value']['langCode'])) $rapp_profile_type['langCode'] = $res_item['value']['langCode'];
        if(isset($res_item['value']['preferedLangCodes'])) $rapp_profile_type['preferedLangCodes'] = $res_item['value']['preferedLangCodes'];
        if(isset($res_item['value']['pushIdentificator'])) $rapp_profile_type['pushIdentificator'] = $res_item['value']['pushIdentificator'];
        if(isset($res_item['value']['secondsFromGMT'])) $rapp_profile_type['secondsFromGMT'] = $res_item['value']['secondsFromGMT'];
        if(isset($res_item['value']['appId'])) $rapp_profile_type['appId'] = $res_item['value']['appId'];
        if(isset($res_item['value']['fbEmail'])) $rapp_profile_type['fbEmail'] = $res_item['value']['fbEmail'];
        $channel_results[$res_item['value']['customChannels']]=$rapp_profile_type;
      }
    }
  }
  return $channel_results;
}

function get_trainings_for_channel($cblink, $channels) {
  global $DEBUG;
  global $trainings;
  $channel_trainings=array();
  $chunk_size=100;
  $i=0;
  foreach (array_chunk($channels, $chunk_size) as $traininglog_key_chunk) {
    $cb_result = $cblink->view('directemail', 'lasttranninglog', array('keys' => $traininglog_key_chunk));
    if($DEBUG) echo "lasttranninglog chunk #".$i++."\n";
    if(isset($cb_result, $cb_result['total_rows'], $cb_result['rows'])) {
      if($DEBUG) echo "lasttranninglog total_rows=".$cb_result['total_rows']." lasttranninglog rows=".count($cb_result['rows'])."\n";
      foreach($cb_result['rows'] as &$res_item) {
        if(!isset($channel_trainings[$res_item['key']])) {
          $channel_trainings[$res_item['key']]=array();
        }
        array_push($channel_trainings[$res_item['key']], array(
            'timestamp' => $res_item['value']['timeStamp'],
            'trainingid' => $res_item['value']['trainingId'],
            'appId' => isset($res_item['value']['appId']) ? $res_item['value']['appId'] : '',
            'id' => $res_item['id'],
            'trainingtype' => $trainings[$res_item['value']['trainingId']]['trainingtype'],
            'week' => $trainings[$res_item['value']['trainingId']]['week'],
            ));
      }
    }
  }
  return $channel_trainings;
}

function get_emails_by_customchannel($cblink, $channels) {
  global $DEBUG;
  $email_results=array();
  $chunk_size=100;
  $i=0;
  foreach (array_chunk($channels, $chunk_size) as $channels_chunk) {
    $cb_result = $cblink->view('custchannelemails', 'emails', array('keys' => $channels_chunk));
    if($DEBUG) echo "emailsByChannel chunk #".$i++."\n";
    if(isset($cb_result, $cb_result['total_rows'], $cb_result['rows'])) {
      if($DEBUG) echo "emailsByChannel total_rows=".$cb_result['total_rows']." emailsByChannel rows=".count($cb_result['rows'])."\n";
      foreach($cb_result['rows'] as &$res_item) {
        $email_results[$res_item['key']]=$res_item['value'];
      }
    }
  }
  return $email_results;
}

function get_cb_doc($cblink, $id) {
  $ret=array();
  $result = $cblink->get($id);
  if(isset($result)) {
    $ret=$result;
    $ret=json_decode($result, true);
  }
  return $ret;
}

function get_training_plans($link) {
  $trainings=array();
  $db2_results = $link->query("select * from workingdays");
  while ($row = $db2_results->fetchArray(SQLITE3_ASSOC)) {
    $trainings[$row['id']]=$row;
    if(!isset($trainings['stats'][$row['trainingtype']])) {
      $trainings['stats'][$row['trainingtype']]=$row['week'];
    } elseif($trainings['stats'][$row['trainingtype']] < $row['week']) {
      $trainings['stats'][$row['trainingtype']]=$row['week'];
    }
    
    if(!isset($trainings['lasts'][$row['trainingtype']][$row['week']])) {
      $trainings['lasts'][$row['trainingtype']][$row['week']]=$row['id'];
    } elseif ($trainings['lasts'][$row['trainingtype']][$row['week']] < $row['id']) {
      $trainings['lasts'][$row['trainingtype']][$row['week']]=$row['id'];
    }
  }
    
  return $trainings;
}

function mk_ago_planevents($weeks) {
  $planevents=array();
  switch ($weeks) {
//    case 3:
//      $planevents['ago']=array('2' => array(0),'3' => array(1,2),'4' => array(3),);
//      break;
//    case 6:
//      $planevents['ago']=array('2' => array(0,1,2),'3' => array(3,4),'4' => array(5,6),);
//      break;
    case 7:
      $planevents['ago']=array('2' => array(0,1,2),'3' => array(3,4,5),'4' => array(6,7),);
      break;
//    case 9:
//      $planevents['ago']=array('2' => array(0,1,2,3),'3' => array(4,5,6,7),'4' => array(8,9),);
//      break;
//    case 11:
//      $planevents['ago']=array('2' => array(0,1,2,3,4),'3' => array(5,6,7,8),'4' => array(9,10,11),);
//      break;
//    case 15:
//      $planevents['ago']=array('2' => array(0,1,2,3,4,5),'3' => array(6,7,8,9,10),'4' => array(11,12,13,14,15),);
//      break;
  }
  return $planevents;
}

function mk_last_planevents($weeks) {
  $planevents=array();
  switch ($weeks) {
    case 3:
      $planevents['last']=array('6' => 0,'7' => 1,'8' => 3,);
      break;
//    case 6:
//      $planevents['last']=array('6' => 0,'7' => 3,'8' => 6,);
//      break;
    case 7:
      $planevents['last']=array('6' => 0,'7' => 3,'8' => 7,);
      break;
    case 9:
      $planevents['last']=array('6' => 0,'7' => 4,'8' => 9,);
      break;
    case 11:
      $planevents['last']=array('6' => 0,'7' => 5,'8' => 11,);
      break;
    case 15:
      $planevents['last']=array('6' => 0,'7' => 7,'8' => 15,);
      break;
  }
  return $planevents;
}


function get_ago_event($channeltrainings,$trainings) {
  $ret=999;
  $lastdata=array('timestamp' => 0, 'trainingid' => 0, 'id' => 0,);
  foreach($channeltrainings as $trainingdata) {
    if($trainingdata['timestamp'] > $lastdata['timestamp']) {
      $lastdata=$trainingdata;
    } elseif ($trainingdata['timestamp'] == $lastdata['timestamp']) {
      if($trainingdata['trainingid'] > $lastdata['trainingid']) {
        $lastdata=$trainingdata;
      }
    }
  }
  $plan=mk_ago_planevents($trainings['stats'][$lastdata['trainingtype']]);
  if(isset($plan['ago'])) {
    foreach($plan['ago'] as $eventnum => $weeks) {
      if(in_array($lastdata['week'], $weeks)) $ret=$eventnum;
    }
  }
  return $ret;
}

function get_last_event($channeltrainings,$trainings,$sts,$cblink) {
  $ret=999;
  $fts=$sts+(24*60*60);
  
  foreach($channeltrainings as $trainingdata) {
    if($trainingdata['timestamp'] >= $sts and $trainingdata['timestamp'] < $fts) {
//      echo gmdate("YmdHis", $sts)."|".gmdate("YmdHis", $fts)."-".$trainingdata['week']."\n";
//      $ret=888;
      $plan=mk_last_planevents($trainings['stats'][$trainingdata['trainingtype']]);
      if(isset($plan['last'])) {
        foreach ($plan['last'] as $eventnum => $week) {
//          echo "has week#".$week." : ".$trainingdata['trainingid']."~".$trainings['lasts'][$trainingdata['trainingtype']][$week]."\n";
          // if this training log is eventable -> last training of week
          if($trainings['lasts'][$trainingdata['trainingtype']][$week] == $trainingdata['trainingid']) {
            //$fulltraining=get_cb_doc($cblink, $trainingdata['id']);
            $ret=$eventnum;
            //print_r($fulltraining);
          }
        }
      }
    }
  }
  return $ret;
}

//function get_email($data) {
//  $ret=isset($data['fbdb']) ? $data['fbdb']['fbemail'] :
//    isset($data['fbcb']) ? $data['fbcb']['fbEmail'] :
//    isset($data['emailcb']) ? $data['emailcb']
//    and !isset($item['fbcb']) and !isset($item['emailcb'])) continue;
//}

function get_lang($data) {
  $ret='en';
  $supported=array('en','fr','de','es','it','pt','ru','ko','zh','ja');
  
  $appl=(isset($data['langCode']) and in_array(substr($data['langCode'],0,2), $supported)) ? substr($data['langCode'],0,2) : 'en';
  $pref='en';
  if(isset($data['preferedLangCodes'])) {
    foreach(array_map('trim',  explode(',', $data['preferedLangCodes'])) as $try_lang) {
      if(in_array(substr($try_lang,0,2), $supported)) {
        $pref=substr($try_lang,0,2);
        break;
      }
    }
  }
  $ret=($appl !== 'en') ? $appl : $pref;
  //echo $ret."|".(isset($data['langCode']) ? $data['langCode'] : "")."|".(isset($data['preferedLangCodes']) ? $data['preferedLangCodes'] : "")."\n";
  return $ret;
}

function chk_email_valid($email) {
  return (!filter_var($email, FILTER_VALIDATE_EMAIL) or !checkdnsrr(substr(strrchr($email, "@"), 1), 'MX')) ? false : true;
}

function put_events_log($link, $data) {
  $query="insert into `events_log` set ".
  "`start_time`='".$data['start_time']."',".
  "`start_time_day`='".$data['start_time_day']."',".
  "`start_time_lastday`='".$data['start_time_lastday']."',".
  "`start_time_daysago`='".$data['start_time_daysago']."',".
  "`tart_time_olddaysago`='".$data['start_time_olddaysago']."',".
  "`lastday_adverts`=".$data['num_advert_keys_3'].",".
  "`daysago_adverts`=".$data['num_advert_keys_2'].",".
  "`olddaysago_adverts`=".$data['num_advert_keys_1'].",".
  "`all_adverts`=".$data['num_advert_keys'].",".
  "`profiles`=".$data['num_channel_profiles'].",".
  "`trainings`=".$data['num_channel_trainings'].",".
  "`event1catched`=".(isset($data['event1catched']) ? $data['event1catched'] : 0).",".
  "`event2catched`=".(isset($data['event2catched']) ? $data['event2catched'] : 0).",".
  "`event3catched`=".(isset($data['event3catched']) ? $data['event3catched'] : 0).",".
  "`event4catched`=".(isset($data['event4catched']) ? $data['event4catched'] : 0).",".
  "`event5catched`=".(isset($data['event5catched']) ? $data['event5catched'] : 0).",".
  "`event6catched`=".(isset($data['event6catched']) ? $data['event6catched'] : 0).",".
  "`event7catched`=".(isset($data['event7catched']) ? $data['event7catched'] : 0).",".
  "`event8catched`=".(isset($data['event8catched']) ? $data['event8catched'] : 0).",".
  "`event9catched`=".(isset($data['event9catched']) ? $data['event9catched'] : 0).",".
  "`event1hasemail`=".(isset($data['event1hasemail']) ? $data['event1hasemail'] : 0).",".
  "`event2hasemail`=".(isset($data['event2hasemail']) ? $data['event2hasemail'] : 0).",".
  "`event3hasemail`=".(isset($data['event3hasemail']) ? $data['event3hasemail'] : 0).",".
  "`event4hasemail`=".(isset($data['event4hasemail']) ? $data['event4hasemail'] : 0).",".
  "`event5hasemail`=".(isset($data['event5hasemail']) ? $data['event5hasemail'] : 0).",".
  "`event6hasemail`=".(isset($data['event6hasemail']) ? $data['event6hasemail'] : 0).",".
  "`event7hasemail`=".(isset($data['event7hasemail']) ? $data['event7hasemail'] : 0).",".
  "`event8hasemail`=".(isset($data['event8hasemail']) ? $data['event8hasemail'] : 0).",".
  "`event9hasemail`=".(isset($data['event9hasemail']) ? $data['event9hasemail'] : 0).",".
  "`subscribed_init`=".(isset($data['subscribed_init']) ? $data['subscribed_init'] : 0).",".
  "`unsubscribed_init`=".(isset($data['unsubscribed_init']) ? $data['unsubscribed_init'] : 0).",".
  "`event1segment`=".(isset($data['event1segment']) ? $data['event1segment'] : 0).",".
  "`event2segment`=".(isset($data['event2segment']) ? $data['event2segment'] : 0).",".
  "`event3segment`=".(isset($data['event3segment']) ? $data['event3segment'] : 0).",".
  "`event4segment`=".(isset($data['event4segment']) ? $data['event4segment'] : 0).",".
  "`event5segment`=".(isset($data['event5segment']) ? $data['event5segment'] : 0).",".
  "`event6segment`=".(isset($data['event6segment']) ? $data['event6segment'] : 0).",".
  "`event7segment`=".(isset($data['event7segment']) ? $data['event7segment'] : 0).",".
  "`event8segment`=".(isset($data['event8segment']) ? $data['event8segment'] : 0).",".
  "`event9segment`=".(isset($data['event9segment']) ? $data['event9segment'] : 0).",".
  "`event1campaign_id`='".(isset($data['event1campaign_id']) ? $data['event1campaign_id'] : 0)."',".
  "`event2campaign_id`='".(isset($data['event1campaign_id']) ? $data['event1campaign_id'] : 0)."',".
  "`event3campaign_id`='".(isset($data['event1campaign_id']) ? $data['event1campaign_id'] : 0)."',".
  "`event4campaign_id`='".(isset($data['event1campaign_id']) ? $data['event1campaign_id'] : 0)."',".
  "`event5campaign_id`='".(isset($data['event1campaign_id']) ? $data['event1campaign_id'] : 0)."',".
  "`event6campaign_id`='".(isset($data['event1campaign_id']) ? $data['event1campaign_id'] : 0)."',".
  "`event7campaign_id`='".(isset($data['event1campaign_id']) ? $data['event1campaign_id'] : 0)."',".
  "`event8campaign_id`='".(isset($data['event1campaign_id']) ? $data['event1campaign_id'] : 0)."',".
  "`event9campaign_id`='".(isset($data['event1campaign_id']) ? $data['event1campaign_id'] : 0)."' ";
//  "`s_ts`=".$data[].",".
//  "`f_ts`=".$data[]." ";
  if (!$link->query($query)) {
    echo "'Query error [".$link->error."]";
  }
}

function put_emails2send($link, $logid, $data, $event) {
  $eventnum=(ctype_digit((string)substr($event, 5))) ? intval(substr($event, 5)) : 0;
  foreach($data as $item) {
    $query="insert into `events_mail_log` set ".
            "`events_log_id`='".$logid."',".
            "`email`='".$item['EMAIL']."',".
            "`language`='".$item['mc_language']."',".
            "`event`='".$eventnum."' ";
    if (!$link->query($query)) {
      echo "'Query error [".$link->error."]";
    }
  }
}

