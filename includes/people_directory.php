<?php
// Read-only directory. Account decisions and deletion continue through existing handlers.
function people_directory_filters(array $input):array {
    $allowed=static fn(string $key,array $values,string $default=''):string => in_array($input[$key]??'',$values,true)?$input[$key]:$default;
    $text=static fn(string $key):string=>is_string($input[$key]??null)?mb_substr(trim($input[$key]),0,100):'';
    return ['q'=>$text('q'),
        'account'=>$allowed('account',['active','inactive','blocked']),
        'review'=>$allowed('review',['draft','submitted','approved','changes_requested','rejected','suspended']),
        'activity'=>$allowed('activity',['driver','guide','experience','rental','accommodation','other']),
        'city'=>$text('city'),
        'sort'=>$allowed('sort',['newest','oldest','name','reservations'],'newest')];
}
function people_directory_url(string $view,array $filters,array $changes=[]):string {
    return 'users.php?'.http_build_query(array_filter(array_replace(['view'=>$view],$filters,$changes),static fn($v)=>$v!==''&&$v!==null));
}
function people_search_pattern(string $value):string {return '%'.strtr($value,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';}
function people_initials(string $first,string $last):string {
    return mb_strtoupper(mb_substr(trim($first),0,1).mb_substr(trim($last),0,1))?:'—';
}
function render_people_directory(string $view):void {
    $professional=$view==='collaborators';$f=people_directory_filters($_GET);
    if(!$professional){$f['activity']=$f['city']=$f['review']='';}
    $account="COALESCE(ac.account_status,'active')";
    $review="CASE WHEN p.status='draft' AND JSON_UNQUOTE(JSON_EXTRACT(p.profile_json,'$._reviewState'))='changes_requested' THEN 'changes_requested' ELSE COALESCE(p.status,'draft') END";
    $from=" FROM users u LEFT JOIN partner_profiles p ON p.user_id=u.id LEFT JOIN (
        SELECT a.entity_id,JSON_UNQUOTE(JSON_EXTRACT(a.details_json,'$.status')) account_status FROM audit_log a
        JOIN (SELECT entity_id,MAX(id) latest FROM audit_log WHERE action='user.account_control_changed' AND entity_type='user' GROUP BY entity_id) latest ON latest.latest=a.id
    ) ac ON ac.entity_id=u.id";
    $where=$professional?"(u.role='partner' OR u.partner_intent=1)":"u.role='customer'";$params=[];
    if($f['q']!==''){$where.=" AND (CONCAT(u.first_name,' ',u.last_name) LIKE ? ESCAPE '!' OR u.email LIKE ? ESCAPE '!' OR u.phone LIKE ? ESCAPE '!')";$params=array_fill(0,3,people_search_pattern($f['q']));}
    if($f['account']!==''){$where.=" AND $account=?";$params[]=$f['account'];}
    if($f['review']!==''){$where.=" AND ($review)=?";$params[]=$f['review'];}
    if($f['activity']!==''){$where.=" AND JSON_CONTAINS(JSON_EXTRACT(p.profile_json,'$.onboarding.categories'),JSON_QUOTE(?))";$params[]=$f['activity'];}
    if($f['city']!==''){$where.=" AND JSON_SEARCH(p.profile_json,'one',?,'!','$.onboarding.workCities[*]') IS NOT NULL";$params[]=people_search_pattern($f['city']);}
    $s=db()->prepare("SELECT COUNT(*) total,COALESCE(SUM($account='active'),0) active,COALESCE(SUM($account='blocked'),0) blocked,COALESCE(SUM(($review)='submitted'),0) pending".$from.' WHERE '.$where);$s->execute($params);$summary=$s->fetch();$total=(int)$summary['total'];
    $pages=max(1,(int)ceil($total/25));$page=min($pages,max(1,(int)($_GET['page']??1)));$offset=($page-1)*25;
    $owner=$professional?'partner_user_id':'customer_user_id';
    $sort=['newest'=>'u.created_at DESC,u.id','oldest'=>'u.created_at ASC,u.id','name'=>'u.first_name ASC,u.last_name ASC,u.id','reservations'=>'reservations DESC,u.created_at DESC,u.id'][$f['sort']];
    $s=db()->prepare("SELECT u.id,u.first_name,u.last_name,u.email,u.phone,u.created_at,$account account_status,($review) review_state,
        JSON_EXTRACT(p.profile_json,'$.onboarding.categories') categories,
        COALESCE(r.total,0) reservations,COALESCE(r.accepted,0) accepted,COALESCE(r.completed,0) completed".$from.
        " LEFT JOIN (SELECT `$owner` user_id,COUNT(*) total,SUM(status='accepted') accepted,SUM(status='completed') completed FROM reservations GROUP BY `$owner`) r ON r.user_id=u.id WHERE $where ORDER BY $sort LIMIT 25 OFFSET $offset");$s->execute($params);$rows=$s->fetchAll();
    $reviewLabels=['draft'=>'Incompleto','submitted'=>'Pendiente de revisión','approved'=>'Verificado','changes_requested'=>'Correcciones solicitadas','rejected'=>'Rechazado','suspended'=>'Suspendido'];
    $activities=['driver'=>'Chófer privado','guide'=>'Guía turístico','rental'=>'Alquiler de vehículos','experience'=>'Experiencias','accommodation'=>'Alojamiento','other'=>'Otra actividad'];
    $canDelete=can_manage_deletions('delete');$hasFilters=(bool)array_filter(array_diff_key($f,['sort'=>true]));
    ?>
    <section class="people-intro"><div><p class="eyebrow">DIRECTORIO</p><h2><?=$professional?'Tu red de colaboradores':'Tus clientes, de un vistazo'?></h2><p><?=$professional?'Consulta la actividad, revisa el registro y abre cada perfil profesional.':'Encuentra sus datos de contacto y consulta su actividad en una sola ficha.'?></p></div><?php if($professional&&(admin()['role']??'')==='admin'):?><a class="btn secondary" href="users.php?view=collaborators&amp;registration=settings">Requisitos y comisiones</a><?php endif;?></section>
    <div class="people-metrics" aria-label="Resumen del directorio">
    <article><span><?=$hasFilters?'Resultados':'Total de cuentas'?></span><strong><?=$total?></strong></article><article><span data-people-text="Cuentas activas">Cuentas activas</span><strong><?=(int)$summary['active']?></strong></article><article><span data-people-text="Bloqueadas">Bloqueadas</span><strong><?=(int)$summary['blocked']?></strong></article><?php if($professional):?><article><span data-people-text="Pendientes de revisión">Pendientes de revisión</span><strong><?=(int)$summary['pending']?></strong></article><?php endif;?></div>
    <section class="panel people-directory"><form method="get" class="people-filters" role="search"><input type="hidden" name="view" value="<?=e($view)?>">
    <label class="people-query"><span data-people-text="Buscar">Buscar</span><input type="search" name="q" value="<?=e($f['q'])?>" placeholder="Nombre, correo o teléfono" autocomplete="off"></label>
    <label><span data-people-text="Estado de cuenta">Estado de cuenta</span><select name="account"><option data-people-text="Todas las cuentas" value="">Todas las cuentas</option><?php foreach(account_labels() as $key=>$label):?><option data-people-text="<?=e($label)?>" value="<?=e($key)?>" <?=$f['account']===$key?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
    <?php if($professional):?><label><span data-people-text="Revisión profesional">Revisión profesional</span><select name="review"><option data-people-text="Todos los registros" value="">Todos los registros</option><?php foreach($reviewLabels as $key=>$label):?><option data-people-text="<?=e($label)?>" value="<?=e($key)?>" <?=$f['review']===$key?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label><label><span data-people-text="Actividad">Actividad</span><select name="activity"><option data-people-text="Todas las actividades" value="">Todas las actividades</option><?php foreach($activities as $key=>$label):?><option data-people-text="<?=e($label)?>" value="<?=e($key)?>" <?=$f['activity']===$key?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label><label><span data-people-text="Ciudad de trabajo">Ciudad de trabajo</span><input name="city" value="<?=e($f['city'])?>" placeholder="Por ejemplo, Madrid"></label><?php endif;?>
    <label><span data-people-text="Ordenar por">Ordenar por</span><select name="sort"><?php foreach(['newest'=>'Altas recientes','oldest'=>'Más antiguos','name'=>'Nombre A–Z','reservations'=>'Más servicios'] as $key=>$label):?><option data-people-text="<?=e($label)?>" value="<?=e($key)?>" <?=$f['sort']===$key?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
    <div class="people-filter-actions"><button class="btn primary" data-people-text="Aplicar filtros">Aplicar filtros</button><?php if($hasFilters||$f['sort']!=='newest'):?><a href="users.php?view=<?=e($view)?>" data-people-text="Limpiar filtros">Limpiar filtros</a><?php endif;?></div></form>
    <div class="people-result-heading"><p><strong><?=$total?></strong> <?=$professional?'colaboradores':'clientes'?><?=$hasFilters?' encontrados':''?></p><small><?=$total?($offset+1).'–'.min($offset+25,$total):'0'?> de <?=$total?></small></div>
    <?php if(!$rows):?><div class="people-empty"><?=ui_icon('users')?><h3><?=$hasFilters?'No hay coincidencias':'Todavía no hay cuentas'?></h3><p><?=$hasFilters?'Prueba otro nombre o elimina algún filtro.':'Las nuevas altas aparecerán aquí cuando se registren.'?></p><?php if($hasFilters):?><a class="btn secondary" href="users.php?view=<?=e($view)?>" data-people-text="Ver todas las cuentas">Ver todas las cuentas</a><?php endif;?></div><?php else:?>
    <div class="table-wrap people-table"><table><thead><tr><th><?=$professional?'Colaborador':'Cliente'?></th><th data-people-text="Contacto">Contacto</th><th data-people-text="Estado">Estado</th><th data-people-text="Servicios">Servicios</th><th data-people-text="Gestión">Gestión</th></tr></thead><tbody>
    <?php foreach($rows as $row):$url='users.php?view='.$view.'&id='.rawurlencode($row['id']);$name=trim($row['first_name'].' '.$row['last_name'])?:'Sin nombre';?>
    <tr><td><div class="people-identity"><span class="people-avatar" aria-hidden="true"><?=e(people_initials($row['first_name'],$row['last_name']))?></span><div><a class="people-name" href="<?=e($url)?>" data-editorial-text><?=e($name)?></a><small>Alta <?=e(substr($row['created_at'],0,10))?></small><?php if($professional):$categories=json_decode($row['categories']??'[]',true);?><div class="people-activities"><?php foreach(is_array($categories)?$categories:[] as $category):if(is_string($category)&&isset($activities[$category])):?><span><?=e($activities[$category])?></span><?php endif;endforeach;?></div><?php endif;?></div></div></td>
    <td class="people-contact" data-editorial-text><?php if(filter_var($row['email'],FILTER_VALIDATE_EMAIL)):?><a href="mailto:<?=e($row['email'])?>"><bdi dir="ltr"><?=e($row['email'])?></bdi></a><?php else:?><span class="muted">Sin correo</span><?php endif;?><?php if($row['phone']):?><a href="tel:<?=e(preg_replace('/[^+0-9]/','',$row['phone']))?>"><bdi dir="ltr"><?=e($row['phone'])?></bdi></a><?php else:?><span class="muted">Sin móvil</span><?php endif;?></td>
    <td><span class="people-status <?=e($row['account_status'])?>"><span data-people-text="<?=e(account_labels()[$row['account_status']]??'Sin confirmar')?>"><?=e(account_labels()[$row['account_status']]??'Sin confirmar')?></span></span><?php if($professional):?><span class="people-status <?=e($row['review_state'])?>"><span data-people-text="<?=e($reviewLabels[$row['review_state']]??'Sin confirmar')?>"><?=e($reviewLabels[$row['review_state']]??'Sin confirmar')?></span></span><?php endif;?></td>
    <td><strong class="people-service-count"><?=(int)$row['reservations']?></strong><small>solicitudes</small><small><?=(int)$row['accepted']?> confirmados · <?=(int)$row['completed']?> realizados</small></td>
    <td><div class="people-actions"><a class="btn secondary" href="<?=e($url)?>">Ver ficha <span aria-hidden="true">→</span></a><?php if($canDelete):?><button type="button" class="people-delete" data-delete-user="<?=e($row['id'])?>" aria-label="Eliminar cuenta de <?=e($name)?>">Eliminar</button><?php endif;?></div></td></tr>
    <?php endforeach;?></tbody></table></div><?php endif;?>
    <nav class="directory-pagination people-pagination" aria-label="Páginas del directorio"><?php if($page>1):?><a class="btn secondary" href="<?=e(people_directory_url($view,$f,['page'=>$page-1]))?>">← Anterior</a><?php endif;?><span>Página <?=$page?> de <?=$pages?></span><?php if($page<$pages):?><a class="btn secondary" href="<?=e(people_directory_url($view,$f,['page'=>$page+1]))?>">Siguiente →</a><?php endif;?></nav></section>
    <?php if($canDelete)require __DIR__.'/deletion_dialog.php';
}
