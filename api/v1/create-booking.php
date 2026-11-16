<?php
header('Content-Type: application/json; charset=utf-8');http_response_code(410);echo json_encode(['error'=>['code'=>'ENDPOINT_RETIRED','message'=>'Use POST /v1/quotes followed by POST /v1/reservation-requests.','fields'=>(object)[]],'meta'=>['requestId'=>'req_'.bin2hex(random_bytes(8))]]);exit;
require dirname(__DIR__,2).'/includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8'); header('Access-Control-Allow-Origin: *'); header('Access-Control-Allow-Headers: Content-Type, Accept'); header('Access-Control-Allow-Methods: POST, OPTIONS');
function respond(int $status,array $body):never{http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
if($_SERVER['REQUEST_METHOD']==='OPTIONS')exit; if($_SERVER['REQUEST_METHOD']!=='POST')respond(405,['success'=>false,'message'=>'POST required']);
$raw=file_get_contents('php://input'); $input=json_decode($raw,true);
if(!is_array($input))respond(400,['success'=>false,'message'=>'A valid JSON object is required.']);
$errors=[]; foreach(['full_name','phone','booking_type'] as $field)if(trim((string)($input[$field]??''))==='')$errors[$field]="$field is required";
$types=['vehicle','hotel','service','flight','visa','activity']; if(!empty($input['booking_type'])&&!in_array($input['booking_type'],$types,true))$errors['booking_type']='Unsupported booking type.';
foreach(['start_date','end_date'] as $field)if(!empty($input[$field])&&!DateTimeImmutable::createFromFormat('!Y-m-d',(string)$input[$field]))$errors[$field]='Use YYYY-MM-DD.';
if(!empty($input['start_date'])&&!empty($input['end_date'])&&$input['end_date']<$input['start_date'])$errors['end_date']='End date cannot be before start date.';
if(isset($input['guests'])&&(int)$input['guests']<1)$errors['guests']='Must be at least 1.';
if(isset($input['total_amount'])&&(float)$input['total_amount']<0)$errors['total_amount']='Cannot be negative.';
if($errors)respond(422,['success'=>false,'message'=>'Validation failed.','errors'=>$errors]);
try{
 db()->beginTransaction();
 $stmt=db()->prepare('SELECT id FROM customers WHERE phone=? LIMIT 1');$stmt->execute([$input['phone']]);$customerId=$stmt->fetchColumn();
 if(!$customerId){$stmt=db()->prepare('INSERT INTO customers(full_name,email,phone) VALUES(?,?,?)');$stmt->execute([trim($input['full_name']),$input['email']??null,trim($input['phone'])]);$customerId=db()->lastInsertId();}
 else{$stmt=db()->prepare('UPDATE customers SET full_name=?,email=COALESCE(?,email) WHERE id=?');$stmt->execute([trim($input['full_name']),$input['email']??null,$customerId]);}
 $code='ASK-'.date('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(3)),0,6));
 $stmt=db()->prepare("INSERT INTO bookings(booking_code,customer_id,booking_type,item_id,source,start_date,end_date,pickup_location,dropoff_location,guests,total_amount,notes) VALUES(?,?,?,?, 'mobile',?,?,?,?,?,?,?)");
 $stmt->execute([$code,$customerId,$input['booking_type'],(int)($input['item_id']??0)?:null,$input['start_date']??null,$input['end_date']??null,$input['pickup_location']??null,$input['dropoff_location']??null,(int)($input['guests']??1),(float)($input['total_amount']??0),$input['notes']??null]);
 $bookingId=(int)db()->lastInsertId(); db()->commit();respond(201,['success'=>true,'message'=>'Reservation created.','data'=>['id'=>$bookingId,'booking_code'=>$code,'status'=>'pending','source'=>'mobile']]);
}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();respond(500,['success'=>false,'message'=>'Booking could not be created.']);}
