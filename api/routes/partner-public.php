<?php
// A projection of the existing profile, never a second collaborator record.
if($method!=='GET'||!preg_match('#^collaborators/([a-f0-9-]{36})(?:/media/([a-f0-9-]{36}))?$#',$path,$match))return;
$uid=$match[1];$s=db()->prepare("SELECT profile_json FROM partner_profiles WHERE user_id=? AND status='approved'");$s->execute([$uid]);$raw=$s->fetchColumn();
if(!$raw||!partner_operational($uid))fail(404,'NOT_FOUND','The public profile is not available.');
$profile=decoded($raw);$draft=$profile['onboarding']??[];$public=partner_safe_public_profile($draft);
$ids=array_values(array_filter([$draft['professionalPhotoUploadId']??null,$draft['coverUploadId']??null,...($draft['galleryUploadIds']??[])]));
if(isset($match[2])){
    if(!in_array($match[2],$ids,true))fail(404,'NOT_FOUND','The public image is not available.');
    $s=db()->prepare("SELECT path,content_type FROM uploads WHERE id=? AND user_id=? AND purpose IN ('professional-photo','professional-cover','professional-gallery')");$s->execute([$match[2],$uid]);$image=$s->fetch();$file=$image?stored_asset_file($image['path']):null;
    if(!$file||!in_array($image['content_type'],['image/jpeg','image/png','image/webp'],true))fail(404,'NOT_FOUND','Image not available.');
    header('Content-Type: '.$image['content_type']);header('X-Content-Type-Options: nosniff');header('Cache-Control: public, max-age=60');readfile($file);exit;
}
unset($public['professionalPhotoUploadId'],$public['coverUploadId'],$public['galleryUploadIds']);
$base=rtrim($config['api_public_base_url'],'/').'/collaborators/'.$uid.'/media/';
$public['photoUrl']=empty($draft['professionalPhotoUploadId'])?null:$base.$draft['professionalPhotoUploadId'];
$public['coverUrl']=empty($draft['coverUploadId'])?null:$base.$draft['coverUploadId'];$public['galleryUrls']=array_map(fn($id)=>$base.$id,$draft['galleryUploadIds']??[]);
out($public);
