<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/includes/bootstrap.php';require dirname(__DIR__).'/includes/deletions.php';
if(($config['db']['host']??'')!=='127.0.0.1'||(string)($config['db']['port']??'')!=='33387')throw new RuntimeException('Disposable loopback database only.');
$_SESSION['admin']=db()->query("SELECT id,name,email,role FROM admins WHERE role='admin' AND active=1 ORDER BY id LIMIT 1")->fetch();
$checks=0;function verify(bool $condition):void{global $checks;if(!$condition)throw new RuntimeException('Recovery integrity assertion failed.');$checks++;}
$customer='10000000-0000-4000-8000-000000000001';$partner='10000000-0000-4000-8000-000000000002';
$before=(int)db()->query("SELECT COUNT(*) FROM audit_log WHERE action='entity.deleted'")->fetchColumn();
try{delete_managed('users',$customer,'stale-revision','Local stale form');throw new LogicException('Stale form accepted.');}catch(RuntimeException $e){verify(str_contains($e->getMessage(),'cambiado'));}
verify((int)db()->query("SELECT COUNT(*) FROM audit_log WHERE action='entity.deleted'")->fetchColumn()===$before);
db()->beginTransaction();
try{
 db()->prepare("UPDATE reservations SET payment_status='paid' WHERE customer_user_id=?")->execute([$customer]);
 try{deletion_snapshot('users',$customer);throw new LogicException('Paid reservation accepted.');}catch(RuntimeException $e){verify(str_contains($e->getMessage(),'pagos'));}
}finally{db()->rollBack();}
$plan=deletion_snapshot('users',$partner);verify(!empty($plan['rows']['driver_services']));
$backup=delete_managed('users',$partner,deletion_revision($plan),'Prueba local de recuperación profesional');
$s=db()->prepare('SELECT COUNT(*) FROM users WHERE id=?');$s->execute([$partner]);verify((int)$s->fetchColumn()===0);
restore_managed($backup);$after=deletion_snapshot('users',$partner);verify(deletion_revision($after)===deletion_revision($plan));
$s=db()->prepare('SELECT COUNT(*) FROM sessions WHERE user_id=?');$s->execute([$partner]);verify((int)$s->fetchColumn()===0);
$home=json_decode(db()->query('SELECT config_json FROM home_config WHERE id=1')->fetchColumn(),true);
if(!empty($home['tripIdeas'])){
 $id=(string)$home['tripIdeas'][0]['id'];$plan=deletion_snapshot('home_tripIdeas',$id);$backup=delete_managed('home_tripIdeas',$id,deletion_revision($plan),'Prueba local de tarjeta editorial');restore_managed($backup);
 $restored=json_decode(db()->query('SELECT config_json FROM home_config WHERE id=1')->fetchColumn(),true);verify($restored===$home);
}
echo "Isolated deletion integrity checks passed: $checks assertions.\n";
