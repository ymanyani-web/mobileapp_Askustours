<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/bootstrap.php';
require_once dirname(__DIR__).'/includes/account_controls.php';
function ensure_active_account(string $uid):void{if(!account_enabled($uid))fail(403,'ACCOUNT_RESTRICTED','This account is restricted. Contact support.');}
header('Content-Type: application/json; charset=utf-8');
$origin=trim((string)($_SERVER['HTTP_ORIGIN']??''));$allowedOrigins=$config['cors_allowed_origins']??[];if($origin!==''&&in_array($origin,$allowedOrigins,true)){header('Access-Control-Allow-Origin: '.$origin);header('Vary: Origin');}
header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept, Idempotency-Key');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){if($origin!==''&&!in_array($origin,$allowedOrigins,true)){http_response_code(403);exit;}http_response_code(204);exit;}
function request_id():string{return 'req_'.bin2hex(random_bytes(8));}
function uuid():string{$b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($b),4));}
function json_body():array{$raw=file_get_contents('php://input');if($raw==='')return [];$v=json_decode($raw,true);if(!is_array($v))fail(400,'MALFORMED_JSON','Request body must be a JSON object.');return $v;}
function out(mixed $data,int $status=200,array $meta=[]):never{http_response_code($status);echo json_encode(['data'=>$data,'meta'=>['requestId'=>request_id(),...$meta]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function fail(int $status,string $code,string $message,array $fields=[]):never{http_response_code($status);echo json_encode(['error'=>compact('code','message','fields'),'meta'=>['requestId'=>request_id()]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function decoded(?string $json):mixed{return json_decode($json?:'{}',true)?:[];}
function encoded(mixed $value):string{return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function token():?string{$h=$_SERVER['HTTP_AUTHORIZATION']??'';return preg_match('/^Bearer\s+(.+)$/i',$h,$m)?$m[1]:null;}
function user(bool $required=true):?array{$t=token();if(!$t){if($required)fail(401,'UNAUTHENTICATED','A verified session is required.');return null;}$s=db()->prepare('SELECT u.* FROM sessions s JOIN users u ON u.id=s.user_id WHERE s.access_token_hash=? AND s.revoked_at IS NULL AND s.access_expires_at>NOW()');$s->execute([hash('sha256',$t)]);$u=$s->fetch();if(!$u)fail(401,'UNAUTHENTICATED','The session is invalid or expired.');ensure_active_account((string)$u['id']);return $u;}
function verified_user():array{$u=user();if(empty($u['verified_at']))fail(403,'VERIFICATION_REQUIRED','A verified account is required.');return $u;}
function require_partner(array $u,bool $approved=false):array{if($u['role']!=='partner'&&!(bool)$u['partner_intent'])fail(403,'PARTNER_REQUIRED','A collaborator account is required.');if($approved){if(empty($u['verified_at']))fail(403,'VERIFICATION_REQUIRED','A verified account is required.');$s=db()->prepare("SELECT status,profile_json FROM partner_profiles WHERE user_id=?");$s->execute([$u['id']]);$p=$s->fetch();if(!$p||$p['status']!=='approved'||!partner_operational((string)$u['id']))fail(403,'PARTNER_NOT_APPROVED','An approved collaborator profile is required.');$profile=decoded($p['profile_json']);if(($profile['onboarding']['businessType']??'')==='employee')fail(403,'EMPLOYER_AUTHORIZATION_REQUIRED','Employee services must be managed and billed by an authorised company.');if(empty($profile['professionalPhotoUploadId']))fail(403,'PROFESSIONAL_PHOTO_REQUIRED','An approved professional photo is required.');}return $u;}
function require_fields(array $body,array $fields):void{$errors=[];foreach($fields as $f)if(!array_key_exists($f,$body)||$body[$f]===''||$body[$f]===null)$errors[$f]=['Required.'];if($errors)fail(422,'VALIDATION_ERROR','Please review the supplied values.',$errors);}
function pagination(int $count,?string $nextCursor=null,bool $hasMore=false,?int $total=null):array{return ['pagination'=>['limit'=>(int)($_GET['limit']??20),'nextCursor'=>$nextCursor,'hasMore'=>$hasMore,'total'=>$total??$count]];}
function limit():int{$limit=filter_var($_GET['limit']??20,FILTER_VALIDATE_INT);if($limit===false||$limit<1||$limit>100)fail(422,'VALIDATION_ERROR','The request contains invalid data.',['query.limit'=>['Must be between 1 and 100.']]);return $limit;}
function cursor_value(string $scope):?array{$raw=trim((string)($_GET['cursor']??''));if($raw==='')return null;$padded=strtr($raw,'-_','+/');$padded.=str_repeat('=',(4-strlen($padded)%4)%4);$json=base64_decode($padded,true);$value=$json===false?null:json_decode($json,true);if(!is_array($value)||($value['scope']??null)!==$scope||!is_string($value['sort']??null)||!is_string($value['id']??null))fail(422,'VALIDATION_ERROR','The request contains invalid data.',['query.cursor'=>['The cursor is invalid for this collection.']]);return $value;}
function cursor_token(string $scope,string $sort,string $id):string{return rtrim(strtr(base64_encode(encoded(compact('scope','sort','id'))),'+/','-_'),'=');}
function cursor_page(array $rows,int $max,string $scope,string $sortKey='created_at',string $idKey='id',?int $total=null):array{$hasMore=count($rows)>$max;if($hasMore)array_pop($rows);$next=null;if($hasMore&&$rows){$last=$rows[array_key_last($rows)];$next=cursor_token($scope,(string)$last[$sortKey],(string)$last[$idKey]);}return [$rows,pagination(count($rows),$next,$hasMore,$total)];}
function iso(?string $value):?string{return $value===null?null:(new DateTimeImmutable($value,new DateTimeZone('UTC')))->format(DATE_ATOM);}
function upload_url(?string $id):?string{if(!$id)return null;global $config;return rtrim((string)$config['api_public_base_url'],'/').'/uploads/'.rawurlencode($id);}
function public_asset_url(?string $path):?string{if(!$path)return null;if(preg_match('#^https://#i',$path))return $path;global $config;return rtrim((string)$config['base_url'],'/').'/'.ltrim(str_replace('\\','/',$path),'/');}
function table_exists(string $table):bool{static $known=[];if(array_key_exists($table,$known))return $known[$table];$s=db()->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');$s->execute([$table]);return $known[$table]=(bool)$s->fetchColumn();}
function home_banner_slides():array {
    if(!table_exists('banners')) return [];
    $home=home_configuration();
    $rows=db()->query("SELECT * FROM banners WHERE active=1 AND image_path IS NOT NULL AND image_path<>'' ORDER BY sort_order ASC,id ASC")->fetchAll();
    $slides=[];
    foreach($rows as $row) {
        if(!public_asset_available($row['image_path'])) continue;
        $details=slide_details($row,$home);
        $destination=$details['destination']??'none';
        $slides[]=[
            'id'=>'banner_'.$row['id'],
            'imageUrl'=>public_content_media_url('banners',(int)$row['id'],$row['image_path']),
            'title'=>$details['title'], 'subtitle'=>$details['description'], 'description'=>$details['description'],
            'highlights'=>$details['highlights']??[], 'sectionHeading'=>$details['sectionHeading']??null,
            'sections'=>$details['sections']??[],
            'destination'=>$destination, 'opensDrivers'=>$destination==='drivers',
            'linkUrl'=>!empty($row['link_url'])?$row['link_url']:null,
            'displayOrder'=>(int)$row['sort_order'],
        ];
    }
    return $slides;
}
function home_featured_offers():array {
    if(!table_exists('home_offers'))return [];
    $home=home_configuration();$offers=[];
    foreach(db()->query("SELECT * FROM home_offers WHERE active=1 AND image_path IS NOT NULL AND image_path<>'' ORDER BY sort_order,id")->fetchAll() as $row) {
        if(!public_asset_available($row['image_path']))continue;
        $details=slide_details($row,$home,'_offerDetails');$destination=$details['destination']??'none';$link=(string)($row['link_url']??'');
        $offers[]=['id'=>'offer_'.$row['id'],'imageUrl'=>public_content_media_url('offers',(int)$row['id'],$row['image_path']),'title'=>$details['title'],'description'=>$details['description'],'highlights'=>$details['highlights']??[],'sectionHeading'=>$details['sectionHeading']??null,'sections'=>$details['sections']??[],'destination'=>$destination,'opensDrivers'=>$destination==='drivers'||stripos($link,'driver')!==false,'linkUrl'=>$link!==''?$link:null,'displayOrder'=>(int)$row['sort_order']];
    }
    return $offers;
}
function travel_package_data(array $package):array{if(($package['demo']??false)!==true)return $package;if(empty($package['destinations']))$package['destinations']=[['cityCode'=>'madrid','name'=>['es'=>'Madrid','en'=>'Madrid','ar'=>'مدريد'],'nights'=>2,'position'=>1]];if(empty($package['image'])||!is_array($package['image']))$package['image']=['url'=>'assets/placeholder.svg','alt'=>['es'=>'Madrid de demostración','en'=>'Demo Madrid','ar'=>'مدريد التجريبية']];$imageUrl=(string)($package['image']['url']??'');if($imageUrl!==''&&!preg_match('#^https://#i',$imageUrl))$imageUrl=public_asset_url($imageUrl);$package['image']['url']=$imageUrl;$package['imageUrl']=$imageUrl;if(empty($package['itinerary']))$package['itinerary']=[['day'=>1,'title'=>['es'=>'Llegada','en'=>'Arrival','ar'=>'الوصول'],'description'=>['es'=>'Recepción de demostración.','en'=>'Synthetic welcome.','ar'=>'استقبال تجريبي.']],['day'=>2,'title'=>['es'=>'Madrid histórico','en'=>'Historic Madrid','ar'=>'مدريد التاريخية'],'description'=>['es'=>'Ruta ficticia para pruebas.','en'=>'Synthetic route for testing.','ar'=>'مسار تجريبي للاختبار.']],['day'=>3,'title'=>['es'=>'Salida','en'=>'Departure','ar'=>'المغادرة'],'description'=>['es'=>'Fin del paquete de demostración.','en'=>'End of the demo package.','ar'=>'نهاية الباقة التجريبية.']]];if(empty($package['terms']))$package['terms']=['version'=>'demo-v1','cancellation'=>'No commercial terms; staging fixture only.'];if(empty($package['availability']))$package['availability']=['requestOnly'=>true,'from'=>gmdate('Y-m-d',strtotime('+14 days')),'to'=>gmdate('Y-m-d',strtotime('+180 days'))];return $package;}
function upload_reference(string $uid,string $id):?array{$s=db()->prepare('SELECT id,purpose,file_name,content_type,path,size_bytes,created_at FROM uploads WHERE id=? AND user_id=?');$s->execute([$id,$uid]);$r=$s->fetch();if(!$r||!(str_starts_with($r['path'],'private-documents/')?partner_private_file($r['path'])!==null:public_asset_available($r['path'])))return null;return ['uploadId'=>$r['id'],'url'=>upload_url($r['id']),'purpose'=>$r['purpose'],'fileName'=>$r['file_name'],'contentType'=>$r['content_type'],'sizeBytes'=>(int)$r['size_bytes'],'createdAt'=>iso($r['created_at'])];}
function service_media(string $uid,array $service,string $type):array{$ids=$type==='driver'?($service['photoUploadIds']??[]):array_filter([$service['photoUploadId']??null]);if(!is_array($ids))$ids=[];$refs=[];foreach($ids as $id){$ref=upload_reference($uid,(string)$id);if($ref)$refs[]=$ref;}$service['media']=$refs;return $service;}
function contact_verified(array $u,string $channel):bool {
 if(empty($u[$channel])||empty($u['verified_at']))return false;
 if(empty($u['firebase_uid']))return true;
 $verified=decoded($u['profile_json'])['_verifiedContacts'][$channel]??null;
 return is_string($verified)&&hash_equals($verified,(string)$u[$channel]);
}
require_once __DIR__.'/traveler-profile.php';
function public_user(array $u):array{$p=decoded($u['profile_json']);$photo=$u['photo_upload_id']?upload_reference($u['id'],(string)$u['photo_upload_id']):null;return ['id'=>$u['id'],'firstName'=>$u['first_name'],'lastName'=>$u['last_name'],'accountRole'=>$u['role'],'partnerIntent'=>(bool)$u['partner_intent'],'email'=>$u['email'],'phone'=>$u['phone'],'emailVerified'=>contact_verified($u,'email'),'phoneVerified'=>contact_verified($u,'phone'),'photoUrl'=>$photo['url']??null,'photoUploadId'=>$u['photo_upload_id'],'avatar'=>$u['avatar'],'address'=>$p['address']??null,'city'=>$p['city']??null,'postalCode'=>$p['postalCode']??null,'country'=>$p['country']??null,'travelerDetails'=>(object)($u['role']==='customer'?array_intersect_key((array)($p['travelerDetails']??[]),array_flip(traveler_extra_fields())):[]),'preferences'=>(object)decoded($u['preferences_json'])];}
function session_result(string $access,string $refresh,array $u):array{return ['accessToken'=>$access,'refreshToken'=>$refresh,'tokenType'=>'Bearer','expiresIn'=>3600,'user'=>public_user($u)];}
function idempotency_key():string{$key=trim((string)($_SERVER['HTTP_IDEMPOTENCY_KEY']??''));if($key===''||strlen($key)>190)fail(422,'VALIDATION_ERROR','The request contains invalid data.',['headers.Idempotency-Key'=>['A valid Idempotency-Key is required.']]);return $key;}
function body_hash(array $body):string{return hash('sha256',encoded($body));}
function idempotent_replay(string $uid,string $operation,string $key,array $body):void{$s=db()->prepare('SELECT body_hash,http_status,response_json FROM idempotency_keys WHERE user_id=? AND operation=? AND key_value=?');$s->execute([$uid,$operation,$key]);$row=$s->fetch();if(!$row)return;if(!hash_equals($row['body_hash'],body_hash($body)))fail(409,'IDEMPOTENCY_CONFLICT','This idempotency key was used with a different request.');out(decoded($row['response_json']),(int)$row['http_status']);}
function store_idempotency(string $uid,string $operation,string $key,array $body,int $status,array $response):void{db()->prepare('INSERT INTO idempotency_keys(user_id,operation,key_value,body_hash,http_status,response_json) VALUES(?,?,?,?,?,?)')->execute([$uid,$operation,$key,body_hash($body),$status,encoded($response)]);}
function reservation_data(array $row):array{$snapshot=decoded($row['snapshot_json']);$quote=$snapshot['quote']??[];return ['id'=>$row['id'],'clientId'=>$row['customer_user_id'],'partnerId'=>$row['partner_user_id'],'quoteId'=>$row['quote_id'],'type'=>$row['service_type'],'driverServiceId'=>$row['service_type']==='driver'?$row['service_id']:null,'guideServiceId'=>$row['service_type']==='guide'?$row['service_id']:null,'packageOption'=>$quote['packageOption']??null,'search'=>$quote['search']??null,'guideSearch'=>$quote['guideSearch']??null,'customer'=>$snapshot['customer']??null,'price'=>$quote['price']??($quote['total']??null),'commission'=>$quote['commission']??null,'status'=>$row['status'],'paymentStatus'=>$row['payment_status'],'version'=>(int)$row['version'],'createdAt'=>iso($row['created_at']),'updatedAt'=>iso($row['updated_at'])];}
require_once dirname(__DIR__).'/includes/partner_registration.php';
function partner_profile_data(string $uid,?array $row):array{$p=$row?decoded($row['profile_json']):[];$photo=isset($p['professionalPhotoUploadId'])?upload_reference($uid,(string)$p['professionalPhotoUploadId']):null;return ['id'=>$uid,'userId'=>$uid,...$p,'profile'=>$p,'professionalPhotoUrl'=>$photo['url']??null,'professionalPhoto'=>$photo,'schemaVersion'=>1,'status'=>$row?($row['status']==='submitted'?'pending':$row['status']):'draft','submittedAt'=>$row?iso($row['submitted_at']):null,'reviewedAt'=>$row?iso($row['reviewed_at']):null,'revision'=>(int)($row['revision']??0),...partner_registration_public_data($uid,$p,$row??[])];}
function partner_service_data(array $row):array{return ['id'=>$row['id'],'type'=>$row['service_type'],'version'=>(int)$row['revision'],'status'=>$row['status'],...decoded($row['service_json'])];}
function firebase_identity(string $idToken):array{
 global $config;
 $firebase=$config['firebase']??[];$apiKey=trim((string)($firebase['web_api_key']??''));$projectId=trim((string)($firebase['project_id']??''));
 if($apiKey===''||$projectId==='')fail(503,'FIREBASE_NOT_CONFIGURED','Firebase Authentication is not configured.');
 if(!function_exists('curl_init'))fail(503,'FIREBASE_UNAVAILABLE','The server cannot contact Firebase Authentication.');
 $ch=curl_init('https://identitytoolkit.googleapis.com/v1/accounts:lookup?key='.rawurlencode($apiKey));
 if($ch===false)fail(503,'FIREBASE_UNAVAILABLE','Firebase Authentication is unavailable.');
 curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>encoded(['idToken'=>$idToken]),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>max(1,(int)($firebase['timeout_seconds']??8)),CURLOPT_FOLLOWLOCATION=>false]);
 $response=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
 if($response===false||$status>=500||$status===429)fail(503,'FIREBASE_UNAVAILABLE','Firebase Authentication is temporarily unavailable.');
 $payload=is_string($response)?json_decode($response,true):null;$firebaseError=is_array($payload)?(string)($payload['error']['message']??''):'';
 if(in_array($firebaseError,['API_KEY_INVALID','PROJECT_NOT_FOUND','CONFIGURATION_NOT_FOUND'],true))fail(503,'FIREBASE_NOT_CONFIGURED','Firebase Authentication configuration is invalid.');
 $identity=is_array($payload)?($payload['users'][0]??null):null;
 if($status!==200||!is_array($identity)||empty($identity['localId']))fail(401,'INVALID_FIREBASE_TOKEN','The Firebase identity token is invalid or expired.');
 $providers=[];foreach(($identity['providerUserInfo']??[]) as $provider){$id=trim((string)($provider['providerId']??''));if($id!=='')$providers[]=$id;}
 if(!empty($identity['phoneNumber']))$providers[]='phone';$providers=array_values(array_unique($providers));
 $emailVerified=filter_var($identity['emailVerified']??false,FILTER_VALIDATE_BOOLEAN);
 return ['uid'=>(string)$identity['localId'],'email'=>$emailVerified&&filter_var($identity['email']??null,FILTER_VALIDATE_EMAIL)?(string)$identity['email']:null,'phone'=>preg_match('/^\+[1-9][0-9]{7,14}$/',(string)($identity['phoneNumber']??''))?(string)$identity['phoneNumber']:null,'displayName'=>trim((string)($identity['displayName']??'')),'providers'=>$providers];
}
function deliver_otp(string $channel,string $destination,string $code):void{global $config;$otp=$config['otp']??[];$url=trim((string)($otp['webhook_url']??''));if($url==='')fail(503,'FEATURE_NOT_CONFIGURED','Email/SMS verification is not configured.');if(!function_exists('curl_init'))fail(503,'OTP_PROVIDER_UNAVAILABLE','The server cannot contact the verification provider.');$payload=encoded(['channel'=>$channel,'destination'=>$destination,'code'=>$code,'sender'=>(string)($otp['sender']??'ASKUSTOURS'),'expiresInSeconds'=>600]);$headers=['Content-Type: application/json','Accept: application/json'];$token=trim((string)($otp['webhook_token']??''));if($token!=='')$headers[]='Authorization: Bearer '.$token;$ch=curl_init($url);if($ch===false)fail(503,'OTP_PROVIDER_UNAVAILABLE','The verification provider is unavailable.');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>max(1,(int)($otp['timeout_seconds']??8)),CURLOPT_FOLLOWLOCATION=>false]);$result=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);if($result===false||$status<200||$status>=300){error_log('OTP provider failure: status='.$status.' error='.$error);fail(503,'OTP_PROVIDER_UNAVAILABLE','The verification message could not be delivered.');}}
set_exception_handler(function(Throwable $e){if(isset($GLOBALS['config'])){try{$pdo=db();if($pdo->inTransaction())$pdo->rollBack();}catch(Throwable){}}error_log((string)$e);$message=getenv('APP_DEBUG')==='1'?$e->getMessage():'An unexpected error occurred.';fail(500,'INTERNAL_ERROR',$message);});
