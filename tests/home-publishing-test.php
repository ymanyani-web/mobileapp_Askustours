<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
function public_asset_available(?string $path):bool{return $path!==null&&$path!=='missing';}
function public_asset_url(?string $path):?string{return $path;}
class FixtureStatement {
    public string $type='';
    function execute(array $values):void{$this->type=$values[0];}
    function fetchAll():array {
        $service=fn($id,$score,$demo=false,$photo='https://example.test/photo.jpg')=>['id'=>$id,'city'=>$id==='high'?'barcelona':'madrid','score'=>$score,'service_json'=>json_encode(['title'=>['es'=>$id],'description'=>['es'=>'Texto'],'demo'=>$demo,'photo'=>$photo])];
        return $this->type==='driver'?[$service('low',1),$service('high',4),$service('demo',100,true),$service('zero',0),$service('missing',20,false,'missing')]:[$service('guide',2)];
    }
}
class FixtureDatabase {function prepare(string $query):FixtureStatement {
    if(!str_contains($query,"s.status='published'")||!str_contains($query,"('accepted','completed')")||!str_contains($query,'90 DAY'))throw new RuntimeException('Ranking must use published services and recent accepted/completed reservations.');
    return new FixtureStatement();
}}
function db():FixtureDatabase{return new FixtureDatabase();}
require dirname(__DIR__).'/includes/home_publishing.php';
function check(bool $test):void{if(!$test)throw new RuntimeException('Mixed publishing contract failed.');}
$home=['offers'=>[['id'=>'editorial']],'experiences'=>[],'destinations'=>[['cityCode'=>'madrid'],['cityCode'=>'barcelona']]];
check(home_mix($home)===$home);
$home['_homePublishing']=['mode'=>'mixed'];$mixed=home_mix($home);
check(array_column($mixed['offers'],'id')===['editorial','recommended_driver_high','recommended_guide_guide','recommended_driver_low']);
check(count($mixed['experiences'])===1&&$mixed['experiences'][0]['action']==='guides');
check($mixed['destinations'][0]['cityCode']==='barcelona');
check(!isset($mixed['offers'][1]['score'])&&!isset($mixed['destinations'][0]['_score']));
echo "Mixed publishing contract checks passed.\n";
