<?php
declare(strict_types=1);

function deletion_types():array{return [
 'users'=>['users','id','Clientes y colaboradores'], 'drivers'=>['driver_services','id','Servicios de chófer'],
 'guides'=>['guide_services','id','Servicios de guía'], 'packages'=>['travel_packages','id','Paquetes'],
 'reservations'=>['reservations','id','Solicitudes de servicio'], 'quotes'=>['quotes','id','Presupuestos'],
 'trips'=>['trip_requests','id','Solicitudes de viaje'], 'drafts'=>['partner_service_drafts','id','Borradores de colaboradores'],
 'documents'=>['content_documents','slug','Documentos de la app'], 'banners'=>['banners','id','Slides principales'],
 'offers'=>['home_offers','id','Ofertas destacadas'],
 'home_tripIdeas'=>['virtual','id','Crea tu viaje'], 'home_destinations'=>['virtual','id','Destinos populares'], 'home_experiences'=>['virtual','id','Experiencias para ti'],
];}
function deletion_tables():array{return ['users','driver_services','guide_services','travel_packages','reservations','quotes','trip_requests','trip_drafts','partner_profiles','partner_service_drafts','wallet_ledger','favorites','basket_items','uploads','idempotency_keys','service_calendar','reviews','content_documents','banners','home_offers'];}
function deletion_table_label(string $table):string{return [
 'users'=>'Cuentas','driver_services'=>'Servicios de chófer','guide_services'=>'Servicios de guía','travel_packages'=>'Paquetes','reservations'=>'Reservas y solicitudes','quotes'=>'Presupuestos','trip_requests'=>'Solicitudes de viaje','trip_drafts'=>'Borradores de viaje','partner_profiles'=>'Perfil profesional','partner_service_drafts'=>'Borradores de servicios','wallet_ledger'=>'Movimientos de billetera','favorites'=>'Favoritos','basket_items'=>'Elementos de cesta','uploads'=>'Referencias a fotos y documentos','idempotency_keys'=>'Confirmaciones de operaciones','service_calendar'=>'Fechas de disponibilidad','reviews'=>'Valoraciones','content_documents'=>'Documentos de la app','banners'=>'Slides principales','home_offers'=>'Ofertas destacadas'
][$table]??'Datos asociados';}
function deletion_title(array $plan):string{
 if($plan['virtual'])return (string)($plan['virtual']['card']['title']['es']??'Tarjeta de portada');
 $table=deletion_types()[$plan['type']][0];$row=reset($plan['rows'][$table]);
 if(isset($row['first_name']))return trim($row['first_name'].' '.$row['last_name']);
 if(isset($row['title']))return (string)$row['title'];
 if(isset($row['title_json'])){$title=json_decode($row['title_json'],true);if(!is_array($title))$title=[];return (string)($title['es']??reset($title)?:$plan['id']);}
 return $plan['id'];
}
function deletion_keys(string $table):array{
 if(!in_array($table,deletion_tables(),true))throw new RuntimeException('Tabla no permitida.');
 static $cache=[];if(!isset($cache[$table])){$keys=db()->query("SHOW KEYS FROM `$table` WHERE Key_name='PRIMARY'")->fetchAll();usort($keys,fn($a,$b)=>(int)$a['Seq_in_index']<=>(int)$b['Seq_in_index']);$cache[$table]=array_column($keys,'Column_name');}return $cache[$table];
}
function deletion_collect(array &$plan,string $table,string $where,array $params,bool $lock):void{
 deletion_keys($table);$s=db()->prepare("SELECT * FROM `$table` WHERE $where".($lock?' FOR UPDATE':''));$s->execute($params);
 foreach($s->fetchAll() as $row){$key=json_encode(array_intersect_key($row,array_flip(deletion_keys($table))),JSON_THROW_ON_ERROR);$plan['rows'][$table][$key]=$row;}
}
function deletion_in(array &$plan,string $table,string $field,array $ids,bool $lock):void{
 if(!$ids)return;deletion_collect($plan,$table,'`'.$field.'` IN ('.implode(',',array_fill(0,count($ids),'?')).')',array_values($ids),$lock);
}
function deletion_snapshot(string $type,string $id,bool $lock=false):array{
 $definition=deletion_types()[$type]??null;if(!$definition)throw new RuntimeException('Tipo no permitido.');
 $plan=['type'=>$type,'id'=>$id,'rows'=>[],'virtual'=>null,'metadata'=>null];
 if($definition[0]==='virtual'){
  $s=db()->query('SELECT config_json FROM home_config WHERE id=1'.($lock?' FOR UPDATE':''));$home=json_decode($s->fetchColumn()?:'{}',true,512,JSON_THROW_ON_ERROR);$section=substr($type,5);
  foreach(($home[$section]??[]) as $index=>$card)if((string)($card['id']??'')===$id){$plan['virtual']=['section'=>$section,'card'=>$card,'index'=>$index];break;}
  if(!$plan['virtual'])throw new RuntimeException('El contenido ya no existe.');return $plan;
 }
 [$table,$key]=$definition;deletion_collect($plan,$table,"`$key`=?",[$id],$lock);
 if(empty($plan['rows'][$table]))throw new RuntimeException('El registro ya no existe.');
 if($type==='users'){
  foreach(['driver_services','guide_services'] as $serviceTable)deletion_collect($plan,$serviceTable,'owner_user_id=?',[$id],$lock);
  deletion_collect($plan,'reservations','customer_user_id=? OR partner_user_id=?',[$id,$id],$lock);
  deletion_collect($plan,'quotes','user_id=?',[$id],$lock);
  foreach(['trip_requests','trip_drafts','partner_profiles','partner_service_drafts','wallet_ledger','favorites','basket_items','uploads','idempotency_keys'] as $child)deletion_collect($plan,$child,'user_id=?',[$id],$lock);
 }
 $quoteIds=array_column(array_values($plan['rows']['quotes']??[]),'id');deletion_in($plan,'reservations','quote_id',$quoteIds,$lock);
 foreach(($plan['rows']['reservations']??[]) as $reservation){
  if(in_array($reservation['payment_status'],['paid','refunded'],true))throw new RuntimeException('Este registro tiene pagos registrados. Conserva su historial y utiliza cancelar o desactivar.');
  deletion_collect($plan,'idempotency_keys',"user_id=? AND operation='reservation-request' AND key_value=?",[$reservation['customer_user_id'],$reservation['idempotency_key']],$lock);
 }
 foreach(['driver_services'=>'driver','guide_services'=>'guide'] as $serviceTable=>$serviceType){
  $ids=array_column(array_values($plan['rows'][$serviceTable]??[]),'id');
  if($ids){foreach(['service_calendar','reviews'] as $child)deletion_collect($plan,$child,"service_type=? AND service_id IN (".implode(',',array_fill(0,count($ids),'?')).')',[$serviceType,...$ids],$lock);if($serviceType==='driver')deletion_in($plan,'favorites','driver_service_id',$ids,$lock);}
 }
 if(isset($plan['rows']['wallet_ledger']))foreach($plan['rows']['wallet_ledger'] as $entry)if($entry['status']==='paid')throw new RuntimeException('Hay liquidaciones registradas. Desactiva la cuenta para conservar el historial.');
 if(in_array($type,['banners','offers'],true)){
  $home=json_decode(db()->query('SELECT config_json FROM home_config WHERE id=1'.($lock?' FOR UPDATE':''))->fetchColumn()?:'{}',true,512,JSON_THROW_ON_ERROR);$metaKey=$type==='banners'?'_bannerDetails':'_offerDetails';
  $plan['metadata']=['key'=>$metaKey,'value'=>$home[$metaKey][$id]??null];
 }
 foreach($plan['rows'] as &$rows)ksort($rows);unset($rows);ksort($plan['rows']);
 return $plan;
}
function deletion_revision(array $plan):string{return hash('sha256',json_encode($plan,JSON_THROW_ON_ERROR));}
function deletion_mutate_home(array $plan,bool $restore):void{
 if(!$plan['virtual']&&!$plan['metadata'])return;
 $home=json_decode(db()->query('SELECT config_json FROM home_config WHERE id=1 FOR UPDATE')->fetchColumn()?:'{}',true,512,JSON_THROW_ON_ERROR);
 if($virtual=$plan['virtual']){
  $cards=$home[$virtual['section']]??[];
  if($restore){foreach($cards as $card)if(($card['id']??'')===$plan['id'])throw new RuntimeException('El identificador de este contenido está ocupado.');array_splice($cards,min((int)$virtual['index'],count($cards)),0,[$virtual['card']]);}
  else $cards=array_values(array_filter($cards,fn($card)=>(string)($card['id']??'')!==$plan['id']));
  $home[$virtual['section']]=$cards;
 }
 if($meta=$plan['metadata']){if($restore&&$meta['value']!==null)$home[$meta['key']][$plan['id']]=$meta['value'];else unset($home[$meta['key']][$plan['id']]);}
 db()->prepare('UPDATE home_config SET config_json=? WHERE id=1')->execute([json_encode($home,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
}
function record_managed_backup(string $type,string $id,string $reason,?array $plan=null):int{
 require_owner();if(!db()->inTransaction())throw new RuntimeException('La copia requiere una transacción activa.');
 $plan??=deletion_snapshot($type,$id,true);$encoded=json_encode(['reason'=>$reason,'backup'=>$plan],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
 if(strlen($encoded)>2000000)throw new RuntimeException('El respaldo supera el tamaño de esta herramienta. Exporta y revisa este caso antes de eliminarlo.');
 db()->prepare("INSERT INTO audit_log(admin_id,action,entity_type,entity_id,details_json) VALUES(?,'entity.deleted',?,?,?)")->execute([admin()['id'],$type,$id,$encoded]);return (int)db()->lastInsertId();
}
function delete_managed(string $type,string $id,string $revision,string $reason):int{
 require_owner();if(mb_strlen($reason)<5||mb_strlen($reason)>500)throw new RuntimeException('Indica un motivo de entre 5 y 500 caracteres.');
 db()->beginTransaction();
 try{
  lock_owner();$plan=deletion_snapshot($type,$id,true);
  if(!hash_equals(deletion_revision($plan),$revision))throw new RuntimeException('Los datos han cambiado. Revisa el resumen actualizado antes de eliminar.');
  // Immutable, admin-only database recovery record; no files, passwords or session tokens are backed up.
  $backupId=record_managed_backup($type,$id,$reason,$plan);
  $order=['idempotency_keys','favorites','basket_items','trip_drafts','trip_requests','partner_service_drafts','partner_profiles','wallet_ledger','uploads','reviews','service_calendar','reservations','quotes','driver_services','guide_services','travel_packages','content_documents','banners','home_offers','users'];
  foreach($order as $table)foreach(($plan['rows'][$table]??[]) as $row){$keys=deletion_keys($table);$where=implode(' AND ',array_map(fn($key)=>"`$key`=?",$keys));db()->prepare("DELETE FROM `$table` WHERE $where")->execute(array_map(fn($key)=>$row[$key],$keys));}
  deletion_mutate_home($plan,false);db()->commit();return $backupId;
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
function restore_managed(int $backupId):void{
 require_owner();db()->beginTransaction();
 try{
  lock_owner();$s=db()->prepare("SELECT * FROM audit_log WHERE id=? AND action='entity.deleted' FOR UPDATE");$s->execute([$backupId]);$event=$s->fetch();if(!$event)throw new RuntimeException('Copia no encontrada.');
  $s=db()->prepare("SELECT 1 FROM audit_log WHERE action='entity.restored' AND entity_type='deletion' AND entity_id=?");$s->execute([(string)$backupId]);if($s->fetchColumn())throw new RuntimeException('Esta copia ya fue recuperada.');
  $plan=json_decode($event['details_json'],true,512,JSON_THROW_ON_ERROR)['backup'];
  $order=['users','driver_services','guide_services','travel_packages','content_documents','banners','home_offers','quotes','reservations','service_calendar','reviews','uploads','partner_profiles','partner_service_drafts','wallet_ledger','trip_requests','trip_drafts','basket_items','favorites','idempotency_keys'];
  foreach($order as $table)foreach(($plan['rows'][$table]??[]) as $row){deletion_keys($table);$columns=array_keys($row);$sql="INSERT INTO `$table` (`".implode('`,`',$columns).'`) VALUES('.implode(',',array_fill(0,count($columns),'?')).')';db()->prepare($sql)->execute(array_values($row));}
  deletion_mutate_home($plan,true);
  db()->prepare("INSERT INTO audit_log(admin_id,action,entity_type,entity_id,details_json) VALUES(?,'entity.restored','deletion',?,'{}')")->execute([admin()['id'],(string)$backupId]);
  db()->commit();
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
