<?php
require 'includes/bootstrap.php';require_admin();require_owner();require 'includes/layout.php';
function staff_roles():array{return ['admin'=>'Administrador','operator'=>'Gestor','reviewer'=>'Solo consulta'];}
function staff_revision(array $row):string{return hash('sha256',json_encode([array_intersect_key($row,array_flip(['id','name','email','role','active'])),deletion_permission_record((int)$row['id'])['eventId']],JSON_THROW_ON_ERROR));}
$error=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 try{
  $id=(int)($_POST['id']??0);$name=trim((string)($_POST['name']??''));$email=mb_strtolower(trim((string)($_POST['email']??'')));$role=(string)($_POST['role']??'reviewer');$active=isset($_POST['active'])?1:0;$password=(string)($_POST['password']??'');
  if($name===''||mb_strlen($name)>120||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190||!isset(staff_roles()[$role]))throw new RuntimeException('Revisa el nombre, correo y rol.');
  if(($id===0||$password!=='')&&(strlen($password)<12||strlen($password)>512))throw new RuntimeException('Utiliza una contraseña de entre 12 y 512 caracteres.');
  $grants=array_values(array_intersect((array)($_POST['deletion_grants']??[]),['delete','restore','archive']));
  if($role!=='operator')$grants=[];
  db()->beginTransaction();
  // Lock all administrators in stable order to protect the last active owner.
  $locked=db()->query('SELECT id,name,email,role,active FROM admins ORDER BY id FOR UPDATE')->fetchAll();lock_owner();
  $current=null;foreach($locked as $row)if((int)$row['id']===$id)$current=$row;
  if($id && (!$current||!hash_equals(staff_revision($current),(string)($_POST['revision']??''))))throw new RuntimeException('Esta cuenta cambió. Recarga antes de guardar.');
  if($id===(int)admin()['id']&&($role!=='admin'||!$active))throw new RuntimeException('No puedes retirar tu propio acceso de administrador desde esta pantalla.');
  $owners=count(array_filter($locked,fn($r)=>$r['role']==='admin'&&(int)$r['active']===1));
  if($current&&$current['role']==='admin'&&$current['active']&&($role!=='admin'||!$active)&&$owners<=1)throw new RuntimeException('Debe quedar al menos un administrador activo.');
  if($id){
   db()->prepare('UPDATE admins SET name=?,email=?,role=?,active=? WHERE id=?')->execute([$name,$email,$role,$active,$id]);
   if($password!=='')db()->prepare('UPDATE admins SET password_hash=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$id]);
  }else{
   db()->prepare('INSERT INTO admins(name,email,password_hash,role,active) VALUES(?,?,?,?,?)')->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT),$role,$active]);$id=(int)db()->lastInsertId();
  }
  db()->prepare("INSERT INTO audit_log(admin_id,action,entity_type,entity_id,details_json) VALUES(?,'staff.deletion_permissions_changed','admin',?,?)")->execute([admin()['id'],(string)$id,json_encode(['role'=>$role,'grants'=>$grants],JSON_THROW_ON_ERROR)]);
  db()->prepare("INSERT INTO audit_log(admin_id,action,entity_type,entity_id,details_json) VALUES(?,'staff.access_changed','admin',?,?)")->execute([admin()['id'],(string)$id,json_encode(['previousRole'=>$current['role']??null,'role'=>$role,'active'=>$active,'passwordChanged'=>$password!==''],JSON_THROW_ON_ERROR)]);
  db()->commit();flash('success','Acceso guardado. Los cambios de permisos se aplican en la siguiente petición.');redirect('staff.php');
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();$error=$e instanceof PDOException?'No se pudo guardar. Comprueba que el correo no esté registrado.':$e->getMessage();}
}
$rows=db()->query('SELECT id,name,email,role,active,created_at FROM admins ORDER BY active DESC,name,id')->fetchAll();$edit=null;
foreach($rows as $row)if((int)$row['id']===(int)($_GET['edit']??0))$edit=$row;
page_header('Trabajadores y permisos');
?>
<section class="panel"><p class="eyebrow">CONTROL EXCLUSIVO DEL ADMINISTRADOR</p><h3>Quién puede hacer qué</h3><div class="table-wrap"><table><thead><tr><th>Rol</th><th>Permisos</th></tr></thead><tbody><tr><td>Administrador</td><td>Gestión completa, aprobación de colaboradores, eliminaciones, recuperación y cuentas de trabajadores.</td></tr><tr><td>Gestor</td><td>Gestionar contenido, catálogo, disponibilidad y solicitudes. Solo puede eliminar, limpiar el historial o recuperar datos si el administrador le concede esos permisos expresamente. No puede aprobar cuentas, bloquear personas ni cambiar permisos.</td></tr><tr><td>Solo consulta</td><td>Consultar información e historial. No puede guardar cambios ni eliminar.</td></tr></tbody></table></div></section>
<?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<div class="person-grid"><section class="panel"><h3><?=$edit?'Editar acceso':'Crear cuenta de trabajador'?></h3><p class="muted">Desactivar impide el siguiente acceso al panel, incluso con una sesión abierta. Las contraseñas no se envían por correo.</p><form method="post" autocomplete="off"><input type="hidden" name="_csrf" value="<?=csrf()?>"><input type="hidden" name="id" value="<?=(int)($edit['id']??0)?>"><input type="hidden" name="revision" value="<?=e($edit?staff_revision($edit):'')?>"><label>Nombre<input name="name" maxlength="120" required value="<?=e($edit['name']??'')?>"></label><label>Correo<input type="email" name="email" maxlength="190" required value="<?=e($edit['email']??'')?>"></label><label>Rol<select name="role"><?php foreach(staff_roles() as $key=>$label):?><option value="<?=e($key)?>" <?=($edit['role']??'reviewer')===$key?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label><fieldset><legend>Permisos de eliminación para el rol Gestor</legend><p class="muted">Sin marcar, el trabajador no puede usar estas operaciones. Solo consulta nunca puede modificar datos; Administrador conserva el control completo.</p><?php $granted=$edit?deletion_permission_record((int)$edit['id'])['grants']:[];foreach(['delete'=>'Eliminar registros con copia recuperable','restore'=>'Recuperar copias','archive'=>'Vaciar el historial visible conservando las copias'] as $permission=>$permissionLabel):?><label><input type="checkbox" name="deletion_grants[]" value="<?=e($permission)?>" <?=in_array($permission,$granted,true)?'checked':''?>> <?=e($permissionLabel)?></label><?php endforeach;?></fieldset><label><?=$edit?'Nueva contraseña (dejar vacío para conservarla)':'Contraseña inicial'?><input type="password" name="password" autocomplete="new-password" minlength="12" maxlength="512" <?=$edit?'':'required'?>></label><label><input type="checkbox" name="active" value="1" <?=!$edit||$edit['active']?'checked':''?>> Acceso activo</label><button class="btn primary" data-confirm="¿Guardar los permisos de este trabajador?">Guardar acceso</button><?php if($edit):?> <a href="staff.php">Cancelar edición</a><?php endif;?></form></section>
<section class="panel"><h3>Cuentas del equipo</h3><div class="table-wrap"><table><thead><tr><th>Persona</th><th>Rol / acceso</th><th>Control</th></tr></thead><tbody><?php foreach($rows as $row):?><tr><td><strong><?=e($row['name'])?></strong><small><?=e($row['email'])?></small></td><td><?=e(staff_roles()[$row['role']]??$row['role'])?><small><?=$row['active']?'Activo':'Desactivado'?></small></td><td><a href="staff.php?edit=<?=(int)$row['id']?>">Editar permisos</a></td></tr><?php endforeach;?></tbody></table></div></section></div>
<?php page_footer();
