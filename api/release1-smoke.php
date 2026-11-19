<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/includes/bootstrap.php';
$requiredTables=['users','auth_challenges','sessions','uploads','banners','home_offers','driver_services','guide_services','travel_packages','service_calendar','quotes','reservations','favorites','basket_items','partner_profiles','partner_service_drafts','wallet_ledger','idempotency_keys','audit_log'];
$requiredRoutes=['home','metadata/catalog','driver-services','guide-services','travel-packages','auth/challenges','auth/sessions','auth/firebase-session','auth/session-refresh','auth/session','quotes','reservation-requests','uploads','partner-terms','me','me/reservations','me/favorites','me/basket','me/partner-profile','me/partner-services','me/partner-jobs','me/wallet','me/partner-wallet'];
$failures=[];
try{$database=(string)db()->query('SELECT DATABASE()')->fetchColumn();$stmt=db()->prepare('SELECT table_name FROM information_schema.tables WHERE table_schema=?');$stmt->execute([$database]);$tables=$stmt->fetchAll(PDO::FETCH_COLUMN);foreach($requiredTables as $table)if(!in_array($table,$tables,true))$failures[]="Missing table: $table";}catch(Throwable $e){$failures[]='Database connection/schema check failed: '.$e->getMessage();}
$source=(file_get_contents(__DIR__.'/index.php')?:'').(file_get_contents(__DIR__.'/routes/me.php')?:'');foreach($requiredRoutes as $route)if(!str_contains($source,$route))$failures[]="Route is not wired: $route";
if($failures){foreach($failures as $failure)fwrite(STDERR,"FAIL: $failure\n");exit(1);}echo "Release 1 smoke checks passed. Database: $database; tables and route wiring present.\n";
