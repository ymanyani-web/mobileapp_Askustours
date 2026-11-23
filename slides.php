<?php
require_once 'includes/bootstrap.php'; require_admin();
require_once 'includes/layout.php'; require_once 'includes/home_content.php'; require_once 'includes/slides.php';
require_once 'includes/deletions.php';
$type=($_GET['type']??'banners')==='offers'?'offers':'banners';$table=$type==='offers'?'home_offers':'banners';$metadataKey=$type==='offers'?'_offerDetails':'_bannerDetails';
$error=null;$posted=null;$editId=(int)($_GET['edit']??0);
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();$action=(string)($_POST['action']??'save');$id=(int)($_POST['id']??0);
    try {
        if (!in_array($action,['save','toggle','delete','restore'],true)) throw new RuntimeException('Acción no válida.');
        db()->beginTransaction();
        $raw=db()->query('SELECT config_json FROM home_config WHERE id=1 FOR UPDATE')->fetchColumn();
        if ($raw===false) throw new RuntimeException('Falta la configuración inicial de portada.');
        $home=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
        $row=null;
        if ($id) {$s=db()->prepare("SELECT * FROM `$table` WHERE id=? FOR UPDATE");$s->execute([$id]);$row=$s->fetch();if(!$row)throw new RuntimeException('Este slide ya no existe.');}
        if ($action==='restore') {
            restore_original_slides($home,$type);
        } else {
            if ($action!=='save'&&!$row) throw new RuntimeException('Selecciona un slide.');
            $details=$row?slide_details($row,$home,$metadataKey):[];
            if ($row&&!hash_equals(slide_revision($row,$details),(string)($_POST['revision']??''))) throw new RuntimeException('Otro administrador ha cambiado este slide. Recarga antes de guardar.');
            if ($action==='delete') {
                record_managed_backup($type,(string)$id,'Eliminación desde el editor de slides');
                db()->prepare("DELETE FROM `$table` WHERE id=?")->execute([$id]);unset($home[$metadataKey][(string)$id]);
            } elseif ($action==='toggle') {
                if (!$row['active']&&!public_asset_available($row['image_path'])) throw new RuntimeException('Sube una imagen válida antes de publicar.');
                db()->prepare("UPDATE `$table` SET active=1-active WHERE id=?")->execute([$id]);
            } else {
                $details=slide_validate($_POST,$details);
                $order=filter_var($_POST['sort_order']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>100000]]);
                if ($order===false) throw new RuntimeException('El orden debe estar entre 0 y 100000.');
                $link=trim((string)($_POST['link_url']??''));
                if ($link!==''&&(!filter_var($link,FILTER_VALIDATE_URL)||strtolower((string)parse_url($link,PHP_URL_SCHEME))!=='https'||parse_url($link,PHP_URL_USER)!==null)) throw new RuntimeException('Usa un enlace HTTPS válido.');
                if ($details['destination']!=='none'&&$link!=='') throw new RuntimeException('Elige un apartado de la app o un enlace externo, no ambos.');
                $image=upload_image('image',$row['image_path']??null);
                if (!public_asset_available($image)) throw new RuntimeException('Sube una foto JPG, PNG o WebP de hasta 5 MB.');
                $values=[$details['title']['es'],mb_substr($details['description']['es'],0,255),$image,$link,$order,isset($_POST['active'])?1:0];
                if ($row) db()->prepare("UPDATE `$table` SET title=?,subtitle=?,image_path=?,link_url=?,sort_order=?,active=? WHERE id=?")->execute([...$values,$id]);
                else {db()->prepare("INSERT INTO `$table`(title,subtitle,image_path,link_url,sort_order,active) VALUES(?,?,?,?,?,?)")->execute($values);$id=(int)db()->lastInsertId();}
                $home[$metadataKey][(string)$id]=$details;
            }
        }
        db()->prepare('UPDATE home_config SET config_json=? WHERE id=1')->execute([json_encode($home,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
        db()->prepare('INSERT INTO audit_log(admin_id,action,entity_type,entity_id,details_json) VALUES(?,?,?,?,?)')->execute([admin()['id'],'banner.'.$action,'banner',(string)$id,'{}']);
        db()->commit();flash('success',$action==='restore'?'Carrusel original recuperado. Los anteriores quedan ocultos y conservados.':'Cambios guardados.');
        redirect('content.php?type='.$type);
    } catch (Throwable $exception) {
        if(db()->inTransaction())db()->rollBack();
        $error=$exception instanceof PDOException?'No se pudo guardar. Inténtalo de nuevo.':$exception->getMessage();
        if($action==='save'){$posted=$_POST;$editId=$id;}
    }
}
$home=home_configuration();$items=db()->query("SELECT * FROM `$table` ORDER BY sort_order,id")->fetchAll();$edit=null;
foreach($items as $item)if((int)$item['id']===$editId)$edit=$item;
$details=$edit?slide_details($edit,$home,$metadataKey):[];$revision=$edit?slide_revision($edit,$details):'';
if($posted){$revision=(string)($posted['revision']??'');foreach(['title','description'] as $field)foreach(['es','en','ar'] as $locale)$details[$field][$locale]=(string)($posted[$field.'_'.$locale]??'');$details['destination']=$posted['destination']??'none';}
page_header($type==='offers'?'Home offers':'Home slides');home_content_navigation($type);
?>
<section class="home-content-intro"><div><p class="eyebrow"><?=$type==='offers'?'OFERTAS DESTACADAS':'CARRUSEL PRINCIPAL'?></p><h2><?=$type==='offers'?'Ofertas destacadas':'Slides principales'?></h2><p>La foto aparece en el carrusel. Al pulsarla, el cliente lee los detalles y encuentra el botón hacia el apartado elegido.</p></div></section>
<?php if($error):?><div class="alert error" role="alert"><?=e($error)?></div><?php endif;?>
<div class="content-layout home-editor-layout"><section class="panel home-card-editor"><h3><?=$edit?'Editar slide':'Nuevo slide'?></h3>
<form method="post" enctype="multipart/form-data" class="editor-form" data-home-editor>
<input type="hidden" name="_csrf" value="<?=csrf()?>"><input type="hidden" name="id" value="<?=(int)($edit['id']??0)?>"><input type="hidden" name="revision" value="<?=e($revision)?>">
<label class="home-full-field">Foto · JPG, PNG o WebP<input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-home-image><small>Hasta 5 MB. Se conserva la foto actual si no subes otra.</small></label>
<div class="home-card-preview"><img data-home-preview src="<?=e($edit?public_content_media_url($type,(int)$edit['id'],$edit['image_path']):'assets/placeholder.svg')?>" alt="Vista previa del slide"><div><strong data-home-preview-title><?=e($details['title']['es']??'Título del slide')?></strong><p data-home-preview-description><?=e($details['description']['es']??'Descripción al abrir los detalles')?></p></div></div>
<?php foreach(['es'=>'Español','en'=>'English','ar'=>'العربية'] as $locale=>$name):?>
<fieldset class="home-locale" <?=$locale==='ar'?'dir="rtl"':''?>><legend><?=e($name)?></legend><label>Título<input name="title_<?=$locale?>" maxlength="160" <?=$locale==='es'?'required data-home-title':''?> value="<?=e($details['title'][$locale]??'')?>"></label><label>Descripción<textarea name="description_<?=$locale?>" maxlength="4000" rows="4" <?=$locale==='es'?'data-home-description':''?>><?=e($details['description'][$locale]??'')?></textarea></label></fieldset>
<?php endforeach;?>
<label class="home-full-field">Botón en los detalles<select name="destination"><?php foreach(slide_destinations() as $value=>$label):?><option value="<?=e($value)?>" <?=($details['destination']??'none')===$value?'selected':''?>><?=e($label)?></option><?php endforeach;?></select><small>El botón lleva a este apartado sin salir de la app.</small></label>
<label class="home-full-field">Enlace externo opcional<input type="url" name="link_url" value="<?=e($posted['link_url']??$edit['link_url']??'')?>" placeholder="https://"><small>Déjalo vacío al elegir un apartado de la app.</small></label>
<label>Orden<input type="number" name="sort_order" min="0" max="100000" value="<?=e((string)($posted['sort_order']??$edit['sort_order']??0))?>" required></label>
<label class="check"><input type="checkbox" name="active" <?=($posted?isset($posted['active']):($edit['active']??true))?'checked':''?>> Publicar en la app</label>
<button class="btn primary wide">Guardar slide</button><?php if($edit):?><a href="content.php?type=<?=e($type)?>">Cancelar edición</a><?php endif;?></form></section>
<section class="panel list-panel"><h3><?=count($items)?> slides · <?=count(array_filter($items,fn($x)=>(bool)$x['active']))?> publicados</h3><div class="cards-list">
<?php foreach($items as $item):$d=slide_details($item,$home,$metadataKey);$valid=public_asset_available($item['image_path']);?><article class="content-card"><img class="thumb" src="<?=e($valid?public_content_media_url($type,(int)$item['id'],$item['image_path']):'assets/placeholder.svg')?>" alt=""><div class="card-info"><strong><?=e($d['title']['es']??$item['title'])?></strong><small>Orden <?=(int)$item['sort_order']?> · <?=e(slide_destinations()[$d['destination']??'none']??'Sin botón')?></small><span class="visibility <?=$item['active']&&$valid?'on':'off'?>"><?=$valid?($item['active']?'Publicado':'Oculto'):'Falta la imagen'?></span></div><div class="card-actions"><a href="?type=<?=e($type)?>&edit=<?=(int)$item['id']?>">Editar</a><form method="post"><input type="hidden" name="_csrf" value="<?=csrf()?>"><input type="hidden" name="id" value="<?=(int)$item['id']?>"><input type="hidden" name="revision" value="<?=e(slide_revision($item,$d))?>"><button name="action" value="toggle"><?=$item['active']?'Ocultar':'Publicar'?></button><button name="action" value="delete" class="danger" data-confirm="¿Eliminar este slide definitivamente?">Eliminar</button></form></div></article><?php endforeach;?></div>
<details style="margin-top:24px"><summary>Recuperar el carrusel original</summary><p>Recupera las cinco fotos, los textos en tres idiomas y el botón de chóferes. Los otros slides se ocultan y se conservan. Si ya recuperaste un slide, se respetan sus cambios posteriores.</p><form method="post"><input type="hidden" name="_csrf" value="<?=csrf()?>"><button class="btn" name="action" value="restore" data-confirm="¿Publicar los cinco slides originales y ocultar los demás?">Recuperar fotos originales</button></form></details>
</section></div>
<?php page_footer();
