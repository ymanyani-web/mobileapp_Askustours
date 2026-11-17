<?php
require 'includes/bootstrap.php'; require_admin(); require 'includes/layout.php';
$services=db()->query("SELECT id,JSON_UNQUOTE(JSON_EXTRACT(title_json,'$.es')) title FROM driver_services WHERE status IN ('review','published','paused') ORDER BY updated_at DESC")->fetchAll();
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 try{
  $serviceId=(string)($_POST['service_id']??'');$from=new DateTimeImmutable((string)($_POST['from']??''));$to=new DateTimeImmutable((string)($_POST['to']??''));
  $available=isset($_POST['available'])?1:0;$price=trim((string)($_POST['price_minor']??''));$price=$price===''?null:filter_var($price,FILTER_VALIDATE_INT);
  $details=json_decode((string)($_POST['details_json']??'{}'),true);
  if(!array_filter($services,fn($service)=>$service['id']===$serviceId))throw new RuntimeException('Select a managed driver service.');
  if($to<$from||$to->diff($from)->days>366)throw new RuntimeException('The range must be chronological and at most 366 days.');
  if($price===false||($price!==null&&$price<0))throw new RuntimeException('Price must be a non-negative integer in cents.');
  if(!is_array($details))throw new RuntimeException('Details must be a valid JSON object.');
  db()->beginTransaction();$save=db()->prepare("INSERT INTO service_calendar(service_type,service_id,service_date,available,price_minor,details_json) VALUES('driver',?,?,?,?,?) ON DUPLICATE KEY UPDATE available=VALUES(available),price_minor=VALUES(price_minor),details_json=VALUES(details_json)");
  for($day=$from;$day<=$to;$day=$day->modify('+1 day'))$save->execute([$serviceId,$day->format('Y-m-d'),$available,$price,json_encode($details,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
  db()->prepare("INSERT INTO audit_log(admin_id,action,entity_type,entity_id,details_json) VALUES(?,'calendar.range_upsert','driver_service',?,?)")->execute([admin()['id'],$serviceId,json_encode(['from'=>$from->format('Y-m-d'),'to'=>$to->format('Y-m-d'),'available'=>(bool)$available,'priceMinor'=>$price])]);
  db()->commit();flash('success','Availability range saved.');
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();flash('error',$e->getMessage());}
 redirect('calendar.php?service_id='.urlencode((string)($_POST['service_id']??'')));
}
$selected=(string)($_GET['service_id']??($services[0]['id']??''));$stmt=db()->prepare("SELECT service_date,available,price_minor,details_json FROM service_calendar WHERE service_type='driver' AND service_id=? AND service_date>=CURDATE() ORDER BY service_date LIMIT 180");$stmt->execute([$selected]);$days=$stmt->fetchAll();
page_header('Availability calendar'); ?>
<div class="content-layout"><section class="panel"><div class="panel-head"><div><p class="eyebrow">DRIVER AVAILABILITY</p><h3>Set an explicit date range</h3></div></div><form method="post" class="editor-form"><input type="hidden" name="_csrf" value="<?=csrf()?>"><label>Driver service<select name="service_id" required><?php foreach($services as $service):?><option value="<?=e($service['id'])?>" <?=$selected===$service['id']?'selected':''?>><?=e($service['title']?:$service['id'])?></option><?php endforeach;?></select></label><label>From<input type="date" name="from" value="<?=e(gmdate('Y-m-d'))?>" required></label><label>To<input type="date" name="to" value="<?=e(gmdate('Y-m-d',strtotime('+30 days')))?>" required></label><label><input type="checkbox" name="available" value="1" checked> Available</label><label>Price in cents (optional)<input type="number" min="0" name="price_minor"></label><label>Details JSON<textarea name="details_json" rows="4">{}</textarea></label><button class="btn primary wide">Save range</button></form></section><section class="panel"><div class="panel-head"><div><p class="eyebrow">EXPLICIT DATES</p><h3><?=count($days)?> upcoming records</h3></div><form method="get"><select name="service_id" onchange="this.form.submit()"><?php foreach($services as $service):?><option value="<?=e($service['id'])?>" <?=$selected===$service['id']?'selected':''?>><?=e($service['title']?:$service['id'])?></option><?php endforeach;?></select></form></div><div class="table-wrap"><table><thead><tr><th>Date</th><th>Availability</th><th>Price</th><th>Details</th></tr></thead><tbody><?php foreach($days as $day):?><tr><td><?=e($day['service_date'])?></td><td><?=$day['available']?'Available':'Closed'?></td><td><?=$day['price_minor']===null?'Base price':e((string)$day['price_minor']).' cents'?></td><td><small><?=e($day['details_json'])?></small></td></tr><?php endforeach;?><?php if(!$days):?><tr><td colspan="4" class="empty">No explicit dates. Add a range before accepting requests.</td></tr><?php endif;?></tbody></table></div></section></div>
<?php page_footer();
