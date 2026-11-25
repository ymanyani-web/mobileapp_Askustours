<?php
declare(strict_types=1);

function traveler_extra_fields():array{return ['passportUploadId','birthDate','gender','nationality','residenceCountry','documentType','documentNumber','documentCountry','documentIssueDate','documentExpiryDate','region','taxNumber'];}
function validate_traveler_extra(mixed $raw):array {
    if(!is_array($raw))fail(422,'VALIDATION_ERROR','Invalid traveler details.',['travelerDetails'=>['Expected an object.']]);
    $unknown=array_diff(array_keys($raw),traveler_extra_fields());if($unknown)fail(422,'VALIDATION_ERROR','Invalid traveler fields.',['travelerDetails'=>['Unknown fields.']]);
    foreach($raw as $key=>$value){if(!is_string($value)||preg_match_all('/./us',$value)>100)fail(422,'VALIDATION_ERROR','Invalid traveler value.',['travelerDetails.'.$key=>['Expected text up to 100 characters.']]);$raw[$key]=trim($value);}
    foreach(['birthDate','documentIssueDate','documentExpiryDate'] as $key){$value=$raw[$key]??'';if($value!==''){$date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);if(!$date||$date->format('Y-m-d')!==$value)fail(422,'VALIDATION_ERROR','Invalid date.',['travelerDetails.'.$key=>['Expected YYYY-MM-DD.']]);if($key!=='documentExpiryDate'&&$date>new DateTimeImmutable('today'))fail(422,'VALIDATION_ERROR','Date cannot be in the future.',['travelerDetails.'.$key=>['Future date.']]);}}
    if(($raw['gender']??'')!==''&&!in_array($raw['gender'],['male','female','unspecified'],true))fail(422,'VALIDATION_ERROR','Invalid gender.');
    if(($raw['documentType']??'')!==''&&!in_array($raw['documentType'],['passport','id'],true))fail(422,'VALIDATION_ERROR','Invalid document type.');
    if(!empty($raw['documentIssueDate'])&&!empty($raw['documentExpiryDate'])&&$raw['documentExpiryDate']<=$raw['documentIssueDate'])fail(422,'VALIDATION_ERROR','Document expiry must follow issue.');
    return $raw;
}
function traveler_requirements(array $service):array {
    $allowed=array_merge(['firstName','lastName','phone','email','address','city','postalCode','country'],traveler_extra_fields());
    $extra=is_array($service['travelerRequirements']??null)?array_values(array_intersect(array_filter($service['travelerRequirements'],'is_string'),$allowed)):[];
    $extra=array_map(fn($field)=>in_array($field,['documentNumber','documentCountry','documentIssueDate','documentExpiryDate','documentType'],true)?'passportUploadId':$field,$extra);return array_values(array_unique(array_merge(['firstName','lastName','phone'],$extra)));
}
function traveler_profile_values(array $user):array {
    $profile=decoded($user['profile_json']);
    return array_merge(is_array($profile['travelerDetails']??null)?$profile['travelerDetails']:[],['firstName'=>$user['first_name'],'lastName'=>$user['last_name'],'phone'=>$user['phone'],'email'=>$user['email']],array_intersect_key($profile,array_flip(['address','city','postalCode','country'])));
}
function require_traveler_profile(array $user,array $requirements):array {
    $values=traveler_profile_values($user);$errors=[];$snapshot=[];
    foreach($requirements as $field){if(trim((string)($values[$field]??''))==='')$errors['travelerDetails.'.$field]=['Required for this service.'];else $snapshot[$field]=$values[$field];}
    if(in_array('documentExpiryDate',$requirements,true)&&!empty($values['documentExpiryDate'])&&$values['documentExpiryDate']<gmdate('Y-m-d'))$errors['travelerDetails.documentExpiryDate']=['The document has expired.'];
    if(in_array('passportUploadId',$requirements,true)&&!empty($values['passportUploadId'])&&!owned_upload($user['id'],(string)$values['passportUploadId'],'customer-passport'))$errors['travelerDetails.passportUploadId']=['An owned private passport photo is required.'];if($errors)fail(422,'TRAVELER_DETAILS_REQUIRED','Complete the traveler details for this service.',$errors);
    return $snapshot;
}
