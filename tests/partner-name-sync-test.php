<?php
if(PHP_SAPI!=='cli')exit(2);$_SERVER['REQUEST_METHOD']='GET';
require dirname(__DIR__).'/api/bootstrap.php';
if($config['db']['host']!=='127.0.0.1'||(int)$config['db']['port']!==33388)throw new RuntimeException('Disposable loopback database only.');
$fixture=decoded(file_get_contents(dirname(__DIR__,2).'/.tools/partner-registration-fixture.json'));$uid=$fixture['users']['partner'];$checks=0;
$pdo=db();$emulated=$pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);$collation=$pdo->query('SELECT @@collation_connection')->fetchColumn();
$check=function($actual,$expected)use(&$checks){if($actual!==$expected)throw new RuntimeException('Name synchronization regression.');$checks++;};
$pdo->beginTransaction();
try{
 foreach(['utf8mb4_general_ci','utf8mb4_unicode_ci'] as $connectionCollation){
  $pdo->exec('SET NAMES utf8mb4 COLLATE '.$connectionCollation);
  foreach([false,true] as $mode){
   $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,$mode);
   foreach([['Sara','López'],['عبدالله','منصور']] as [$first,$last]){
    partner_sync_names($uid,['firstName'=>$first,'lastName'=>$last]);
    $query=$pdo->prepare('SELECT first_name,last_name,partner_intent FROM users WHERE id=?');$query->execute([$uid]);$row=$query->fetch();
    $check($row['first_name'],$first);$check($row['last_name'],$last);$check((int)$row['partner_intent'],1);
    partner_sync_names($uid,['firstName'=>'','lastName'=>'']);$query->execute([$uid]);$row=$query->fetch();
    $check($row['first_name'],$first);$check($row['last_name'],$last);
    partner_sync_names($uid,[]);$query->execute([$uid]);$row=$query->fetch();$check($row['first_name'],$first);
   }
  }
 }
 $pdo->rollBack();echo "$checks name-sync collation checks passed\n";
}finally{
 if($pdo->inTransaction())$pdo->rollBack();
 $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,$emulated);
 if(in_array($collation,['utf8mb4_general_ci','utf8mb4_unicode_ci'],true))$pdo->exec('SET NAMES utf8mb4 COLLATE '.$collation);
}
