<?php
require dirname(__DIR__).'/includes/partner_registration.php';
$count=0;
function check($actual,$expected){global $count;if($actual!==$expected)throw new RuntimeException('Approval assertion failed: '.json_encode([$actual,$expected]));$count++;}
$service=['id'=>'service-a','title'=>['es'=>'Visita'],'description'=>['es'=>'Recorrido'],'vehicleId'=>'vehicle-a','currency'=>'EUR','pricesCents'=>['transfer'=>5000],'closedWeekdays'=>[0]];
$old=['status'=>'approved','service_json'=>json_encode($service)];
check(partner_service_next_state(null,$service),'review');
check(partner_service_next_state($old,$service),'approved');
foreach(['title'=>['es'=>'Nuevo recorrido'],'description'=>['es'=>'Ruta diferente'],'vehicleId'=>'vehicle-b','hours'=>4,'currency'=>'USD'] as $key=>$value){$changed=$service;$changed[$key]=$value;check(partner_service_next_state($old,$changed),'review');}
foreach(['pricesCents'=>['transfer'=>5500],'closedWeekdays'=>[0,1],'schedule'=>['start'=>'09:00'],'availability'=>['2026-12-01'=>false]] as $key=>$value){$changed=$service;$changed[$key]=$value;check(partner_service_next_state($old,$changed),'approved');}
check(partner_service_next_state(['status'=>'rejected','service_json'=>json_encode($service)],$service),'rejected');
check(partner_price_policy($service,['priceMinMinor'=>4000,'priceMaxMinor'=>6000]),true);
check(partner_price_policy($service,['priceMinMinor'=>6000,'priceMaxMinor'=>7000]),false);
check(partner_price_policy($service,['priceMinMinor'=>1000,'priceMaxMinor'=>4000]),false);
check(partner_price_policy(array_replace($service,['pricesCents'=>['transfer'=>-1]]),[]),false);
check(partner_price_policy(array_replace($service,['pricesCents'=>['transfer'=>1.5]]),[]),false);
check(partner_price_policy(array_replace($service,['pricesCents'=>['transfer'=>0]]),[]),false);
check(partner_price_policy(array_replace($service,['currency'=>'USD']),[]),false);
check(partner_price_policy(['title'=>'Empty draft'],[]),false);
check(partner_price_policy(['pricesCents'=>'malformed'],[]),false);
check(partner_service_material(['b'=>['y'=>1,'x'=>2],'a'=>3]),partner_service_material(['a'=>3,'b'=>['x'=>2,'y'=>1]]));
// Exercise the publication gate against independent server-owned decisions.
$operational=true;
$profile=['onboarding'=>['businessType'=>'self_employed','categories'=>['driver','guide']], '_vehicles'=>['vehicle-a'=>['status'=>'approved','vehicle'=>['documents'=>[]]]], '_authorizedActivities'=>['driver','guide'], '_publicationChecks'=>['commercial'=>true,'legal'=>true,'payout'=>true]];
$storedDraft=['status'=>'approved','service_json'=>json_encode($service)];
function partner_operational($uid){global $operational;return $operational;}
function db(){return new class {
    function prepare($sql){return new class($sql){private array $params=[];function __construct(private $sql){} function execute($params){$this->params=$params;}
    function fetchColumn(){global $profile;return json_encode($profile);}
    function fetch(){global $storedDraft;return ($this->params[0]??'')==='service-a'&&($this->params[1]??'')==='partner-a'?$storedDraft:false;}
    };}
};}
check(partner_service_authorized('partner-a',$service,'driver'),true);
$operational=false;check(partner_service_authorized('partner-a',$service,'driver'),false);$operational=true;
$storedDraft['status']='review';check(partner_service_authorized('partner-a',$service,'driver'),false);$storedDraft['status']='approved';
check(partner_service_authorized('other-owner',$service,'driver'),false);
check(partner_service_authorized('partner-a',array_replace($service,['id'=>'unknown']),'driver'),false);
check(partner_service_authorized('partner-a',array_replace($service,['description'=>'Changed']),'driver'),false);
foreach(['commercial','legal','payout'] as $key){$profile['_publicationChecks'][$key]=false;check(partner_service_authorized('partner-a',$service,'driver'),false);$profile['_publicationChecks'][$key]=true;}
$profile['_authorizedActivities']=['guide'];check(partner_service_authorized('partner-a',$service,'driver'),false);$profile['_authorizedActivities'][]='driver';
$profile['_vehicles']['vehicle-a']['status']='pending_review';check(partner_service_authorized('partner-a',$service,'driver'),false);$profile['_vehicles']['vehicle-a']['status']='approved';
$profile['_vehicles']['vehicle-a']['vehicle']['documents']=[['uploadId'=>'synthetic','expiresAt'=>'2000-01-01']];check(partner_service_authorized('partner-a',$service,'driver'),false);
$profile['_vehicles']['vehicle-a']['vehicle']['documents']=[];
$profile['onboarding']['businessType']='employee';check(partner_service_authorized('partner-a',$service,'driver'),false);
echo "Independent approval policy: $count assertions passed.\n";
