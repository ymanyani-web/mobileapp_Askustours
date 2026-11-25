<?php
if(PHP_SAPI!=='cli')exit(2);$_SERVER['REQUEST_METHOD']='GET';
require dirname(__DIR__).'/api/bootstrap.php';
if($config['db']['host']!=='127.0.0.1'||(int)$config['db']['port']!==33387)throw new RuntimeException('Disposable loopback database only.');
$fixture=decoded(file_get_contents(dirname(__DIR__,2).'/.tools/partner-registration-fixture.json'));$uid=$fixture['users']['partner'];$checks=0;
$check=function($actual,$expected)use(&$checks){if($actual!==$expected)throw new RuntimeException('Policy assertion failed.');$checks++;};
db()->beginTransaction();try{
 $s=db()->prepare('SELECT profile_json FROM partner_profiles WHERE user_id=?');$s->execute([$uid]);$profile=decoded($s->fetchColumn());$vehicles=$profile['_vehicles'];$approved=array_key_first(array_filter($vehicles,fn($v)=>$v['status']==='approved'));$pending=array_key_first(array_filter($vehicles,fn($v)=>$v['status']==='pending_review'));
 db()->prepare("UPDATE partner_profiles SET status='approved' WHERE user_id=?")->execute([$uid]);
 $check(partner_service_authorized($uid,['vehicleId'=>$approved],'driver'),true);
 $check(partner_service_authorized($uid,['vehicleId'=>$pending],'driver'),false);
 $check(partner_service_authorized($uid,[],'driver'),false);
 $profile['onboarding']['businessType']='employee';db()->prepare('UPDATE partner_profiles SET profile_json=? WHERE user_id=?')->execute([encoded($profile),$uid]);
 $check(partner_service_authorized($uid,['vehicleId'=>$approved],'driver'),false);
 $check(partner_service_authorized($uid,[],'guide'),false);
 $check(partner_document_state(['uploadId'=>'replacement'],['uploadId'=>'old','status'=>'verified']),'attached');
 $check(partner_document_state(['uploadId'=>'current','expiresAt'=>'2000-01-01'],['uploadId'=>'current','status'=>'verified']),'expired');
 $check(partner_document_state(['uploadId'=>'current'],['uploadId'=>'current','status'=>'rejected']),'rejected');
 $home=decoded(db()->query('SELECT config_json FROM home_config WHERE id=1')->fetchColumn());$home['_partnerRegistration']['commissionRules']=[['basisPoints'=>2500],['category'=>'driver','basisPoints'=>2000],['partnerId'=>$uid,'basisPoints'=>3000],['serviceId'=>'synthetic-service','basisPoints'=>1000]];
 db()->prepare('UPDATE home_config SET config_json=? WHERE id=1')->execute([encoded($home)]);
 $check(partner_commission_rule('driver',$uid,'synthetic-service'),1000);
 $check(partner_commission_rule('driver',$uid,'other'),3000);
 $check(partner_commission_rule('driver','other','other'),2000);
 $check(partner_commission_rule('guide','other','other'),2500);
 $public=partner_safe_public_profile($profile['onboarding']);foreach(['taxId','phone','email','documents','fiscalAddress'] as $key)$check(array_key_exists($key,$public),false);
 db()->rollBack();echo "Partner policy checks passed: $checks assertions.\n";
}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
