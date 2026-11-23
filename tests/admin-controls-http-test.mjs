import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
const base='http://127.0.0.1:8092';
const fixture=JSON.parse(await fs.readFile(new URL('../../.tools/admin-controls-fixture.json',import.meta.url)));
let cookie;let checks=0;
const check=(v,e)=>{assert.deepEqual(v,e);checks++};
async function req(path,options={}){const r=await fetch(base+path,{...options,redirect:'manual',headers:{...(cookie?{Cookie:cookie}:{}),...options.headers}});const c=r.headers.getSetCookie().find(v=>v.startsWith('PHPSESSID='));if(c)cookie=c.split(';')[0];return r;}
async function page(path){const r=await req(path);check(r.status,200);return r.text();}
function field(html,name){const v=html.match(new RegExp(`name="${name}" value="([^"]*)"`))?.[1];assert.notEqual(v,undefined);return v;}
const detail=(who)=>'/users.php?view='+ (who==='partner'?'collaborators':'customers')+'&id='+fixture[who];
check((await req(detail('partner')+'&photo=professional')).status,302);
const login=await page('/login.php');check((await req('/login.php',{method:'POST',body:new URLSearchParams({_csrf:field(login,'_csrf'),email:'admin@askustours.com',password:'password'})})).status,302);
const dashboard=await page('/dashboard.php');check(dashboard.includes('Estado de la actividad'),true);check(dashboard.includes('Portada y slides'),true);
for(const path of ['/users.php','/users.php?view=collaborators','/reservations.php?status=pending',detail('customer')])await page(path);
let html=await page(detail('partner'));check(html.includes('100,00 EUR'),true);check(html.includes('50,00 USD'),true);check(html.includes('20,00 EUR'),true);check(html.includes('Historial de servicios'),true);check(html.includes('Servicios ofrecidos'),true);check(html.includes('Valoraciones de sus servicios'),true);
check((await req(detail('partner')+'&photo=professional')).headers.get('cache-control'),'private, no-store');
async function decide(who,action,status,reason='Motivo sintético',old){const h=old??await page(detail(who));const r=await req(detail(who),{method:'POST',body:new URLSearchParams({_csrf:field(h,'_csrf'),user_id:fixture[who],revision:field(h,'revision'),action,status,reason})});check(r.status,302);return page(detail(who));}
html=await decide('partner','partner','approved');check(html.includes('Decisión guardada.'),true);
const jobs=await req('/api/v1/me/partner-jobs',{headers:{Authorization:'Bearer fixture-partner-access'}});check(jobs.status,200);const job=(await jobs.json()).data.items[0];
const accepted=await req('/api/v1/me/partner-jobs/'+job.id,{method:'PATCH',headers:{Authorization:'Bearer fixture-partner-access','Content-Type':'application/json'},body:JSON.stringify({status:'accepted',expectedVersion:1,policyVersion:1})});check(accepted.status,200);check((await accepted.json()).data.status,'accepted');
const stale=html;html=await decide('partner','account','blocked','Incidencia de prueba <script>');check(html.includes('Bloqueada'),true);check(html.includes('&lt;script&gt;'),true);
check((await req('/api/v1/me',{headers:{Authorization:'Bearer fixture-partner-access'}})).status,401);
check((await req('/api/v1/auth/session-refresh',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({refreshToken:'fixture-partner-access-refresh'})})).status,401);
html=await decide('partner','account','active','Revisión',stale);check(html.includes('Otro administrador'),true);check(html.includes('Bloqueada'),true);
html=await decide('partner','account','active');check(html.includes('Decisión guardada.'),true);
const catalog=await req('/api/v1/driver-services');check((await catalog.json()).data.items.length,0);
html=await decide('partner','partner','suspended');check(html.includes('Suspendido'),true);
html=await decide('partner','partner','rejected','');check(html.includes('Indica el motivo'),true);
html=await decide('customer','account','inactive');check(html.includes('Desactivada'),true);check((await req('/api/v1/me',{headers:{Authorization:'Bearer fixture-customer-access'}})).status,401);
const bad=await req(detail('customer'),{method:'POST',body:new URLSearchParams({_csrf:'bad',user_id:fixture.customer,action:'account',status:'active'})});check(bad.status,419);
check((await req('/people.php?view=collaborators')).headers.get('location'),'users.php?view=collaborators');check((await req('/bookings.php')).headers.get('location'),'reservations.php');
const deniedLogin=await req('/api/v1/auth/sessions',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({challengeId:'94000000-0000-4000-8000-000000000001',code:'246810'})});check(deniedLogin.status,403);check((await deniedLogin.json()).error.code,'ACCOUNT_RESTRICTED');
await req('/logout.php');const operatorLogin=await page('/login.php');check((await req('/login.php',{method:'POST',body:new URLSearchParams({_csrf:field(operatorLogin,'_csrf'),email:'operator@example.test',password:'password'})})).status,302);
const operatorDetail=await page(detail('customer'));check(operatorDetail.includes('Guardar estado de cuenta'),false);
const operatorCatalog=await page('/catalog.php?view=drivers');
const forbidden=await req(detail('customer'),{method:'POST',body:new URLSearchParams({_csrf:field(operatorCatalog,'_csrf'),user_id:fixture.customer,revision:'invalid',action:'account',status:'active'})});check(forbidden.status,302);check((await page(detail('customer'))).includes('Solo un administrador'),true);
console.log(`Isolated account-management checks passed: ${checks} assertions.`);
