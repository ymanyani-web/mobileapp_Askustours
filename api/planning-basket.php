<?php
declare(strict_types=1);
function planning_basket_ready():bool {
    static $ready;
    if($ready!==null)return $ready;
    $column=db()->query("SHOW COLUMNS FROM basket_items LIKE 'item_type'")->fetch();
    return $ready=$column&&str_contains((string)$column['Type'],"'planning'");
}
function planning_basket_plan(mixed $raw):array {
    if(!is_array($raw)||array_is_list($raw)||strlen(encoded($raw))>65536)fail(422,'VALIDATION_ERROR','Invalid planning snapshot.');
    $allowed=explode(',', 'schemaVersion,style,destination,adviseDestination,duration,adults,children,childAges,budget,hotel,transport,pace,language,needs,name,contact,notes,months,interests,completed,destinations,cityStays,routeTransport,dateMode,approximateDays,arrival,departure,infants,childAgeYears,infantAgeMonths,rooms,services,serviceDetails,budgetMode,budgetAmount,budgetCurrency,budgetScope,budgetServices,phone,email,hasWhatsApp,preferredContact,privacyAcknowledged,requestId,packageId,packageVersion,packageName,requestType');
    if(array_diff(array_keys($raw),$allowed))fail(422,'VALIDATION_ERROR','Unknown planning fields.');
    $cities=$raw['destinations']??null;
    if(!is_array($cities)||!array_is_list($cities)||count($cities)<1||count($cities)>30)fail(422,'VALIDATION_ERROR','Choose at least one destination.');
    foreach($cities as $city)if(!is_string($city)||!preg_match('/^[a-z0-9_-]{1,80}$/D',$city))fail(422,'VALIDATION_ERROR','Invalid destination.');
    foreach(['adults'=>[1,20],'children'=>[0,10],'infants'=>[0,10],'approximateDays'=>[2,90]] as $key=>[$min,$max])if(isset($raw[$key])&&(!is_int($raw[$key])||$raw[$key]<$min||$raw[$key]>$max))fail(422,'VALIDATION_ERROR','Invalid traveler count or duration.');
    if(isset($raw['cityStays'])&&!is_array($raw['cityStays']))fail(422,'VALIDATION_ERROR','Invalid accommodation.');
    foreach(($raw['cityStays']??[]) as $city=>$stay){
        if(!in_array($city,$cities,true)||!is_array($stay))fail(422,'VALIDATION_ERROR','Invalid accommodation.');
        foreach(['nights'=>[0,90],'rooms'=>[1,20]] as $key=>[$min,$max])if(isset($stay[$key])&&(!is_int($stay[$key])||$stay[$key]<$min||$stay[$key]>$max))fail(422,'VALIDATION_ERROR','Invalid nights or rooms.');
    }
    // This is an editable planning draft, never a paid booking or approved itinerary.
    $raw['completed']=false;
    return $raw;
}
function save_planning_basket(array $user,string $id,array $body):never {
    if($user['role']!=='customer')fail(403,'FORBIDDEN','Customer account required.');
    if(!planning_basket_ready())fail(503,'PLANNING_NOT_CONFIGURED','Planning basket migration is pending.');
    if(!preg_match('/^[a-zA-Z0-9_-]{1,100}$/D',$id))fail(422,'VALIDATION_ERROR','Invalid basket ID.');
    require_traveler_profile($user,traveler_base_requirements());
    $plan=planning_basket_plan($body['plan']??null);
    $home=decoded(db()->query('SELECT config_json FROM home_config WHERE id=1')->fetchColumn()?:'{}');
    $fee=$home['planningFee']??['amountMinor'=>2000,'currency'=>'EUR'];
    if(!is_int($fee['amountMinor']??null)||$fee['amountMinor']<0||!preg_match('/^[A-Z]{3}$/D',(string)($fee['currency']??'')))fail(409,'PRICING_NOT_CONFIGURED','Planning fee unavailable.');
    $item=['type'=>'planning','plan'=>$plan,'planningFee'=>$fee,'paymentStatus'=>'notCollected'];
    db()->beginTransaction();
    try{
        $lock=db()->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');$lock->execute([$user['id']]);
        $s=db()->prepare('SELECT item_type FROM basket_items WHERE user_id=? AND id=? FOR UPDATE');$s->execute([$user['id'],$id]);$old=$s->fetchColumn();
        if($old!==false&&$old!=='planning')fail(409,'BASKET_ITEM_TYPE_CONFLICT','Basket item type cannot change.');
        $s=db()->prepare('SELECT COUNT(*) FROM basket_items WHERE user_id=?');$s->execute([$user['id']]);
        if($old===false&&(int)$s->fetchColumn()>=100)fail(409,'BASKET_LIMIT_REACHED','Basket limit reached.');
        db()->prepare("INSERT INTO basket_items(id,user_id,item_type,item_json) VALUES(?,?,'planning',?) ON DUPLICATE KEY UPDATE item_json=VALUES(item_json),revision=revision+1")->execute([$id,$user['id'],encoded($item)]);
        $s=db()->prepare('SELECT revision,updated_at FROM basket_items WHERE user_id=? AND id=?');$s->execute([$user['id'],$id]);$row=$s->fetch();
        db()->commit();out(['id'=>$id,'type'=>'planning','item'=>$item,'revision'=>(int)$row['revision'],'updatedAt'=>iso($row['updated_at'])]);
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
