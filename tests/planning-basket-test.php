<?php
if(PHP_SAPI!=='cli')exit(2);
class ApiResult extends RuntimeException { public function __construct(public array $data,public int $status=200){parent::__construct();}}
function fail(int $status,string $code,string $message='',array $details=[]):never{throw new ApiResult(['code'=>$code,'details'=>$details],$status);}
function out(array $data):never{throw new ApiResult($data);}
function db():PDO{global $pdo;return $pdo;}
function encoded($v):string{return json_encode($v,JSON_THROW_ON_ERROR);}
function decoded($v):array{return json_decode($v,true)??[];}
function iso($v){return $v;}
function owned_upload(...$args):bool{return false;}
require dirname(__DIR__).'/api/traveler-profile.php';
require dirname(__DIR__).'/api/planning-basket.php';
$pdo=new PDO('mysql:host=127.0.0.1;port=33388;dbname=askustours;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/migration_2026_11_28_planning_basket.sql'));
$checks=0;
function check($v){global $checks;if(!$v)throw new RuntimeException('Assertion '.($checks+1));$checks++;}
function callApi(callable $fn):ApiResult{try{$fn();}catch(ApiResult $r){if(db()->inTransaction())db()->rollBack();return $r;}throw new RuntimeException('Expected API result');}
$id='planningqa'.bin2hex(random_bytes(8));
$user=['id'=>$id,'role'=>'customer','first_name'=>'Test','last_name'=>'Traveler','phone'=>'+34999999999','email'=>$id.'@example.test','profile_json'=>encoded(['travelerDetails'=>['birthDate'=>'1990-01-01','gender'=>'unspecified','nationality'=>'ES','region'=>'Madrid'],'address'=>'Test street','city'=>'Madrid','postalCode'=>'28001','country'=>'ES'])];
try{
 $pdo->prepare("INSERT INTO users(id,first_name,last_name,email,phone,role,profile_json,preferences_json) VALUES(?,?,?,?,?,'customer',?,'{}')")->execute([$id,$user['first_name'],$user['last_name'],$user['email'],$user['phone'],$user['profile_json']]);
 check(planning_basket_ready());
 check(!in_array('residenceCountry',traveler_requirements(['travelerRequirements'=>['residenceCountry']]),true));
 $incomplete=$user;$incomplete['profile_json']='{}';
 check(callApi(fn()=>require_traveler_profile($incomplete,[]))->status===422);
 check(count(require_traveler_profile($user,[]))===12);
 $plan=['destinations'=>['madrid'],'adults'=>2,'children'=>0,'infants'=>0,'approximateDays'=>7,'cityStays'=>['madrid'=>['nights'=>3,'rooms'=>1]],'completed'=>true];
 $r=callApi(fn()=>save_planning_basket($user,'draft',['plan'=>$plan]));check($r->status===200);check($r->data['item']['plan']['completed']===false);check($r->data['item']['paymentStatus']==='notCollected');
 $fee=decoded($pdo->query('SELECT config_json FROM home_config WHERE id=1')->fetchColumn()?:'{}')['planningFee']??['amountMinor'=>2000,'currency'=>'EUR'];check($r->data['item']['planningFee']===$fee);
 $r=callApi(fn()=>save_planning_basket($user,'draft',['plan'=>$plan]));check($r->data['revision']===2);
 $s=$pdo->prepare('SELECT COUNT(*) FROM basket_items WHERE user_id=?');$s->execute([$id]);check((int)$s->fetchColumn()===1);
 $partner=$user;$partner['role']='partner';check(callApi(fn()=>save_planning_basket($partner,'blocked',['plan'=>$plan]))->status===403);
 $bad=$plan;$bad['cityStays']='bad';check(callApi(fn()=>planning_basket_plan($bad))->status===422);
 $bad=$plan;$bad['approximateDays']=500;check(callApi(fn()=>planning_basket_plan($bad))->status===422);
 $bad=$plan;$bad['approved']=true;check(callApi(fn()=>planning_basket_plan($bad))->status===422);
 echo "$checks checks passed (isolated local database).\n";
}finally{$pdo->prepare('DELETE FROM basket_items WHERE user_id=?')->execute([$id]);$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);}
