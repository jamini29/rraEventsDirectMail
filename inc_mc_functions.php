<?php

function get_all_listmembers($apilink, $lid, $membertype) {
  $ret=array();
  $has_members_in_current_page=0;
  $page_num=0;
  do {
    $member_list = $apilink->listMembers($lid, $membertype, null, $page_num++, 100 ); // subscribed, unsubscribed, cleaned, updated
    if ($apilink->errorCode){
      echo "listMembers error: Code=".$apilink->errorCode." Msg=".$apilink->errorMessage."\n";
    } else {
      $has_members_in_current_page=sizeof($member_list['data']);
      foreach($member_list['data'] as $member){
        array_push($ret, array('EMAIL' => $member['email']));
      }
    }
  } while ($has_members_in_current_page);
  return $ret;
}

function reset_all_listmembers($apilink, $lid, $list) {
  $ret=array('success'=>0);
  foreach (array_keys($list) as $key) {
    $list[$key]['GROUPINGS']=array(array('id'=>1, 'groups'=>''));
    $list[$key]['CUSTOMSUBJ']='';
  }
  $optin = false; // do not send requests - just add/update
  $update_exist = true; // important - all we need to do !
  $replace_int = true; // important - all we need to do !
  $revals = $apilink->listBatchSubscribe($lid, $list, $optin, $update_exist, $replace_int);
  if ($apilink->errorCode){
    echo "Batch Subscribe failed: Code=".$apilink->errorCode." Mmsg=".$apilink->errorMessage."\n";
  } else {
    $ret['success']=1;
    $ret['return']=$revals;
  }
  return $ret;
}

function set_listmembers_group($apilink, $lid, $list, $group) {
  $ret=array('success'=>0);
  $langs=array('en','fr','de','es','it','pt','ru','ko','zh','ja');
  global $eventmailtexts;
  foreach (array_keys($list) as $key) {
    $list[$key]['mc_language']=(!isset($list[$key]['mc_language']) or !in_array($list[$key]['mc_language'], $langs)) ? 'en' : $list[$key]['mc_language']; 
    $list[$key]['GROUPINGS']=array(array('id'=>1, 'groups'=>$group));
    $list[$key]['CUSTOMSUBJ']=isset($eventmailtexts[$list[$key]['mc_language']][$group]['subject']) ? $eventmailtexts[$list[$key]['mc_language']][$group]['subject'] : $eventmailtexts['en'][$group]['subject'];
  }
  $optin = false; // do not send requests - just add/update
  $update_exist = true; // important - all we need to do !
  $replace_int = true; // important - all we need to do !
  $revals = $apilink->listBatchSubscribe($lid, $list, $optin, $update_exist, $replace_int);
  if ($apilink->errorCode){
    echo "Batch Subscribe failed: Code=".$apilink->errorCode." Mmsg=".$apilink->errorMessage."\n";
  } else {
    $ret['success']=1;
    $ret['return']=$revals;
  }
  return $ret;
}

//function get_camp_data($event_name, $lang_code) {
//  global $eventmailtexts;
//  global $eventmailads;
//  $ads_keys=array(0,1,2);
//  $camp_data=$eventmailtexts['en'][$event_name]; // init by 'en' texts data
//  $camp_data['title']=isset($eventmailtexts[$lang_code]['title']) ?
//                        $eventmailtexts[$lang_code]['title'] :
//                        $eventmailtexts['en']['title'];
//  $camp_data['regards']=isset($eventmailtexts[$lang_code]['regards']) ?
//                        $eventmailtexts[$lang_code]['regards'] :
//                        $eventmailtexts['en']['regards'];
//  $camp_data['checkoutmore']=isset($eventmailtexts[$lang_code]['checkoutmore']) ?
//                        $eventmailtexts[$lang_code]['checkoutmore'] :
//                        $eventmailtexts['en']['checkoutmore'];
//  $camp_data['followus']=isset($eventmailtexts[$lang_code]['followus']) ?
//                        $eventmailtexts[$lang_code]['followus'] :
//                        $eventmailtexts['en']['followus'];
//    
//  $camp_data['subject']=isset($eventmailtexts[$lang_code][$event_name]['subject']) ? $eventmailtexts[$lang_code][$event_name]['subject'] : $eventmailtexts['en'][$event_name]['subject'];
//  $camp_data['text']=isset($eventmailtexts[$lang_code][$event_name]['text']) ? $eventmailtexts[$lang_code][$event_name]['text'] : $eventmailtexts['en'][$event_name]['text'];
//
//  $camp_data['from_email']='andrew.minich@redrockapps.com';
//  $camp_data['from_name']='Red Rock Apps';
//  
//  $camp_data['ads']=array();
//  foreach ($ads_keys as $key => $adkey) {
//    $camp_data['ads']['adlinkimg'.$key]="<a href='".$eventmailads['links'][$adkey]."' target='_blank'>".
//                                          "<img align='none' src='".$eventmailads['imglinks'][$adkey]."' style='width: 152px; height: 152px; margin: 0px;'>".
//                                        "</a>";
//    $camp_data['ads']['adname'.$key]=isset($eventmailads[$lang_code][$adkey]) ? $eventmailads[$lang_code][$adkey] : $eventmailads['en'][$adkey];
//  }
//
//  return $camp_data;
//}

function get_camp_data_multilang($event_name) {
  global $eventmailtexts;
  global $eventmailads;
  $ads_keys=array(0,1,2);
  $camp_data=array('title'=>'','regards'=>'','checkoutmore'=>'','followus'=>'','text'=>'','ads'=>array('adname0'=>'','adname1'=>'','adname2'=>''));
  $rotate_lang=array('fr','de','es','it','pt','ru','ko','zh','ja'); // IMPORTANT! : no 'en'
  $camp_data_fields=array('title','regards','checkoutmore','followus','text');
//  $camp_data=$eventmailtexts['en'][$event_name]; // init by 'en' texts data
  foreach($camp_data_fields as $field) {
    foreach($rotate_lang as $key => $lang_code) {
      $camp_data[$field].=($key ? "\n*|ELSEIF:MC_LANGUAGE=".$lang_code."|*\n" : "\n*|IF:MC_LANGUAGE=".$lang_code."|*\n");
      $camp_data[$field].=$field !== 'text'
        ? (isset($eventmailtexts[$lang_code][$field]) ? $eventmailtexts[$lang_code][$field] : $eventmailtexts['en'][$field])
        : (isset($eventmailtexts[$lang_code][$event_name][$field]) ? $eventmailtexts[$lang_code][$event_name][$field] : $eventmailtexts['en'][$event_name][$field]);
    }
    $camp_data[$field].=$field !== 'text'
      ? "\n*|ELSE:|*\n".$eventmailtexts['en'][$field]."\n*|END:IF|*\n"
      : "\n*|ELSE:|*\n".$eventmailtexts['en'][$event_name][$field]."\n*|END:IF|*\n";
  }
  foreach ($ads_keys as $keyaddarr => $adkey) {
    $camp_data['ads']['adlinkimg'.$keyaddarr]="<a href='".$eventmailads['links'][$adkey]."' target='_blank'>".
                                        "<img align='none' src='".$eventmailads['imglinks'][$adkey]."' style='width: 152px; height: 152px; margin: 0px;'>".
                                        "</a>";
    foreach($rotate_lang as $key => $lang_code) {
      $camp_data['ads']['adname'.$keyaddarr].=($key ? "\n*|ELSEIF:MC_LANGUAGE=".$lang_code."|*\n" : "\n*|IF:MC_LANGUAGE=".$lang_code."|*\n");
      $camp_data['ads']['adname'.$keyaddarr].=isset($eventmailads[$lang_code][$adkey]) ? $eventmailads[$lang_code][$adkey] : $eventmailads['en'][$adkey];
    }
    $camp_data['ads']['adname'.$keyaddarr].="\n*|ELSE:|*\n".$eventmailads['en'][$adkey]."\n*|END:IF|*\n";
  }
  $camp_data['subject']="*|CUSTOMSUBJ|*";
  $camp_data['from_email']='pr@redrockapps.com';
  $camp_data['from_name']='Red Rock Apps';
  
  return $camp_data;
}

//function chk_segment($apilink, $lid, $evnt, $lng) {
//  $ret=0;
//  $conditions[] = array('field'=>'interests-1', 'op'=>'one', 'value'=>$evnt);
//  $conditions[] = array('field'=>'mc_language', 'op'=>'eq', 'value'=>$lng);
//  $segment_opts = array('match'=>'all', 'conditions'=>$conditions);
//  $retval = $apilink->campaignSegmentTest($lid, $segment_opts);
//  if ($apilink->errorCode){
//    echo "Unable to Segment Campaign! Code=".$apilink->errorCode." Msg=".$apilink->errorMessage."\n";
//  } else {
//    $ret=$retval;
//  }
//  return $ret;
//}

function chk_segment_multilang($apilink, $lid, $evnt) {
  $ret=0;
  $conditions[] = array('field'=>'interests-1', 'op'=>'one', 'value'=>$evnt);
  $segment_opts = array('match'=>'all', 'conditions'=>$conditions);
  $retval = $apilink->campaignSegmentTest($lid, $segment_opts);
  if ($apilink->errorCode){
    echo "Unable to Segment Campaign! Code=".$apilink->errorCode." Msg=".$apilink->errorMessage."\n";
  } else {
    $ret=$retval;
  }
  return $ret;
}


//function create_campaign($apilink, $lid, $event_name, $lang_code, $titleprefix) {
//  $camp_id=0;
//  $template_id='17513';
//  //campaignCreate($type, $options, $content, $segment_opts=NULL, $type_opts=NULL) {
//  $camp_data=get_camp_data($event_name, $lang_code);
//  $type = 'regular';
//  $opts['list_id']=$lid;
//  $opts['subject'] = $camp_data['subject'];
//  $opts['from_email'] = $camp_data['from_email']; 
//  $opts['from_name'] = $camp_data['from_name'];
//  $opts['tracking']=array('opens' => true, 'html_clicks' => true, 'text_clicks' => false);
//  $opts['authenticate'] = false;
//  $opts['title'] = $titleprefix."_".$event_name."_".$lang_code;
//  $opts['template_id'] = $template_id;
//  
//  $content = array(
//      'html_title'=> $camp_data['title'],
//      'html_text' => $camp_data['text'],
//      'html_regards' => $camp_data['regards'],
//      'html_checkoutmore' => $camp_data['checkoutmore'],
//      'html_followus' => $camp_data['followus'],
//      );
//  // just for ads part of email
//  foreach($camp_data['ads'] as $key => $ad_val) {
//    $content['html_'.$key]=$ad_val;
//  }
//
//  $conditions[] = array('field'=>'interests-1', 'op'=>'one', 'value'=>$event_name);
//  $conditions[] = array('field'=>'mc_language', 'op'=>'eq', 'value'=>$lang_code);
//  $segment_opts = array('match'=>'all', 'conditions'=>$conditions);
// 
//  $retval = $apilink->campaignCreate($type, $opts, $content, $segment_opts);
//  
//  if ($apilink->errorCode){
//    echo "Unable to Create New Campaign! Code=".$apilink->errorCode." Msg=".$apilink->errorMessage."\n";
//  } else {
//    $camp_id=$retval;
//  }
//  return $camp_id;
//}

function create_campaign_multilang($apilink, $lid, $event_name, $titleprefix, $template_id, $folder_id) {
  $camp_id=0;
//  $template_id='17513';

  $camp_data=get_camp_data_multilang($event_name);
  
  $type = 'regular';
  $opts['list_id']=$lid;
  $opts['subject'] = $camp_data['subject'];
  $opts['from_email'] = $camp_data['from_email']; 
  $opts['from_name'] = $camp_data['from_name'];
  $opts['tracking']=array('opens' => true, 'html_clicks' => true, 'text_clicks' => false);
  $opts['authenticate'] = false;
  $opts['title'] = $titleprefix."_".$event_name;//."_".$lang_code;
  $opts['template_id'] = $template_id;
  $opts['folder_id'] = $folder_id;
  
  $content = array(
      'html_title'=> $camp_data['title'],
      'html_text' => $camp_data['text'],
      'html_regards' => $camp_data['regards'],
      'html_checkoutmore' => $camp_data['checkoutmore'],
      'html_followus' => $camp_data['followus'],
      );
//  print_r($camp_data);
//  print_r($content);
  // just for ads part of email
  foreach($camp_data['ads'] as $key => $ad_val) {
    $content['html_'.$key]=$ad_val;
  }

  $conditions[] = array('field'=>'interests-1', 'op'=>'one', 'value'=>$event_name);
  $segment_opts = array('match'=>'all', 'conditions'=>$conditions);
 
  $retval = $apilink->campaignCreate($type, $opts, $content, $segment_opts);
  
  if ($apilink->errorCode){
    echo "Unable to Create New Campaign! Code=".$apilink->errorCode." Msg=".$apilink->errorMessage."\n";
  } else {
    $camp_id=$retval;
  }

  return $camp_id;
}

function send_campaign_now($apilink, $campid) {
  $ret=$apilink->campaignSendNow($campid);
  if ($apilink->errorCode){
    echo "Unable to Send Campaign! Code=".$apilink->errorCode." Msg=".$apilink->errorMessage."\n";
  }
  return $ret;
}





















