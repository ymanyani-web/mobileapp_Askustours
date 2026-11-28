<?php
require_once dirname(__DIR__,2).'/includes/chauffeur_enquiries.php';
if($u['role']!=='customer')fail(403,'FORBIDDEN','Customer account required.');
if($path==='me/driver-requests'&&$method==='GET'){
 $max=limit();$cursor=cursor_value('driver-requests');$where="user_id=? AND JSON_UNQUOTE(JSON_EXTRACT(answers_json,'$.requestType'))='chauffeur'";$params=[$u['id']];if($cursor){$where.=' AND (created_at<? OR (created_at=? AND id<?))';array_push($params,$cursor['sort'],$cursor['sort'],$cursor['id']);}
 $s=db()->prepare('SELECT * FROM trip_requests WHERE '.$where.' ORDER BY created_at DESC,id DESC LIMIT '.($max+1));$s->execute($params);[$rows,$meta]=cursor_page($s->fetchAll(),$max,'driver-requests');out(['items'=>array_map('chauffeur_public',$rows)],200,$meta);
}
if($path==='me/driver-requests'&&$method==='POST'){
 $u=verified_user();require_traveler_profile($u,traveler_base_requirements());$b=json_body();$key=idempotency_key();idempotent_replay($u['id'],'chauffeur-enquiry',$key,$b);
 try{$request=chauffeur_validate($b);}catch(DomainException $e){fail(422,'VALIDATION_ERROR',$e->getMessage());}
 db()->beginTransaction();try{
  db()->prepare('SELECT id FROM users WHERE id=? FOR UPDATE')->execute([$u['id']]);idempotent_replay($u['id'],'chauffeur-enquiry',$key,$b);
  $s=db()->prepare("SELECT COUNT(*) FROM trip_requests WHERE user_id=? AND created_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY) AND JSON_UNQUOTE(JSON_EXTRACT(answers_json,'$.requestType'))='chauffeur'");$s->execute([$u['id']]);if((int)$s->fetchColumn()>=20)fail(429,'RATE_LIMITED','Daily request limit reached.');
  $id=uuid();$snapshot=require_traveler_profile($u,traveler_base_requirements());$p=['requestType'=>'chauffeur','workflow'=>'new','request'=>$request,'travelerSnapshot'=>$snapshot,'customerSnapshot'=>['firstName'=>$u['first_name'],'lastName'=>$u['last_name'],'phone'=>$u['phone'],'email'=>$u['email']]];
  db()->prepare("INSERT INTO trip_requests(id,user_id,status,answers_json,submitted_at) VALUES(?,?,'submitted',?,UTC_TIMESTAMP())")->execute([$id,$u['id'],chauffeur_json($p)]);$response=chauffeur_public(chauffeur_load($id,$u['id']));store_idempotency($u['id'],'chauffeur-enquiry',$key,$b,201,$response);chauffeur_audit(null,$id,'','new');db()->commit();out($response,201);
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
if(preg_match('#^me/driver-requests/([a-f0-9-]{36})$#D',$path,$match)){
 if($method==='GET'){$row=chauffeur_load($match[1],$u['id']);if(!$row)fail(404,'NOT_FOUND','Request not found.');out(chauffeur_public($row));}
 if($method==='PATCH'){
  $b=json_body();if(array_diff(array_keys($b),['status','revision','proposalVersion']))fail(422,'VALIDATION_ERROR','Unknown action fields.');
  db()->beginTransaction();try{$row=chauffeur_load($match[1],$u['id'],true);if(!$row)fail(404,'NOT_FOUND','Request not found.');$p=chauffeur_payload($row);$to=$b['status']??'';if((int)($b['revision']??0)!==(int)$row['revision'])fail(409,'REVISION_CONFLICT','Reload the request.');if(!chauffeur_transition($p['workflow'],$to,true))fail(409,'INVALID_TRANSITION','Action not allowed.');
   if($to==='accepted'){if(!contact_verified($u,'phone'))fail(403,'PHONE_VERIFICATION_REQUIRED','Verify your mobile number before accepting.');require_traveler_profile($u,traveler_base_requirements());if((int)($b['proposalVersion']??0)!==(int)($p['proposal']['version']??0)||empty($p['proposal'])||strtotime($p['proposal']['expiresAt'])<=time())fail(409,'PROPOSAL_EXPIRED','The proposal expired or changed.');$p['acceptance']=['proposalVersion'=>$p['proposal']['version'],'acceptedAt'=>gmdate('c'),'userId'=>$u['id']];}
   $from=$p['workflow'];$p['workflow']=$to;chauffeur_store($row,$p);chauffeur_audit(null,$row['id'],$from,$to);$response=chauffeur_public(chauffeur_load($row['id'],$u['id']));db()->commit();out($response);
  }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
 }
}
fail(405,'METHOD_NOT_ALLOWED','Unsupported chauffeur action.');
