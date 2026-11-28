<?php
if(PHP_SAPI!=='cli')exit(2);
require dirname(__DIR__).'/includes/chauffeur_enquiries.php';
$checks=0;function check($v){global $checks;if(!$v)throw new RuntimeException('Check '.($checks+1));$checks++;}
function rejects(callable $f){try{$f();}catch(DomainException){return true;}return false;}
$b=['city'=>'madrid','journey'=>'airport','direction'=>'arrival','pickup'=>'Test airport','destination'=>'Test hotel','departure'=>gmdate('Y-m-d\TH:i:s\Z',time()+3*86400),'passengers'=>2,'bags'=>2,'hours'=>null,'flight'=>'','notes'=>'','vehiclePreference'=>'recommend','returnDeparture'=>null];
check(chauffeur_validate($b)['flight']==='');
foreach([['status','confirmed'],['passengers',0],['bags',21],['departure','bad'],['departure','2030-13-01T10:00:00Z'],['journey','admin'],['pickup',''],['notes',str_repeat('a',1501)],['returnDeparture',$b['departure']],['vehiclePreference','invented']] as [$key,$value])check(rejects(fn()=>chauffeur_validate(array_replace($b,[$key=>$value]))));
foreach(['new','in_review','accepted','confirmed','completed','cancelled'] as $state)check(!chauffeur_transition($state,'confirmed',true));
check(chauffeur_transition('proposal_sent','accepted',true));check(chauffeur_transition('awaiting_acceptance','accepted',true));check(!chauffeur_transition('confirmed','proposal_sent',false));check(!chauffeur_transition('new','confirmed',false));check(chauffeur_transition('accepted','confirmed',false));check(chauffeur_transition('confirmed','completed',false));check(!chauffeur_transition('cancelled','new',false));
check(rejects(fn()=>chauffeur_validate(array_replace($b,['journey'=>'hourly','hours'=>0]))));
check(chauffeur_validate(array_replace($b,['journey'=>'hourly','hours'=>8,'destination'=>'']))['hours']===8);
check(count(chauffeur_states())===8);
echo "$checks chauffeur validation and transition checks passed.\n";
