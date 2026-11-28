<?php
declare(strict_types=1);
// Chauffeur enquiries reuse trip_requests; confirmed services use reservations.
function chauffeur_states():array{return ['new'=>'Nueva solicitud','in_review'=>'En revisión','proposal_sent'=>'Presupuesto enviado','awaiting_acceptance'=>'Pendiente de aceptación','accepted'=>'Aceptada','confirmed'=>'Confirmada','completed'=>'Finalizada','cancelled'=>'Cancelada'];}
function chauffeur_json(array $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}
function chauffeur_payload(array $row):array{return json_decode($row['answers_json'],true,512,JSON_THROW_ON_ERROR);}
function chauffeur_id():string{$b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($b),4));}
function chauffeur_validate(array $b):array {
 $allowed=['city','journey','direction','pickup','destination','departure','passengers','bags','hours','flight','notes','vehiclePreference','returnDeparture'];
 if(array_diff(array_keys($b),$allowed))throw new DomainException('Unknown request fields.');
 foreach(['pickup'=>250,'destination'=>250,'flight'=>40,'notes'=>1500,'city'=>80] as $key=>$max){if(!is_string($b[$key]??'' )||mb_strlen($b[$key]??'')>$max)throw new DomainException('Invalid '.$key);$b[$key]=trim($b[$key]??'');}
 if($b['pickup']===''||!preg_match('/^[a-z_-]{1,80}$/D',$b['city']))throw new DomainException('Pickup and city are required.');
 if(!in_array($b['journey']??null,['airport','intercity','international','hourly'],true)||!in_array($b['direction']??null,['arrival','departure'],true))throw new DomainException('Invalid service type.');
 if($b['journey']!=='hourly'&&$b['destination']==='')throw new DomainException('Destination is required.');
 foreach(['passengers'=>[1,20],'bags'=>[0,20]] as $key=>[$min,$max])if(!is_int($b[$key]??null)||$b[$key]<$min||$b[$key]>$max)throw new DomainException('Invalid '.$key);
 if($b['journey']==='hourly'&&!in_array($b['hours']??null,[4,8,12],true))throw new DomainException('Invalid hours.');
 if(!in_array($b['vehiclePreference']??null,['recommend','business','first','van','minibus'],true))throw new DomainException('Invalid vehicle preference.');
 foreach(['departure','returnDeparture'] as $key){if($key==='returnDeparture'&&($b[$key]??null)===null)continue;$value=$b[$key]??null;if(!is_string($value)||!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?Z$/D',$value))throw new DomainException('Invalid date.');try{$date=new DateTimeImmutable($value);}catch(Throwable){throw new DomainException('Invalid date.');}$errors=DateTimeImmutable::getLastErrors();if($errors&&($errors['warning_count']||$errors['error_count']))throw new DomainException('Invalid date.');if($key==='departure'&&($date->getTimestamp()<time()+43200||$date->getTimestamp()>time()+366*86400))throw new DomainException('Request at least 12 hours in advance, within one year.');if($key==='returnDeparture'&&$date<=new DateTimeImmutable($b['departure']))throw new DomainException('Return must follow departure.');}
 return $b;
}
function chauffeur_current_state(array $p):string {if(!empty($p['reservationId'])){$s=db()->prepare('SELECT status FROM reservations WHERE id=?');$s->execute([$p['reservationId']]);$status=$s->fetchColumn();return match($status){'cancelled','declined'=>'cancelled','completed'=>'completed','accepted'=>'confirmed',default=>$p['workflow']};}return $p['workflow'];}
function chauffeur_public(array $r):array{$p=chauffeur_payload($r);$p['workflow']=chauffeur_current_state($p);return ['id'=>$r['id'],'reference'=>'CH-'.strtoupper(substr(str_replace('-','',$r['id']),-12)),'status'=>$p['workflow'],'request'=>$p['request'],'proposal'=>$p['proposal']??null,'reservationId'=>$p['reservationId']??null,'revision'=>(int)$r['revision'],'createdAt'=>$r['created_at'],'updatedAt'=>$r['updated_at'],'paymentStatus'=>'notCollected'];}
function chauffeur_transition(string $from,string $to,bool $customer):bool {
 if($customer)return ($to==='accepted'&&in_array($from,['proposal_sent','awaiting_acceptance'],true))||($to==='cancelled'&&in_array($from,['new','in_review','proposal_sent','awaiting_acceptance','accepted'],true));
 return in_array($to,match($from){'new'=>['in_review','cancelled'],'in_review'=>['proposal_sent','cancelled'],'proposal_sent'=>['proposal_sent','awaiting_acceptance','cancelled'],'awaiting_acceptance'=>['proposal_sent','cancelled'],'accepted'=>['confirmed','proposal_sent','cancelled'],'confirmed'=>['completed','cancelled'],default=>[]},true);
}
function chauffeur_load(string $id,?string $owner=null,bool $lock=false):?array{$s=db()->prepare("SELECT * FROM trip_requests WHERE id=? AND JSON_UNQUOTE(JSON_EXTRACT(answers_json,'$.requestType'))='chauffeur'".($owner!==null?' AND user_id=?':'').($lock?' FOR UPDATE':''));$s->execute($owner!==null?[$id,$owner]:[$id]);return $s->fetch()?:null;}
function chauffeur_store(array $row,array $p):void{$status=match($p['workflow']){'new'=>'submitted','in_review'=>'in_review','proposal_sent','awaiting_acceptance','accepted','confirmed'=>'proposal_ready',default=>'closed'};db()->prepare('UPDATE trip_requests SET status=?,answers_json=?,revision=revision+1 WHERE id=?')->execute([$status,chauffeur_json($p),$row['id']]);}
function chauffeur_audit(?int $adminId,string $id,string $from,string $to):void{db()->prepare("INSERT INTO audit_log(admin_id,action,entity_type,entity_id,details_json) VALUES(?,'chauffeur.workflow_changed','trip_request',?,?)")->execute([$adminId,$id,chauffeur_json(['from'=>$from,'to'=>$to])]);}
function chauffeur_admin_update(string $id,int $revision,string $to,array $proposal,int $actor):void {
 db()->beginTransaction();try{
 $row=chauffeur_load($id,null,true);if(!$row||(int)$row['revision']!==$revision)throw new DomainException('La solicitud ha cambiado. Recarga antes de continuar.');
 $p=chauffeur_payload($row);$p['workflow']=chauffeur_current_state($p);$from=$p['workflow'];if(!chauffeur_transition($from,$to,false))throw new DomainException('Transición no permitida.');
 if($to==='proposal_sent'){
  $price=(string)($proposal['amount']??'');if(!preg_match('/^[0-9]{1,7}(\.[0-9]{1,2})?$/D',$price))throw new DomainException('Introduce un precio válido en EUR.');$minor=(int)round((float)$price*100);$terms=trim((string)($proposal['terms']??''));$days=(int)($proposal['validDays']??0);
  if($minor<1||$minor>100000000||$terms===''||mb_strlen($terms)>5000||$days<1||$days>30)throw new DomainException('Completa el precio, condiciones y vigencia (1–30 días).');
  $p['proposal']=['amountMinor'=>$minor,'currency'=>'EUR','terms'=>$terms,'expiresAt'=>gmdate('c',time()+$days*86400),'version'=>(int)($p['proposal']['version']??0)+1,'sentAt'=>gmdate('c')];unset($p['acceptance']);
 }
 if($to==='confirmed'){
  require_once __DIR__.'/account_controls.php';require_once __DIR__.'/partner_registration.php';
  $s=db()->prepare("SELECT * FROM driver_services WHERE id=? AND status='published' FOR UPDATE");$s->execute([(string)($proposal['serviceId']??'')]);$service=$s->fetch();
  $details=$service?json_decode($service['service_json'],true):[];
  if(!$service||!partner_service_authorized((string)$service['owner_user_id'],array_replace($details,['id'=>$service['id']]),'driver'))throw new DomainException('Elige un servicio y vehículo profesional aprobados y vigentes.');
  foreach(array_filter([$p['request']['departure'],$p['request']['returnDeparture']??null]) as $departure){$date=substr($departure,0,10);$s=db()->prepare("SELECT available FROM service_calendar WHERE service_type='driver' AND service_id=? AND service_date=? FOR UPDATE");$s->execute([$service['id'],$date]);if((int)$s->fetchColumn()!==1)throw new DomainException('No hay disponibilidad publicada para cada trayecto.');}
  if(!account_enabled($row['user_id']))throw new DomainException('La cuenta del cliente está restringida.');
  $s=db()->prepare('SELECT profile_json FROM users WHERE id=?');$s->execute([$row['user_id']]);$customerProfile=json_decode($s->fetchColumn()?:'{}',true);$requirements=$details['travelerRequirements']??[];if(array_intersect($requirements,['passportUploadId','documentType','documentNumber','documentCountry','documentIssueDate','documentExpiryDate'])&&!owned_upload($row['user_id'],$customerProfile['travelerDetails']['passportUploadId']??null,'customer-passport'))throw new DomainException('Este servicio necesita el pasaporte privado del cliente.');
  if(($p['request']['passengers']??0)>(int)($details['seats']??$details['capacity']??0)||($p['request']['bags']??0)>(int)($details['bags']??0))throw new DomainException('La capacidad del vehículo no cubre la solicitud.');
  if(strtotime($p['request']['departure'])<=time())throw new DomainException('La fecha del servicio ya ha pasado.');
  $q=chauffeur_id();$r=chauffeur_id();$price=['amountMinor'=>$p['proposal']['amountMinor'],'currency'=>'EUR'];
  $quote=['price'=>$price,'search'=>$p['request'],'chauffeurRequestId'=>$id,'proposal'=>$p['proposal'],'partnerId'=>$service['owner_user_id']];
  db()->prepare("INSERT INTO quotes(id,user_id,service_type,service_id,request_json,quote_json,currency,total_minor,expires_at) VALUES(?,?,'driver',?,?,?,'EUR',?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))")->execute([$q,$row['user_id'],$service['id'],chauffeur_json($p['request']),chauffeur_json($quote),$p['proposal']['amountMinor']]);
  db()->prepare("INSERT INTO reservations(id,customer_user_id,partner_user_id,quote_id,service_type,service_id,status,payment_status,snapshot_json,idempotency_key) VALUES(?,?,?,?,'driver',?,'accepted','notCollected',?,?)")->execute([$r,$row['user_id'],$service['owner_user_id'],$q,$service['id'],chauffeur_json(['quote'=>$quote,'travelerDetails'=>$p['travelerSnapshot'],'customer'=>$p['customerSnapshot']]),'chauffeur:'.$id]);
  $p['reservationId']=$r;
 }
 if(in_array($to,['completed','cancelled'],true)&&!empty($p['reservationId']))db()->prepare('UPDATE reservations SET status=?,version=version+1 WHERE id=?')->execute([$to,$p['reservationId']]);
 $p['workflow']=$to;chauffeur_store($row,$p);chauffeur_audit($actor,$id,$from,$to);db()->commit();
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
