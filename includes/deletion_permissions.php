<?php
// Reuse the append-only audit log; staff permissions never come from the browser session.
function deletion_permission_record(int $id): array {
    $s=db()->prepare("SELECT id,details_json FROM audit_log WHERE action='staff.deletion_permissions_changed' AND entity_type='admin' AND entity_id=? ORDER BY id DESC LIMIT 1");$s->execute([(string)$id]);$row=$s->fetch();
    return $row?['eventId'=>(int)$row['id'],...json_decode($row['details_json'],true,512,JSON_THROW_ON_ERROR)]:['eventId'=>0,'role'=>'operator','grants'=>[]];
}
function deletion_grant_allowed(array $actor,array $record,string $capability): bool {
    if(empty($actor['active']))return false;
    if(($actor['role']??'')==='admin')return true;
    if(($actor['role']??'')!=='operator'||($record['role']??'')!=='operator')return false;
    $grants=$record['grants']??[];
    if($capability==='access')return (bool)array_intersect($grants,['delete','restore','archive']);
    return in_array($capability,$grants,true);
}
function can_manage_deletions(string $capability='access'): bool {
    if(!admin())return false;
    $s=db()->prepare('SELECT role,active FROM admins WHERE id=?');$s->execute([admin()['id']]);$actor=$s->fetch();
    if(!$actor)return false;
    return deletion_grant_allowed($actor,$actor['role']==='admin'?[]:deletion_permission_record((int)admin()['id']),$capability);
}
function require_deletion_permission(string $capability='access'): void {
    if(!can_manage_deletions($capability)){http_response_code(403);exit('No tienes permiso para esta operación de eliminación. Consulta al administrador.');}
}
function lock_deletion_actor(string $capability): void {
    $s=db()->prepare('SELECT role,active FROM admins WHERE id=? FOR UPDATE');$s->execute([admin()['id']]);$actor=$s->fetch();
    if(!$actor||!deletion_grant_allowed($actor,$actor['role']==='admin'?[]:deletion_permission_record((int)admin()['id']),$capability))throw new RuntimeException('Tus permisos han cambiado. Recarga antes de continuar.');
}
