<?php

define("ROOTDIR", dirname(__FILE__));
chdir(ROOTDIR);

$ini=parse_ini_file("config.ini", true);
$DEBUG=$ini['global']['debug'];
$DEV=$ini['global']['development'];

$cbini=$ini["couchdb"];
$cb = new Couchbase($cbini['hostname'].":".$cbini['port'], $cbini['username'], $cbini['password'], $cbini['database']);
//$cbtest = new Couchbase($cbini['hostname'].":".$cbini['port'], $cbini['testdbusername'], $cbini['password'], $cbini['testdbdatabase']);

$test_emails=array(
//    'andrew.minich@redrockapps.com',
    'jamini29@gmail.com',
//    'inf.kalevich@gmail.com',
//    'igor.pomaz@redrockapps.com',
//    'lp@redrockapps.com',
//    'ekaterina.sazonova@redrockapps.com',
//    '17luba@tut.by',
//    'minich.bn.by@gmail.com',
);

$i=0;
$page_length=10000;
$params=array('limit' => $page_length);
$last_page=false;
$allnums=0;
do {
  $cb_result = $cb->view('directemail', 'advertIdentificator', $params);
  if(isset($cb_result, $cb_result['total_rows'], $cb_result['rows'])) {
    echo 'page# '.str_pad($i,4,' ',STR_PAD_LEFT)." ".str_pad(($i * $page_length + count($cb_result['rows'])),8,' ',STR_PAD_LEFT)." of ".$cb_result['total_rows']."\n";
    $i++;
    if(count($cb_result['rows']) < $page_length) $last_page=true;
    $last_key=array_pop(array_keys($cb_result['rows']));
    foreach($cb_result['rows'] as $key => $res_item) {
      if($key != $last_key or $last_page) { // do what you want 
        if($res_item['value']['fbEmail'] !== '' and
          in_array($res_item['value']['fbEmail'], $test_emails)) {
//          echo $res_item['id']."\t".
//               $res_item['value']['fbEmail']."\t".
//               (($res_item['value']['appId'] !== '') ? $res_item['value']['appId'] : "")."\t".
//               $res_item['value']['customChannels']."\n";
          echo "array('docid'=>'".$res_item['id']."','custch'=>'".$res_item['value']['customChannels']."','appid'=>'".$res_item['value']['appId']."','email'=>'".$res_item['value']['fbEmail']."',),\n";
        }
      } else { //last row - use as start_key for the next view
        $params['startkey']=$res_item['key'];
        $params['startkey_docid']=$res_item['id'];
      }
    }
  }
} while(!$last_page);



//$i=1;
//$page_length=500;
//$params=array('limit' => $page_length);
//$last_page=false;
//do {
//  $cb_result = $cb->view('directemail', 'lasttranninglog', $params);
//  if(isset($cb_result, $cb_result['total_rows'], $cb_result['rows'])) {
//    if(count($cb_result['rows']) < $page_length) $last_page=true;
//    $last_key=array_pop(array_keys($cb_result['rows']));
//    foreach($cb_result['rows'] as $key => $res_item) {
//      if($key != $last_key) { // do what you want
//        if($res_item['key'] === '26652de35dac46589d15867a982ebe06') // 'f570866a8492436db7437b912d53d0f4')
//          echo $res_item['id']."\t".
//               $res_item['value']['trainingId']."\t".
//               $res_item['value']['timeStamp']."\t".
//               (($res_item['value']['appId'] !== '') ? $res_item['value']['appId'] : "")."\n";
//      } else { //last row - use as start_key for the next view
//        $params['startkey']=$res_item['key'];
//        $params['startkey_docid']=$res_item['id'];
//      }
//    }
//  }
//  echo 'page# '.$i++."\n";
//} while(!$last_page);

$test=array(
  array('docid'=>'01A3EDB4-C90A-40DD-ADF9-4E84BB9C0331','custch'=>'9afb4b5b2ce34cf5a4630cc4157db776','appid'=>'com.grinasys.runningforweightlosspro','email'=>'inf.kalevich@gmail.com',),
  array('docid'=>'5D063765-3CD6-405D-B1E9-2791451672BE','custch'=>'9afb4b5b2ce34cf5a4630cc4157db776','appid'=>'com.grinasys.runningforweightlosspro','email'=>'inf.kalevich@gmail.com',),
  array('docid'=>'04E06DE3-B189-4DB5-9936-B79790A56E4D','custch'=>'7874900f130b45939a24d48b2fe1f2b0','appid'=>'com.grinasys.runningforweightlosspro','email'=>'inf.kalevich@gmail.com',),
  array('docid'=>'4264F313-6088-4BA7-9655-94C348969CCE','custch'=>'7874900f130b45939a24d48b2fe1f2b0','appid'=>'com.grinasys.runningforweightloss','email'=>'inf.kalevich@gmail.com',),
  array('docid'=>'78655A28-3A2F-482E-A7E8-4A23F5776DB6','custch'=>'7874900f130b45939a24d48b2fe1f2b0','appid'=>'com.grinasys.runningforweightloss','email'=>'inf.kalevich@gmail.com',),
  array('docid'=>'C7A8F30E-217A-4218-82C0-672155AB161D','custch'=>'bd7f6a95deaa4fd6a47efb5ddf8ce590','appid'=>'com.grinasys.fitnessforweightloss','email'=>'jamini29@gmail.com',),
  array('docid'=>'04A06EFD-1D88-4B08-AF4A-D73B5758F234','custch'=>'7bd2af827f7d4839a936bb4b2780324e','appid'=>'com.grinasys.runningforweightlosspro','email'=>'inf.kalevich@gmail.com',),
  array('docid'=>'11691539-4AB2-4D61-9319-D09EAF182C8E','custch'=>'df17ee93f9ed465fa0fb6dd0bfdc67c2','appid'=>'com.grinasys.runningforweightlosspro','email'=>'inf.kalevich@gmail.com',),
  array('docid'=>'4B57DE13-2ED1-47B1-BBD7-1F07F18A887D','custch'=>'df17ee93f9ed465fa0fb6dd0bfdc67c2','appid'=>'com.grinasys.runningforweightlosspro','email'=>'inf.kalevich@gmail.com',),
  array('docid'=>'03F0B92D-9F26-4EAF-B26C-E2990889F8CE','custch'=>'3820e8366c4546e3ac79069b35797e7e','appid'=>'com.grinasys.runningforweightloss','email'=>'inf.kalevich@gmail.com',),
  array('docid'=>'BEE9D3A5-1DD7-4072-B24A-78DCDFC5A0CE','custch'=>'3820e8366c4546e3ac79069b35797e7e','appid'=>'com.grinasys.massage','email'=>'inf.kalevich@gmail.com',),
  array('docid'=>'22B62D3E-F3DC-4BBD-BE64-114C09273BB8','custch'=>'26652de35dac46589d15867a982ebe06','appid'=>'com.grinasys.runningforweightloss','email'=>'jamini29@gmail.com',),
  array('docid'=>'2172B852-8153-4A0D-8204-948D8A7D71B8','custch'=>'412e6db40c1748dcb005cc1c3a22a319','appid'=>'com.grinasys.runningforbeginners','email'=>'inf.kalevich@gmail.com',),
  array('docid'=>'08A878D8-D856-421D-BD26-DF673A1C4469','custch'=>'3afd17488759481498d9078f948acfbe','appid'=>'com.grinasys.runningforweightloss','email'=>'inf.kalevich@gmail.com',),
  array('docid'=>'1197EBAB-7AF2-43C8-A318-0A67A98760E2','custch'=>'3afd17488759481498d9078f948acfbe','appid'=>'com.grinasys.runningforweightlosspro','email'=>'inf.kalevich@gmail.com',),
  array('docid'=>'19AF7F0F-ED88-4111-B2C0-E3FA08695CEF','custch'=>'3afd17488759481498d9078f948acfbe','appid'=>'com.grinasys.runningforweightloss','email'=>'inf.kalevich@gmail.com',),
  array('docid'=>'01B9BC61-B280-473D-B165-CA5878F337E8','custch'=>'f570866a8492436db7437b912d53d0f4','appid'=>'com.grinasys.runningforweightlosspro','email'=>'17luba@tut.by',),
  array('docid'=>'0CE3DC83-61D8-4575-B2FB-6DCF988C2C75','custch'=>'f570866a8492436db7437b912d53d0f4','appid'=>'com.grinasys.fitnessforweightloss','email'=>'17luba@tut.by',),
  array('docid'=>'4FF34099-8011-4802-8241-F89957AC69BF','custch'=>'f570866a8492436db7437b912d53d0f4','appid'=>'com.grinasys.runningforweightloss','email'=>'17luba@tut.by',),
  array('docid'=>'50D05B94-74F8-4A99-894A-59C2B752CE49','custch'=>'f570866a8492436db7437b912d53d0f4','appid'=>'com.grinasys.massage','email'=>'17luba@tut.by',),
  array('docid'=>'57C55808-73E5-4709-A635-3C7575646DF1','custch'=>'f570866a8492436db7437b912d53d0f4','appid'=>'com.grinasys.runningforbeginnerspro','email'=>'17luba@tut.by',),
  array('docid'=>'83B2875C-5B1B-47E1-9943-B08232F5562D','custch'=>'f570866a8492436db7437b912d53d0f4','appid'=>'com.grinasys.runningforbeginners','email'=>'17luba@tut.by',),
  array('docid'=>'FA07C90B-2500-4D61-B7E7-430A33FBDDFB','custch'=>'f570866a8492436db7437b912d53d0f4','appid'=>'com.grinasys.runningforweightlosspro','email'=>'17luba@tut.by',),
  array('docid'=>'76A45AD0-516C-4259-8350-3984CBE513A3','custch'=>'4dce9ea4a22b4c9a960df9d30b6425a4','appid'=>'com.grinasys.runningforbeginners','email'=>'17luba@tut.by',),
  array('docid'=>'A12B94B3-3027-47C9-B858-B946457C84AD','custch'=>'4dce9ea4a22b4c9a960df9d30b6425a4','appid'=>'com.grinasys.runningforbeginners','email'=>'17luba@tut.by',),
  array('docid'=>'5B9632D0-0A9F-41D4-A7AD-8C24049FF3FA','custch'=>'cede5dd7f74945d49e387e2306ecef27','appid'=>'com.grinasys.runningforbeginners','email'=>'inf.kalevich@gmail.com',),
);


//$test=array(
//array('docid'=>'01A3EDB4-C90A-40DD-ADF9-4E84BB9C0331','custch'=>'9afb4b5b2ce34cf5a4630cc4157db776','email'=>'inf.kalevich@gmail.com','appid'=>'com.grinasys.runningforweightlosspro',
//array('docid'=>'5D063765-3CD6-405D-B1E9-2791451672BE','custch'=>'9afb4b5b2ce34cf5a4630cc4157db776','email'=>'inf.kalevich@gmail.com','appid'=>'com.grinasys.runningforweightlosspro',
//array('docid'=>'04E06DE3-B189-4DB5-9936-B79790A56E4D','custch'=>'7874900f130b45939a24d48b2fe1f2b0','email'=>'inf.kalevich@gmail.com','appid'=>'com.grinasys.runningforweightlosspro',
//array('docid'=>'4264F313-6088-4BA7-9655-94C348969CCE','custch'=>'7874900f130b45939a24d48b2fe1f2b0','email'=>'inf.kalevich@gmail.com','appid'=>'com.grinasys.runningforweightloss',
//array('docid'=>'78655A28-3A2F-482E-A7E8-4A23F5776DB6','email'=>'inf.kalevich@gmail.com','appid'=>'com.grinasys.runningforweightloss','custch'=>'7874900f130b45939a24d48b2fe1f2b0',
//array('docid'=>'C7A8F30E-217A-4218-82C0-672155AB161D','email'=>'jamini29@gmail.com','appid'=>'com.grinasys.fitnessforweightloss','custch'=>'bd7f6a95deaa4fd6a47efb5ddf8ce590',
//array('docid'=>'04A06EFD-1D88-4B08-AF4A-D73B5758F234','email'=>'inf.kalevich@gmail.com','appid'=>'com.grinasys.runningforweightlosspro','custch'=>'7bd2af827f7d4839a936bb4b2780324e',
//array('docid'=>'11691539-4AB2-4D61-9319-D09EAF182C8E','email'=>'inf.kalevich@gmail.com','appid'=>'com.grinasys.runningforweightlosspro','custch'=>'df17ee93f9ed465fa0fb6dd0bfdc67c2',
//array('docid'=>'4B57DE13-2ED1-47B1-BBD7-1F07F18A887D','email'=>'inf.kalevich@gmail.com','appid'=>'com.grinasys.runningforweightlosspro','custch'=>'df17ee93f9ed465fa0fb6dd0bfdc67c2',
//array('docid'=>'03F0B92D-9F26-4EAF-B26C-E2990889F8CE','email'=>'inf.kalevich@gmail.com','appid'=>'com.grinasys.runningforweightloss','custch'=>'3820e8366c4546e3ac79069b35797e7e',
//array('docid'=>'BEE9D3A5-1DD7-4072-B24A-78DCDFC5A0CE','email'=>'inf.kalevich@gmail.com','appid'=>'com.grinasys.massage','custch'=>'3820e8366c4546e3ac79069b35797e7e',
//array('docid'=>'22B62D3E-F3DC-4BBD-BE64-114C09273BB8','email'=>'jamini29@gmail.com','appid'=>'com.grinasys.runningforweightloss','custch'=>'26652de35dac46589d15867a982ebe06',
//array('docid'=>'2172B852-8153-4A0D-8204-948D8A7D71B8','email'=>'inf.kalevich@gmail.com','appid'=>'com.grinasys.runningforbeginners','custch'=>'412e6db40c1748dcb005cc1c3a22a319',
//array('docid'=>'08A878D8-D856-421D-BD26-DF673A1C4469','email'=>'inf.kalevich@gmail.com','appid'=>'com.grinasys.runningforweightloss','custch'=>'3afd17488759481498d9078f948acfbe',
//array('docid'=>'1197EBAB-7AF2-43C8-A318-0A67A98760E2','email'=>'inf.kalevich@gmail.com','appid'=>'com.grinasys.runningforweightlosspro','custch'=>'3afd17488759481498d9078f948acfbe',
//array('docid'=>'19AF7F0F-ED88-4111-B2C0-E3FA08695CEF','email'=>'inf.kalevich@gmail.com','appid'=>'com.grinasys.runningforweightloss','custch'=>'3afd17488759481498d9078f948acfbe',
//array('docid'=>'01B9BC61-B280-473D-B165-CA5878F337E8','email'=>'17luba@tut.by','appid'=>'com.grinasys.runningforweightlosspro','custch'=>'f570866a8492436db7437b912d53d0f4',
//array('docid'=>'0CE3DC83-61D8-4575-B2FB-6DCF988C2C75','email'=>'17luba@tut.by','appid'=>'com.grinasys.fitnessforweightloss','custch'=>'f570866a8492436db7437b912d53d0f4',
//array('docid'=>'4FF34099-8011-4802-8241-F89957AC69BF','email'=>'17luba@tut.by','appid'=>'com.grinasys.runningforweightloss','custch'=>'f570866a8492436db7437b912d53d0f4',
//array('docid'=>'50D05B94-74F8-4A99-894A-59C2B752CE49','email'=>'17luba@tut.by','appid'=>'com.grinasys.massage','custch'=>'f570866a8492436db7437b912d53d0f4',
//array('docid'=>'57C55808-73E5-4709-A635-3C7575646DF1','email'=>'17luba@tut.by','appid'=>'com.grinasys.runningforbeginnerspro','custch'=>'f570866a8492436db7437b912d53d0f4',
//array('docid'=>'83B2875C-5B1B-47E1-9943-B08232F5562D','email'=>'17luba@tut.by','appid'=>'com.grinasys.runningforbeginners','custch'=>'f570866a8492436db7437b912d53d0f4',
//array('docid'=>'FA07C90B-2500-4D61-B7E7-430A33FBDDFB','email'=>'17luba@tut.by','appid'=>'com.grinasys.runningforweightlosspro','custch'=>'f570866a8492436db7437b912d53d0f4',
//array('docid'=>'76A45AD0-516C-4259-8350-3984CBE513A3','email'=>'17luba@tut.by','appid'=>'com.grinasys.runningforbeginners	4dce9ea4a22b4c9a960df9d30b6425a4',
//array('docid'=>'A12B94B3-3027-47C9-B858-B946457C84AD','email'=>'17luba@tut.by','appid'=>'com.grinasys.runningforbeginners	4dce9ea4a22b4c9a960df9d30b6425a4',
//array('docid'=>'5B9632D0-0A9F-41D4-A7AD-8C24049FF3FA','email'=>'inf.kalevich@gmail.com','appid'=>'com.grinasys.runningforbeginners	cede5dd7f74945d49e387e2306ecef27',
//)
    