<?php
require 'includes/bootstrap.php';require_admin();require 'includes/layout.php';require 'includes/home_content.php';require 'includes/slides.php';require 'includes/home_publishing.php';
$error=null;
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    try {
        db()->beginTransaction();$raw=db()->query('SELECT config_json FROM home_config WHERE id=1 FOR UPDATE')->fetchColumn();
        if($raw===false)throw new RuntimeException('Falta la configuración de portada.');
        $home=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
        $action=$_POST['action']??'save';
        if($action==='restore') {restore_original_slides($home,'offers');restore_original_rails($home);}
        elseif($action==='save') {
            $mode=(string)($_POST['mode']??'manual');if(!in_array($mode,['manual','mixed'],true))throw new RuntimeException('Modo no válido.');
            $home['_homePublishing']=['mode'=>$mode];
        } else throw new RuntimeException('Acción no válida.');
        db()->prepare('UPDATE home_config SET config_json=? WHERE id=1')->execute([json_encode($home,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
        db()->prepare('INSERT INTO audit_log(admin_id,action,entity_type,entity_id,details_json) VALUES(?,?,?,?,?)')->execute([admin()['id'],'home_publishing.'.$action,'home','1','{}']);
        db()->commit();flash('success','Portada actualizada.');redirect('home-publishing.php');
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();$error=$e instanceof PDOException?'No se pudo guardar.':$e->getMessage();}
}
$home=home_configuration();page_header('Publicación de portada');home_content_navigation('publishing');
?>
<section class="home-content-intro"><div><p class="eyebrow">PORTADA DE LA APP</p><h2>Promociones y recomendaciones</h2><p>Combina tus fotos promocionales con servicios publicados que tienen reservas reales.</p></div></section>
<?php if($error):?><div class="alert error" role="alert"><?=e($error)?></div><?php endif;?>
<section class="panel"><h3>Cómo se publica</h3><form method="post" class="editor-form"><input type="hidden" name="_csrf" value="<?=csrf()?>"><label>Publicación<select name="mode"><option value="manual" <?=($home['_homePublishing']['mode']??'manual')==='manual'?'selected':''?>>Solo contenido elegido desde el panel</option><option value="mixed" <?=($home['_homePublishing']['mode']??'manual')==='mixed'?'selected':''?>>Mixto: promociones + servicios con reservas</option></select></label><button class="btn primary" name="action" value="save">Guardar modo</button></form>
<p>Se añaden hasta cuatro servicios en Ofertas y cuatro visitas guiadas en Experiencias, ordenados por reservas aceptadas o completadas de los últimos 90 días. Destinos se ordena por esas reservas. Se excluyen servicios de prueba, ocultos, sin foto o sin reservas. Si faltan datos, queda tu selección editorial.</p><p>Crea tu viaje mantiene los estilos elegidos desde el panel. Las búsquedas y las compras todavía no se usan para ordenar: no existe un registro de búsquedas y el cobro no está activado.</p></section>
<section class="panel" style="margin-top:22px"><h3>Recuperar las otras filas originales</h3><p>Recupera las cinco ofertas, seis ideas de viaje, cuatro destinos y cuatro experiencias con sus fotos e idiomas originales. Se conservan las ediciones posteriores y no se duplican al repetir. Las ofertas anteriores se ocultan.</p><form method="post"><input type="hidden" name="_csrf" value="<?=csrf()?>"><button class="btn" name="action" value="restore" data-confirm="¿Recuperar las fotos originales de las otras filas?">Recuperar fotos de las filas</button></form></section>
<?php page_footer();
