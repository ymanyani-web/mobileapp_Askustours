import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
const base='http://127.0.0.1:8095';
const fixture=JSON.parse(await fs.readFile(new URL('../../.tools/partner-registration-fixture.json',import.meta.url)));
let checks=0;const check=(a,b)=>{assert.deepEqual(a,b);checks++};
const field=(html,name)=>{const v=html.match(new RegExp(`name="${name}" value="([^"]*)"`))?.[1];assert.notEqual(v,undefined);return v;};
function client(){let cookie;return async(path,options={})=>{const r=await fetch(base+path,{...options,redirect:'manual',headers:{...(cookie?{Cookie:cookie}:{}),...options.headers}});const current=r.headers.getSetCookie().find(x=>x.startsWith('PHPSESSID='));if(current)cookie=current.split(';')[0];return r;};}
const owner=client(),worker=client();
async function login(client,email){const html=await(await client('/login.php')).text();check((await client('/login.php',{method:'POST',body:new URLSearchParams({_csrf:field(html,'_csrf'),email,password:'password'})})).status,302);}
await login(owner,'admin@askustours.com');await login(worker,'operator@example.test');
check((await worker('/deletions.php')).status,403);
let workerList=await(await worker('/users.php?view=customers')).text();check(workerList.includes('data-delete-user='),false);
const staffHtml=await(await owner('/staff.php')).text();const operatorId=staffHtml.match(/operator@example\.test<\/small><\/td>.*?staff\.php\?edit=(\d+)/s)?.[1];assert.ok(operatorId);
async function grant(grants){const html=await(await owner('/staff.php?edit='+operatorId)).text();const body=new URLSearchParams({_csrf:field(html,'_csrf'),id:operatorId,revision:field(html,'revision'),name:'Synthetic operator',email:'operator@example.test',role:'operator',active:'1',password:''});for(const g of grants)body.append('deletion_grants[]',g);check((await owner('/staff.php',{method:'POST',body})).status,302);}
await grant(['delete','restore','archive']);
workerList=await(await worker('/users.php?view=customers')).text();check(workerList.includes('data-delete-user="'+fixture.users.customer+'"'),true);check(workerList.includes('user-deletion-dialog'),true);
check((await worker('/staff.php')).status,403);
let previewResponse=await worker('/deletions.php?type=users&format=json&id='+fixture.users.customer);check(previewResponse.status,200);let preview=await previewResponse.json();check(preview.id,fixture.users.customer);check(preview.counts.users,1);
const csrf=field(workerList,'_csrf');
const deleteBody=new URLSearchParams({_csrf:csrf,action:'delete',user_id:fixture.users.customer,revision:preview.revision,reason:'Synthetic deletion test'});
check((await worker('/users.php?view=customers',{method:'POST',body:deleteBody})).status,302);
check((await worker('/deletions.php?type=users&format=json&id='+fixture.users.customer)).status,200);
deleteBody.set('confirmation','1');deleteBody.set('revision','stale');check((await worker('/users.php?view=customers',{method:'POST',body:deleteBody})).status,302);check((await worker('/deletions.php?type=users&format=json&id='+fixture.users.customer)).status,200);
deleteBody.set('revision',preview.revision);check((await worker('/users.php?view=customers',{method:'POST',body:deleteBody})).status,302);check((await worker('/deletions.php?type=users&format=json&id='+fixture.users.customer)).status,409);
let history=await(await worker('/deletions.php')).text();let backup=history.match(/Recuperar copia #(\d+)/)?.[1];assert.ok(backup);
const archive=new URLSearchParams({_csrf:field(history,'_csrf'),action:'archive',revision:field(history,'revision'),confirmation:'1'});
check((await worker('/deletions.php',{method:'POST',body:archive})).status,302);
let clean=await(await worker('/deletions.php')).text();check(clean.includes('Recuperar copia #'),false);
let archived=await(await worker('/deletions.php?archived=1')).text();check(archived.includes('Recuperar copia #'+backup),true);
check((await worker('/deletions.php',{method:'POST',body:new URLSearchParams({_csrf:field(archived,'_csrf'),action:'restore',backup_id:backup})})).status,302);
check((await worker('/deletions.php?type=users&format=json&id='+fixture.users.customer)).status,200);
await grant([]);check((await worker('/deletions.php')).status,403);check((await worker('/users.php?view=customers',{method:'POST',body:deleteBody})).status,403);check((await worker('/deletions.php',{method:'POST',body:archive})).status,403);
console.log(`List deletion, history and delegated access: ${checks} assertions passed.`);
