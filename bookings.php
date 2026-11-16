<?php
require 'includes/bootstrap.php'; require_admin(); redirect('reservations.php'); require 'includes/layout.php';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 verify_csrf();
 if(($_POST['action']??'')==='create'){
  try{
   $customerId=(int)($_POST['customer_id']??0); $type=$_POST['booking_type']??'';
   if(!$customerId)throw new RuntimeException('Please select a client.');
   if(!in_array($type,['vehicle','hotel','service','flight','visa','activity'],true))throw new RuntimeException('Invalid reservation type.');
   $start=$_POST['start_date']?:null; $end=$_POST['end_date']?:null;
   if($start&&$end&&$end<$start)throw new RuntimeException('End date cannot be before start date.');
   $code='ASK-'.date('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(3)),0,6));
   $stmt=db()->prepare('INSERT INTO bookings(booking_code,customer_id,collaborator_id,booking_type,item_id,source,start_date,end_date,pickup_location,dropoff_location,guests,total_amount,notes) VALUES(?,?,?,?,?,\'admin\',?,?,?,?,?,?,?)');
   $stmt->execute([$code,$customerId,(int)($_POST['collaborator_id']??0)?:null,$type,(int)($_POST['item_id']??0)?:null,$start,$end,trim($_POST['pickup_location'])?:null,trim($_POST['dropoff_location'])?:null,max(1,(int)($_POST['guests']??1)),max(0,(float)($_POST['total_amount']??0)),trim($_POST['notes'])?:null]);
   flash('success','Reservation '.$code.' created.');
  }catch(Throwable $e){flash('error',$e->getMessage());}
 }else{
  $id=(int)($_POST['id']??0); $status=$_POST['status']??''; $allowed=['pending','confirmed','in_progress','completed','cancelled'];
  if(in_array($status,$allowed,true)){ $stmt=db()->prepare('UPDATE bookings SET status=? WHERE id=?');$stmt->execute([$status,$id]);flash('success','Booking status updated.'); }
 }
 redirect('bookings.php');
}
$status=$_GET['status']??''; $params=[]; $where='';
if(in_array($status,['pending','confirmed','in_progress','completed','cancelled'],true)){$where='WHERE b.status=?';$params[]=$status;}
$stmt=db()->prepare("SELECT b.*,c.full_name,c.email,c.phone,co.name collaborator_name FROM bookings b LEFT JOIN customers c ON c.id=b.customer_id LEFT JOIN collaborators co ON co.id=b.collaborator_id $where ORDER BY b.created_at DESC");$stmt->execute($params);$rows=$stmt->fetchAll();
$clients=db()->query('SELECT id,full_name,phone FROM customers ORDER BY full_name')->fetchAll();
$collaborators=db()->query('SELECT id,name,collaborator_type FROM collaborators WHERE active=1 ORDER BY name')->fetchAll();
page_header('Reservations'); ?><link rel="stylesheet" href="assets/operations.css">
<section class="panel reservation-form"><div class="panel-head"><div><p class="eyebrow">MANUAL ENTRY</p><h3>Create reservation</h3></div></div><form method="post" class="editor-form"><input type="hidden" name="_csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="create">
<label>Client<select name="customer_id" required><option value="">Choose client</option><?php foreach($clients as $client):?><option value="<?=(int)$client['id']?>"><?=e($client['full_name'].' · '.$client['phone'])?></option><?php endforeach;?></select></label>
<label>Type<select name="booking_type" required><?php foreach(['vehicle','hotel','service','flight','visa','activity'] as $type):?><option value="<?=e($type)?>"><?=e(ucfirst($type))?></option><?php endforeach;?></select></label>
<label>Collaborator<select name="collaborator_id"><option value="">Unassigned</option><?php foreach($collaborators as $person):?><option value="<?=(int)$person['id']?>"><?=e($person['name'].' · '.str_replace('_',' ',$person['collaborator_type']))?></option><?php endforeach;?></select></label><label>Catalog item ID<input type="number" min="1" name="item_id"></label>
<label>Start date<input type="date" name="start_date"></label><label>End date<input type="date" name="end_date"></label><label>Pickup / hotel location<input name="pickup_location"></label><label>Drop-off location<input name="dropoff_location"></label><label>Guests<input type="number" min="1" name="guests" value="1"></label><label>Total amount<input type="number" min="0" step="0.01" name="total_amount" value="0"></label><label>Notes<textarea name="notes" rows="3"></textarea></label><button class="btn primary wide">Create reservation</button></form></section>
<section class="panel"><div class="panel-head"><div><p class="eyebrow">OPERATIONS</p><h3>All reservations</h3></div><div class="filters"><a class="<?=!$status?'active':''?>" href="bookings.php">All</a><?php foreach(['pending','confirmed','in_progress','completed','cancelled'] as $s): ?><a class="<?=$status===$s?'active':''?>" href="?status=<?=e($s)?>"><?=e(ucwords(str_replace('_',' ',$s)))?></a><?php endforeach; ?></div></div><div class="table-wrap"><table><thead><tr><th>Booking</th><th>Customer</th><th>Travel details</th><th>Amount</th><th>Status</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><strong><?=e($row['booking_code'])?></strong><small><?=e(ucfirst($row['booking_type']))?> · <?=date('M j, Y',strtotime($row['created_at']))?></small></td><td><?=e($row['full_name']??'Guest')?><small><?=e($row['phone']??$row['email']??'')?></small></td><td><?=e($row['pickup_location']?:'—')?><?php if($row['start_date']): ?><small><?=date('M j',strtotime($row['start_date']))?><?= $row['end_date']?' – '.date('M j',strtotime($row['end_date'])):'' ?></small><?php endif; ?></td><td><strong>$<?=number_format((float)$row['total_amount'],2)?></strong></td><td><form method="post" class="status-form"><input type="hidden" name="_csrf" value="<?=csrf()?>"><input type="hidden" name="id" value="<?=(int)$row['id']?>"><select name="status" onchange="this.form.submit()"><?php foreach(['pending','confirmed','in_progress','completed','cancelled'] as $s): ?><option value="<?=e($s)?>" <?=$row['status']===$s?'selected':''?>><?=e(ucwords(str_replace('_',' ',$s)))?></option><?php endforeach; ?></select></form></td></tr><?php endforeach; ?><?php if(!$rows): ?><tr><td colspan="5" class="empty">No bookings found.</td></tr><?php endif; ?></tbody></table></div></section>
<?php page_footer();
