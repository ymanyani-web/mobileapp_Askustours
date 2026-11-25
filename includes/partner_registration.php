<?php
declare(strict_types=1);

function partner_private_storage_root(): string {
    $configured=trim((string)getenv('PRIVATE_DOCUMENT_STORAGE_PATH'));
    if($configured!=='')return rtrim($configured,'/\\');
    $root=str_replace('\\','/',dirname(__DIR__,2));
    $public=strpos($root,'/public_html');
    return ($public===false?$root:substr($root,0,$public)).'/.askus-private-documents';
}
function partner_private_file(string $path): ?string {
    if(!preg_match('#^private-documents/([a-f0-9-]{36}/[a-f0-9-]{36}\.(?:pdf|jpg|png))$#',$path,$m))return null;
    $file=partner_private_storage_root().'/'.$m[1];return is_file($file)?$file:null;
}
// Extends partner_profiles.profile_json; there is still one profile per user.
function partner_registration_defaults(): array {
    $text=fn($es,$en,$ar)=>compact('es','en','ar');
    $categories=[];
    foreach([
        ['driver','Chofer privado','Private driver','سائق خاص'],
        ['guide','Guía turístico','Tour guide','مرشد سياحي'],
        ['experience','Actividades y experiencias','Activities and experiences','الأنشطة والتجارب'],
        ['rental','Alquiler de vehículos','Vehicle rental','تأجير المركبات'],
        ['accommodation','Alojamientos','Accommodation','أماكن الإقامة'],
        ['other','Otros servicios turísticos','Other tourism services','خدمات سياحية أخرى'],
    ] as [$id,$es,$en,$ar])$categories[]=['id'=>$id,'title'=>$text($es,$en,$ar)];
    $documents=[];
    foreach([
        ['identity','Identificación del titular o representante','Holder or representative ID','هوية المالك أو الممثل',[],true,false],
        ['fiscal','Documentación fiscal o de empresa','Tax or company documentation','وثائق ضريبية أو وثائق الشركة',[],true,false],
        ['driving','Permiso de conducir','Driving licence','رخصة القيادة',['driver'],false,true],
        ['transport','Autorización de transporte aplicable','Applicable transport authorisation','ترخيص النقل المعمول به',['driver','rental'],false,true],
        ['guide','Acreditación de guía','Guide accreditation','اعتماد المرشد',['guide'],false,true],
        ['insurance','Seguro de responsabilidad civil','Liability insurance','تأمين المسؤولية',['guide','experience','rental'],false,true],
        ['licence','Licencias y permisos de la actividad','Activity licences and permits','تراخيص وتصاريح النشاط',['experience','accommodation','other'],false,true],
        ['tourism','Registro turístico y capacidad de comercialización','Tourism registration and marketing authority','التسجيل السياحي وصلاحية التسويق',['accommodation'],false,false],
        ['employment','Vinculación con la empresa','Company affiliation','الارتباط بالشركة',[],false,false],
    ] as [$id,$es,$en,$ar,$activities,$required,$expires])$documents[]=['id'=>$id,'title'=>$text($es,$en,$ar),'categories'=>$activities,'required'=>$required,'expires'=>$expires,'businessTypes'=>$id==='employment'?['employee']:[],'cities'=>[]];
    $cities=[];
    foreach(['madrid'=>'Madrid','barcelona'=>'Barcelona','malaga'=>'Málaga','marbella'=>'Marbella','sevilla'=>'Sevilla','granada'=>'Granada','cordoba'=>'Córdoba','valencia'=>'Valencia','alicante'=>'Alicante','bilbao'=>'Bilbao','toledo'=>'Toledo','palma'=>'Palma de Mallorca'] as $id=>$name)$cities[]=['id'=>$id,'name'=>$name];
    $defaults=json_decode(file_get_contents(__DIR__.'/partner-registration-defaults.json'),true,512,JSON_THROW_ON_ERROR);
    return ['version'=>1,'categories'=>$categories,'documents'=>$documents,'cities'=>$cities,'legalSlugs'=>['collaboration'=>'collaboration-terms','privacy'=>'privacy','economic'=>'partner-economic-terms'],'commissionRules'=>$defaults['commissionRules'],'retentionDays'=>365,'requirementsReviewed'=>false];
}

function partner_registration_config(): array {
    $raw=db()->query('SELECT config_json FROM home_config WHERE id=1')->fetchColumn();
    $home=$raw?json_decode($raw,true,512,JSON_THROW_ON_ERROR):[];
    return array_replace(partner_registration_defaults(),$home['_partnerRegistration']??[]);
}

function partner_review_state(array $row,array $profile): string {
    return match($row['status']??'draft'){
        'submitted'=>$profile['_reviewState']??'pending_review',
        'draft'=>($profile['_reviewState']??'')==='changes_requested'?'changes_requested':'draft',
        default=>$row['status'],
    };
}

function partner_required_documents(array $draft,array $config): array {
    return array_values(array_filter($config['documents'],static function($rule)use($draft){
        if(!empty($rule['categories'])&&!array_intersect($rule['categories'],$draft['categories']??[]))return false;
        if(!empty($rule['businessTypes'])&&!in_array($draft['businessType']??'',$rule['businessTypes'],true))return false;
        if(!empty($rule['cities'])&&!array_intersect($rule['cities'],array_column($draft['workCities']??[],'id')))return false;
        return true;
    }));
}

function partner_document_state(array $document,?array $review): string {
    if(!empty($document['expiresAt'])&&$document['expiresAt']<gmdate('Y-m-d'))return 'expired';
    if(empty($document['uploadId']))return 'missing';
    return $review&&($review['uploadId']??'')===$document['uploadId']?($review['status']??'attached'):'attached';
}

function partner_onboarding_validate(array $draft,string $uid,bool $complete=false): array {
    $allowed=['step','completedSteps','categories','firstName','lastName','phone','email','country','languages','languageLevels','businessType','fiscalName','businessName','taxId','fiscalAddress','postalCode','city','province','fiscalCountry','experienceYears','representative','representativeRole','businessPhone','employerName','employerTaxId','jobRole','workCities','zones','publicName','descriptions','specialties','professionalPhotoUploadId','coverUploadId','galleryUploadIds','documents'];
    $unknown=array_diff(array_keys($draft),$allowed);if($unknown)throw new InvalidArgumentException('Unknown onboarding fields: '.implode(', ',$unknown));
    if(!is_int($draft['step']??null)||$draft['step']<0||$draft['step']>6)throw new InvalidArgumentException('Invalid registration step.');
    foreach($draft as $key=>$value)if(is_string($value)&&mb_strlen($value)>1000)throw new InvalidArgumentException('Field too long: '.$key);
    foreach(['categories','languages','completedSteps','workCities','zones','specialties','galleryUploadIds','documents'] as $key)if(isset($draft[$key])&&(!is_array($draft[$key])||!array_is_list($draft[$key])||count($draft[$key])>50))throw new InvalidArgumentException('Invalid collection: '.$key);
    foreach($draft['categories']??[] as $category)if(!in_array($category,['driver','guide','experience','rental','accommodation','other'],true))throw new InvalidArgumentException('Invalid activity.');
    if(!empty($draft['businessType'])&&!in_array($draft['businessType'],['self_employed','company','employee'],true))throw new InvalidArgumentException('Invalid business type.');
    if(isset($draft['experienceYears'])&&$draft['experienceYears']!==''&&filter_var($draft['experienceYears'],FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>100]])===false)throw new InvalidArgumentException('Experience must be a number between 0 and 100.');
    foreach($draft['languages']??[] as $language)if(!is_string($language)||strlen($language)>40)throw new InvalidArgumentException('Invalid language.');
    foreach($draft['specialties']??[] as $specialty)if(!is_string($specialty)||mb_strlen($specialty)>80)throw new InvalidArgumentException('Invalid specialty.');
    foreach($draft['zones']??[] as $zone)if(!in_array($zone,['city','intercity','national','international'],true))throw new InvalidArgumentException('Invalid work area.');
    if(!empty($draft['email'])&&!filter_var($draft['email'],FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Invalid email.');
    if(!empty($draft['phone'])&&!preg_match('/^\+[1-9][0-9]{7,14}$/',$draft['phone']))throw new InvalidArgumentException('Use an international phone number.');
    $cities=[];foreach($draft['workCities']??[] as $city){if(!is_array($city)||!preg_match('/^[a-z0-9:_-]{1,100}$/',$city['id']??'')||empty($city['name'])||mb_strlen($city['name'])>120)throw new InvalidArgumentException('Invalid work city.');$cities[$city['id']]=['id'=>$city['id'],'name'=>$city['name']];}$draft['workCities']=array_values($cities);
    if(isset($draft['descriptions'])&&(!is_array($draft['descriptions'])||count($draft['descriptions'])>20))throw new InvalidArgumentException('Invalid localized text.');foreach($draft['descriptions']??[] as $locale=>$text)if(!is_string($text)||mb_strlen($text)>2000||!preg_match('/^[a-z]{2,8}$/',(string)$locale))throw new InvalidArgumentException('Invalid localized text.');
    if(isset($draft['languageLevels'])&&(!is_array($draft['languageLevels'])||count($draft['languageLevels'])>50))throw new InvalidArgumentException('Invalid language level.');if(isset($draft['languageLevels']))$draft['languageLevels']=array_intersect_key($draft['languageLevels'],array_flip($draft['languages']??[]));foreach($draft['languageLevels']??[] as $language=>$level)if(!in_array($level,['A1','A2','B1','B2','C1','C2'],true))throw new InvalidArgumentException('Invalid language level.');
    foreach(['professionalPhotoUploadId'=>'professional-photo','coverUploadId'=>'professional-cover'] as $key=>$purpose)if(!empty($draft[$key])&&!owned_upload($uid,(string)$draft[$key],$purpose))throw new InvalidArgumentException('Photo is not owned by this account.');
    foreach($draft['galleryUploadIds']??[] as $id)if(!is_string($id)||!owned_upload($uid,$id,'professional-gallery'))throw new InvalidArgumentException('Gallery image is not owned.');
    $documents=[];
    foreach($draft['documents']??[] as $document){if(!is_array($document)||!preg_match('/^[a-z0-9_-]{1,60}$/',$document['requirementId']??'')||empty($document['uploadId'])||!owned_upload($uid,(string)$document['uploadId'],'professional-document'))throw new InvalidArgumentException('Invalid private document.');$expiry=$document['expiresAt']??null;if($expiry!==null&&(!is_string($expiry)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$expiry)||!strtotime($expiry)))throw new InvalidArgumentException('Invalid expiry date.');$documents[$document['requirementId']]=['requirementId'=>$document['requirementId'],'uploadId'=>$document['uploadId'],'expiresAt'=>$expiry];}
    $draft['documents']=array_values($documents);
    if($complete){
        foreach(['firstName','lastName','phone','email','country','businessType','experienceYears','publicName','professionalPhotoUploadId'] as $key)if(trim((string)($draft[$key]??''))==='')throw new InvalidArgumentException('Required: '.$key);
        if(empty($draft['categories'])||empty($draft['languages'])||empty($draft['workCities'])||!array_filter($draft['descriptions']??[]))throw new InvalidArgumentException('Complete activities, languages, cities and public description.');
        $fields=$draft['businessType']==='employee'?['employerName','employerTaxId','jobRole']:['fiscalName','taxId','fiscalAddress','postalCode','city','province','fiscalCountry'];
        if($draft['businessType']==='company')$fields=[...$fields,'businessName','representative','representativeRole','businessPhone'];
        foreach($fields as $key)if(trim((string)($draft[$key]??''))==='')throw new InvalidArgumentException('Required: '.$key);
        $config=partner_registration_config();
        if(!$config['requirementsReviewed'])throw new InvalidArgumentException('Administration must configure the applicable document requirements before applications can be submitted.');
        foreach(partner_required_documents($draft,$config) as $rule)if($rule['required']){
            $doc=$documents[$rule['id']]??[];
            if(empty($doc['uploadId'])||($rule['expires']&&empty($doc['expiresAt']))||partner_document_state($doc,null)==='expired')throw new InvalidArgumentException('Required current document: '.$rule['id']);
        }
    }
    return $draft;
}

function partner_registration_public_data(string $uid,array $profile,array $row): array {
    $draft=$profile['onboarding']??null;if(!is_array($draft))return [];
    $documents=[];foreach($draft['documents']??[] as $document)$documents[]=[...$document,'file'=>upload_reference($uid,$document['uploadId']),'status'=>partner_document_state($document,$profile['_documentReviews'][$document['requirementId']]??null),'reason'=>$profile['_documentReviews'][$document['requirementId']]['reason']??''];
    return ['onboarding'=>$draft,'documents'=>$documents,'reviewStatus'=>partner_review_state($row,$profile),'reviewReason'=>$profile['_reviewReason']??'','savedAt'=>$profile['_savedAt']??null,'vehicles'=>$profile['_vehicles']??[],'notifications'=>partner_registration_notifications($profile['_notifications']??[]),'canPublish'=>partner_operational($uid),'publicProfile'=>partner_safe_public_profile($draft)];
}

function partner_safe_public_profile(array $draft): array {
    return array_intersect_key($draft,array_flip(['publicName','descriptions','languages','experienceYears','specialties','workCities','professionalPhotoUploadId','coverUploadId','galleryUploadIds']));
}

function partner_registration_event(array &$profile,string $type,string $target,string $message,?string $reason=null): void {
    $items=$profile['_notifications']??[];$items[]=['id'=>bin2hex(random_bytes(12)),'type'=>$type,'target'=>$target,'message'=>$message,'reason'=>$reason,'createdAt'=>gmdate(DATE_ATOM),'read'=>false];$profile['_notifications']=array_slice($items,-100);
}

function partner_registration_notifications(array $items): array {
    $language=substr((string)($_SERVER['HTTP_ACCEPT_LANGUAGE']??'es'),0,2);$index=['es'=>0,'en'=>1,'ar'=>2][$language]??0;
    $subjects=['profile'=>['Perfil','Profile','الملف'],'document'=>['Documento','Document','المستند'],'vehicle'=>['Vehículo','Vehicle','المركبة']];
    $states=['under_review'=>['En revisión','Under review','قيد المراجعة'],'changes_requested'=>['Correcciones solicitadas','Changes requested','تعديلات مطلوبة'],'approved'=>['Aprobado','Approved','معتمد'],'verified'=>['Verificado','Verified','موثق'],'rejected'=>['Rechazado','Rejected','مرفوض'],'suspended'=>['Suspendido','Suspended','معلق'],'changed'=>['Pendiente de nueva revisión','Awaiting a new review','بانتظار مراجعة جديدة']];
    foreach($items as &$item){$type=(string)($item['type']??'');if($type==='application_submitted')$item['message']=['Solicitud recibida. Pendiente de verificación.','Application received. Pending verification.','تم استلام الطلب. بانتظار التحقق.'][$index];else foreach($subjects as $subject=>$labels)if(str_starts_with($type,$subject.'_')){$state=substr($type,strlen($subject)+1);if(isset($states[$state]))$item['message']=$labels[$index].' · '.$states[$state][$index];break;}if(!empty($item['reason']))$item['message'].="\n".$item['reason'];}unset($item);return $items;
}

function partner_commission_rule(string $category,?string $partnerId=null,?string $serviceId=null): ?int {
    $chosen=null;$best=-1;
    foreach(partner_registration_config()['commissionRules'] as $rule){
        if(isset($rule['category'])&&$rule['category']!==$category)continue;
        if(isset($rule['partnerId'])&&$rule['partnerId']!==$partnerId)continue;
        if(isset($rule['serviceId'])&&$rule['serviceId']!==$serviceId)continue;
        $score=(isset($rule['serviceId'])?4:0)+(isset($rule['partnerId'])?2:0)+(isset($rule['category'])?1:0);
        if($score>$best){$chosen=(int)$rule['basisPoints'];$best=$score;}
    }
    return $chosen;
}

function partner_service_authorized(string $uid,array $service,string $type): bool {
    if(!partner_operational($uid))return false;
    $s=db()->prepare('SELECT profile_json FROM partner_profiles WHERE user_id=?');$s->execute([$uid]);$profile=json_decode($s->fetchColumn()?:'{}',true,512,JSON_THROW_ON_ERROR);
    if(!isset($profile['onboarding']))return true;
    if(($profile['onboarding']['businessType']??'')==='employee')return false;
    if($type==='driver'){
        $vehicle=$profile['_vehicles'][$service['vehicleId']??'']??null;
        if(!$vehicle||($vehicle['status']??'')!=='approved')return false;
        foreach($vehicle['vehicle']['documents']??[] as $doc)if(partner_document_state($doc,null)==='expired')return false;
    }
    return true;
}

function partner_approval_checks(string $uid,array $profile): void {
    if(empty($profile['onboarding']))return;
    $draft=partner_onboarding_validate($profile['onboarding'],$uid,true);
    foreach(partner_required_documents($draft,partner_registration_config()) as $rule)if($rule['required']){
        $doc=null;foreach($draft['documents']??[] as $item)if($item['requirementId']===$rule['id'])$doc=$item;
        if(!$doc||partner_document_state($doc,$profile['_documentReviews'][$rule['id']]??null)!=='verified')throw new RuntimeException('Verifica los documentos obligatorios antes de aprobar.');
    }
    if(empty($profile['_acceptances']))throw new RuntimeException('Faltan las aceptaciones legales.');
}

function partner_registration_upload_in_use(string $uid,string $id): bool {
    $s=db()->prepare('SELECT profile_json FROM partner_profiles WHERE user_id=?');$s->execute([$uid]);$profile=json_decode($s->fetchColumn()?:'{}',true,512,JSON_THROW_ON_ERROR);
    $walk=function($node)use(&$walk,$id):bool{if(is_array($node)){foreach($node as $value)if($walk($value))return true;return false;}return $node===$id;};
    return $walk($profile);
}
