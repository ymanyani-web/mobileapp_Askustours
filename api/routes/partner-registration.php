<?php
require_once dirname(__DIR__,2).'/includes/partner_registration.php';
$u=user();require_partner($u);
if($path==='me/partner-registration-config'&&$method==='GET'){
    $registrationConfig=partner_registration_config();unset($registrationConfig['commissionRules'],$registrationConfig['retentionDays']);
    $registrationConfig['legalDocuments']=[];foreach($registrationConfig['legalSlugs'] as $key=>$slug){$s=db()->prepare('SELECT slug,version FROM content_documents WHERE slug=? AND active=1');$s->execute([$slug]);$registrationConfig['legalDocuments'][$key]=$s->fetch()?:null;}
    out($registrationConfig);
}
if($path==='me/partner-profile'&&$method==='PUT'){
    $b=json_body();if(!isset($b['onboarding']))return;
    if(array_diff(array_keys($b),['schemaVersion','expectedRevision','onboarding']))fail(422,'VALIDATION_ERROR','Unsupported profile fields.');
    try{$draft=partner_onboarding_validate($b['onboarding'],$u['id']);}catch(InvalidArgumentException $e){fail(422,'VALIDATION_ERROR',$e->getMessage(),['onboarding'=>[$e->getMessage()]]);}
    db()->beginTransaction();$s=db()->prepare('SELECT * FROM partner_profiles WHERE user_id=? FOR UPDATE');$s->execute([$u['id']]);$row=$s->fetch()?:null;
    if((int)($b['expectedRevision']??-1)!==(int)($row['revision']??0))fail(409,'VERSION_CONFLICT','Reload the latest profile before saving.');
    if(($row['status']??'')==='suspended')fail(409,'ACCOUNT_REVIEW_LOCKED','This profile is suspended.');
    $profile=$row?decoded($row['profile_json']):[];$oldDraft=$profile['onboarding']??[];
    $profile['onboarding']=$draft;$profile['_savedAt']=gmdate(DATE_ATOM);
    $profile=array_replace($profile,['businessName'=>$draft['businessName']??$draft['publicName']??'','contactName'=>trim(($draft['firstName']??'').' '.($draft['lastName']??'')),'phone'=>$draft['phone']??'','email'=>$draft['email']??'','activity'=>implode(',',$draft['categories']??[]),'cities'=>array_column($draft['workCities']??[],'id'),'citiesText'=>implode(', ',array_column($draft['workCities']??[],'name')),'professionalPhotoUploadId'=>$draft['professionalPhotoUploadId']??null]);
    $status=$row['status']??'draft';
    $criticalOld=$oldDraft;$criticalNew=$draft;unset($criticalOld['step'],$criticalOld['completedSteps'],$criticalNew['step'],$criticalNew['completedSteps']);
    if($status==='approved'&&$criticalOld!==$criticalNew){$status='submitted';$profile['_reviewState']='pending_review';pause_partner_services($u['id']);partner_registration_event($profile,'profile_changed','profile','La información modificada requiere revisión.');}
    db()->prepare('INSERT INTO partner_profiles(user_id,status,profile_json,revision) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE status=VALUES(status),profile_json=VALUES(profile_json),revision=revision+1')->execute([$u['id'],$status,encoded($profile)]);
    db()->prepare('UPDATE users SET partner_intent=1 WHERE id=?')->execute([$u['id']]);
    db()->commit();$s=db()->prepare('SELECT * FROM partner_profiles WHERE user_id=?');$s->execute([$u['id']]);out(partner_profile_data($u['id'],$s->fetch()));
}
if($path==='me/partner-profile/submission'&&$method==='POST'){
    $s=db()->prepare('SELECT * FROM partner_profiles WHERE user_id=?');$s->execute([$u['id']]);$row=$s->fetch();$profile=$row?decoded($row['profile_json']):[];
    if(!isset($profile['onboarding']))return;
    verified_user();$body=json_body();$key=idempotency_key();idempotent_replay($u['id'],'partner-profile-submission',$key,$body);
    try{partner_onboarding_validate($profile['onboarding'],$u['id'],true);foreach($profile['onboarding']['documents']??[] as $doc)if(partner_document_state($doc,$profile['_documentReviews'][$doc['requirementId']]??null)==='rejected')throw new InvalidArgumentException('Replace the rejected document before resubmitting.');}catch(InvalidArgumentException $e){fail(422,'VALIDATION_ERROR',$e->getMessage(),['onboarding'=>[$e->getMessage()]]);}
    $registrationConfig=partner_registration_config();$accepted=$body['acceptances']??[];
    foreach($registrationConfig['legalSlugs'] as $name=>$slug){$s=db()->prepare('SELECT version FROM content_documents WHERE slug=? AND active=1');$s->execute([$slug]);$version=$s->fetchColumn();if(!$version)fail(503,'LEGAL_NOT_CONFIGURED','The collaboration legal documents are not published.');if(($accepted[$name]['version']??null)!==$version||($accepted[$name]['accepted']??false)!==true)fail(422,'VALIDATION_ERROR','Accept the current legal documents.',['acceptances'=>[$name]]);}
    if(($body['informationCorrect']??false)!==true)fail(422,'VALIDATION_ERROR','Confirm that the information is correct.');
    db()->beginTransaction();$s=db()->prepare('SELECT * FROM partner_profiles WHERE user_id=? FOR UPDATE');$s->execute([$u['id']]);$current=$s->fetch();
    if((int)$current['revision']!==(int)($body['expectedRevision']??-1))fail(409,'VERSION_CONFLICT','The profile has changed.');
    if(in_array($current['status'],['submitted','approved','suspended'],true))fail(409,'ACCOUNT_REVIEW_LOCKED','The profile is already submitted or restricted.');
    $profile['_acceptances']=['documents'=>$accepted,'acceptedAt'=>gmdate(DATE_ATOM),'informationCorrect'=>true,'profileRevision'=>(int)$current['revision'],'profileDigest'=>hash('sha256',encoded($profile['onboarding'])),'firebaseUid'=>$u['firebase_uid']??null];$profile['_reviewState']='pending_review';
    partner_registration_event($profile,'application_submitted','profile','Solicitud recibida. Pendiente de verificación.');
    db()->prepare("UPDATE partner_profiles SET status='submitted',submitted_at=NOW(),profile_json=?,revision=revision+1 WHERE user_id=?")->execute([encoded($profile),$u['id']]);
    $response=partner_profile_data($u['id'],array_replace($current,['status'=>'submitted','profile_json'=>encoded($profile),'revision'=>(int)$current['revision']+1,'submitted_at'=>gmdate('Y-m-d H:i:s')]));
    store_idempotency($u['id'],'partner-profile-submission',$key,$body,202,$response);db()->commit();out($response,202);
}
if(preg_match('#^me/partner-vehicles/([a-f0-9-]{36})$#',$path,$m)&&$method==='PUT'){
    $body=json_body();$vehicle=$body['vehicle']??[];
    $allowed=['brand','model','year','plate','color','passengers','bags','category','exteriorUploadIds','interiorUploadIds','documents'];if(!is_array($vehicle)||array_diff(array_keys($vehicle),$allowed))fail(422,'VALIDATION_ERROR','Invalid vehicle fields.');
    foreach(['brand','model','year','plate','color','passengers','bags','category'] as $field)if(!is_scalar($vehicle[$field]??null)||trim((string)$vehicle[$field])===''||strlen((string)$vehicle[$field])>100)fail(422,'VALIDATION_ERROR','Complete the vehicle fields.',[$field=>['Required.']]);
    if((int)$vehicle['year']<1950||(int)$vehicle['year']>(int)gmdate('Y')+1||(int)$vehicle['passengers']<1||(int)$vehicle['passengers']>60||(int)$vehicle['bags']<0)fail(422,'VALIDATION_ERROR','Invalid year or capacity.');
    if(!in_array($vehicle['category'],['standard','executive','luxury','suv','minivan','premium_van','minibus'],true))fail(422,'VALIDATION_ERROR','Invalid vehicle category.');
    foreach(['exteriorUploadIds','interiorUploadIds'] as $group){if(!is_array($vehicle[$group]??null)||count($vehicle[$group])>8)fail(422,'VALIDATION_ERROR','Select up to eight vehicle photos per group.');foreach($vehicle[$group] as $id)if(!is_string($id)||!owned_upload($u['id'],$id,'vehicle-photo'))fail(403,'MEDIA_NOT_OWNED','Vehicle photo is not owned.');}
    if(!is_array($vehicle['documents']??null)||count($vehicle['documents'])>10)fail(422,'VALIDATION_ERROR','Invalid vehicle documents.');
    foreach($vehicle['documents'] as &$doc){if(!is_array($doc)||!in_array($doc['requirementId']??'',['registration','inspection','insurance','transport'],true)||!owned_upload($u['id'],(string)($doc['uploadId']??''),'vehicle-document'))fail(403,'MEDIA_NOT_OWNED','Invalid vehicle document.');$doc=array_intersect_key($doc,array_flip(['requirementId','uploadId','expiresAt']));if(!empty($doc['expiresAt'])&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$doc['expiresAt']))fail(422,'VALIDATION_ERROR','Invalid expiry date.');}unset($doc);
    db()->beginTransaction();$s=db()->prepare('SELECT * FROM partner_profiles WHERE user_id=? FOR UPDATE');$s->execute([$u['id']]);$row=$s->fetch();if(!$row)fail(422,'VALIDATION_ERROR','Save the professional profile first.');
    if((int)$row['revision']!==(int)($body['expectedRevision']??-1))fail(409,'VERSION_CONFLICT','Reload the profile before saving.');
    if($row['status']==='suspended')fail(403,'ACCOUNT_RESTRICTED','This professional account is suspended.');
    $p=decoded($row['profile_json']);if(!array_intersect($p['onboarding']['categories']??[],['driver','rental']))fail(403,'PARTNER_REQUIRED','A transport activity is required.');
    $vehicles=$p['_vehicles']??[];if(count($vehicles)>=20&&!isset($vehicles[$m[1]]))fail(422,'VALIDATION_ERROR','Vehicle limit reached.');
    if(($vehicles[$m[1]]['status']??'')==='approved')db()->prepare("UPDATE driver_services SET status='paused',revision=revision+1 WHERE owner_user_id=? AND status='published' AND JSON_UNQUOTE(JSON_EXTRACT(service_json,'$.vehicleId'))=?")->execute([$u['id'],$m[1]]);
    $vehicles[$m[1]]=['id'=>$m[1],'vehicle'=>$vehicle,'status'=>'pending_review','updatedAt'=>gmdate(DATE_ATOM)];$p['_vehicles']=$vehicles;
    db()->prepare('UPDATE partner_profiles SET profile_json=?,revision=revision+1 WHERE user_id=?')->execute([encoded($p),$u['id']]);db()->commit();out($vehicles[$m[1]],201);
}
