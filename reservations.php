<?php
require 'includes/bootstrap.php'; require_admin(); require 'includes/layout.php';
$allowed=['requested','assigned','accepted','declined','cancelled','completed'];
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf(); $id=(string)($_POST['id']??''); $status=(string)($_POST['status']??''); $partnerId=trim((string)($_POST['partner_user_id']??''));
 if($id!==''&&($_POST['action']??'status')==='assign'){
  if($partnerId===''){$stmt=db()->prepare("UPDATE reservations SET partner_user_id=NULL,status=IF(status='assigned','requested',status),version=version+1 WHERE id=?");$stmt->execute([$id]);}
  else{$check=db()->prepare("SELECT u.id FROM users u JOIN partner_profiles p ON p.user_id=u.id WHERE u.id=? AND p.status='approved'");$check->execute([$partnerId]);if(!$check->fetchColumn())throw new RuntimeException('Only approved collaborators can be assigned.');$stmt=db()->prepare("UPDATE reservations SET partner_user_id=?,status=IF(status='requested','assigned',status),version=version+1 WHERE id=?");$stmt->execute([$partnerId,$id]);}
  flash('success','Collaborator assignment updated.');
 } elseif($id!==''&&in_array($status,$allowed,true)){
  $stmt=db()->prepare('UPDATE reservations SET status=?,version=version+1 WHERE id=?'); $stmt->execute([$status,$id]); flash('success','Service request status updated.');
 }
 redirect('reservations.php');
}
$status=$_GET['status']??'';$params=[];$where='';
if(in_array($status,$allowed,true)){$where='WHERE r.status=?';$params[]=$status;}
$stmt=db()->prepare("SELECT r.*,CONCAT(c.first_name,' ',c.last_name) customer_name,c.email customer_email,c.phone customer_phone,CONCAT(p.first_name,' ',p.last_name) partner_name FROM reservations r JOIN users c ON c.id=r.customer_user_id LEFT JOIN users p ON p.id=r.partner_user_id $where ORDER BY r.created_at DESC");$stmt->execute($params);$rows=$stmt->fetchAll();
$partners=db()->query("SELECT u.id,CONCAT(u.first_name,' ',u.last_name) name FROM users u JOIN partner_profiles p ON p.user_id=u.id WHERE p.status='approved' ORDER BY u.first_name,u.last_name")->fetchAll();
page_header('Service requests'); ?>
<section class="panel"><div class="panel-head"><div><p class="eyebrow">OPERATIONS</p><h3>Driver and guide requests</h3></div><div class="filters"><a class="<?=!$status?'active':''?>" href="reservations.php">All</a><?php foreach($allowed as $s):?><a class="<?=$status===$s?'active':''?>" href="?status=<?=e($s)?>"><?=e(ucfirst($s))?></a><?php endforeach;?></div></div><div class="table-wrap"><table><thead><tr><th>Request</th><th>Customer</th><th>Service</th><th>Collaborator</th><th>Payment</th><th>Status</th></tr></thead><tbody><?php foreach($rows as $row):?><tr><td><strong><?=e(substr($row['id'],0,14))?></strong><small><?=e(date('M j, Y H:i',strtotime($row['created_at'])))?></small></td><td><?=e($row['customer_name'])?><small><?=e($row['customer_email']?:$row['customer_phone'])?></small></td><td><?=e(ucfirst($row['service_type']))?><small><?=e(substr($row['service_id'],0,14))?></small></td><td><form method="post" class="status-form"><input type="hidden" name="_csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="assign"><input type="hidden" name="id" value="<?=e($row['id'])?>"><select name="partner_user_id" onchange="this.form.submit()"><option value="">Unassigned</option><?php foreach($partners as $partner):?><option value="<?=e($partner['id'])?>" <?=$row['partner_user_id']===$partner['id']?'selected':''?>><?=e($partner['name'])?></option><?php endforeach;?></select></form></td><td><?=e($row['payment_status'])?></td><td><form method="post" class="status-form"><input type="hidden" name="_csrf" value="<?=csrf()?>"><input type="hidden" name="id" value="<?=e($row['id'])?>"><select name="status" onchange="this.form.submit()"><?php foreach($allowed as $s):?><option value="<?=e($s)?>" <?=$row['status']===$s?'selected':''?>><?=e(ucfirst($s))?></option><?php endforeach;?></select></form></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="6" class="empty">No service requests found.</td></tr><?php endif;?></tbody></table></div></section>
<?php page_footer();
