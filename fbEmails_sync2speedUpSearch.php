<?php

define("ROOTDIR", dirname(__FILE__));
chdir(ROOTDIR);

$ini=parse_ini_file("config.ini", true);
$DEBUG=$ini['global']['debug'];
$DEV=$ini['global']['development'];

$db1ini=$ini["mysql1".($DEV ? "_dev" : "")];
$db1 = new mysqli($db1ini['host'], $db1ini['username'], $db1ini['password'], $db1ini['db']);
if ($db1->connect_errno) { die("Failed to connect to db1: " . $mysqli->connect_error); }
$syn=array();

//$query="select `idfa`, `email` from `fbusers` where `idfa`!='' and `email`!=''";
$query="select `advertIdentificator`, `email` from `adv_email`";
if(!$result=$db1->query($query)){
  die("Query error [".$db1->error."]\n");
}
$num=1;
if($result->num_rows) while($row=$result->fetch_assoc()) {
  $syn[$row['advertIdentificator']]=$row['email'];
  if($DEBUG) echo $num++."\t'advertIdentificator'=".$row['advertIdentificator']."\t'email'=".$row['email']."\n";
}
if(isset($result)) $result->free();

//$num_out=1;
//foreach ($syn as $advertIdentificator => $email) {
//  $query="insert into `adv_email` set `advertIdentificator`='".$advertIdentificator."', `email`='".$email."' ".
//          "on duplicate key update `email`='".$email."'";
//  if (!$db1->query($query)) {
//    die("Query error [".$db1->error."]\n");
//  } else {
//    if($DEBUG) echo $num_out++."\t of \t".$num." - done\n";
//  }
//}

$db1->close();





