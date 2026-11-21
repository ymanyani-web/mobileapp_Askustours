<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {http_response_code(404);exit;}
// Pure contract checks; no production bootstrap, credentials or database access.
function public_asset_available(?string $path): bool {return $path !== '' && $path !== 'missing';}
function public_content_media_url(string $type,int $id,?string $path): string {return 'https://admin.example/media.php?type='.$type.'&id='.$id;}
require dirname(__DIR__).'/includes/home_content.php';
function check(bool $condition,string $message): void {if(!$condition)throw new RuntimeException($message);}
$cards = [
 ['id'=>'first','title'=>['es'=>'Uno'],'imageUrl'=>'one','displayOrder'=>20],
 ['id'=>'hidden','imageUrl'=>'two','displayOrder'=>0,'_active'=>false],
 ['id'=>'missing','imageUrl'=>'missing','displayOrder'=>0],
 ['id'=>'second','title'=>['es'=>'Dos'],'imageUrl'=>'two','displayOrder'=>10,'_imagePath'=>'uploads/two.webp','_mediaId'=>123],
 ['id'=>'tie','imageUrl'=>'three','displayOrder'=>10],
];
$public = home_public_cards('tripIdeas',$cards);
check(array_column($public,'id') === ['second','tie','first'],'Visible cards must preserve editorial order and stable ties.');
check($public[0]['imageUrl'] === 'https://admin.example/media.php?type=home_tripIdeas&id=123','Media route must target its own section.');
check(!array_key_exists('_imagePath',$public[0]) && !array_key_exists('_mediaId',$public[0]),'Private editorial metadata must not leak.');
check(array_column(home_public_cards('destinations',[['id'=>'a','imageUrl'=>'a'],['id'=>'b','imageUrl'=>'b']]),'id') === ['a','b'],'Legacy array order must be retained.');
$input=['title_es'=>'Familia','title_ar'=>'عائلة','description_es'=>'Viaja a tu ritmo','displayOrder'=>'2','prefillStyle'=>'family','active'=>'on'];
$card=home_validate_card($input,['id'=>'stable','coordinates'=>['latitude'=>1]],'tripIdeas');
check($card['id']==='stable' && $card['coordinates']['latitude']===1,'Edits must retain identity and unrelated properties.');
check($card['title']['ar']==='عائلة' && $card['action']==='designTrip','Localized titles and trip intent must be retained.');
foreach ([['displayOrder'=>'-1'],['displayOrder'=>'1.5'],['title_es'=>''],['prefillStyle'=>'unknown']] as $bad) {
 $rejected=false;try{home_validate_card([...$input,...$bad],[],'tripIdeas');}catch(RuntimeException){$rejected=true;}
 check($rejected,'Invalid editorial data must be rejected.');
}
check(home_validate_card($input,[],'experiences')['action']==='placeholder','Editorial experiences must not promise bookings.');
echo "Home content contract checks passed.\n";
