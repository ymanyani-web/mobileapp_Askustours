<?php
declare(strict_types=1);

/** Server-only bridge; reuses the dashboard's session, owner role and CSRF. */
function otp_admin_bridge(array $request): array {
    global $config;
    $secret=(string)(getenv('OTP_ADMIN_BRIDGE_SECRET') ?: ($config['otp_admin_bridge']['secret'] ?? ''));
    if(strlen($secret)<32)throw new RuntimeException('Falta configurar el puente seguro con Firebase. El envío sigue desactivado.');
    if(!function_exists('curl_init'))throw new RuntimeException('El servidor necesita la extensión cURL.');
    $body=json_encode($request,JSON_THROW_ON_ERROR);
    $timestamp=(string)(int)floor(microtime(true)*1000);
    $nonce=bin2hex(random_bytes(24));
    $signature=hash_hmac('sha256',$timestamp.'.'.$nonce.'.'.$body,$secret);
    $ch=curl_init('https://europe-west1-hala-spain.cloudfunctions.net/adminOtpSettings');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-AskUs-Time: '.$timestamp,'X-AskUs-Nonce: '.$nonce,'X-AskUs-Signature: '.$signature]]);
    $response=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    if($response===false||$status<200||$status>=300)throw new RuntimeException($status===409?'Los límites cambiaron. Recarga la página antes de guardar.':'No se pudo consultar Firebase. Revisa el despliegue y el puente seguro.');
    $data=json_decode($response,true,512,JSON_THROW_ON_ERROR);
    if(!is_array($data)||!is_array($data['limits']??null))throw new RuntimeException('Respuesta de configuración no válida.');
    return $data;
}
