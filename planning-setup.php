<?php
require __DIR__.'/includes/bootstrap.php';require_admin();require_owner();require __DIR__.'/includes/layout.php';
$column=db()->query("SHOW COLUMNS FROM basket_items LIKE 'item_type'")->fetch();
$ready=$column&&str_contains((string)$column['Type'],"'planning'");
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    if(!$ready){db()->exec(file_get_contents(__DIR__.'/database/migration_2026_11_28_planning_basket.sql'));}
    flash('success','Cesta de planificación activada. Los pagos siguen pendientes de Redsys.');redirect('planning-setup.php');
}
page_header('Cesta de planificación');
?><section class="panel"><h2><?=$ready?'Cesta de planificación activa':'Activar cesta de planificación'?></h2><p>Guarda los itinerarios de los clientes en su cesta existente. Los artículos actuales se conservan. No activa cobros ni confirma reservas.</p><?php if(!$ready):?><form method="post"><input type="hidden" name="_csrf" value="<?=e(csrf())?>"><button class="btn primary">Activar cesta de planificación</button></form><?php else:?><p>Pago pendiente de integración con Redsys.</p><?php endif;?></section><?php page_footer();
