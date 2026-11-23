import assert from 'node:assert/strict';
import fs from 'node:fs/promises';

// Refuse real infrastructure. Use a freshly seeded, disposable local database.
const base = process.env.HALA_TEST_BASE || 'http://127.0.0.1:8092';
if (!/^http:\/\/127\.0\.0\.1:\d+$/.test(base)) throw new Error('Only an isolated loopback test server is allowed.');
let cookie;
let assertions = 0;
function check(value, expected) {assert.deepEqual(value,expected); assertions++;}
async function request(path, options={}) {
 const response = await fetch(base+path,{...options,redirect:'manual',headers:{...(cookie?{Cookie:cookie}:{}),...options.headers}});
 const session = response.headers.getSetCookie().find(value=>value.startsWith('PHPSESSID='));
 if (session) cookie=session.split(';')[0];
 return response;
}
async function page(path) {const response=await request(path);check(response.status,200);return response.text();}
function field(html,name) {const value=html.match(new RegExp(`name="${name}" value="([^"]*)"`))?.[1];assert.notEqual(value,undefined);return value;}
async function home() {const response=await request('/api/v1/home');check(response.status,200);return (await response.json()).data;}
async function form(section,id='') {
 const html=await page(`/home-content.php?section=${section}${id?'&edit='+id:''}`);
 return {_csrf:field(html,'_csrf'),id,revision:field(html,'revision')};
}
async function post(section,data,image=false) {
 const body = new FormData();
 for (const [key,value] of Object.entries(data)) body.append(key,value);
 if (image) body.append('image',new Blob([await fs.readFile(new URL('../assets/placeholder.svg',import.meta.url))],{type:'image/svg+xml'}),'invalid.svg');
 return request('/home-content.php?section='+section,{method:'POST',body});
}
async function save(section,title,order,fields={}) {
 const body=new FormData();
 const fieldsAndDefaults={...await form(section),'title_es':title,'title_en':title,'title_ar':'عنوان','description_es':'Descripción de prueba',displayOrder:String(order),active:'on',...fields};
 for(const [key,value] of Object.entries(fieldsAndDefaults))body.append(key,value);
 const png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jWZkAAAAASUVORK5CYII=','base64');
 body.append('image',new Blob([png],{type:'image/png'}),'fixture.png');
 const response=await request('/home-content.php?section='+section,{method:'POST',body});check(response.status,302);
 return (await home())[section].find(card=>card.title.es===title);
}
check((await request('/home-content.php')).status,302);
const login=await page('/login.php');
const signed=await request('/login.php',{method:'POST',body:new URLSearchParams({_csrf:field(login,'_csrf'),email:'admin@askustours.com',password:'password'})});
check(signed.status,302);
for(const path of ['/dashboard.php','/content.php?type=banners','/content.php?type=offers','/home-content.php?section=tripIdeas','/home-content.php?section=destinations','/home-content.php?section=experiences'])await page(path);
const before=await home();
const legacyIds = [];
for (const type of ['banners','offers']) {
 for (const order of [90,3]) {
  const html=await page('/content.php?type='+type);
  const body=new FormData();
  for (const [key,value] of Object.entries({_csrf:field(html,'_csrf'),id:'0',title:type+' '+order,title_es:type+' '+order,description_es:'Descripción',subtitle:'Descripción',link_url:'https://example.com',sort_order:String(order),active:'on'}))body.append(key,value);
  body.append('image',new Blob([Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jWZkAAAAASUVORK5CYII=','base64')],{type:'image/png'}),'fixture.png');
  check((await request('/content.php?type='+type,{method:'POST',body})).status,302);
  legacyIds.push({type,id:(await home())[type].find(card=>card.title.es===type+' '+order).id});
 }
 const selected=(await home())[type].filter(card=>legacyIds.some(item=>item.id===card.id));
 check(selected.map(card=>card.displayOrder),[3,90]);
}
check((await home()).banners.every(card=>!card.id.startsWith('offer_')),true);
check((await home()).offers.every(card=>!card.id.startsWith('banner_')),true);
const first=await save('tripIdeas','Tarde',20,{prefillStyle:'family'});
const second=await save('tripIdeas','Primero',2,{prefillStyle:'nature'});
check((await home()).tripIdeas.map(card=>card.id),[second.id,first.id]);
check(first.prefillStyle,'family');
check(Object.keys(first).some(key=>key.startsWith('_')),false);
check(first.description.es,'Descripción de prueba');
const photo=await fetch(first.imageUrl);check(photo.status,200);check(photo.headers.get('content-type'),'image/png');
const oldForm=await form('tripIdeas',first.id);
const toggle=await post('tripIdeas',{...oldForm,action:'toggle'});check(toggle.status,302);
check((await home()).tripIdeas.map(card=>card.id),[second.id]);
const stale=await post('tripIdeas',{...oldForm,action:'delete'});check(stale.status,200);check((await stale.text()).includes('Otro administrador'),true);
check((await post('tripIdeas',{...await form('tripIdeas',first.id),action:'toggle'})).status,302);
const edit=await post('tripIdeas',{...await form('tripIdeas',first.id),title_es:'Editado',displayOrder:'1',prefillStyle:'friends',active:'on'});check(edit.status,302);
check((await home()).tripIdeas[0].id,first.id);
check((await home()).tripIdeas[0].title.es,'Editado');
check((await post('tripIdeas',{...await form('tripIdeas',first.id),title_es:'Inválido',displayOrder:'-1',active:'on'})).status,200);
check((await home()).tripIdeas[0].title.es,'Editado');
check((await post('tripIdeas',{...await form('tripIdeas',first.id),action:'delete',_csrf:'invalid'})).status,419);
check((await post('tripIdeas',{...await form('tripIdeas',first.id),title_es:'X',displayOrder:'1',active:'on'},true)).status,200);
for(const section of ['destinations','experiences']) {
 const card=await save(section,section,0);
 check(card.action,'placeholder');
 check((await post(section,{...await form(section,card.id),action:'delete'})).status,302);
 check((await home())[section],before[section]);
}
for(const card of [first,second])check((await post('tripIdeas',{...await form('tripIdeas',card.id),action:'delete'})).status,302);
for(const item of legacyIds) {
 const html=await page('/content.php?type='+item.type+'&edit='+item.id.replace(/^[a-z]+_/,''));
 check((await request('/content.php?type='+item.type,{method:'POST',body:new URLSearchParams({_csrf:field(html,'_csrf'),revision:field(html,'revision'),id:item.id.replace(/^[a-z]+_/,''),action:'delete'})})).status,302);
}
const after=await home();
for(const key of ['banners','offers','shortcuts','planningFee','tripIdeas'])check(after[key],before[key]);
check((await request('/media.php?type=home_private&id=1')).status,404);
const slidePage=await page('/content.php?type=banners');
check((await request('/content.php?type=banners',{method:'POST',body:new URLSearchParams({_csrf:field(slidePage,'_csrf'),action:'restore'})})).status,302);
const restored=await home();
check(restored.banners.length,5);
check(restored.banners[0].destination,'drivers');
check(restored.banners[0].opensDrivers,true);
check(restored.banners[0].sections.length,5);
check(restored.banners[0].title.ar,'سيارة خاصة مع سائق في إسبانيا');
check('_bannerDetails' in restored,false);
check(restored.offers,before.offers);
check(restored.planningFee,before.planningFee);
for(const slide of restored.banners)check((await fetch(slide.imageUrl)).status,200);
const slideId=restored.banners[0].id.replace('banner_','');
const editSlide=await page('/content.php?type=banners&edit='+slideId);
const editData={_csrf:field(editSlide,'_csrf'),id:slideId,revision:field(editSlide,'revision'),title_es:'Título editado',description_es:'Descripción editada',title_ar:restored.banners[0].title.ar,sort_order:'0',destination:'guides',active:'on'};
check((await request('/content.php?type=banners',{method:'POST',body:new URLSearchParams(editData)})).status,302);
check((await home()).banners[0].destination,'guides');
check((await home()).banners[0].sections.length,5);
check((await request('/content.php?type=banners',{method:'POST',body:new URLSearchParams(editData)})).status,200);
const repeatPage=await page('/content.php?type=banners');
check((await request('/content.php?type=banners',{method:'POST',body:new URLSearchParams({_csrf:field(repeatPage,'_csrf'),action:'restore'})})).status,302);
check((await home()).banners.length,5);
check((await home()).banners[0].title.es,'Título editado');
const publishingPage=await page('/home-publishing.php');
check((await request('/home-publishing.php',{method:'POST',body:new URLSearchParams({_csrf:field(publishingPage,'_csrf'),action:'restore'})})).status,302);
const rails=await home();
check(rails.offers.length,5);
check(rails.offers[0].opensDrivers,true);
check(rails.offers[0].sections.length,5);
check(rails.tripIdeas.length,6);
check(rails.destinations.length,4);
check(rails.experiences.length,4);
for(const card of [...rails.tripIdeas,...rails.destinations,...rails.experiences])check((await fetch(card.imageUrl,{method:'HEAD'})).status,200);
const settings=await page('/home-publishing.php');
check((await request('/home-publishing.php',{method:'POST',body:new URLSearchParams({_csrf:field(settings,'_csrf'),action:'save',mode:'mixed'})})).status,302);
const mixed=await home();
check(mixed.offers.length,5); // Synthetic demo catalog is never promoted.
check('_homePublishing' in mixed,false);
check('_offerDetails' in mixed,false);
check(mixed.planningFee,before.planningFee);
console.log(`Isolated homepage HTTP checks passed: ${assertions} assertions.`);
