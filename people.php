<?php
require 'includes/bootstrap.php'; require_admin(); redirect('users.php?view='.urlencode($_GET['view']??'customers')); require 'includes/layout.php';
$view=$_GET['view']??'clients'; if(!in_array($view,['clients','collaborators'],true))$view='clients';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf(); $kind=$_POST['kind']??'';
 try{
  if($kind==='client'){
   foreach(['full_name','phone'] as $f)if(trim((string)($_POST[$f]??''))==='')throw new RuntimeException(ucwords(str_replace('_',' ',$f)).' is required.');
   $stmt=db()->prepare('INSERT INTO customers(full_name,email,phone) VALUES(?,?,?)'); $stmt->execute([trim($_POST['full_name']),trim($_POST['email'])?:null,trim($_POST['phone'])]);
  }else{
   foreach(['name','phone'] as $f)if(trim((string)($_POST[$f]??''))==='')throw new RuntimeException(ucfirst($f).' is required.');
   $types=['driver','hotel','transport_company','other']; $type=in_array($_POST['collaborator_type']??'',$types,true)?$_POST['collaborator_type']:'other';
   $stmt=db()->prepare('INSERT INTO collaborators(name,collaborator_type,email,phone,company_name,notes) VALUES(?,?,?,?,?,?)'); $stmt->execute([trim($_POST['name']),$type,trim($_POST['email'])?:null,trim($_POST['phone']),trim($_POST['company_name'])?:null,trim($_POST['notes'])?:null]);
  }
  flash('success',ucfirst($kind).' created.');
 }catch(Throwable $e){flash('error',$e->getMessage());}
 redirect('people.php?view='.$view);
}
$rows=$view==='clients'?db()->query('SELECT c.*,COUNT(b.id) booking_count FROM customers c LEFT JOIN bookings b ON b.customer_id=c.id GROUP BY c.id ORDER BY c.created_at DESC')->fetchAll():db()->query('SELECT c.*,COUNT(b.id) booking_count FROM collaborators c LEFT JOIN bookings b ON b.collaborator_id=c.id GROUP BY c.id ORDER BY c.created_at DESC')->fetchAll();
page_header('Clients & collaborators'); ?><link rel="stylesheet" href="assets/operations.css">
<div class="filters people-tabs"><a class="<?=$view==='clients'?'active':''?>" href="?view=clients">Clients</a><a class="<?=$view==='collaborators'?'active':''?>" href="?view=collaborators">Collaborators</a></div>
<div class="content-layout"><section class="panel"><div class="panel-head"><div><p class="eyebrow">DIRECTORY</p><h3>Add <?=e(rtrim($view,'s'))?></h3></div></div><form method="post" class="editor-form"><input type="hidden" name="_csrf" value="<?=csrf()?>"><input type="hidden" name="kind" value="<?=$view==='clients'?'client':'collaborator'?>">
<?php if($view==='clients'): ?><label>Full name<input name="full_name" required></label><?php else: ?><label>Name<input name="name" required></label><label>Type<select name="collaborator_type"><option value="driver">Driver</option><option value="hotel">Hotel</option><option value="transport_company">Transport company</option><option value="other">Other</option></select></label><label>Company<input name="company_name"></label><?php endif; ?><label>Phone<input name="phone" required></label><label>Email<input type="email" name="email"></label><?php if($view==='collaborators'): ?><label>Notes<textarea name="notes" rows="4"></textarea></label><?php endif; ?><button class="btn primary wide">Save</button></form></section>
<section class="panel"><div class="panel-head"><div><p class="eyebrow">CONTACTS</p><h3><?=count($rows)?> <?=e($view)?></h3></div></div><div class="table-wrap"><table><thead><tr><th>Name</th><th>Contact</th><th><?= $view==='clients'?'Bookings':'Type / bookings'?></th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><strong><?=e($row['full_name']??$row['name'])?></strong><?php if(!empty($row['company_name'])):?><small><?=e($row['company_name'])?></small><?php endif;?></td><td><?=e($row['phone'])?><small><?=e($row['email']??'')?></small></td><td><?= $view==='collaborators'?e(ucwords(str_replace('_',' ',$row['collaborator_type']))).' · ':'' ?><?=(int)$row['booking_count']?></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="3" class="empty">No records yet.</td></tr><?php endif;?></tbody></table></div></section></div>
<?php page_footer();
