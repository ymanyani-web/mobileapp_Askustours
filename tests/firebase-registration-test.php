<?php
require_once __DIR__.'/../includes/firebase_registration.php';
$cases=[
 ['', '', 'partner', []],
 ['Sara', '', 'partner', []],
 ['', '', 'customer', ['firstName','lastName']],
 ['Sara', '', 'customer', ['lastName']],
 ['Sara', 'Lopez', 'customer', []],
 ['Sara', 'Lopez', 'admin', ['accountRole']],
];
foreach($cases as [$first,$last,$role,$expected]){
 if(array_keys(firebase_registration_errors($first,$last,$role))!==$expected)throw new RuntimeException('Registration policy failed');
}
echo "6 Firebase registration policy checks passed\n";
