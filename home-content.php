<?php
require 'includes/bootstrap.php';
require_admin();
require_once 'includes/deletions.php';
require 'includes/layout.php';
require 'includes/home_content.php';

$section = (string)($_GET['section'] ?? 'tripIdeas');
$sections = home_sections();
if (!isset($sections[$section])) redirect('home-content.php?section=tripIdeas');
$error = null;
$posted = null;
$editId = (string)($_GET['edit'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (string)($_POST['id'] ?? '');
    $action = (string)($_POST['action'] ?? 'save');
    try {
        if (!in_array($action,['save','toggle','delete'],true)) throw new RuntimeException('Acción no válida.');
        db()->beginTransaction();
        // Lock the shared config row so two editors cannot overwrite other sections.
        $statement = db()->query('SELECT config_json FROM home_config WHERE id=1 FOR UPDATE');
        $raw = $statement->fetchColumn();
        if ($raw === false) throw new RuntimeException('Falta la configuración inicial de la portada.');
        $home = json_decode($raw,true,512,JSON_THROW_ON_ERROR);
        $cards = $home[$section] ?? [];
        $index = null;
        foreach ($cards as $key=>$card) if ((string)$card['id'] === $id) {$index=$key; break;}
        if ($id !== '' && $index === null) throw new RuntimeException('Este contenido ya no existe. Recarga la página.');
        if ($action !== 'save' && $index === null) throw new RuntimeException('Selecciona un contenido.');
        $existing = $index !== null ? $cards[$index] : [];
        // Reject stale forms instead of silently replacing another administrator's edit.
        if ($index !== null && !hash_equals(hash('sha256',json_encode($existing)),(string)($_POST['revision'] ?? ''))) {
            throw new RuntimeException('Otro administrador ha cambiado este contenido. Recarga antes de guardar.');
        }
        if ($action === 'delete') {
            record_managed_backup('home_'.$section,$id,'Eliminación desde el editor de portada');
            array_splice($cards,$index,1);
        } elseif ($action === 'toggle') {
            if (($existing['_active'] ?? true) === false && !public_asset_available(home_card_image($existing))) throw new RuntimeException('Sube una imagen válida antes de publicar.');
            $cards[$index]['_active'] = !($existing['_active'] ?? true);
        } else {
            $card = home_validate_card($_POST,$existing,$section);
            $image = upload_image('image',home_card_image($existing) ?: null);
            if (!public_asset_available($image)) throw new RuntimeException('Sube una foto JPG, PNG o WebP de hasta 5 MB.');
            $card['id'] = $existing['id'] ?? 'home_'.bin2hex(random_bytes(12));
            $card['_imagePath'] = $image;
            $card['_mediaId'] = !empty($_FILES['image']['name']) ? random_int(100000000000,999999999999) : ($existing['_mediaId'] ?? random_int(100000000000,999999999999));
            $card['imageUrl'] = home_card_media_url($section,$card);
            if ($index === null) $cards[]=$card; else $cards[$index]=$card;
            $id = $card['id'];
        }
        $home[$section] = array_values($cards);
        db()->prepare('UPDATE home_config SET config_json=? WHERE id=1')->execute([json_encode($home,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]);
        db()->prepare('INSERT INTO audit_log(admin_id,action,entity_type,entity_id,details_json) VALUES(?,?,?,?,?)')->execute([admin()['id'],'home_content.'.$action,$section,$id,'{}']);
        db()->commit();
        flash('success',$action === 'delete' ? 'Contenido eliminado.' : 'Cambios guardados.');
        redirect('home-content.php?section='.$section);
    } catch (Throwable $exception) {
        if (db()->inTransaction()) db()->rollBack();
        $error = !($exception instanceof PDOException) && ($exception instanceof RuntimeException || $exception instanceof JsonException) ? $exception->getMessage() : 'No se pudo guardar. Inténtalo de nuevo.';
        if ($action === 'save') {$posted=$_POST; $editId=$id;}
    }
}
$home = home_configuration();
$cards = home_sorted_cards($home[$section] ?? []);
$edit = [];
foreach ($cards as $card) if ((string)$card['id'] === $editId) $edit=$card;
$revision = $edit ? hash('sha256',json_encode($edit)) : '';
// Retain the submitted revision and values after validation/conflict errors.
if ($posted) {
    $revision = (string)($posted['revision'] ?? '');
    foreach (['title','description'] as $field) foreach (['es','en','ar'] as $locale) $edit[$field][$locale]=(string)($posted[$field.'_'.$locale] ?? '');
    $edit['displayOrder']=$posted['displayOrder'] ?? 0;
    $edit['_active']=isset($posted['active']);
    $edit['prefillStyle']=$posted['prefillStyle'] ?? '';
}
page_header($sections[$section]);
home_content_navigation($section);
?>
<section class="home-content-intro"><div><p class="eyebrow">PORTADA DE LA APP</p><h2><?=e($sections[$section])?></h2><p>Prepara tus fotos y textos, revisa la vista previa y decide qué aparece en la aplicación.</p></div><a class="btn secondary" href="api/v1/home" target="_blank" rel="noopener">Ver datos de la app ↗</a></section>
<?php if($error):?><div role="alert" class="alert error"><?=e($error)?></div><?php endif;?>
<div class="content-layout home-editor-layout"><section class="panel">
<div class="panel-head"><h3><?=$editId !== ''?'Editar contenido':'Nuevo contenido'?></h3><?php if($editId !== ''):?><a href="?section=<?=e($section)?>">Cancelar edición</a><?php endif;?></div>
<form method="post" enctype="multipart/form-data" class="editor-form home-card-editor" data-home-editor>
<input type="hidden" name="_csrf" value="<?=csrf()?>"><input type="hidden" name="id" value="<?=e($editId)?>"><input type="hidden" name="revision" value="<?=e($revision)?>">
<label class="home-full-field">Foto · JPG, PNG o WebP<input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-home-image <?=$editId === ''?'required':''?>><small>Hasta 5 MB. Formato horizontal recomendado.</small></label>
<div class="home-card-preview" aria-label="Vista previa editorial"><img data-home-preview src="<?=e($edit && public_asset_available(home_card_image($edit)) ? home_card_media_url($section,$edit) : 'assets/placeholder.svg')?>" alt=""><div data-editorial-text><strong data-home-preview-title><?=e($edit['title']['es'] ?? 'Título del contenido')?></strong><p data-home-preview-description><?=e($edit['description']['es'] ?? '')?></p></div></div>
<?php foreach(['es'=>'Español','en'=>'English','ar'=>'العربية'] as $locale=>$label):?><fieldset class="home-locale"><legend><?=e($label)?></legend><label>Título<input name="title_<?=$locale?>" maxlength="160" value="<?=e($edit['title'][$locale] ?? '')?>" <?=$locale==='es'?'required data-home-title':''?> <?=$locale==='ar'?'dir="rtl"':''?>></label><label>Descripción<textarea name="description_<?=$locale?>" maxlength="1000" rows="3" <?=$locale==='es'?'data-home-description':''?> <?=$locale==='ar'?'dir="rtl"':''?>><?=e($edit['description'][$locale] ?? '')?></textarea></label></fieldset><?php endforeach;?>
<?php if($section==='tripIdeas'):?><label class="home-full-field">Estilo de viaje<select name="prefillStyle"><option value="">General</option><?php foreach(['nature'=>'Naturaleza','honeymoon'=>'Luna de miel','family'=>'Familia','friends'=>'Amigos','shopping'=>'Compras','sports'=>'Deporte'] as $style=>$label):?><option value="<?=$style?>" <?=($edit['prefillStyle']??'')===$style?'selected':''?>><?=$label?></option><?php endforeach;?></select><small>Clasifica el contenido por estilo de viaje.</small></label><?php endif;?>
<label>Orden de aparición<input type="number" name="displayOrder" min="0" max="100000" step="1" required value="<?=e((string)($edit['displayOrder']??count($cards)))?>"></label>
<label class="check"><input type="checkbox" name="active" <?=($edit['_active']??true)?'checked':''?>> Publicar en la app</label>
<button class="btn primary wide" name="action" value="save">Guardar contenido</button>
</form></section><section class="panel list-panel"><div class="panel-head"><div><p class="eyebrow">CONTENIDO DE LA SECCIÓN</p><h3><?=count($cards)?> elementos</h3></div></div>
<div class="cards-list"><?php foreach($cards as $card): $available=public_asset_available(home_card_image($card)); $active=$card['_active']??true; $hash=hash('sha256',json_encode($card));?>
<article class="content-card"><img class="thumb" src="<?=e($available?home_card_media_url($section,$card):'assets/placeholder.svg')?>" alt=""><div class="card-info" data-editorial-text><strong><?=e($card['title']['es']??reset($card['title'])?:'Sin título')?></strong><small>Orden <?=(int)($card['displayOrder']??0)?></small><span class="visibility <?=$active&&$available?'on':'off'?>"><?=$available?($active?'Publicado':'Oculto'):'Falta la imagen'?></span></div><div class="card-actions"><a href="?section=<?=e($section)?>&edit=<?=e(rawurlencode((string)$card['id']))?>">Editar</a><form method="post"><input type="hidden" name="_csrf" value="<?=csrf()?>"><input type="hidden" name="id" value="<?=e((string)$card['id'])?>"><input type="hidden" name="revision" value="<?=e($hash)?>"><button name="action" value="toggle"><?=$active?'Ocultar':'Publicar'?></button><button class="danger" name="action" value="delete" data-confirm="¿Eliminar este contenido de la portada?">Eliminar</button></form></div></article>
<?php endforeach;?><?php if(!$cards):?><div class="empty-state"><h3>Prepara el primer contenido</h3><p>Añade una foto y un título. Solo se mostrará en la app si lo publicas.</p></div><?php endif;?></div></section></div>
<?php page_footer();
