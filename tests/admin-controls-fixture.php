<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/includes/bootstrap.php';
if(($config['db']['host']??'')!=='127.0.0.1'||(string)($config['db']['port']??'')!=='33387')throw new RuntimeException('Disposable loopback database only.');
db()->exec(file_get_contents(dirname(__DIR__).'/database/seed_demo.sql'));
$customer=db()->query("SELECT id FROM users WHERE role='customer' ORDER BY id LIMIT 1")->fetchColumn();
$partner=db()->query("SELECT id FROM users WHERE role='partner' ORDER BY id LIMIT 1")->fetchColumn();
if(!$customer||!$partner)throw new RuntimeException('Import the disposable schema and seed first.');
$dir=upload_storage_root();if(!is_dir($dir))mkdir($dir,0750,true);
$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jWZkAAAAASUVORK5CYII=');file_put_contents($dir.'/admin-professional-fixture.png',$png);
$photo='93000000-0000-4000-8000-000000000001';
db()->prepare("INSERT INTO uploads(id,user_id,purpose,file_name,content_type,path,size_bytes) VALUES(?,?,'professional-photo','fixture.png','image/png','uploads/admin-professional-fixture.png',?) ON DUPLICATE KEY UPDATE path=VALUES(path)")->execute([$photo,$partner,strlen($png)]);
$profile=['businessName'=>'Empresa de prueba','contactName'=>'Persona de prueba','phone'=>'+34600000000','email'=>'fixture@example.test','activity'=>'driver','cities'=>['madrid'],'professionalPhotoUploadId'=>$photo];
db()->prepare("UPDATE partner_profiles SET status='submitted',profile_json=?,revision=revision+1 WHERE user_id=?")->execute([json_encode($profile),$partner]);
foreach([$customer=>'fixture-customer-access',$partner=>'fixture-partner-access'] as $uid=>$token){db()->prepare('INSERT INTO sessions(id,user_id,access_token_hash,refresh_token_hash,access_expires_at,refresh_expires_at) VALUES(?,?,?,?,DATE_ADD(NOW(),INTERVAL 1 HOUR),DATE_ADD(NOW(),INTERVAL 1 DAY))')->execute([new_uuid(),$uid,hash('sha256',$token),hash('sha256',$token.'-refresh')]);}
db()->prepare("UPDATE reservations SET status='assigned',partner_user_id=?,version=1")->execute([$partner]);
foreach([['EUR','available',10000],['USD','available',5000],['EUR','pending',2000]] as [$currency,$status,$amount])db()->prepare("INSERT INTO wallet_ledger(id,user_id,wallet_type,entry_type,amount_minor,currency,status,description) VALUES(?,?,'partner','fixture',?,?,?,'Movimiento sintético')")->execute([new_uuid(),$partner,$amount,$currency,$status]);
$out=dirname(__DIR__,2).'/.tools/admin-controls-fixture.json';file_put_contents($out,json_encode(['customer'=>$customer,'partner'=>$partner]));
echo "Disposable account-control fixtures ready.\n";
db()->prepare("INSERT INTO admins(name,email,password_hash,role) VALUES('Operador de prueba','operator@example.test',?,'operator') ON DUPLICATE KEY UPDATE role='operator'")->execute([password_hash('password',PASSWORD_DEFAULT)]);
db()->prepare("INSERT INTO auth_challenges(id,channel,destination,code_hash,expires_at) VALUES('94000000-0000-4000-8000-000000000001','email','demo.customer@askustours.test',?,DATE_ADD(NOW(),INTERVAL 1 HOUR)) ON DUPLICATE KEY UPDATE consumed_at=NULL,expires_at=VALUES(expires_at)")->execute([password_hash('246810',PASSWORD_DEFAULT)]);
