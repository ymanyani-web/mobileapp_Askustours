<?php
// Development router for the isolated HTTP test suite; never a deployment entry.
if (PHP_SAPI !== 'cli-server') {http_response_code(404);exit;}
$path = parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if (preg_match('#(^|/)\.|\.(sql|md)$|^/(tests|includes)/|^/config#',$path)) {http_response_code(403);exit;}
if (preg_match('#^/api/v1(?:/(.*))?$#',$path,$match)) {
 $_GET['route']=$match[1]??'';
 require dirname(__DIR__).'/api/index.php';
 return true;
}
return false;
