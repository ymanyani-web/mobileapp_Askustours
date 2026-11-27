<?php
$u=user();require_partner($u);
if($path==='me/partner-dashboard'&&$method==='GET'){
    $counts=db()->prepare("SELECT status,COUNT(*) n FROM reservations WHERE partner_user_id=? GROUP BY status");$counts->execute([$u['id']]);$bookings=0;$pending=0;foreach($counts as $row){$bookings+=(int)$row['n'];if(in_array($row['status'],['requested','assigned'],true))$pending+=(int)$row['n'];}
    $active=0;foreach(['driver_services','guide_services'] as $table){$q=db()->prepare("SELECT COUNT(*) FROM $table WHERE owner_user_id=? AND status='published'");$q->execute([$u['id']]);$active+=(int)$q->fetchColumn();}
    $income=db()->prepare("SELECT currency,COALESCE(SUM(amount_minor),0) amountMinor FROM wallet_ledger WHERE user_id=? AND wallet_type='partner' AND status<>'void' GROUP BY currency");$income->execute([$u['id']]);$earnings=$income->fetchAll();foreach($earnings as &$amount)$amount['amountMinor']=(int)$amount['amountMinor'];unset($amount);if(!$earnings)$earnings=[['currency'=>'EUR','amountMinor'=>0]];
    $q=db()->prepare("SELECT * FROM reservations WHERE partner_user_id=? AND status='accepted'");$q->execute([$u['id']]);$upcoming=[];
    foreach($q as $row){$item=reservation_data($row);$search=$item['search']??$item['guideSearch']??[];$raw=$search['departure']??$search['date']??null;if(!$raw)continue;try{$date=new DateTimeImmutable($raw);}catch(Throwable){continue;}if($date<new DateTimeImmutable())continue;$item['startsAt']=$date->format(DATE_ATOM);$upcoming[]=$item;}
    usort($upcoming,fn($a,$b)=>strtotime($a['startsAt'])<=>strtotime($b['startsAt']));
    out(['schemaVersion'=>1,'activeServices'=>$active,'bookings'=>$bookings,'pendingRequests'=>$pending,'earnings'=>$earnings,'upcoming'=>array_slice($upcoming,0,5)]);
}
if(preg_match('#^me/partner-services/([^/]+)/(state|duplicate)$#',$path,$m)&&$method==='POST'){
    $b=json_body();db()->beginTransaction();$q=db()->prepare('SELECT * FROM partner_service_drafts WHERE id=? AND user_id=? FOR UPDATE');$q->execute([$m[1],$u['id']]);$row=$q->fetch();if(!$row)fail(404,'NOT_FOUND','The requested service was not found.');
    if((int)($b['expectedRevision']??-1)!==(int)$row['revision'])fail(409,'SERVICE_REVISION_CONFLICT','Reload the current service.');
    $body=decoded($row['service_json']);$table=$row['service_type']==='driver'?'driver_services':'guide_services';
    if($m[2]==='duplicate'){
        $id=uuid();unset($body['_workspaceState'],$body['id']);
        db()->prepare("INSERT INTO partner_service_drafts(id,user_id,service_type,status,service_json,revision) VALUES(?,?,?,'draft',?,1)")->execute([$id,$u['id'],$row['service_type'],encoded($body)]);
        db()->prepare('INSERT INTO audit_log(action,entity_type,entity_id,details_json) VALUES(?,?,?,?)')->execute(['partner.service.duplicated','partner_service',$id,encoded(['ownerId'=>$u['id'],'sourceId'=>$row['id']])]);
        db()->commit();out(['id'=>$id,'type'=>$row['service_type'],'status'=>'draft','revision'=>1,'service'=>service_media($u['id'],$body,$row['service_type'])],201);
    }
    $state=$b['state']??'';if(!in_array($state,['active','paused','archived'],true))fail(422,'VALIDATION_ERROR','Invalid service state.');
    $body['_workspaceState']=$state;
    db()->prepare('UPDATE partner_service_drafts SET service_json=?,revision=revision+1 WHERE id=? AND user_id=?')->execute([encoded($body),$row['id'],$u['id']]);
    if($state==='active'){
        if(!partner_service_authorized($u['id'],array_replace($body,['id'=>$row['id']]),$row['service_type']))fail(403,'PARTNER_NOT_APPROVED','The profile, activity, documents, agreement, vehicle and service must be authorised.');
        $q=db()->prepare("SELECT 1 FROM $table WHERE id=? AND owner_user_id=?");$q->execute([$row['id'],$u['id']]);if(!$q->fetchColumn())fail(409,'SERVICE_NOT_READY','Administration must prepare the approved service for publication.');
    }
    db()->prepare("UPDATE $table SET status=?,revision=revision+1 WHERE id=? AND owner_user_id=?")->execute([$state==='active'?'published':'paused',$row['id'],$u['id']]);
    db()->prepare('INSERT INTO audit_log(action,entity_type,entity_id,details_json) VALUES(?,?,?,?)')->execute(['partner.service.'.$state,'partner_service',$row['id'],encoded(['ownerId'=>$u['id']])]);
    db()->commit();out(['id'=>$row['id'],'type'=>$row['service_type'],'status'=>$row['status'],'revision'=>(int)$row['revision']+1,'service'=>service_media($u['id'],$body,$row['service_type'])]);
}
