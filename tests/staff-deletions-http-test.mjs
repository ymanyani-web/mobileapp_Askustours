import assert from 'node:assert/strict';
const base='http://127.0.0.1:8092';
let cookie, checks=0;
const check=(value,expected)=>{assert.deepEqual(value,expected);checks++};
async function request(path,options={}){
 const response=await fetch(base+path,{redirect:'manual',...options,headers:{...(cookie?{Cookie:cookie}:{}),...options.headers}});
 const session=response.headers.getSetCookie?.().find(v=>v.startsWith('PHPSESSID='));if(session)cookie=session.split(';')[0];return response;
}
async function page(path){const r=await request(path);check(r.status,200);return r.text();}
function field(html,name){const value=html.match(new RegExp(`name="${name}" value="([^"]*)"`))?.[1];assert.notEqual(value,undefined);return value;}
async function login(email,password){cookie=undefined;const html=await page('/login.php');check((await request('/login.php',{method:'POST',body:new URLSearchParams({_csrf:field(html,'_csrf'),email,password})})).status,302);}
await login('admin@askustours.com','password');const ownerCookie=cookie;
const dashboard=await page('/dashboard.php');check(dashboard.includes('Trabajadores y permisos'),true);check(dashboard.includes('users.php?view=customers'),true);check(dashboard.includes('users.php?view=collaborators'),true);
let html=await page('/staff.php');
check((await request('/staff.php',{method:'POST',body:new URLSearchParams({_csrf:'invalid'})})).status,419);
check((await request('/staff.php',{method:'POST',body:new URLSearchParams({_csrf:field(html,'_csrf'),id:'0',name:'Trabajador sintético',email:'staff-test@example.test',role:'operator',active:'1',password:'SyntheticPassword2026'})})).status,302);
html=await page('/staff.php');const workerId=html.match(/staff-test@example\.test[\s\S]*?href="staff\.php\?edit=(\d+)"/)?.[1];assert.ok(workerId);
await login('staff-test@example.test','SyntheticPassword2026');let workerCookie=cookie;
check((await request('/staff.php')).status,403);check((await request('/deletions.php')).status,403);
const workerSlides=await page('/content.php?type=banners');
check((await request('/content.php?type=banners',{method:'POST',body:new URLSearchParams({_csrf:field(workerSlides,'_csrf'),action:'delete',id:'1'})})).status,403);
cookie=ownerCookie;html=await page('/staff.php?edit='+workerId);
check((await request('/staff.php',{method:'POST',body:new URLSearchParams({_csrf:field(html,'_csrf'),id:workerId,revision:field(html,'revision'),name:'Trabajador sintético',email:'staff-test@example.test',role:'operator',active:'1',password:'ChangedSyntheticPassword2026'})})).status,302);
cookie=workerCookie;check((await request('/dashboard.php')).status,302);
await login('staff-test@example.test','ChangedSyntheticPassword2026');workerCookie=cookie;
cookie=ownerCookie;html=await page('/staff.php?edit='+workerId);const stale=html;
const edit=(h,role,active=true)=>request('/staff.php',{method:'POST',body:new URLSearchParams({_csrf:field(h,'_csrf'),id:workerId,revision:field(h,'revision'),name:'Trabajador sintético',email:'staff-test@example.test',role,...(active?{active:'1'}:{})})});
check((await edit(html,'reviewer')).status,302);
cookie=workerCookie;check((await request('/catalog.php?view=drivers')).status,200);check((await request('/catalog.php?view=drivers',{method:'POST',body:new URLSearchParams({action:'create'})})).status,403);
cookie=ownerCookie;const staleResponse=await edit(stale,'admin');check(staleResponse.status,200);check((await staleResponse.text()).includes('Esta cuenta cambió'),true);
html=await page('/staff.php?edit='+workerId);check((await edit(html,'reviewer',false)).status,302);
cookie=workerCookie;check((await request('/dashboard.php')).status,302);
cookie=ownerCookie;html=await page('/staff.php?edit=1');
const self=await request('/staff.php',{method:'POST',body:new URLSearchParams({_csrf:field(html,'_csrf'),id:'1',revision:field(html,'revision'),name:'Administrador',email:'admin@askustours.com',role:'reviewer',active:'1'})});check(self.status,200);check((await self.text()).includes('propio acceso'),true);
const customer='10000000-0000-4000-8000-000000000001';
html=await page('/deletions.php?type=users&id='+customer);check(html.includes('Resumen de eliminación'),true);check(html.includes('Movimientos de billetera'),true);
check((await request('/deletions.php?type=users',{method:'POST',body:new URLSearchParams({_csrf:field(html,'_csrf'),type:'users',id:customer,revision:field(html,'revision'),reason:'Limpieza local sintética',action:'delete'})})).status,302);
const deleted=await page('/deletions.php?type=users');check(deleted.includes('demo.customer@askustours.test'),false);const backupId=field(deleted,'backup_id');
check((await request('/api/v1/me',{headers:{Authorization:'Bearer fixture-customer-access'}})).status,401);
check((await request('/deletions.php',{method:'POST',body:new URLSearchParams({_csrf:field(deleted,'_csrf'),backup_id:backupId,action:'restore'})})).status,302);
html=await page('/users.php?view=customers&id='+customer);check(html.includes('Sara Ahmed'),true);check(html.includes('25,00 EUR'),true);check(html.includes('Historial de servicios'),true);
const recovered=await page('/deletions.php');const replay=await request('/deletions.php',{method:'POST',body:new URLSearchParams({_csrf:field(html,'_csrf'),backup_id:backupId,action:'restore'})});check(replay.status,200);check((await replay.text()).includes('ya fue recuperada'),true);
check((await request('/api/v1/me',{headers:{Authorization:'Bearer fixture-customer-access'}})).status,401);
console.log(`Isolated staff and recovery HTTP checks passed: ${checks} assertions.`);
