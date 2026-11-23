<?php
declare(strict_types=1);

function home_ranked_services(): array {
    // Aggregate only; no customer, payment or reservation details enter /home.
    $rows=[];
    foreach(['driver'=>'driver_services','guide'=>'guide_services'] as $type=>$table) {
        $s=db()->prepare("SELECT s.id,s.city,s.service_json,COUNT(r.id) score FROM `$table` s LEFT JOIN reservations r ON r.service_type=? AND r.service_id=s.id AND r.status IN ('accepted','completed') AND r.created_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 90 DAY) WHERE s.status='published' GROUP BY s.id,s.city,s.service_json ORDER BY score DESC,s.id ASC");
        $s->execute([$type]);
        foreach($s->fetchAll() as $row) {
            $data=json_decode($row['service_json'],true,512,JSON_THROW_ON_ERROR);
            if(($data['demo']??false) || ($data['isDemo']??false)) continue;
            $photo=$data['imageUrl']??$data['photo']??($data['photos'][0]??null);
            if(is_array($photo))$photo=$photo['url']??$photo['imageUrl']??null;
            if(!is_string($photo)||!public_asset_available($photo)) continue;
            $rows[]=['id'=>$row['id'],'city'=>$row['city'],'type'=>$type,'score'=>(int)$row['score'],'data'=>$data,'photo'=>$photo];
        }
    }
    usort($rows,fn($a,$b)=>($b['score']<=>$a['score'])?:strcmp($a['id'],$b['id']));
    return $rows;
}

function home_mix(array $home): array {
    if(($home['_homePublishing']['mode']??'manual')!=='mixed')return $home;
    $services=home_ranked_services();$cities=[];$automatic=[];$guides=[];
    foreach($services as $service) {
        $cities[$service['city']]=($cities[$service['city']]??0)+$service['score'];
        if($service['score']<1)continue;
        $data=$service['data'];
        $card=['id'=>'recommended_'.$service['type'].'_'.$service['id'],'title'=>$data['title']??[], 'description'=>$data['description']??[], 'imageUrl'=>public_asset_url($service['photo']), 'destination'=>$service['type']==='driver'?'drivers':'guides','opensDrivers'=>$service['type']==='driver','action'=>$service['type']==='driver'?'drivers':'guides','linkUrl'=>null];
        $automatic[]=$card;
        if($service['type']==='guide')$guides[]=$card;
    }
    // Editorial cards retain their order and visibility; ranking adds a bounded
    // group of genuinely reserved services, never demo inventory or zero scores.
    $home['offers']=[...($home['offers']??[]),...array_slice($automatic,0,4)];
    $home['experiences']=[...($home['experiences']??[]),...array_slice($guides,0,4)];
    if(!empty($home['destinations'])) {
        foreach($home['destinations'] as $i=>&$card){$card['_position']=$i;$card['_score']=$cities[$card['cityCode']??'']??0;}unset($card);
        usort($home['destinations'],fn($a,$b)=>($b['_score']<=>$a['_score'])?:($a['_position']<=>$b['_position']));
        foreach($home['destinations'] as &$card)unset($card['_position'],$card['_score']);unset($card);
    }
    return $home;
}

function restore_original_rails(array &$home): void {
    $manifest=json_decode(file_get_contents(__DIR__.'/original-rails.json'),true,512,JSON_THROW_ON_ERROR);
    $dir=upload_storage_root();
    if(!is_dir($dir)&&!mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('No se pudo preparar el almacenamiento.');
    foreach($manifest as $section=>$presets) {
        $cards=$home[$section]??[];
        foreach($presets as $order=>$preset) {
            $found=false;
            foreach($cards as $card)if(($card['_originalKey']??null)===$preset['key']){$found=true;break;}
            if($found)continue;
            $source=dirname(__DIR__).'/assets/original-rails/'.basename($preset['image']);
            if(!is_file($source))throw new RuntimeException('Falta una foto original.');
            $name='rail-'.bin2hex(random_bytes(12)).'.jpg';
            if(!copy($source,$dir.'/'.$name))throw new RuntimeException('No se pudo recuperar la foto.');
            $preset['_originalKey']=$preset['key'];unset($preset['key'],$preset['image']);
            if(isset($preset['_cityCode'])){$preset['cityCode']=$preset['_cityCode'];unset($preset['_cityCode']);}
            $preset['id']='home_'.bin2hex(random_bytes(12));$preset['_mediaId']=random_int(100000000000,999999999999);
            $preset['_imagePath']='uploads/'.$name;$preset['_active']=true;$preset['displayOrder']=$order;
            $cards[]=$preset;
        }
        $home[$section]=$cards;
    }
}
