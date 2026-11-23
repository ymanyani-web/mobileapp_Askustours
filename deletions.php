<?php
require 'includes/bootstrap.php';require_admin();require_owner();require 'includes/layout.php';require 'includes/deletions.php';
$types=deletion_types();$type=(string)($_GET['type']??'users');if(!isset($types[$type]))$type='users';$id=trim((string)($_GET['id']??''));$error=null;$plan=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 try{
  if(($_POST['action']??'')==='restore'){restore_managed((int)($_POST['backup_id']??0));flash('success','Registro recuperado. Las sesiones cerradas no se recuperan; la persona debe iniciar sesión de nuevo.');}
  elseif(($_POST['action']??'')==='delete'){
   $backup=delete_managed((string)($_POST['type']??''),(string)($_POST['id']??''),(string)($_POST['revision']??''),trim((string)($_POST['reason']??'')));flash('success','Eliminado con copia recuperable #'.$backup.'.');
  }else throw new RuntimeException('Acción no válida.');
  redirect('deletions.php?type='.rawurlencode($type));
 }catch(Throwable $e){$error=$e instanceof PDOException?'No se pudo completar por una relación o identificador existente. No se ha aplicado ninguna operación parcial.':$e->getMessage();}
}
if($id!==''){try{$plan=deletion_snapshot($type,$id);}catch(Throwable $e){$error=$e->getMessage();}}
[$table,$key,$label]=$types[$type];$items=[];
if($table==='virtual'){$home=json_decode(db()->query('SELECT config_json FROM home_config WHERE id=1')->fetchColumn()?:'{}',true);foreach(($home[substr($type,5)]??[]) as $card)$items[]=['id'=>(string)$card['id'],'label'=>(string)($card['title']['es']??$card['id'])];}
else{
 foreach(db()->query("SELECT * FROM `$table` ORDER BY `$key` LIMIT 100")->fetchAll() as $row){
  $title=$row['first_name']??$row['title']??$row['id']??$row['slug']??'';if(isset($row['first_name']))$title.=' '.$row['last_name'];
  if(isset($row['title_json'])){$titles=json_decode($row['title_json'],true)?:[];$title=$titles['es']??reset($titles)?:$title;}
  $items[]=['id'=>(string)$row[$key],'label'=>(string)$title];
 }
}
$backups=db()->query("SELECT a.id,a.entity_type,a.entity_id,a.created_at,COALESCE(u.name,'Administrador anterior') actor FROM audit_log a LEFT JOIN admins u ON u.id=a.admin_id WHERE a.action='entity.deleted' AND NOT EXISTS(SELECT 1 FROM audit_log r WHERE r.action='entity.restored' AND r.entity_type='deletion' AND BINARY r.entity_id=BINARY CAST(a.id AS CHAR)) ORDER BY a.id DESC LIMIT 30")->fetchAll();
page_header('Eliminar y recuperar');
?>
<section class="panel"><p class="eyebrow">SOLO ADMINISTRADORES</p><h3>Revisa antes de eliminar</h3><p>La eliminación guarda una copia recuperable y registra quién la realizó. Al eliminar una cuenta también se eliminan sus reservas, saldos y datos asociados que aparezcan en el resumen. Las imágenes físicas se conservan para poder recuperar el contenido. Los pagos o liquidaciones ya registrados se conservan: utiliza cancelar o desactivar en esos casos.</p><form method="get"><label>Sección<select name="type"><?php foreach($types as $value=>$definition):?><option value="<?=e($value)?>" <?=$type===$value?'selected':''?>><?=e($definition[2])?></option><?php endforeach;?></select></label><button class="btn secondary">Ver sección</button></form></section>
<?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<?php if($plan):?><section class="panel"><h3>Resumen de eliminación</h3><h4><?=e(deletion_title($plan))?></h4><p><?=e($label)?> · <?=e($id)?></p><ul><?php foreach($plan['rows'] as $name=>$rows):?><li><?=e(deletion_table_label($name))?>: <?=count($rows)?> registros</li><?php endforeach;?><?php if($plan['virtual']):?><li>1 tarjeta de portada</li><?php endif;?></ul><form method="post"><input type="hidden" name="_csrf" value="<?=csrf()?>"><input type="hidden" name="type" value="<?=e($type)?>"><input type="hidden" name="id" value="<?=e($id)?>"><input type="hidden" name="revision" value="<?=e(deletion_revision($plan))?>"><label>Motivo<input name="reason" required minlength="5" maxlength="500"></label><button class="btn primary" name="action" value="delete" data-confirm="¿Eliminar este registro y todos los datos indicados en el resumen? Se guardará una copia recuperable.">Eliminar con copia recuperable</button> <a href="deletions.php?type=<?=e($type)?>">Cancelar</a></form></section><?php endif;?>
<div class="person-grid"><section class="panel"><h3><?=e($label)?></h3><p class="muted">Primeros 100 registros. También puedes acceder desde la ficha correspondiente.</p><div class="table-wrap"><table><thead><tr><th>Registro</th><th>Control</th></tr></thead><tbody><?php foreach($items as $item):?><tr><td><strong><?=e($item['label'])?></strong><small><?=e($item['id'])?></small></td><td><a href="deletions.php?type=<?=e($type)?>&amp;id=<?=e(rawurlencode($item['id']))?>">Revisar eliminación</a></td></tr><?php endforeach;?><?php if(!$items):?><tr><td colspan="2">Sin registros.</td></tr><?php endif;?></tbody></table></div></section>
<section class="panel"><h3>Copias recuperables</h3><p class="muted">Últimas 30 eliminaciones pendientes de recuperar. Los accesos y contraseñas no se restauran.</p><?php foreach($backups as $backup):?><article class="review-entry"><strong><?=e($types[$backup['entity_type']][2]??$backup['entity_type'])?> · <?=e($backup['entity_id'])?></strong><small><?=e($backup['created_at'])?> · <?=e($backup['actor'])?></small><form method="post"><input type="hidden" name="_csrf" value="<?=csrf()?>"><input type="hidden" name="backup_id" value="<?=(int)$backup['id']?>"><button name="action" value="restore" class="btn secondary" data-confirm="¿Recuperar este registro y sus datos asociados?">Recuperar copia #<?=(int)$backup['id']?></button></form></article><?php endforeach;?><?php if(!$backups):?><p>No hay copias pendientes.</p><?php endif;?></section></div>
<?php page_footer();
