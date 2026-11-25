<?php
if(PHP_SAPI!=='cli')exit(2);
$_SERVER['REQUEST_METHOD']='GET';
require dirname(__DIR__).'/api/bootstrap.php';
$u=['email'=>'traveler@example.test','phone'=>'+34600000000','verified_at'=>'2026-11-25 12:00:00','firebase_uid'=>'fixture-identity','profile_json'=>encoded(['_verifiedContacts'=>['email'=>'traveler@example.test','phone'=>null]])];
$checks=0;
$check=function($actual,$expected)use(&$checks){if($actual!==$expected)throw new RuntimeException('Verified contact policy failed.');$checks++;};
$check(contact_verified($u,'email'),true);
$check(contact_verified($u,'phone'),false);
$u['profile_json']=encoded(['_verifiedContacts'=>['email'=>'traveler@example.test','phone'=>'+34600000000']]);
$check(contact_verified($u,'phone'),true);
$u['phone']='+34611111111';$check(contact_verified($u,'phone'),false);
$u['email']='other@example.test';$check(contact_verified($u,'email'),false);
$u['profile_json']='{}';$check(contact_verified($u,'phone'),false);
$u['verified_at']=null;$check(contact_verified($u,'email'),false);
echo "Verified contacts checks passed: $checks assertions.\n";
