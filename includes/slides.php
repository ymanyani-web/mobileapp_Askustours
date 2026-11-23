<?php
declare(strict_types=1);

function slide_destinations(): array {
    return ['none'=>'Sin botón', 'drivers'=>'Chóferes · Reserva ahora', 'guides'=>'Guías · Ver guías', 'designTrip'=>'Crea tu viaje · Empezar'];
}

function slide_validate(array $input, array $previous): array {
    $details = $previous;
    foreach (['title'=>160,'description'=>4000] as $field=>$max) {
        foreach (['es','en','ar'] as $locale) {
            $text=trim((string)($input[$field.'_'.$locale] ?? ''));
            if (mb_strlen($text)>$max) throw new RuntimeException('El texto supera el tamaño permitido.');
            $details[$field][$locale]=$text;
        }
    }
    if ($details['title']['es']==='') throw new RuntimeException('Escribe el título en español.');
    $destination=(string)($input['destination']??'none');
    if (!array_key_exists($destination,slide_destinations())) throw new RuntimeException('Elige un apartado válido.');
    $details['destination']=$destination;
    return $details;
}

function slide_details(array $row, array $home, string $key='_bannerDetails'): array {
    return $home[$key][(string)$row['id']] ?? [
        'title'=>array_fill_keys(['es','en','ar'],(string)$row['title']),
        'description'=>array_fill_keys(['es','en','ar'],(string)($row['subtitle']??'')),
        'destination'=>'none',
    ];
}

function slide_revision(array $row, array $details): string {
    return hash('sha256',json_encode([$row,$details],JSON_THROW_ON_ERROR));
}

function original_slides(): array {
    return json_decode(file_get_contents(__DIR__.'/original-slides.json'),true,512,JSON_THROW_ON_ERROR);
}

// The source images are versioned recovery assets. Copy them into persistent
// runtime storage before publishing, just like normal administrator uploads.
function restore_original_slides(array &$home, string $type='banners'): void {
    $table=$type==='offers'?'home_offers':'banners';
    $metadataKey=$type==='offers'?'_offerDetails':'_bannerDetails';
    $dir=upload_storage_root();
    if (!is_dir($dir) && !mkdir($dir,0750,true) && !is_dir($dir)) throw new RuntimeException('No se pudo preparar el almacenamiento.');
    $ids=[];
    foreach (original_slides() as $order=>$preset) {
        $id=null;
        foreach ($home[$metadataKey]??[] as $key=>$details) {
            if (($details['_originalKey']??'')===$preset['key']) {
                $s=db()->prepare("SELECT id FROM `$table` WHERE id=?");$s->execute([$key]);
                if ($s->fetchColumn()!==false) {$id=(int)$key;break;}
            }
        }
        // Repeated restoration never overwrites later editorial changes.
        if ($id!==null) {
            db()->prepare("UPDATE `$table` SET active=1 WHERE id=?")->execute([$id]);
            $ids[]=$id;continue;
        }
        $source=dirname(__DIR__).'/assets/original-slides/'.basename($preset['image']);
        if (!is_file($source)) throw new RuntimeException('Falta una imagen original. No se ha restaurado el carrusel.');
        $ext=pathinfo($source,PATHINFO_EXTENSION);
        $name='original-'.bin2hex(random_bytes(12)).'.'.$ext;
        if (!copy($source,$dir.'/'.$name)) throw new RuntimeException('No se pudo recuperar la imagen.');
        $s=db()->prepare("INSERT INTO `$table`(title,subtitle,image_path,link_url,sort_order,active) VALUES(?,?,?,?,?,1)");
        $s->execute([$preset['title']['es'],mb_substr($preset['description']['es'],0,255),'uploads/'.$name,'',$order]);
        $id=(int)db()->lastInsertId();
        unset($preset['image'],$preset['key']);
        $preset['_originalKey']=original_slides()[$order]['key'];
        $home[$metadataKey][(string)$id]=$preset;
        $ids[]=$id;
    }
    // Keep earlier slides recoverable through the editor; never delete them.
    $marks=implode(',',array_fill(0,count($ids),'?'));
    db()->prepare("UPDATE `$table` SET active=0 WHERE id NOT IN ($marks)")->execute($ids);
}
