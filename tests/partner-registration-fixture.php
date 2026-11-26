<?php
if(PHP_SAPI!=='cli')exit(2);
$_SERVER['REQUEST_METHOD']='GET';
require dirname(__DIR__).'/api/bootstrap.php';
if($config['db']['host']!=='127.0.0.1'||!in_array((int)$config['db']['port'],[33387,33388],true))throw new RuntimeException('Isolated loopback database only.');
$fixtureFile=dirname(__DIR__,2).'/.tools/partner-registration-fixture.json';
if(($argv[1]??'')==='cleanup'){
 $fixture=json_decode(file_get_contents($fixtureFile),true,512,JSON_THROW_ON_ERROR);
 foreach($fixture['users'] as $uid){foreach(['driver_services','guide_services'] as $table)db()->prepare("DELETE FROM $table WHERE owner_user_id=?")->execute([$uid]);db()->prepare("DELETE FROM audit_log WHERE entity_id=?")->execute([$uid]);db()->prepare('DELETE FROM partner_profiles WHERE user_id=?')->execute([$uid]);db()->prepare('DELETE FROM users WHERE id=?')->execute([$uid]);}
 db()->prepare('UPDATE home_config SET config_json=? WHERE id=1')->execute([$fixture['originalHome']]);
 foreach(['test-partner-collaboration','test-partner-privacy','test-partner-economic','test-partner-added-legal'] as $slug)db()->prepare('DELETE FROM content_documents WHERE slug=?')->execute([$slug]);
 echo "Isolated partner fixtures cleaned.\n";exit;
}
db()->exec("DELETE FROM users WHERE first_name='Synthetic' AND last_name='Person' AND firebase_uid LIKE 'synthetic-%' AND email LIKE 'fixture-%@example.test'");
$users=[];foreach(['partner','customer'] as $role){$uid=uuid();$users[$role]=$uid;
 db()->prepare("INSERT INTO users(id,first_name,last_name,email,phone,role,partner_intent,verified_at,profile_json,preferences_json,firebase_uid) VALUES(?,'Synthetic','Person',?,?,?, ?,NOW(),'{}','{}',?)")->execute([$uid,'fixture-'.$uid.'@example.test','+346'.substr(preg_replace('/[^0-9]/','',$uid).'12345678',0,8),$role,(int)($role==='partner'),'synthetic-'.$uid]);
 $token='fixture-'.$role.'-'.$uid;db()->prepare('INSERT INTO sessions(id,user_id,access_token_hash,refresh_token_hash,access_expires_at,refresh_expires_at) VALUES(?,?,?,?,DATE_ADD(NOW(),INTERVAL 1 HOUR),DATE_ADD(NOW(),INTERVAL 1 DAY))')->execute([uuid(),$uid,hash('sha256',$token),hash('sha256',$token.'-refresh')]);
}
$home=db()->query('SELECT config_json FROM home_config WHERE id=1')->fetchColumn();$data=decoded($home);$registration=partner_registration_defaults();$registration['requirementsReviewed']=true;
foreach(['collaboration','privacy','economic'] as $key){$slug='test-partner-'.$key;$registration['legalSlugs'][$key]=$slug;db()->prepare('INSERT INTO content_documents(slug,version,title_json,body_json,active) VALUES(?,\'test-v1\',\'{"es":"Documento sintético"}\',\'{"es":"Solo prueba aislada"}\',1) ON DUPLICATE KEY UPDATE active=1')->execute([$slug]);}
$registration['commissionRules']=[['basisPoints'=>2500],['category'=>'driver','basisPoints'=>2000]];$data['_partnerRegistration']=$registration;db()->prepare('UPDATE home_config SET config_json=? WHERE id=1')->execute([encoded($data)]);
file_put_contents($fixtureFile,encoded(['users'=>$users,'originalHome'=>$home]));echo "Synthetic partner fixtures created in disposable database.\n";
