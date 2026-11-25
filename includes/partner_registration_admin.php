<?php
require_once __DIR__.'/partner_registration.php';

function partner_registration_admin_action(string $uid,array $body): void {
    require_owner();$action=(string)($body['registration_action']??'');
    if($action==='settings'){
        $config=partner_registration_config();$config['requirementsReviewed']=isset($body['requirementsReviewed']);$config['version']++;
        foreach($config['documents'] as &$rule){$id=$rule['id'];$rule['required']=isset($body['required'][$id]);$rule['expires']=isset($body['expires'][$id]);$rule['cities']=array_values(array_filter(array_map('trim',explode(',',(string)($body['cities'][$id]??'')))));}unset($rule);
        if(trim((string)($body['newDocumentTitle']??''))!==''){
            $id=trim((string)($body['newDocumentId']??''));$title=trim((string)$body['newDocumentTitle']);
            if(!preg_match('/^[a-z0-9_-]{1,60}$/',$id)||in_array($id,array_column($config['documents'],'id'),true)||mb_strlen($title)>160)throw new RuntimeException('El nuevo requisito necesita un código único y un título de hasta 160 caracteres.');
            $activities=array_values(array_filter(array_map('trim',explode(',',(string)($body['newDocumentCategories']??'')))));
            if(array_diff($activities,array_column($config['categories'],'id')))throw new RuntimeException('Revisa las actividades del nuevo requisito.');
            $config['documents'][]=['id'=>$id,'title'=>['es'=>$title,'en'=>trim((string)($body['newDocumentEnglish']??''))?:$title,'ar'=>trim((string)($body['newDocumentArabic']??''))?:$title],'categories'=>$activities,'businessTypes'=>[],'cities'=>[],'required'=>isset($body['newDocumentRequired']),'expires'=>isset($body['newDocumentExpiry'])];
        }
        $days=filter_var($body['retentionDays']??'',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>3650]]);if($days===false)throw new RuntimeException('Revisa el plazo de conservación.');$config['retentionDays']=$days;
        if(isset($body['commissionEditor'])){
            $rules=[];foreach($body['commissions']??[] as $entry){if(isset($entry['remove'])||trim((string)($entry['percent']??''))==='')continue;$percent=trim((string)$entry['percent']);if(!preg_match('/^\d{1,3}(?:[.,]\d{1,2})?$/',$percent))throw new RuntimeException('Indica un porcentaje entre 0 y 100.');$rule=['basisPoints'=>(int)round((float)str_replace(',','.',$percent)*100)];foreach(['category','partnerId','serviceId'] as $scope)if(trim((string)($entry[$scope]??''))!=='')$rule[$scope]=trim((string)$entry[$scope]);$rules[]=$rule;}
        }else{$commission=trim((string)($body['commissionRules']??'[]'));$rules=json_decode($commission,true,64,JSON_THROW_ON_ERROR);}if(!is_array($rules)||count($rules)>1000)throw new RuntimeException('Reglas de comisión no válidas.');
        foreach($rules as &$rule){if(!is_array($rule)||array_diff(array_keys($rule),['category','partnerId','serviceId','basisPoints'])||!is_int($rule['basisPoints']??null)||$rule['basisPoints']<0||$rule['basisPoints']>10000)throw new RuntimeException('Cada regla necesita basisPoints entre 0 y 10000 y un ámbito válido.');foreach(['category','partnerId','serviceId'] as $key)if(isset($rule[$key])&&(!is_string($rule[$key])||strlen($rule[$key])>100))throw new RuntimeException('Ámbito no válido.');}unset($rule);$config['commissionRules']=$rules;
        db()->beginTransaction();try{
            $s=db()->query('SELECT config_json FROM home_config WHERE id=1 FOR UPDATE');$home=json_decode($s->fetchColumn()?:'{}',true,512,JSON_THROW_ON_ERROR);if((int)($body['configurationVersion']??0)!==(int)($home['_partnerRegistration']['version']??1))throw new RuntimeException('La configuración ha cambiado. Recarga antes de guardar.');$home['_partnerRegistration']=$config;
            db()->prepare('UPDATE home_config SET config_json=? WHERE id=1')->execute([json_encode($home,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
            db()->prepare("INSERT INTO audit_log(admin_id,action,entity_type,entity_id,details_json) VALUES(?,'partner_requirements.updated','partner_configuration','global',?)")->execute([admin()['id'],json_encode(['version'=>$config['version'],'retentionDays'=>$days])]);db()->commit();
        }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}return;
    }
    db()->beginTransaction();try{
        lock_owner();$s=db()->prepare('SELECT * FROM partner_profiles WHERE user_id=? FOR UPDATE');$s->execute([$uid]);$row=$s->fetch();if(!$row)throw new RuntimeException('Perfil no encontrado.');
        if((int)($body['expectedRevision']??-1)!==(int)$row['revision'])throw new RuntimeException('Otro usuario ha cambiado el registro. Recarga antes de revisar.');
        $p=json_decode($row['profile_json'],true,512,JSON_THROW_ON_ERROR);$draft=$p['onboarding']??[];$status=(string)($body['review_status']??'');$reason=trim((string)($body['reason']??''));if(mb_strlen($reason)>1000)throw new RuntimeException('Motivo demasiado largo.');
        $rowStatus=$row['status'];$target='profile';
        if($action==='document'){
            if(!in_array($status,['under_review','verified','rejected'],true))throw new RuntimeException('Estado documental no válido.');
            if($status==='rejected'&&mb_strlen($reason)<5)throw new RuntimeException('Indica el motivo de rechazo.');
            $requirement=(string)($body['requirementId']??'');$doc=null;foreach($draft['documents']??[] as $candidate)if($candidate['requirementId']===$requirement)$doc=$candidate;
            if(!$doc||!partner_private_file((string)(function()use($uid,$doc){$s=db()->prepare('SELECT path FROM uploads WHERE id=? AND user_id=?');$s->execute([$doc['uploadId'],$uid]);return $s->fetchColumn()?:'';})()))throw new RuntimeException('Falta el archivo privado.');
            if($status==='verified'&&partner_document_state($doc,null)==='expired')throw new RuntimeException('El documento está caducado.');
            $p['_documentReviews'][$requirement]=['status'=>$status,'uploadId'=>$doc['uploadId'],'reason'=>$reason,'reviewedBy'=>admin()['id'],'reviewedAt'=>gmdate(DATE_ATOM)];
            if($status==='rejected'){$rowStatus='draft';$p['_reviewState']='changes_requested';$p['_reviewReason']=$reason;pause_partner_services($uid);}
        }elseif($action==='vehicle'){
            $id=(string)($body['vehicleId']??'');if(!isset($p['_vehicles'][$id])||!in_array($status,['approved','rejected','suspended'],true))throw new RuntimeException('Vehículo o decisión no válidos.');
            if($status!=='approved'&&mb_strlen($reason)<5)throw new RuntimeException('Indica un motivo.');
            $vehicle=$p['_vehicles'][$id]['vehicle'];
            if($status==='approved'){
                if(empty($vehicle['exteriorUploadIds'])||empty($vehicle['interiorUploadIds']))throw new RuntimeException('Faltan fotografías del vehículo.');
                foreach(['registration','insurance'] as $required){$found=false;foreach($vehicle['documents']??[] as $doc)if($doc['requirementId']===$required){$found=true;if($required==='insurance'&&empty($doc['expiresAt']))throw new RuntimeException('Indica la caducidad del seguro.');if(partner_document_state($doc,null)==='expired')throw new RuntimeException('Un documento del vehículo está caducado.');}if(!$found)throw new RuntimeException('Falta documentación: '.$required);}
            }
            $p['_vehicles'][$id]['status']=$status;$p['_vehicles'][$id]['reason']=$reason;$p['_vehicles'][$id]['reviewedBy']=admin()['id'];$p['_vehicles'][$id]['reviewedAt']=gmdate(DATE_ATOM);$target='vehicles';
            if($status!=='approved')pause_partner_services($uid);
        }elseif($action==='profile'){
            if(!in_array($status,['under_review','changes_requested','approved','rejected','suspended'],true))throw new RuntimeException('Decisión no válida.');
            if(in_array($status,['changes_requested','rejected','suspended'],true)&&mb_strlen($reason)<5)throw new RuntimeException('Indica el motivo de la decisión.');
            if($status==='approved'){
                partner_onboarding_validate($draft,$uid,true);
                foreach(partner_required_documents($draft,partner_registration_config()) as $rule)if($rule['required']){
                    $doc=null;foreach($draft['documents']??[] as $candidate)if($candidate['requirementId']===$rule['id'])$doc=$candidate;
                    if(!$doc||partner_document_state($doc,$p['_documentReviews'][$rule['id']]??null)!=='verified')throw new RuntimeException('Verifica todos los documentos obligatorios antes de aprobar.');
                }
                if(empty($p['_acceptances']))throw new RuntimeException('Faltan las aceptaciones legales registradas.');
                if(!account_enabled($uid))throw new RuntimeException('La cuenta está restringida.');
            }
            $rowStatus=match($status){'under_review'=>'submitted','changes_requested'=>'draft',default=>$status};$p['_reviewState']=$status;$p['_reviewReason']=$reason;
            if($status!=='approved')pause_partner_services($uid);
        }else throw new RuntimeException('Acción no válida.');
        partner_registration_event($p,$action.'_'.$status,$target,$reason?:['under_review'=>'La revisión de tu solicitud ha comenzado.','verified'=>'Documento verificado.','approved'=>'Aprobado por administración.'][$status]??'El estado de tu solicitud ha cambiado.',$reason);
        db()->prepare('UPDATE partner_profiles SET status=?,profile_json=?,revision=revision+1,reviewed_at=NOW(),reviewed_by=? WHERE user_id=?')->execute([$rowStatus,json_encode($p,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),admin()['id'],$uid]);
        db()->prepare("INSERT INTO audit_log(admin_id,action,entity_type,entity_id,details_json) VALUES(?,?,'partner_profile',?,?)")->execute([admin()['id'],'partner_registration.'.$action,$uid,json_encode(['status'=>$status,'reason'=>$reason,'requirementId'=>$body['requirementId']??null,'vehicleId'=>$body['vehicleId']??null],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}

function partner_registration_admin_form(string $uid,int $revision,string $action,array $hidden,array $states): void {
    if((admin()['role']??'')!=='admin')return;
    echo '<form method="post" class="editor-form"><input type="hidden" name="_csrf" value="'.csrf().'"><input type="hidden" name="user_id" value="'.e($uid).'"><input type="hidden" name="action" value="registration"><input type="hidden" name="registration_action" value="'.e($action).'"><input type="hidden" name="expectedRevision" value="'.$revision.'">';
    foreach($hidden as $key=>$value)echo '<input type="hidden" name="'.e($key).'" value="'.e($value).'">';
    echo '<label>Decisión<select name="review_status">';foreach($states as $key=>$label)echo '<option value="'.e($key).'">'.e($label).'</option>';echo '</select></label><label>Motivo<textarea name="reason" maxlength="1000"></textarea></label><button class="btn secondary" data-confirm="¿Guardar esta revisión?">Guardar revisión</button></form>';
}

function render_partner_registration(string $uid,?array $row,array $profile): void {
    if(empty($profile['onboarding']))return;$draft=$profile['onboarding'];$revision=(int)$row['revision'];
    echo '<section class="panel"><div class="panel-head"><h3>Registro profesional · '.e(partner_review_state($row,$profile)).'</h3><a href="users.php?view=collaborators&amp;registration=settings">Requisitos y comisiones</a></div>';
    if(!empty($profile['professionalPhotoUploadId']))echo '<img class="professional-portrait" alt="Foto profesional" src="users.php?view=collaborators&amp;id='.e($uid).'&amp;photo=professional">';
    echo '<dl class="person-data">';foreach(['Tipo fiscal'=>'businessType','Nombre fiscal'=>'fiscalName','NIF / NIE'=>'taxId','Domicilio fiscal'=>'fiscalAddress','Código postal'=>'postalCode','Ciudad fiscal'=>'city','Provincia'=>'province','País fiscal'=>'fiscalCountry','Empresa vinculada'=>'employerName','NIF de empresa'=>'employerTaxId','Cargo'=>'jobRole','Representante'=>'representative','Cargo del representante'=>'representativeRole','Móvil profesional'=>'phone','Correo profesional'=>'email','Experiencia'=>'experienceYears','País de residencia'=>'country','Nombre público'=>'publicName'] as $label=>$key)echo '<div><dt>'.e($label).'</dt><dd>'.e((string)($draft[$key]??'—')).'</dd></div>';echo '</dl>';
    foreach(partner_required_documents($draft,partner_registration_config()) as $rule){$doc=null;foreach($draft['documents']??[] as $candidate)if($candidate['requirementId']===$rule['id'])$doc=$candidate;$review=$profile['_documentReviews'][$rule['id']]??null;
        echo '<article class="review-entry"><h4>'.e($rule['title']['es']).'</h4><p>'.e($rule['required']?'Obligatorio':'Según actividad / opcional').' · '.e(partner_document_state($doc??[],$review)).' · '.e($doc['expiresAt']??'Sin caducidad indicada').'</p>';
        if($doc){if((admin()['role']??'')==='admin')echo '<a class="btn secondary" target="_blank" rel="noopener" href="users.php?view=collaborators&amp;id='.e($uid).'&amp;private_document='.e($doc['uploadId']).'">Abrir documento privado</a>';partner_registration_admin_form($uid,$revision,'document',['requirementId'=>$rule['id']],['under_review'=>'En revisión','verified'=>'Verificar','rejected'=>'Rechazar']);}echo '</article>';
    }
    foreach($profile['_vehicles']??[] as $id=>$item){$v=$item['vehicle'];echo '<article class="review-entry"><h4>Vehículo: '.e($v['brand'].' '.$v['model']).'</h4><p>'.e($v['plate']).' · '.e($item['status']).' · '.e((string)$v['passengers']).' plazas</p>';
        if((admin()['role']??'')==='admin')foreach([...($v['exteriorUploadIds']??[]),...($v['interiorUploadIds']??[])] as $image)echo '<a target="_blank" rel="noopener" href="users.php?view=collaborators&amp;id='.e($uid).'&amp;private_document='.e($image).'"><img style="width:140px;height:100px;object-fit:cover;border-radius:12px;margin:8px" alt="Foto del vehículo para revisión" src="users.php?view=collaborators&amp;id='.e($uid).'&amp;private_document='.e($image).'"></a>';foreach($v['documents']??[] as $doc)if((admin()['role']??'')==='admin')echo '<p>'.e($doc['requirementId']).' · '.e($doc['expiresAt']??'Sin caducidad').' <a target="_blank" rel="noopener" href="users.php?view=collaborators&amp;id='.e($uid).'&amp;private_document='.e($doc['uploadId']).'">Ver documento</a></p>';
        partner_registration_admin_form($uid,$revision,'vehicle',['vehicleId'=>$id],['approved'=>'Aprobar vehículo','rejected'=>'Rechazar','suspended'=>'Suspender']);echo '</article>';
    }
    partner_registration_admin_form($uid,$revision,'profile',[],['under_review'=>'Iniciar revisión','changes_requested'=>'Solicitar correcciones','approved'=>'Aprobar perfil','rejected'=>'Rechazar solicitud','suspended'=>'Suspender']);
    echo '<details><summary>Aceptaciones registradas</summary><pre>'.e(json_encode($profile['_acceptances']??[],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)).'</pre></details></section>';
}

function render_partner_registration_settings(): never {
    require_owner();$config=partner_registration_config();page_header('Requisitos de colaboradores');
    echo '<section class="panel partner-registration-settings"><h3>Requisitos documentales por actividad y zona</h3><p>Revisa los requisitos aplicables antes de admitir solicitudes. Las autorizaciones específicas no son obligatorias por defecto. Los códigos de ciudad vacíos aplican a todas las zonas.</p><form method="post" class="editor-form"><input type="hidden" name="_csrf" value="'.csrf().'"><input type="hidden" name="configurationVersion" value="'.(int)$config['version'].'"><input type="hidden" name="action" value="registration"><input type="hidden" name="registration_action" value="settings">';
    foreach($config['documents'] as $rule){$id=$rule['id'];echo '<fieldset><legend>'.e($rule['title']['es']).'</legend><p>Actividades: '.e(implode(', ',$rule['categories'])?:'Todas').' · Tipo: '.e(implode(', ',$rule['businessTypes'])?:'Todos').'</p><label><input type="checkbox" name="required['.e($id).']" '.($rule['required']?'checked':'').'> Obligatorio</label><label><input type="checkbox" name="expires['.e($id).']" '.($rule['expires']?'checked':'').'> Requiere caducidad</label><label>Ciudades (códigos separados por comas)<input name="cities['.e($id).']" value="'.e(implode(',',$rule['cities']??[])).'"></label></fieldset>';}
    echo '<details><summary>Añadir otro requisito documental</summary><label>Código único<input name="newDocumentId" pattern="[a-z0-9_-]{1,60}"></label><label>Nombre español<input name="newDocumentTitle" maxlength="160"></label><label>Nombre inglés<input name="newDocumentEnglish" maxlength="160"></label><label>Nombre árabe<input name="newDocumentArabic" maxlength="160" dir="rtl"></label><label>Actividades (códigos separados por comas, vacío para todas)<input name="newDocumentCategories"></label><label><input type="checkbox" name="newDocumentRequired"> Obligatorio</label><label><input type="checkbox" name="newDocumentExpiry"> Requiere caducidad</label></details>';
    echo '<label>Conservación documental (días, sujeto a revisión legal)<input name="retentionDays" type="number" min="1" max="3650" value="'.(int)$config['retentionDays'].'"></label>';
    render_partner_commission_editor($config);
    echo '<label><input type="checkbox" name="requirementsReviewed" '.($config['requirementsReviewed']?'checked':'').'> Requisitos revisados por la agencia</label><p>Publica las condiciones de colaboración y económicas desde <a href="catalog.php?view=content">Contenido de la app</a>. No se admiten envíos hasta que los documentos legales estén publicados.</p><button class="btn primary">Guardar configuración</button></form></section>';page_footer();exit;
}

function render_partner_commission_editor(array $config): void {
    $partners=db()->query("SELECT id,CONCAT(first_name,' ',last_name) name FROM users WHERE role='partner' OR partner_intent=1 ORDER BY first_name LIMIT 1000")->fetchAll();
    $services=db()->query("SELECT id,title_json FROM driver_services UNION ALL SELECT id,title_json FROM guide_services LIMIT 1000")->fetchAll();
    echo '<h3>Comisiones por reserva</h3><p>La regla de un servicio prevalece sobre la del colaborador; después se aplica la de actividad y, por último, la general. Estos porcentajes no inician cobros ni liquidaciones.</p><input type="hidden" name="commissionEditor" value="1">';
    foreach([...$config['commissionRules'],[]] as $index=>$rule){$prefix='commissions['.$index.']';echo '<fieldset><legend>'.($rule?'Comisión configurada':'Añadir comisión (opcional)').'</legend><label>Actividad<select name="'.$prefix.'[category]"><option value="">Todas las actividades</option>';foreach($config['categories'] as $category)echo '<option value="'.e($category['id']).'" '.(($rule['category']??'')===$category['id']?'selected':'').'>'.e($category['title']['es']).'</option>';echo '</select></label><label>Colaborador<select name="'.$prefix.'[partnerId]"><option value="">Todos los colaboradores</option>';foreach($partners as $partner)echo '<option value="'.e($partner['id']).'" '.(($rule['partnerId']??'')===$partner['id']?'selected':'').'>'.e($partner['name']).'</option>';echo '</select></label><label>Servicio<select name="'.$prefix.'[serviceId]"><option value="">Todos los servicios</option>';foreach($services as $service){$title=json_decode($service['title_json'],true);echo '<option value="'.e($service['id']).'" '.(($rule['serviceId']??'')===$service['id']?'selected':'').'>'.e($title['es']??$service['id']).'</option>';}echo '</select></label><label>Porcentaje (%)<input name="'.$prefix.'[percent]" type="number" min="0" max="100" step="0.01" value="'.(isset($rule['basisPoints'])?e((string)($rule['basisPoints']/100)):'').'"></label>';if($rule)echo '<label><input name="'.$prefix.'[remove]" type="checkbox"> Quitar esta regla</label>';echo '</fieldset>';}
}
