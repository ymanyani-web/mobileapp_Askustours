<?php
// Pure server policy checks. No production database, real identities or uploads.
require dirname(__DIR__).'/includes/partner_registration.php';
function db(){return new class {function query($sql){return new class {function fetchColumn(){return '{"_partnerRegistration":{"requirementsReviewed":true}}';}};}};}
function owned_upload($uid,$id,$purpose){return $uid==='synthetic' && $id==='owned' && in_array($purpose,['professional-document','professional-photo'],true);}
$checks=0;
function check($actual,$expected){global $checks;if($actual!==$expected)throw new RuntimeException('Document policy assertion failed: '.json_encode([$actual,$expected]));$checks++;}
foreach([
 [['driver'],['identity','driving','transport']],
 [['guide'],['identity','guide']],
 [['rental'],['identity']],
 [['driver','guide'],['identity','driving','transport','guide']],
 [['guide','rental'],['identity','guide']],
] as [$categories,$ids]){
 $draft=['step'=>3,'categories'=>$categories,'documents'=>[]];
 check(array_column(partner_required_documents($draft,partner_registration_defaults()),'id'),$ids);
 check(partner_onboarding_validate($draft,'synthetic')['documents'],[]); // incomplete drafts allowed
 foreach(['identity','driving','transport','guide','fiscal','taxi'] as $id){
  $candidate=$draft;$candidate['documents']=[['requirementId'=>$id,'uploadId'=>'owned']];
  try{partner_onboarding_validate($candidate,'synthetic');$accepted=true;}catch(InvalidArgumentException $e){$accepted=false;}
  check($accepted,in_array($id,$ids,true));
 }
}
$rules=partner_document_rules([['id'=>'fiscal','required'=>true],['id'=>'insurance','required'=>true],['id'=>'extra','additional'=>true,'required'=>true,'categories'=>['guide']]]);
check(array_column($rules,'id'),['identity','driving','transport','guide','extra']);
check(partner_document_state(['uploadId'=>'new'],['uploadId'=>'old','status'=>'verified']),'attached');
check(partner_document_state(['uploadId'=>'new','expiresAt'=>'2000-01-01'],['uploadId'=>'new','status'=>'verified']),'expired');
$complete=['step'=>3,'categories'=>['driver','guide'],'businessType'=>'self_employed','firstName'=>'Synthetic','lastName'=>'Professional','phone'=>'+34600000000','email'=>'synthetic@example.test','country'=>'ES','experienceYears'=>'1','publicName'=>'Synthetic','professionalPhotoUploadId'=>'owned','languages'=>['es'],'workCities'=>[['id'=>'madrid','name'=>'Madrid']],'descriptions'=>['es'=>'Synthetic local policy test'],'fiscalName'=>'Synthetic','taxId'=>'Synthetic','fiscalAddress'=>'Synthetic','postalCode'=>'28000','city'=>'Madrid','province'=>'Madrid','fiscalCountry'=>'ES','documents'=>[]];
foreach(['identity','driving','transport','guide'] as $id)$complete['documents'][]=['requirementId'=>$id,'uploadId'=>'owned','expiresAt'=>null];
$profile=['onboarding'=>$complete,'_acceptances'=>['synthetic'=>true]];
try{partner_approval_checks('synthetic',$profile);$blocked=false;}catch(RuntimeException $e){$blocked=true;}check($blocked,true);
foreach($complete['documents'] as $doc)$profile['_documentReviews'][$doc['requirementId']]=['uploadId'=>'owned','status'=>'verified'];
partner_approval_checks('synthetic',$profile);check(true,true);
$profile['_documentReviews']['transport']['uploadId']='previous-file';
try{partner_approval_checks('synthetic',$profile);$blocked=false;}catch(RuntimeException $e){$blocked=true;}check($blocked,true);
echo "Professional document policy: $checks assertions passed.\n";
