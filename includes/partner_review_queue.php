<?php
require_once __DIR__.'/account_controls.php';
require_once __DIR__.'/partner_registration.php';
$tabs=['partners'=>'Colaboradores pendientes','drafts'=>'Servicios pendientes','vehicles'=>'Vehículos pendientes','documents'=>'Documentos caducados o próximos a caducar','corrections'=>'Solicitudes de corrección'];
$view=(string)($_GET['view']??'partners');if(!isset($tabs[$view]))$view='partners';
if($_SERVER['REQUEST_METHOD']==='POST'){
    require_owner();verify_csrf();
    try{
        $id=(string)($_POST['id']??'');$status=(string)($_POST['status']??'');$reason=trim((string)($_POST['reason']??''));
        if($view!=='drafts'||!in_array($status,['review','approved','rejected','draft'],true))throw new RuntimeException('Decisión no válida.');
        if(in_array($status,['rejected','draft'],true)&&mb_strlen($reason)<5)throw new RuntimeException('Indica las correcciones o el motivo del rechazo.');
        db()->beginTransaction();$s=db()->prepare('SELECT * FROM partner_service_drafts WHERE id=? FOR UPDATE');$s->execute([$id]);$row=$s->fetch();
        if(!$row||(int)$row['revision']!==(int)($_POST['revision']??-1))throw new RuntimeException('El servicio ha cambiado. Recarga antes de revisarlo.');
        $profileQuery=db()->prepare('SELECT profile_json FROM partner_profiles WHERE user_id=? FOR UPDATE');$profileQuery->execute([$row['user_id']]);$p=json_decode($profileQuery->fetchColumn()?:'{}',true,512,JSON_THROW_ON_ERROR);
        $body=json_decode($row['service_json'],true,512,JSON_THROW_ON_ERROR);
        if($status==='approved'&&(!partner_price_policy($body,$p['_publicationChecks']??[])||empty($body)))throw new RuntimeException('El servicio está vacío.');
        if($status==='approved'){
            if(!in_array($body['city']??'',['madrid','barcelona','malaga'],true)||empty($body['title']['es'])||empty($body['description']))throw new RuntimeException('Completa ciudad, título en español, descripción y precios del servicio.');
            $catalog=$body;$catalog['id']=$id;$table=$row['service_type']==='driver'?'driver_services':'guide_services';
            $existing=db()->prepare("SELECT owner_user_id FROM $table WHERE id=? FOR UPDATE");$existing->execute([$id]);$owner=$existing->fetchColumn();if($owner!==false&&$owner!==$row['user_id'])throw new RuntimeException('El identificador pertenece a otro registro.');
            if($row['service_type']==='driver'){
                if(empty($body['vehicleId']))throw new RuntimeException('Selecciona un vehículo.');
                $catalog['packages']=[];foreach($body['pricesCents']??[] as $option=>$price)$catalog['packages'][]=['option'=>$option,'priceMinor'=>$price];
                $category=$body['category']??'standard';if(!in_array($category,['standard','executive','van'],true))throw new RuntimeException('Categoría de vehículo no admitida.');
                db()->prepare("INSERT INTO driver_services(id,owner_user_id,status,city,category,title_json,service_json) VALUES(?,?,'draft',?,?,?,?) ON DUPLICATE KEY UPDATE city=VALUES(city),category=VALUES(category),title_json=VALUES(title_json),service_json=VALUES(service_json),revision=revision+1")->execute([$id,$row['user_id'],$body['city'],$category,json_encode($body['title'],JSON_UNESCAPED_UNICODE),json_encode($catalog,JSON_UNESCAPED_UNICODE)]);
            }else db()->prepare("INSERT INTO guide_services(id,owner_user_id,status,city,title_json,service_json) VALUES(?,?,'draft',?,?,?) ON DUPLICATE KEY UPDATE city=VALUES(city),title_json=VALUES(title_json),service_json=VALUES(service_json),revision=revision+1")->execute([$id,$row['user_id'],$body['city'],json_encode($body['title'],JSON_UNESCAPED_UNICODE),json_encode($catalog,JSON_UNESCAPED_UNICODE)]);
        }
        $p['_serviceReviews'][$id]=['status'=>$status==='draft'?'changes_requested':$status,'reason'=>$reason,'reviewedBy'=>admin()['id'],'reviewedAt'=>gmdate(DATE_ATOM)];
        partner_registration_event($p,'service_'.($status==='draft'?'changes_requested':$status),$id,'Estado del servicio actualizado.',$reason);
        db()->prepare('UPDATE partner_service_drafts SET status=?,revision=revision+1 WHERE id=?')->execute([$status,$id]);
        db()->prepare('UPDATE partner_profiles SET profile_json=?,revision=revision+1 WHERE user_id=?')->execute([json_encode($p,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$row['user_id']]);
        if($status!=='approved'){$table=$row['service_type']==='driver'?'driver_services':'guide_services';db()->prepare("UPDATE $table SET status='paused',revision=revision+1 WHERE id=? AND owner_user_id=? AND status='published'")->execute([$id,$row['user_id']]);}
        db()->prepare("INSERT INTO audit_log(admin_id,action,entity_type,entity_id,details_json) VALUES(?,'partner_service.reviewed','partner_service_draft',?,?)")->execute([admin()['id'],$id,json_encode(['status'=>$status,'reason'=>$reason])]);
        db()->commit();flash('success','Revisión guardada. La aprobación del servicio no publica el catálogo ni aprueba el perfil.');
    }catch(Throwable $error){if(db()->inTransaction())db()->rollBack();flash('error',$error instanceof PDOException?'No se pudo guardar la revisión. Recarga e inténtalo de nuevo.':$error->getMessage());}
    redirect('operations.php?view='.$view);
}
page_header('Revisiones profesionales');
echo '<div class="filters people-tabs">';foreach($tabs as $key=>$label)echo '<a class="'.($view===$key?'active':'').'" href="?view='.$key.'">'.e($label).'</a>';echo '</div><section class="panel"><h3>'.e($tabs[$view]).'</h3>';
if($view==='drafts'){
    echo '<p><a href="?view=drafts">Pendientes</a> · <a href="?view=drafts&amp;scope=all">Todos los estados</a></p>';
    $rows=db()->query("SELECT d.*,CONCAT(u.first_name,' ',u.last_name) owner_name FROM partner_service_drafts d JOIN users u ON u.id=d.user_id ORDER BY d.updated_at DESC")->fetchAll();
    foreach($rows as $row){if(($_GET['scope']??'')!=='all'&&$row['status']!=='review')continue;echo '<article class="review-entry"><h4>'.e($row['owner_name']).' · '.e($row['service_type']).'</h4><p>'.e($row['id']).' · '.e($row['status']).'</p><details><summary>Contenido del servicio</summary><pre>'.e(json_encode(json_decode($row['service_json'],true),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)).'</pre></details><a href="users.php?view=collaborators&amp;id='.e($row['user_id']).'">Ver perfil y acreditaciones</a>';
    if((admin()['role']??'')==='admin')echo '<form method="post" class="editor-form"><input type="hidden" name="_csrf" value="'.csrf().'"><input type="hidden" name="id" value="'.e($row['id']).'"><input type="hidden" name="revision" value="'.(int)$row['revision'].'"><label>Decisión<select name="status"><option value="review">Pendiente</option><option value="approved">Aprobar servicio</option><option value="draft">Solicitar correcciones</option><option value="rejected">Rechazar</option></select></label><label>Motivo<textarea name="reason" maxlength="1000"></textarea></label><button class="btn secondary">Guardar revisión</button></form>';echo '</article>';}
}else{
    $rows=db()->query("SELECT p.*,CONCAT(u.first_name,' ',u.last_name) owner_name FROM partner_profiles p JOIN users u ON u.id=p.user_id ORDER BY p.updated_at DESC")->fetchAll();$found=false;
    foreach($rows as $row){$p=json_decode($row['profile_json'],true);$items=[];$state=partner_review_state($row,$p);
        if($view==='partners'&&in_array($state,['pending_review','under_review'],true))$items[]='Perfil · '.$state;
        if($view==='vehicles')foreach($p['_vehicles']??[] as $v)if(($v['status']??'')==='pending_review')$items[]='Vehículo · '.($v['vehicle']['plate']??'');
        if($view==='documents'){
            $docs=$p['onboarding']['documents']??[];foreach($p['_vehicles']??[] as $v)$docs=array_merge($docs,$v['vehicle']['documents']??[]);
            foreach($docs as $doc)if(!empty($doc['expiresAt'])&&$doc['expiresAt']<=gmdate('Y-m-d',time()+30*86400))$items[]=($doc['requirementId']??'Documento').' · '.$doc['expiresAt'];
        }
        if($view==='corrections'){
            if($state==='changes_requested')$items[]='Perfil · '.($p['_reviewReason']??'');
            foreach($p['_serviceReviews']??[] as $id=>$review)if(($review['status']??'')==='changes_requested')$items[]='Servicio '.$id.' · '.($review['reason']??'');
        }
        foreach($items as $item){$found=true;echo '<article class="review-entry"><h4>'.e($row['owner_name']).'</h4><p>'.e($item).'</p><a href="users.php?view=collaborators&amp;id='.e($row['user_id']).'">Abrir revisión privada</a></article>';}
    }if(!$found)echo '<p class="empty">No hay solicitudes en esta sección.</p>';
}
echo '</section>';page_footer();
