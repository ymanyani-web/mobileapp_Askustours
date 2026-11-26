<?php
require dirname(__DIR__).'/includes/deletion_permissions.php';
$checks=0;
function expect($actual,$wanted){global $checks;if($actual!==$wanted)throw new RuntimeException('Permission policy failed.');$checks++;}
foreach(['delete','restore','archive','access'] as $cap){
 expect(deletion_grant_allowed(['role'=>'admin','active'=>1],[],$cap),true);
 expect(deletion_grant_allowed(['role'=>'admin','active'=>0],[],$cap),false);
 expect(deletion_grant_allowed(['role'=>'reviewer','active'=>1],['role'=>'operator','grants'=>['delete','restore','archive']],$cap),false);
 expect(deletion_grant_allowed(['role'=>'operator','active'=>1],['role'=>'operator','grants'=>[]],$cap),false);
 expect(deletion_grant_allowed(['role'=>'operator','active'=>1],['role'=>'operator','grants'=>['delete','restore','archive']],$cap),true);
}
expect(deletion_grant_allowed(['role'=>'operator','active'=>1],['role'=>'operator','grants'=>['archive']],'delete'),false);
expect(deletion_grant_allowed(['role'=>'operator','active'=>1],['role'=>'operator','grants'=>['delete']],'restore'),false);
expect(deletion_grant_allowed(['role'=>'operator','active'=>1],['role'=>'reviewer','grants'=>['delete']],'delete'),false);
echo "Deletion permission policy: $checks assertions passed.\n";
