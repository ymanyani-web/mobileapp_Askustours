import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
const base='http://127.0.0.1:8094';
const fixture=JSON.parse(await fs.readFile(new URL('../../.tools/partner-registration-fixture.json',import.meta.url)));
let checks=0,cookie;
const check=(value,expected)=>{assert.deepEqual(value,expected);checks++;};
const token=role=>'fixture-'+role+'-'+fixture.users[role];
async function api(path,{role='partner',method='GET',body,headers={}}={}){
 const response=await fetch(base+'/api/v1'+path,{method,headers:{Authorization:'Bearer '+token(role),...(body?{'Content-Type':'application/json'}:{}),...headers},body:body?JSON.stringify(body):undefined});
 const text=await response.text();let data;try{data=JSON.parse(text);}catch{throw new Error(path+' HTTP '+response.status+' '+text.slice(0,900));}return {status:response.status,...data};
}
async function admin(path,options={}){
 const response=await fetch(base+path,{...options,redirect:'manual',headers:{...(cookie?{Cookie:cookie}:{}),...options.headers}});
 const current=response.headers.getSetCookie().find(v=>v.startsWith('PHPSESSID='));if(current)cookie=current.split(';')[0];return response;
}
function field(html,name){const value=html.match(new RegExp(`name="${name}" value="([^"]*)"`))?.[1];assert.notEqual(value,undefined);return value;}
const config=(await api('/me/partner-registration-config')).data;check(config.categories.length,6);
check((await api('/me/partner-registration-config',{role:'customer'})).status,403);
const home=await api('/home');check('_partnerRegistration' in home.data,false);
const image='iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jWZkAAAAASUVORK5CYII=';
async function upload(purpose,role='partner'){
 const result=await api('/uploads',{role,method:'POST',body:{purpose,fileName:'synthetic.png',contentType:'image/png',dataUri:'data:image/png;base64,'+image}});check(result.status,201);return result.data;
}
const photo=await upload('professional-photo'),identity=await upload('professional-document'),fiscal=await upload('professional-document');
const foreign=await upload('professional-document','customer');
const pdf=await api('/uploads',{method:'POST',body:{purpose:'professional-document',fileName:'synthetic.pdf',contentType:'application/pdf',dataUri:'data:application/pdf;base64,'+Buffer.from('%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF').toString('base64')}});check(pdf.status,201);check((await fetch(pdf.data.url,{headers:{Authorization:'Bearer '+token('partner')}})).headers.get('content-type'),'application/pdf');
check((await api('/uploads',{method:'POST',body:{purpose:'professional-document',fileName:'invalid.pdf',contentType:'application/pdf',dataUri:'data:application/pdf;base64,'+image}})).status,415);
const ownerRead=await fetch(identity.url,{headers:{Authorization:'Bearer '+token('partner')}});check(ownerRead.status,200);check(ownerRead.headers.get('cache-control'),'private, no-store');
check((await fetch(identity.url,{headers:{Authorization:'Bearer '+token('customer')}})).status,404);
check((await fetch(identity.url)).status,401);
const draft={step:0,categories:['driver','guide'],firstName:'Synthetic',lastName:'Partner',phone:'+34600000000',email:'synthetic@example.test',country:'España',languages:['es','ar'],businessType:'self_employed',fiscalName:'Synthetic Partner',businessName:'Synthetic Travel',taxId:'SYNTHETIC-ONLY',fiscalAddress:'Synthetic address',postalCode:'28000',city:'Madrid',province:'Madrid',fiscalCountry:'España',experienceYears:'5',workCities:[{id:'madrid',name:'Madrid'},{id:'es:custom-town',name:'Synthetic town'}],zones:['city','intercity'],publicName:'Synthetic Professional',descriptions:{es:'Perfil de prueba aislada.',ar:'ملف اختبار معزول'},specialties:['History'],professionalPhotoUploadId:photo.id,documents:[{requirementId:'identity',uploadId:identity.id,expiresAt:null},{requirementId:'fiscal',uploadId:fiscal.id,expiresAt:null}]};
let profile=(await api('/me/partner-profile')).data;check(profile.status,'draft');
async function save(value=draft){const result=await api('/me/partner-profile',{method:'PUT',body:{schemaVersion:1,expectedRevision:profile.revision,onboarding:value}});check(result.status,200);profile=result.data;return profile;}
await save();check(profile.onboarding.categories,['driver','guide']);check(profile.savedAt.length>0,true);
check((await api('/me/partner-profile',{method:'PUT',body:{expectedRevision:0,onboarding:draft}})).status,409);
check((await api('/me/partner-profile',{method:'PUT',body:{expectedRevision:profile.revision,status:'approved',onboarding:draft}})).status,422);
check((await api('/me/partner-profile',{method:'PUT',body:{expectedRevision:profile.revision,onboarding:{...draft,documents:[{requirementId:'identity',uploadId:foreign.id}]}}})).status,422);
profile=(await api('/me/partner-profile')).data;check(profile.onboarding.email,'synthetic@example.test');
check((await api('/uploads/'+identity.id,{method:'DELETE'})).status,409);
for(const businessType of ['company','employee','self_employed']){
 await save({...draft,businessType,employerName:'Synthetic Company',employerTaxId:'SYNTHETIC-COMPANY',jobRole:'Driver',representative:'Synthetic Representative',representativeRole:'Director',businessPhone:'+34600000001'});
 check(profile.onboarding.businessType,businessType);
}
const acceptances=Object.fromEntries(Object.entries(config.legalDocuments).map(([key,value])=>[key,{accepted:true,version:value.version}]));
let submitBody={expectedRevision:profile.revision,informationCorrect:true,acceptances};
check((await api('/me/partner-profile/submission',{method:'POST',body:{...submitBody,informationCorrect:false},headers:{'Idempotency-Key':crypto.randomUUID()}})).status,422);
const key=crypto.randomUUID();let submitted=await api('/me/partner-profile/submission',{method:'POST',body:submitBody,headers:{'Idempotency-Key':key}});check(submitted.status,202);profile=submitted.data;check(profile.reviewStatus,'pending_review');
check((await api('/me/partner-profile/submission',{method:'POST',body:submitBody,headers:{'Idempotency-Key':key}})).data.revision,profile.revision);
const login=await (await admin('/login.php')).text();check((await admin('/login.php',{method:'POST',body:new URLSearchParams({_csrf:field(login,'_csrf'),email:'admin@askustours.com',password:'password'})})).status,302);
const detail='/users.php?view=collaborators&id='+fixture.users.partner;
async function decide(action,status,extra={}){
 const html=await (await admin(detail)).text();
 const response=await admin(detail,{method:'POST',body:new URLSearchParams({_csrf:field(html,'_csrf'),user_id:fixture.users.partner,action:'registration',registration_action:action,expectedRevision:String(profile.revision),review_status:status,reason:'Synthetic review reason',...extra})});check(response.status,302);
 profile=(await api('/me/partner-profile')).data;
 const htmlResult=await (await admin(detail)).text();const failure=htmlResult.match(/class="alert error">([^<]+)/)?.[1];if(failure)console.log('Expected/review validation:',action,status,failure);return htmlResult;
}
await decide('profile','approved');check(profile.status,'pending');
await decide('profile','under_review');check(profile.reviewStatus,'under_review');
await decide('document','rejected',{requirementId:'identity'});check(profile.reviewStatus,'changes_requested');check(profile.documents.find(d=>d.requirementId==='identity').status,'rejected');
const replacement=await upload('professional-document');draft.documents[0].uploadId=replacement.id;
await save(draft);check(profile.documents.find(d=>d.requirementId==='identity').status,'attached');
submitBody={expectedRevision:profile.revision,informationCorrect:true,acceptances};submitted=await api('/me/partner-profile/submission',{method:'POST',body:submitBody,headers:{'Idempotency-Key':crypto.randomUUID()}});check(submitted.status,202);profile=submitted.data;
await decide('document','verified',{requirementId:'identity'});await decide('document','verified',{requirementId:'fiscal'});await decide('profile','approved');check(profile.status,'approved');
const publicProfile=await api('/collaborators/'+fixture.users.partner);check(publicProfile.status,200);for(const key of ['phone','email','taxId','documents','fiscalAddress','firstName','lastName'])check(key in publicProfile.data,false);
check((await fetch(base+'/api/v1/collaborators/'+fixture.users.partner+'/media/'+identity.id)).status,404);
check((await fetch(publicProfile.data.photoUrl)).status,200);
const firstVehicle=crypto.randomUUID(),secondVehicle=crypto.randomUUID();
const vehiclePhoto=await upload('vehicle-photo'),registration=await upload('vehicle-document'),insurance=await upload('vehicle-document');
const vehicle={brand:'Synthetic',model:'Vehicle',year:'2025',plate:'SYNTHETIC',color:'Black',passengers:'4',bags:'3',category:'executive',exteriorUploadIds:[vehiclePhoto.id],interiorUploadIds:[vehiclePhoto.id],documents:[{requirementId:'registration',uploadId:registration.id,expiresAt:null},{requirementId:'insurance',uploadId:insurance.id,expiresAt:'2030-01-01'}]};
for(const id of [firstVehicle,secondVehicle]){check((await api('/me/partner-vehicles/'+id,{method:'PUT',body:{expectedRevision:profile.revision,vehicle}})).status,201);profile=(await api('/me/partner-profile')).data;}
check(Object.keys(profile.vehicles).length,2);await decide('vehicle','approved',{vehicleId:firstVehicle});check(profile.vehicles[firstVehicle].status,'approved');check(profile.vehicles[secondVehicle].status,'pending_review');
check((await api('/me/partner-vehicles/'+firstVehicle,{method:'PUT',body:{expectedRevision:profile.revision,vehicle:{...vehicle,status:'approved'}}})).status,422);
await save({...draft,taxId:'SYNTHETIC-CHANGED'});check(profile.status,'pending');check(profile.reviewStatus,'pending_review');
const filtered=await admin('/users.php?view=collaborators&activity=driver&city=madrid&review=submitted');check(filtered.status,200);check((await filtered.text()).includes('Synthetic'),true);
const settingsPath='/users.php?view=collaborators&registration=settings';const settingsHtml=await (await admin(settingsPath)).text();
const settingsBody=new URLSearchParams({_csrf:field(settingsHtml,'_csrf'),action:'registration',registration_action:'settings',configurationVersion:field(settingsHtml,'configurationVersion'),retentionDays:'365',requirementsReviewed:'on',commissionRules:JSON.stringify([{basisPoints:2500},{category:'guide',basisPoints:1500}]),newDocumentId:'synthetic_extra',newDocumentTitle:'Requisito sintético',newDocumentCategories:'guide'});
for(const rule of config.documents){if(rule.required)settingsBody.set('required['+rule.id+']','on');if(rule.expires)settingsBody.set('expires['+rule.id+']','on');settingsBody.set('cities['+rule.id+']','');}
check((await admin(settingsPath,{method:'POST',body:settingsBody})).status,302);
const updatedConfig=await api('/me/partner-registration-config');check(updatedConfig.data.version,config.version+1);check(updatedConfig.data.documents.some(d=>d.id==='synthetic_extra'),true);
check((await api('/partner-terms')).data.commissionBasisPoints,2500);
settingsBody.delete('newDocumentTitle');const staleConfig=await admin(settingsPath,{method:'POST',body:settingsBody});check(staleConfig.status,302);check((await (await admin(settingsPath)).text()).includes('La configuración ha cambiado'),true);
const contentHtml=await (await admin('/catalog.php?view=content')).text();check(contentHtml.includes('Añadir documento legal'),true);
const freshSettings=await (await admin(settingsPath)).text();const percentages=new URLSearchParams(settingsBody);percentages.set('configurationVersion',field(freshSettings,'configurationVersion'));percentages.set('commissionEditor','1');percentages.delete('commissionRules');percentages.set('commissions[0][percent]','25');percentages.set('commissions[1][category]','guide');percentages.set('commissions[1][percent]','18,75');check((await admin(settingsPath,{method:'POST',body:percentages})).status,302);check((await api('/partner-terms?category=guide')).data.commissionBasisPoints,1875);
const legalCreate=await admin('/catalog.php?view=content',{method:'POST',body:new URLSearchParams({_csrf:field(contentHtml,'_csrf'),action:'create_document',slug:'test-partner-added-legal',version:'test-v2',title_es:'Documento sintético',body_es:'Texto solo para pruebas locales.',title_en:'Synthetic document',body_en:'Local tests only.',title_ar:'مستند تجريبي',body_ar:'للاختبارات المحلية فقط.',publish:'on'})});check(legalCreate.status,302);check((await api('/content/test-partner-added-legal')).data.version,'test-v2');
await admin('/logout.php');const operatorLogin=await (await admin('/login.php')).text();check((await admin('/login.php',{method:'POST',body:new URLSearchParams({_csrf:field(operatorLogin,'_csrf'),email:'operator@example.test',password:'password'})})).status,302);check((await admin(settingsPath)).status,403);
check((await admin(detail+'&private_document='+replacement.id)).status,403);
console.log(`Partner registration isolated HTTP checks passed: ${checks} assertions.`);
