<?php
// A verified professional identity can start a draft before entering its name.
// The professional submission validator still requires first and last names.
function firebase_registration_errors(string $first,string $last,string $role):array{
    $errors=[];
    if($role!=='partner'&&$first==='')$errors['firstName']=['Required for first-time Firebase registration.'];
    if($role!=='partner'&&$last==='')$errors['lastName']=['Required for first-time Firebase registration.'];
    if(!in_array($role,['customer','partner'],true))$errors['accountRole']=['Expected customer or partner.'];
    return $errors;
}
