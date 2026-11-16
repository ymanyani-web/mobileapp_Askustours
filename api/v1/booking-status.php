<?php
header('Content-Type: application/json; charset=utf-8');http_response_code(410);echo json_encode(['error'=>['code'=>'ENDPOINT_RETIRED','message'=>'Use GET /v1/me/reservations/{reservationId}.','fields'=>(object)[]],'meta'=>['requestId'=>'req_'.bin2hex(random_bytes(8))]]);exit;
require dirname(__DIR__,2).'/includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8'); header('Access-Control-Allow-Origin: *');
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);echo json_encode(['success'=>false,'message'=>'GET required']);exit;}
$code=trim((string)($_GET['booking_code']??'')); $phone=trim((string)($_GET['phone']??''));
if($code===''||$phone===''){http_response_code(422);echo json_encode(['success'=>false,'message'=>'booking_code and phone are required']);exit;}
$stmt=db()->prepare('SELECT b.booking_code,b.booking_type,b.start_date,b.end_date,b.pickup_location,b.dropoff_location,b.guests,b.total_amount,b.status,b.created_at FROM bookings b JOIN customers c ON c.id=b.customer_id WHERE b.booking_code=? AND c.phone=? LIMIT 1');
$stmt->execute([$code,$phone]); $booking=$stmt->fetch();
if(!$booking){http_response_code(404);echo json_encode(['success'=>false,'message'=>'Reservation not found.']);exit;}
echo json_encode(['success'=>true,'data'=>$booking],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
