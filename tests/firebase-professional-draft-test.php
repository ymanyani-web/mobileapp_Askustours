<?php
// Execute the real session route against a disposable database with synthetic Firebase proof.
if(PHP_SAPI!=='cli'||getenv('DB_HOST')!=='127.0.0.1'||getenv('DB_PORT')!=='33388')exit(2);
class RouteResult extends RuntimeException{function __construct(public array $data,public int $status){parent::__construct('route result');}}
function db(){static $pdo;return $pdo??=new PDO('mysql:host=127.0.0.1;port=33388;dbname=askustours;charset=utf8mb4',getenv('DB_USER'),getenv('DB_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);}
function json_body(){return $GLOBALS['testBody'];}
function require_fields($body,$fields){foreach($fields as $field)if(!isset($body[$field]))throw new RuntimeException('missing proof');}
function firebase_identity($proof){if($proof!=='synthetic-only')throw new RuntimeException('unexpected proof');return $GLOBALS['testIdentity'];}
function uuid(){$hex=bin2hex(random_bytes(16));return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20,12);}
function decoded($value){return json_decode($value?:'{}',true,512,JSON_THROW_ON_ERROR);}
function encoded($value){return json_encode($value,JSON_THROW_ON_ERROR);}
function ensure_active_account($id){/* synthetic rows only */}
function session_result($access,$refresh,$user){return ['user'=>$user];}
function out($data,$status=200){throw new RouteResult($data,$status);}
function fail($status,$code,$message,$fields=[]){throw new RouteResult(['error'=>$code,'fields'=>$fields],$status);}
function exchange($role){$GLOBALS['testBody']=['idToken'=>'synthetic-only','accountRole'=>$role];try{require __DIR__.'/../api/routes/auth-firebase-session.php';}catch(RouteResult $result){return $result;}throw new RuntimeException('No route response');}
$ids=[];$checks=0;
function check($actual,$expected){global $checks;if($actual!==$expected)throw new RuntimeException('Session route assertion failed');$checks++;}
try{
 $uid='synthetic-'.bin2hex(random_bytes(8));
 $GLOBALS['testIdentity']=['uid'=>$uid,'email'=>$uid.'@example.test','phone'=>null,'displayName'=>'','providers'=>['password']];
 $result=exchange('customer');check($result->status,422);check(array_keys($result->data['fields']),['firstName','lastName']);
 $result=exchange('partner');$user=$result->data['user'];$ids[]=$user['id'];check($result->status,201);check($user['first_name'],'');check($user['last_name'],'');check($user['role'],'partner');check((int)$user['partner_intent'],1);
 // Selecting professional mode must reuse the authenticated customer row.
 db()->prepare("UPDATE users SET role='customer',partner_intent=0,first_name='Sara',last_name='Lopez' WHERE id=?")->execute([$user['id']]);
 $result=exchange('partner');check($result->data['user']['id'],$user['id']);check($result->data['user']['role'],'customer');check((int)$result->data['user']['partner_intent'],1);check($result->data['user']['first_name'],'Sara');
 echo "$checks real professional session route checks passed\n";
}finally{if(db()->inTransaction())db()->rollBack();foreach($ids as $id)db()->prepare('DELETE FROM users WHERE id=?')->execute([$id]);}
