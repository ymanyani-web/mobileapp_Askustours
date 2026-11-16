<?php
// Backward-compatible alias. The canonical endpoint is GET /v1/home.
$_GET['route']='home';
require dirname(__DIR__).'/index.php';
exit;
require dirname(__DIR__,2).'/includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8'); header('Access-Control-Allow-Origin: *');
try {
 $data=[];
 foreach(['banners','categories','services','destinations','hotels','vehicles'] as $table){$stmt=db()->query("SELECT * FROM `$table` WHERE active=1 ORDER BY sort_order,id DESC");$data[$table]=$stmt->fetchAll();}
 echo json_encode(['success'=>true,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch(Throwable $e){http_response_code(500);echo json_encode(['success'=>false,'message'=>'Unable to load app content.']);}
