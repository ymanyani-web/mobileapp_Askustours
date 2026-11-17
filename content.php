<?php
require 'includes/bootstrap.php'; require_admin(); redirect('catalog.php?view='.urlencode($_GET['type']??'drivers')); require 'includes/layout.php';
$resources = [
 'banners'=>['title'=>'Promotional banners','table'=>'banners','fields'=>['title','subtitle','link_url','sort_order'],'image'=>true],
 'categories'=>['title'=>'Home categories','table'=>'categories','fields'=>['name_en','name_ar','icon','sort_order'],'image'=>false],
 'services'=>['title'=>'Travel services','table'=>'services','fields'=>['name_en','name_ar','short_description','price_from','sort_order'],'image'=>true],
 'destinations'=>['title'=>'Destinations & visas','table'=>'destinations','fields'=>['name_en','name_ar','country_code','sort_order'],'image'=>true],
 'hotels'=>['title'=>'Hotels','table'=>'hotels','fields'=>['name','city','country','star_rating','price_per_night','sort_order'],'image'=>true],
 'vehicles'=>['title'=>'Vehicles & drivers','table'=>'vehicles','fields'=>['name','vehicle_type','seats','price_per_day','driver_name','sort_order'],'image'=>true],
];
$type = $_GET['type'] ?? 'banners'; if (!isset($resources[$type])) redirect('content.php?type=banners'); $r=$resources[$type]; $table=$r['table'];
if ($_SERVER['REQUEST_METHOD']==='POST') {
 verify_csrf(); $action=$_POST['action']??'save'; $id=(int)($_POST['id']??0); $wasEdit=$id>0;
 try {
  if ($action==='delete') { $stmt=db()->prepare("DELETE FROM `$table` WHERE id=?"); $stmt->execute([$id]); if($type==='banners')db()->prepare("INSERT INTO audit_log(admin_id,action,entity_type,entity_id,details_json) VALUES(?,'banner.deleted','banner',?,?)")->execute([admin()['id'],(string)$id,'{}']); flash('success','Item deleted.'); }
  elseif ($action==='toggle') { $stmt=db()->prepare("UPDATE `$table` SET active=1-active WHERE id=?"); $stmt->execute([$id]); if($type==='banners')db()->prepare("INSERT INTO audit_log(admin_id,action,entity_type,entity_id,details_json) VALUES(?,'banner.visibility_changed','banner',?,?)")->execute([admin()['id'],(string)$id,'{}']); flash('success','Visibility updated.'); }
  else {
   $data=[]; foreach($r['fields'] as $f) $data[$f]=trim((string)($_POST[$f]??''));
   if($type==='banners'){if($data['title']==='')throw new RuntimeException('Banner title is required.');$order=filter_var($data['sort_order'],FILTER_VALIDATE_INT);if($order===false||$order<0)throw new RuntimeException('Display order must be a non-negative integer.');$data['sort_order']=$order;}
   if ($r['image']) $data['image_path']=upload_image('image', $_POST['current_image']??null);
   if($type==='banners'&&empty($data['image_path']))throw new RuntimeException('A banner image is required.');
   $data['active']=isset($_POST['active'])?1:0;
   if ($id) { $set=implode(',',array_map(fn($k)=>"`$k`=?",array_keys($data))); $stmt=db()->prepare("UPDATE `$table` SET $set WHERE id=?"); $stmt->execute([...array_values($data),$id]); }
   else { $cols=implode(',',array_map(fn($k)=>"`$k`",array_keys($data))); $qs=implode(',',array_fill(0,count($data),'?')); $stmt=db()->prepare("INSERT INTO `$table` ($cols) VALUES ($qs)"); $stmt->execute(array_values($data)); }
   if($type==='banners'){if(!$id)$id=(int)db()->lastInsertId();db()->prepare("INSERT INTO audit_log(admin_id,action,entity_type,entity_id,details_json) VALUES(?,'banner.saved','banner',?,?)")->execute([admin()['id'],(string)$id,json_encode(['sortOrder'=>(int)$data['sort_order'],'active'=>(bool)$data['active']])]);}flash('success',$wasEdit?'Item updated.':'Item created.');
  }
 } catch(Throwable $e){ flash('error',$e->getMessage()); }
 redirect('content.php?type='.$type);
}
$edit=null; if(isset($_GET['edit'])){$stmt=db()->prepare("SELECT * FROM `$table` WHERE id=?");$stmt->execute([(int)$_GET['edit']]);$edit=$stmt->fetch();}
$items=db()->query("SELECT * FROM `$table` ORDER BY sort_order ASC, id DESC")->fetchAll();
$labels=['title'=>'Title','subtitle'=>'Subtitle','link_url'=>'Link URL','sort_order'=>'Display order','name_en'=>'English name','name_ar'=>'Arabic name','icon'=>'Icon / emoji','short_description'=>'Short description','price_from'=>'Starting price','country_code'=>'Country code','name'=>'Name','city'=>'City','country'=>'Country','star_rating'=>'Star rating','price_per_night'=>'Price / night','vehicle_type'=>'Vehicle type','seats'=>'Seats','price_per_day'=>'Price / day','driver_name'=>'Driver name'];
page_header($r['title']); ?>
<div class="content-layout"><section class="panel"><div class="panel-head"><div><p class="eyebrow">APP CONTENT</p><h3><?= $edit?'Edit item':'Add new item' ?></h3></div><?php if($edit): ?><a href="content.php?type=<?=e($type)?>">Cancel</a><?php endif; ?></div><form method="post" enctype="multipart/form-data" class="editor-form"><input type="hidden" name="_csrf" value="<?=csrf()?>"><input type="hidden" name="id" value="<?= (int)($edit['id']??0) ?>"><input type="hidden" name="current_image" value="<?=e($edit['image_path']??'')?>"><?php foreach($r['fields'] as $f): ?><label><?=e($labels[$f]??ucfirst($f))?><?php if($f==='short_description'): ?><textarea name="<?=e($f)?>" rows="4"><?=e($edit[$f]??'')?></textarea><?php else: ?><input name="<?=e($f)?>" value="<?=e((string)($edit[$f]??($f==='sort_order'?0:'')))?>" <?= str_contains($f,'price')||in_array($f,['sort_order','seats','star_rating'])?'type="number" step="0.01"':'' ?>></label><?php endif; ?><?php endforeach; ?><?php if($r['image']): ?><label>Cover image<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label><?php endif; ?><label class="check"><input type="checkbox" name="active" <?=($edit['active']??1)?'checked':''?>> Visible in mobile app</label><button class="btn primary wide"><?= $edit?'Save changes':'Create item' ?> →</button></form></section>
<section class="panel list-panel"><div class="panel-head"><div><p class="eyebrow">PUBLISHED CONTENT</p><h3><?=count($items)?> items</h3></div></div><div class="cards-list"><?php foreach($items as $item): ?><article class="content-card"><?php if($r['image']): ?><div class="thumb" style="background-image:url('<?=e($item['image_path']?:'assets/placeholder.svg')?>')"></div><?php else: ?><div class="thumb icon-thumb"><?=e($item['icon']??'◇')?></div><?php endif; ?><div class="card-info"><strong><?=e($item['title']??$item['name_en']??$item['name']??'Untitled')?></strong><small><?=e($item['subtitle']??$item['name_ar']??$item['city']??$item['vehicle_type']??'')?><?php if($type==='banners'):?> · Order <?=(int)$item['sort_order']?><?php endif;?></small><span class="visibility <?= $item['active']?'on':'off' ?>"><?= $item['active']?'Visible':'Hidden' ?></span></div><div class="card-actions"><a href="?type=<?=e($type)?>&edit=<?=(int)$item['id']?>">Edit</a><form method="post"><input type="hidden" name="_csrf" value="<?=csrf()?>"><input type="hidden" name="id" value="<?=(int)$item['id']?>"><button name="action" value="toggle">Toggle</button><button class="danger" name="action" value="delete" data-confirm="Delete this item permanently?">Delete</button></form></div></article><?php endforeach; ?><?php if(!$items): ?><div class="empty-state">No content yet. Add the first item using the form.</div><?php endif; ?></div></section></div>
<?php page_footer();
