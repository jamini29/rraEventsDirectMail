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
          'email' => $row['email'],
//          'fbname' => $row['name'],
          );
    }
  }
  if(isset($result)) $result->free();
  return $ret;
}

function get_fbusermail_uniq($link, $keys) {
  $ret=array();
  $query="select `advertIdentificator`, `email` from `adv_email`";
  if(!$result=$link->query($query)){
    die('Query error ['.$link->error.']');
  }
  if($result->num_rows) while($row=$result->fetch_assoc()) {
    if(in_array($row['advertIdentificator'], $keys)) {
      $ret[$row['advertIdentificator']]=array(
          'email' => $row['email'],
//          'fbname' => $row['name'],
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

function get_channel_profiles_by_advert_keys($cblink, $adverts) {
  global $DEBUG;
  $channel_results=array();
  $chunk_size=100;
  $i=0;
  $chunks=ceil(sizeof($adverts)/$chunk_size)-1;
  foreach (array_chunk($adverts, $chunk_size) as $advert_keys_chunk) {
    $cb_result = $cblink->view('directemail', 'advertIdentificator', array('keys' => $advert_keys_chunk));
    if($DEBUG) echo "advertIdentificator chunk #".$i++." of ".$chunks."\n";
    if(isset($cb_result, $cb_result['total_rows'], $cb_result['rows'])) {
//      echo $cb_result['total_rows']."\n";
//      if($DEBUG) echo "advertIdentificator total_rows=".$cb_result['total_rows']." advertIdentificator rows=".count($cb_result['rows'])."\n";
      foreach($cb_result['rows'] as &$res_item) {
        $profileData=$res_item['value']; // savedTS, customChannels, langCode (def. 'en'), preferedLangCodes (def. 'en'), secondsFromGMT (def. -18000)
                                         // pushIdentificator ('' is possible), appId ('' is possible), fbEmail ('' is possible)
        $profileData['doc_id']=$res_item['id'];
        $profileData['advertIdentificator']=$res_item['key'];
        $profileData['lang']=get_prefLang($res_item['value']['langCode'], $res_item['value']['preferedLangCodes']);
        $profileData['appId']=($profileData['appId'] !== '' ? $profileData['appId'] : 'unknown'); // just mark empty appId as unknown
        
        // check if custom_channel/appId pair is unique - if not - take last profile
        if(!isset($channel_results[$profileData['customChannels']][$profileData['appId']]) or
           $channel_results[$profileData['customChannels']][$profileData['appId']]['savedTS'] <= $profileData['savedTS'])
        {
//        if(isset($channel_results[$profileData['customChannels']][$profileData['appId']]) and 
//           $channel_results[$profileData['customChannels']][$profileData['appId']]['savedTS'] <= $profileData['savedTS'])
//        {
//          $channel_results[$profileData['customChannels']][$profileData['appId']]=$profileData;
//          echo "dublicate customChannel/appId pair alert!\n";
//        } else {
          $channel_results[$profileData['customChannels']][$profileData['appId']]=$profileData;
        }
//        if($res_item['key'] === '26652de35dac46589d15867a982ebe06') {
//          echo "channels by adverts\n";
//          print_r($profileData);
//        }
      }
    }
  }
  return $channel_results;
}

function get_channel_profiles_by_advert_keys_walk($cblink, $adverts) {
  global $DEBUG;
  $channel_results=array();
  $i=0;
  $page_length=10000;
  $params=array('limit' => $page_length);
  $last_page=false;
  $allnums=0;
  do {
    $cb_result = $cblink->view('directemail', 'advertIdentificator', $params);
    if(isset($cb_result, $cb_result['total_rows'], $cb_result['rows'])) {
      echo 'page# '.str_pad($i,4,' ',STR_PAD_LEFT)." ".str_pad(($i * $page_length + count($cb_result['rows'])),8,' ',STR_PAD_LEFT)." of ".$cb_result['total_rows']."\n";
      $i++;
      if(count($cb_result['rows']) < $page_length) $last_page=true;
      $last_key=array_pop(array_keys($cb_result['rows']));
      foreach($cb_result['rows'] as $key => $res_item) {
        if($key != $last_key or $last_page) { // do what you want
          if(in_array($res_item['key'], $adverts)) {
            $profileData=$res_item['value']; // savedTS, customChannels, langCode (def. 'en'), preferedLangCodes (def. 'en'), secondsFromGMT (def. -18000)
                                             // pushIdentificator ('' is possible), appId ('' is possible), fbEmail ('' is possible)
            $profileData['doc_id']=$res_item['id'];
            $profileData['advertIdentificator']=$res_item['key'];
            $profileData['lang']=get_prefLang($res_item['value']['langCode'], $res_item['value']['preferedLangCodes']);
            $profileData['appId']=($profileData['appId'] !== '' ? $profileData['appId'] : 'unknown'); // just mark empty appId as unknown
            // check if custom_channel/appId pair is unique - if not - take last profile
            if(!isset($channel_results[$profileData['customChannels']][$profileData['appId']]) or
               $channel_results[$profileData['customChannels']][$profileData['appId']]['savedTS'] <= $profileData['savedTS'])
            {
              $channel_results[$profileData['customChannels']][$profileData['appId']]=$profileData;
            }
          }
        } else { //last row - use as start_key for the next view
          $params['startkey']=$res_item['key'];
          $params['startkey_docid']=$res_item['id'];
        }
      }
    }
  } while(!$last_page);
  return $channel_results;
}




function get_trainings_for_channel($cblink, $channels, $from) {
  global $DEBUG;
  global $trainings;
  $trainingTypes=array(
//    0 => 'trainingTypeC25K',
//    1 => 'trainingType3K',
//    2 => 'trainingType10K',
    3 => 'trainingTypeWLBeginner',
    4 => 'trainingTypeWLIntermidiate',
    5 => 'trainingTypeWLAdvanced',
//    6 => 'trainingTypeHalfMarathon',
//    7 => 'trainingTypeMarathon',
  );
    
  $channel_trainings=array();
  $chunk_size=100;
  $i=0;
  $chunks=ceil(sizeof($channels)/$chunk_size)-1;
  foreach (array_chunk($channels, $chunk_size) as $traininglog_key_chunk) {
    $cb_result = $cblink->view('directemail', 'lasttranninglog', array('keys' => $traininglog_key_chunk));
    if($DEBUG) echo "lasttranninglog chunk #".$i++." of ".$chunks."\n";
    if(isset($cb_result, $cb_result['total_rows'], $cb_result['rows'])) {
//      if($DEBUG) echo "lasttranninglog total_rows=".$cb_result['total_rows']." lasttranninglog rows=".count($cb_result['rows'])."\n";
      foreach($cb_result['rows'] as &$res_item) {
        // We filter only weight loss trainings
        if(!isset($res_item['value']['trainingId'])) echo $res_item['id']."\t".$from."\n";
        if(in_array($trainings[$res_item['value']['trainingId']]['trainingtype'], array_keys($trainingTypes))) {
          $trainingData=$res_item['value']; // trainingId, timeStamp
                                            // appId ('' is possible)
          $trainingData['doc_id']=$res_item['id'];
          $trainingData['customChannels']=$res_item['key'];
          $trainingData['appId']=($trainingData['appId'] !== '' ? $trainingData['appId'] : 'unknown'); // just mark empty appId as unknown
          // training data to calculate events
          $trainingData['trainingtype']=$trainings[$trainingData['trainingId']]['trainingtype'];
          $trainingData['week']=$trainings[$trainingData['trainingId']]['week'];
          $trainingData['day']=$trainings[$trainingData['trainingId']]['day'];
          $trainingData['sequencenum']=$trainings[$trainingData['trainingId']]['sequencenum'];
          $trainingData['maxweek']=$trainings['stats'][$trainingData['trainingtype']];
          $trainingData['islastthisweek']=($trainings['lasts'][$trainingData['trainingtype']][$trainingData['week']]==$trainingData['trainingId']) ? 1 : 0;
          if(!isset($channel_trainings[$trainingData['customChannels']][$trainingData['appId']])) {
            $channel_trainings[$trainingData['customChannels']][$trainingData['appId']]=array();
          }
          array_push($channel_trainings[$trainingData['customChannels']][$trainingData['appId']], $trainingData);
//          if($res_item['key'] === '26652de35dac46589d15867a982ebe06') {
//            echo "trainings log by customchannel\n";
//            print_r($trainingData);
//          }
        }
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
  $chunks=ceil(sizeof($channels)/$chunk_size)-1;
  foreach (array_chunk($channels, $chunk_size) as $channels_chunk) {
    $cb_result = $cblink->view('custchannelemails', 'emails', array('keys' => $channels_chunk));
    if($DEBUG) echo "emailsByChannel chunk #".$i++." of ".$chunks."\n";
    if(isset($cb_result, $cb_result['total_rows'], $cb_result['rows'])) {
//      if($DEBUG) echo "emailsByChannel total_rows=".$cb_result['total_rows']." emailsByChannel rows=".count($cb_result['rows'])."\n";
      foreach($cb_result['rows'] as &$res_item) {
        $emailData=$res_item['value']; // appId ('' is possible), email ('' is possible)
        $emailData['doc_id']=$res_item['id'];
        $emailData['customChannels']=$res_item['key'];
        $emailData['appId']=($emailData['appId'] !== '' ? $emailData['appId'] : 'unknown'); // just mark empty appId as unknown
        $email_results[$emailData['customChannels']][$emailData['appId']]=$emailData;
      }
    }
  }
  return $email_results;
}

function get_cb_doc($cblink, $id) {
  $ret=array();
  $result = $cblink->get($id);
  if(isset($result)) {
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
//    case 3:
//      $planevents['last']=array('6' => 0,'7' => 1,'8' => 3,);
//      break;
//    case 6:
//      $planevents['last']=array('6' => 0,'7' => 3,'8' => 6,);
//      break;
    case 7:
      $planevents['last']=array('6' => 0,'7' => 3,'8' => 7,);
      break;
//    case 9:
//      $planevents['last']=array('6' => 0,'7' => 4,'8' => 9,);
//      break;
//    case 11:
//      $planevents['last']=array('6' => 0,'7' => 5,'8' => 11,);
//      break;
//    case 15:
//      $planevents['last']=array('6' => 0,'7' => 7,'8' => 15,);
//      break;
  }
  return $planevents;
}


function get_ago_event($channeltrainings) {
  $ret=999;
  $lastdata=array('timeStamp' => 0,);
  foreach($channeltrainings as $trainingdata) {
    if($trainingdata['timeStamp'] > $lastdata['timeStamp']) {
      $lastdata=$trainingdata;
    }
  }
  $plan=mk_ago_planevents($lastdata['maxweek']);
  if(isset($plan['ago'])) {
    foreach($plan['ago'] as $eventnum => $weeks) {
      if(in_array($lastdata['week'], $weeks)) $ret=$eventnum;
    }
  }
  return $ret;
}

function get_last_event($channeltrainings, $sts) {
  $ret=999;
  $fts=$sts+(24*60*60);
  $lastdata=array('timeStamp' => 0,);
  foreach($channeltrainings as $trainingdata) {
    if($trainingdata['timeStamp'] >= $sts and $trainingdata['timeStamp'] < $fts and $trainingdata['timeStamp'] > $lastdata['timeStamp']) {
//      $ret=888;
      $lastdata=$trainingdata;
    }
  }
  if($lastdata['timeStamp']!=0) {
    $plan=mk_last_planevents($lastdata['maxweek']);
    if(isset($plan['last'])) {
      foreach ($plan['last'] as $eventnum => $week) {
        // if this training log is eventable -> last training of week
        if($lastdata['islastthisweek'] and $lastdata['week']==$week) {
          $ret=$eventnum;
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


function get_prefLang($langCode='en', $preferedLangCodes='en') {
  $supported=array('en','fr','de','es','it','pt','ru','ko','zh','ja');
  $appl=(in_array(substr($langCode,0,2), $supported)) ? substr($langCode,0,2) : 'en';
  $pref='en';
  foreach(array_map('trim',  explode(',', $preferedLangCodes)) as $try_lang) {
    if(in_array(substr($try_lang,0,2), $supported)) {
      $pref=substr($try_lang,0,2);
      break;
    }
  }
  return ($appl !== 'en') ? $appl : $pref;
}



function get_lang($data) {
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
  return ($appl !== 'en') ? $appl : $pref;
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
//  "`trainings`=".$data['num_channel_trainings'].",".
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

function dump2file($file, $what) {
  $dumpfile = fopen($file, "w") or die("Unable to open file!");
  fwrite($dumpfile, $what);
  fclose($dumpfile);
}