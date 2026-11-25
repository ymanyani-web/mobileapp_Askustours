<?php
require 'includes/bootstrap.php';require_admin();require_owner();require 'includes/layout.php';require 'includes/otp_admin_bridge.php';
$labels=['daily'=>'Máximo diario (UTC)','monthly'=>'Máximo mensual (UTC)','projectHourly'=>'Envíos del proyecto por hora','accountHourly'=>'Envíos por cuenta y hora','emailHourly'=>'Envíos por correo y hora','ipHourly'=>'Envíos por IP y hora','verifyIpHourly'=>'Verificaciones por IP y hora'];
$error=null;$result=null;
try {
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
        verify_csrf();
        $limits=['enabled'=>isset($_POST['enabled'])];
        foreach($labels as $key=>$label){$value=filter_var($_POST[$key]??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]]);if($value===false)throw new RuntimeException('Revisa los límites numéricos.');$limits[$key]=$value;}
        $result=otp_admin_bridge(['action'=>'save','limits'=>$limits,'revision'=>(int)($_POST['revision']??-1),'actor'=>(string)admin()['id']]);
        flash('success','Límites guardados y auditados en Firebase.');redirect('otp-settings.php');
    }
    $result=otp_admin_bridge(['action'=>'read','actor'=>(string)admin()['id']]);
}catch(Throwable $exception){$error=$exception instanceof RuntimeException?$exception->getMessage():'No se pudo cargar la configuración.';}
page_header('Verificación y límites de correo');
?>
<section class="panel"><h2>Códigos de correo de Ask Us</h2><p>Control exclusivo del administrador. Estos límites se comprueban en el servidor antes de enviar cada código.</p>
<?php if($error):?><p role="alert"><?=e($error)?></p><?php endif;?>
<?php if($result):$values=$result['limits'];?><form method="post"><input type="hidden" name="_csrf" value="<?=csrf()?>"><input type="hidden" name="revision" value="<?=(int)$result['revision']?>"><label><input type="checkbox" name="enabled" value="1" <?=!empty($values['enabled'])?'checked':''?>> Permitir envío de códigos por correo</label><div class="person-grid"><?php foreach($labels as $key=>$label):?><label><?=e($label)?><input type="number" name="<?=e($key)?>" min="1" max="100000" required value="<?=(int)($values[$key]??1)?>"></label><?php endforeach;?></div><button class="btn primary" data-confirm="¿Guardar los límites de envío de códigos por correo?">Guardar límites</button></form><?php endif;?>
<p class="muted">Caducidad: 5 minutos · Máximo 5 intentos · Reenvío: 60 segundos. Los envíos fallidos también reservan cupo. Los cambios afectan a nuevas solicitudes.</p><p class="muted">El presupuesto de alerta de 50 € no limita la facturación. Este control no modifica ni limita los SMS nativos de Firebase. Suspender SMS no desactiva el correo.</p></section>
<?php page_footer(); ?>
