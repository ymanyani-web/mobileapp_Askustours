<?php
if(PHP_SAPI!=='cli')exit(2);
require dirname(__DIR__).'/includes/people_directory.php';
require dirname(__DIR__).'/includes/account_controls.php';
require dirname(__DIR__).'/includes/layout.php';
function e(?string $v):string{return htmlspecialchars($v??'',ENT_QUOTES,'UTF-8');}
function admin():array{return ['role'=>'admin','name'=>'Equipo de pruebas','email'=>'qa@example.test'];}
function can_manage_deletions(string $action='access'):bool{global $allowDelete;return $allowDelete??false;}
function csrf():string{return 'synthetic-preview-csrf';}
function db():PDO{global $pdo;return $pdo;}
$config=['app_name'=>'Ask Us App'];$_SESSION=[];$_SERVER['SCRIPT_NAME']='users.php';
$checks=0;
function directory_check(bool $value):void{global $checks;if(!$value)throw new RuntimeException('Directory assertion failed #'.($checks+1));$checks++;}
$f=people_directory_filters(['q'=>'  María  ','sort'=>'DROP TABLE users','account'=>'blocked','review'=>'changes_requested','activity'=>'guide']);
directory_check($f['q']==='María'&&$f['sort']==='newest'&&$f['account']==='blocked');
directory_check(people_search_pattern('50%_!')==='%50!%!_!!%');
directory_check(people_initials('مريم','أحمد')==='مأ');
$url=people_directory_url('collaborators',$f,['page'=>2]);directory_check(str_contains($url,'review=changes_requested')&&str_contains($url,'activity=guide')&&str_contains($url,'page=2'));
$pdo=new PDO('mysql:host=127.0.0.1;port=33388;dbname=askustours;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$prefix='DirectoryQA'.bin2hex(random_bytes(4));$pdo->beginTransaction();
$render=static function(array $query,string $view)use($prefix):string{$_GET=['q'=>$prefix,...$query];ob_start();render_people_directory($view);return ob_get_clean();};
try{
 for($i=0;$i<31;$i++){
  $id=substr(hash('sha256',$prefix.$i),0,36);$isPartner=$i<3;
  $pdo->prepare("INSERT INTO users(id,first_name,last_name,email,phone,role,partner_intent,profile_json,preferences_json) VALUES(?,?,?,?,?,?,?,'{}','{}')")->execute([$id,$prefix.' '.str_pad((string)$i,2,'0',STR_PAD_LEFT),'Prueba',$prefix.$i.'@example.test','349'.str_pad((string)$i,8,'0',STR_PAD_LEFT),$isPartner?'partner':'customer',$isPartner?1:0]);
  if($isPartner){$json=['onboarding'=>['categories'=>['guide'],'workCities'=>[['id'=>'madrid','name'=>'Madrid']]],'_reviewState'=>$i===0?'changes_requested':'approved'];$pdo->prepare('INSERT INTO partner_profiles(user_id,status,profile_json) VALUES(?,?,?)')->execute([$id,$i===0?'draft':'approved',json_encode($json)]);}
  if($i===1||$i===4)$pdo->prepare("INSERT INTO audit_log(action,entity_type,entity_id,details_json) VALUES('user.account_control_changed','user',?,?)")->execute([$id,json_encode(['status'=>'blocked'])]);
 }
 $html=$render([],'customers');directory_check(substr_count($html,'class="people-name"')===25);directory_check(!str_contains($html,'data-delete-user='));
 $html=$render(['account'=>'blocked'],'customers');directory_check(substr_count($html,'class="people-name"')===1);directory_check(str_contains($html,'Bloqueada'));
 $html=$render(['review'=>'changes_requested','activity'=>'guide','city'=>'Madrid'],'collaborators');directory_check(substr_count($html,'class="people-name"')===1);directory_check(str_contains($html,'Correcciones solicitadas'));
 $html=$render(['review'=>'approved','account'=>'active'],'collaborators');directory_check(substr_count($html,'class="people-name"')===1);
 $html=$render(['city'=>'NoExiste'],'collaborators');directory_check(str_contains($html,'No hay coincidencias'));
 $html=$render(['q'=>$prefix.'%'],'customers');directory_check(substr_count($html,'class="people-name"')===0);
 $html=$render(['page'=>9999],'customers');directory_check(str_contains($html,'Página 2 de 2'));directory_check(substr_count($html,'class="people-name"')===3);
 $html=$render([],'customers');directory_check(str_contains($html,'page=2'));directory_check(str_contains($html,'q='.$prefix));
 $allowDelete=true;$html=$render([],'customers');directory_check(str_contains($html,'data-delete-user='));directory_check(str_contains($html,'name="confirmation"'));$allowDelete=false;
 // Render only synthetic fixtures to local preview artifacts, then roll back all rows.
 $directory=dirname(__DIR__,2).'/.tools/people-preview';if(!is_dir($directory))mkdir($directory,0700,true);
 foreach(['customers','collaborators'] as $view){$_GET=['q'=>$prefix];ob_start();page_header($view==='customers'?'Customers':'Collaborators');echo '<link rel="stylesheet" href="assets/people-directory.css"><script src="assets/people-directory.js" defer></script>';render_people_directory($view);page_footer();file_put_contents($directory.'/'.$view.'.html',ob_get_clean());}
}finally{$pdo->rollBack();}
echo "$checks directory checks passed. Synthetic rows rolled back.\n";
