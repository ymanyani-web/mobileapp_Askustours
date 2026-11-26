<?php
require_once dirname(__DIR__,2).'/includes/firebase_registration.php';
$body=json_body();require_fields($body,['idToken']);
$identity=firebase_identity((string)$body['idToken']);
if($identity['email']===null&&$identity['phone']===null)fail(422,'FIREBASE_IDENTITY_INCOMPLETE','Firebase did not provide a verified email address or phone number.');

$lookup=db()->prepare('SELECT * FROM users WHERE firebase_uid=? LIMIT 1');$lookup->execute([$identity['uid']]);$user=$lookup->fetch();
if(!$user){
 $conditions=[];$parameters=[];
 if($identity['email']!==null){$conditions[]='email=?';$parameters[]=$identity['email'];}
 if($identity['phone']!==null){$conditions[]='phone=?';$parameters[]=$identity['phone'];}
 $matches=[];
 if($conditions){$existing=db()->prepare('SELECT * FROM users WHERE '.implode(' OR ',$conditions));$existing->execute($parameters);$matches=$existing->fetchAll();}
 if(count($matches)>1)fail(409,'FIREBASE_IDENTITY_CONFLICT','The verified Firebase identifiers belong to different Ask Us App accounts.');
 $user=$matches[0]??null;
 if($user&&!empty($user['firebase_uid'])&&!hash_equals((string)$user['firebase_uid'],(string)$identity['uid']))fail(409,'FIREBASE_IDENTITY_CONFLICT','This Ask Us App account is already linked to another Firebase identity.');
}

$displayParts=preg_split('/\s+/u',$identity['displayName'],-1,PREG_SPLIT_NO_EMPTY)?:[];
$first=trim((string)($body['firstName']??($displayParts[0]??'')));
$last=trim((string)($body['lastName']??(count($displayParts)>1?implode(' ',array_slice($displayParts,1)):'')));
$role=(string)($body['accountRole']??'customer');
if(!$user){
 $errors=firebase_registration_errors($first,$last,$role);if($errors)fail(422,'VALIDATION_ERROR','The request contains invalid data.',$errors);
}
if($user){
 $conflictConditions=[];$conflictParameters=[$user['id']];
 if($identity['email']!==null){$conflictConditions[]='email=?';$conflictParameters[]=$identity['email'];}
 if($identity['phone']!==null){$conflictConditions[]='phone=?';$conflictParameters[]=$identity['phone'];}
 if($conflictConditions){$conflict=db()->prepare('SELECT 1 FROM users WHERE id<>? AND ('.implode(' OR ',$conflictConditions).') LIMIT 1');$conflict->execute($conflictParameters);if($conflict->fetchColumn())fail(409,'FIREBASE_IDENTITY_CONFLICT','A verified Firebase identifier belongs to another Ask Us App account.');}
}

$provider=implode(',',array_slice($identity['providers'],0,4));
db()->beginTransaction();
if($user){
 $lock=db()->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');$lock->execute([$user['id']]);ensure_active_account((string)$user['id']);
 db()->prepare('UPDATE users SET firebase_uid=?,firebase_provider=?,email=COALESCE(?,email),phone=COALESCE(?,phone),verified_at=COALESCE(verified_at,NOW()) WHERE id=?')->execute([$identity['uid'],$provider?:null,$identity['email'],$identity['phone'],$user['id']]);
 $userId=(string)$user['id'];
}else{
 $userId=uuid();
 db()->prepare("INSERT INTO users(id,firebase_uid,firebase_provider,first_name,last_name,email,phone,role,partner_intent,verified_at,profile_json,preferences_json) VALUES(?,?,?,?,?,?,?,?,?,NOW(),'{}','{}')")->execute([$userId,$identity['uid'],$provider?:null,$first,$last,$identity['email'],$identity['phone'],$role,$role==='partner']);
}
if($role==='partner')db()->prepare('UPDATE users SET partner_intent=1 WHERE id=?')->execute([$userId]);
$verifiedProfile=db()->prepare('SELECT profile_json FROM users WHERE id=?');$verifiedProfile->execute([$userId]);$identityProfile=decoded($verifiedProfile->fetchColumn());$identityProfile['_verifiedContacts']=['email'=>$identity['email'],'phone'=>$identity['phone']];db()->prepare('UPDATE users SET profile_json=? WHERE id=?')->execute([encoded($identityProfile),$userId]);
$access=bin2hex(random_bytes(32));$refresh=bin2hex(random_bytes(32));$sessionId=uuid();
db()->prepare('INSERT INTO sessions(id,user_id,access_token_hash,refresh_token_hash,access_expires_at,refresh_expires_at) VALUES(?,?,?,?,DATE_ADD(NOW(),INTERVAL 1 HOUR),DATE_ADD(NOW(),INTERVAL 30 DAY))')->execute([$sessionId,$userId,hash('sha256',$access),hash('sha256',$refresh)]);
db()->commit();

$saved=db()->prepare('SELECT * FROM users WHERE id=?');$saved->execute([$userId]);$user=$saved->fetch();
$result=session_result($access,$refresh,$user);$result['authentication']=['source'=>'firebase','providers'=>$identity['providers']];
out($result,201);
