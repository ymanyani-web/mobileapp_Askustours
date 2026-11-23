<?php
declare(strict_types=1);

// Administrative account decisions live in the existing append-only audit log.
// They are never stored in customer-editable profile/preferences JSON.
function account_control(string $uid): array {
    $s=db()->prepare("SELECT id,details_json,created_at FROM audit_log WHERE action='user.account_control_changed' AND entity_type='user' AND entity_id=? ORDER BY id DESC LIMIT 1");
    $s->execute([$uid]);$row=$s->fetch();
    return $row?['eventId'=>(int)$row['id'],'changedAt'=>$row['created_at'],...json_decode($row['details_json'],true,512,JSON_THROW_ON_ERROR)]:['eventId'=>0,'status'=>'active','reason'=>''];
}

function account_enabled(string $uid): bool {return (account_control($uid)['status']??'active')==='active';}
function partner_operational(string $uid): bool {
    if(!account_enabled($uid))return false;
    $s=db()->prepare('SELECT status FROM partner_profiles WHERE user_id=?');$s->execute([$uid]);return $s->fetchColumn()==='approved';
}

function account_labels(): array {return ['active'=>'Activa','inactive'=>'Desactivada','blocked'=>'Bloqueada'];}
function partner_labels(): array {return ['draft'=>'Registro sin completar','submitted'=>'Pendiente de aprobación','approved'=>'Aprobado','rejected'=>'Rechazado','suspended'=>'Suspendido'];}
function reservation_labels(): array {return ['requested'=>'Pendiente','assigned'=>'Asignado · pendiente de aceptar','accepted'=>'Confirmado','completed'=>'Realizado','cancelled'=>'Cancelado','declined'=>'Rechazado'];}
function account_event_label(string $action): string {return ['user.account_control_changed'=>'Estado de cuenta actualizado','partner_profile.status_changed'=>'Revisión del registro profesional','reservation.status_changed'=>'Estado del servicio actualizado','reservation.assignment_changed'=>'Asignación de servicio actualizada'][$action]??$action;}
function account_state_label(string $status): string {return (account_labels()+partner_labels()+reservation_labels())[$status]??$status;}
function admin_money(int $minor,string $currency): string {return number_format($minor/100,2,',','.').' '.strtoupper($currency);}

function account_revision(array $user,?array $profile,array $control): string {return hash('sha256',json_encode([$user,$profile,$control],JSON_THROW_ON_ERROR));}

function apply_account_decision(string $uid,string $action,string $status,string $reason,string $revision): void {
    if((admin()['role']??'')!=='admin')throw new RuntimeException('Solo un administrador puede revisar o restringir cuentas.');
    if(!in_array($action,['account','partner'],true))throw new RuntimeException('Acción no válida.');
    if(mb_strlen($reason)>1000)throw new RuntimeException('El motivo admite hasta 1000 caracteres.');
    db()->beginTransaction();
    try {
        $s=db()->prepare('SELECT * FROM users WHERE id=? FOR UPDATE');$s->execute([$uid]);$user=$s->fetch();
        if(!$user)throw new RuntimeException('Esta cuenta no existe.');
        $s=db()->prepare('SELECT * FROM partner_profiles WHERE user_id=? FOR UPDATE');$s->execute([$uid]);$profile=$s->fetch()?:null;
        $control=account_control($uid);
        if(!hash_equals(account_revision($user,$profile,$control),$revision))throw new RuntimeException('Otro administrador ha actualizado esta ficha. Recarga antes de continuar.');
        if($action==='account') {
            if(!isset(account_labels()[$status]))throw new RuntimeException('Estado de cuenta no válido.');
            if($status!=='active'&&mb_strlen($reason)<5)throw new RuntimeException('Indica el motivo de la restricción.');
            $details=['status'=>$status,'previousStatus'=>$control['status'],'reason'=>$reason];
            db()->prepare("INSERT INTO audit_log(admin_id,action,entity_type,entity_id,details_json) VALUES(?,'user.account_control_changed','user',?,?)")->execute([admin()['id'],$uid,json_encode($details,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
            if($status!=='active') {
                db()->prepare('UPDATE sessions SET revoked_at=NOW() WHERE user_id=? AND revoked_at IS NULL')->execute([$uid]);
                pause_partner_services($uid);
            }
        } else {
            if(!$profile||!($user['role']==='partner'||$user['partner_intent']))throw new RuntimeException('No hay una solicitud profesional para revisar.');
            if(!in_array($status,['approved','rejected','suspended','submitted'],true))throw new RuntimeException('Decisión no válida.');
            if(in_array($status,['rejected','suspended'],true)&&mb_strlen($reason)<5)throw new RuntimeException('Indica el motivo de la decisión.');
            if($status==='approved') {
                if($control['status']!=='active')throw new RuntimeException('Reactiva la cuenta antes de aprobar el registro profesional.');
                if(!in_array($profile['status'],['submitted','suspended','rejected'],true))throw new RuntimeException('El colaborador debe completar y enviar su registro antes de aprobarlo.');
                $data=json_decode($profile['profile_json'],true,512,JSON_THROW_ON_ERROR);
                foreach(['businessName','contactName','phone','email','activity','professionalPhotoUploadId'] as $field)if(empty($data[$field]))throw new RuntimeException('El registro está incompleto: '.$field.'.');
                if(empty($data['cities'])&&empty($data['citiesText']))throw new RuntimeException('Indica las ciudades del colaborador antes de aprobarlo.');
                $photo=db()->prepare("SELECT path FROM uploads WHERE id=? AND user_id=? AND purpose='professional-photo'");$photo->execute([$data['professionalPhotoUploadId'],$uid]);$path=$photo->fetchColumn();
                if(!$path||!public_asset_available($path))throw new RuntimeException('Falta una foto profesional válida del colaborador.');
            }
            db()->prepare('UPDATE partner_profiles SET status=?,reviewed_at=NOW(),reviewed_by=?,revision=revision+1 WHERE user_id=?')->execute([$status,admin()['id'],$uid]);
            db()->prepare("INSERT INTO audit_log(admin_id,action,entity_type,entity_id,details_json) VALUES(?,'partner_profile.status_changed','partner_profile',?,?)")->execute([admin()['id'],$uid,json_encode(['status'=>$status,'previousStatus'=>$profile['status'],'reason'=>$reason],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
            if($status!=='approved')pause_partner_services($uid);
        }
        db()->commit();
    } catch(Throwable $e) {if(db()->inTransaction())db()->rollBack();throw $e;}
}

function pause_partner_services(string $uid): void {
    foreach(['driver_services','guide_services'] as $table)db()->prepare("UPDATE `$table` SET status='paused',revision=revision+1 WHERE owner_user_id=? AND status='published'")->execute([$uid]);
}
