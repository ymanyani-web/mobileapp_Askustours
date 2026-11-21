document.querySelectorAll('[data-confirm]').forEach(button=>button.addEventListener('click',event=>{if(!confirm(button.dataset.confirm))event.preventDefault()}));

const translations={
  en:{controlCenter:'Control center',overview:'Overview',mobileCatalog:'Mobile catalog',appContent:'App content',customers:'Customers',collaborators:'Collaborators',serviceRequests:'Service requests',workflowReview:'Workflow review',administrator:'Administrator',adminPanel:'ADMIN PANEL',language:'Language',live:'Live',viewApi:'View API',signOut:'Sign out',account:'ACCOUNT',adminProfile:'Administrator profile',fullName:'Full name',email:'Email address',role:'Role',preferredLanguage:'Preferred language',preferences:'Preferences',footer:'HALA SPAIN dashboard · Native PHP + MySQL'},
  es:{controlCenter:'Centro de control',overview:'Resumen',mobileCatalog:'Catálogo móvil',appContent:'Contenido de la app',customers:'Clientes',collaborators:'Colaboradores',serviceRequests:'Solicitudes de servicio',workflowReview:'Revisión de procesos',administrator:'Administrador',adminPanel:'PANEL DE ADMINISTRACIÓN',language:'Idioma',live:'En vivo',viewApi:'Ver API',signOut:'Cerrar sesión',account:'CUENTA',adminProfile:'Perfil del administrador',fullName:'Nombre completo',email:'Correo electrónico',role:'Rol',preferredLanguage:'Idioma preferido',preferences:'Preferencias',footer:'Panel de HALA SPAIN · PHP nativo + MySQL'},
  ar:{controlCenter:'مركز التحكم',overview:'نظرة عامة',mobileCatalog:'كتالوج التطبيق',appContent:'محتوى التطبيق',customers:'العملاء',collaborators:'المتعاونون',serviceRequests:'طلبات الخدمة',workflowReview:'مراجعة سير العمل',administrator:'مسؤول',adminPanel:'لوحة الإدارة',language:'اللغة',live:'مباشر',viewApi:'عرض API',signOut:'تسجيل الخروج',account:'الحساب',adminProfile:'ملف المسؤول',fullName:'الاسم الكامل',email:'البريد الإلكتروني',role:'الدور',preferredLanguage:'اللغة المفضلة',preferences:'التفضيلات',footer:'لوحة HALA SPAIN · PHP + MySQL'}
};

const phraseTranslations={
  es:{'Operations overview':'Resumen de operaciones','Mobile catalog':'Catálogo móvil','Customers & collaborators':'Clientes y colaboradores','Service requests':'Solicitudes de servicio','Workflow review':'Revisión de procesos','LIVE OPERATIONS':'OPERACIONES EN VIVO','AskUs Tours control center.':'Centro de control de AskUs Tours.','Monitor customers, collaborators, service requests and itinerary enquiries.':'Supervisa clientes, colaboradores, solicitudes de servicio y consultas de itinerarios.','Registered accounts':'Cuentas registradas','awaiting review':'pendientes de revisión','Driver and guide requests':'Solicitudes de conductor y guía','Trip enquiries':'Consultas de viaje','published services':'servicios publicados','RECENT ACTIVITY':'ACTIVIDAD RECIENTE','Latest service requests':'Últimas solicitudes de servicio','Manage →':'Gestionar →','Request':'Solicitud','Customer':'Cliente','Service':'Servicio','Status':'Estado','Created':'Creado','No service requests yet.':'Aún no hay solicitudes de servicio.','REVIEW QUEUE':'COLA DE REVISIÓN','Open collaborators':'Abrir colaboradores','No applications waiting.':'No hay solicitudes pendientes.','Driver services':'Servicios de conductor','Guide services':'Servicios de guía','Travel packages':'Paquetes de viaje','Save':'Guardar','Edit':'Editar','Delete':'Eliminar','Pending':'Pendiente','Approved':'Aprobado','Rejected':'Rechazado','Draft':'Borrador','No records found.':'No se encontraron registros.'},
  ar:{'Operations overview':'نظرة عامة على العمليات','Mobile catalog':'كتالوج التطبيق','Customers & collaborators':'العملاء والمتعاونون','Service requests':'طلبات الخدمة','Workflow review':'مراجعة سير العمل','LIVE OPERATIONS':'العمليات المباشرة','AskUs Tours control center.':'مركز تحكم AskUs Tours.','Monitor customers, collaborators, service requests and itinerary enquiries.':'راقب العملاء والمتعاونين وطلبات الخدمة واستفسارات الرحلات.','Registered accounts':'الحسابات المسجلة','awaiting review':'بانتظار المراجعة','Driver and guide requests':'طلبات السائقين والمرشدين','Trip enquiries':'استفسارات الرحلات','published services':'خدمات منشورة','RECENT ACTIVITY':'النشاط الأخير','Latest service requests':'أحدث طلبات الخدمة','Manage →':'إدارة ←','Request':'الطلب','Customer':'العميل','Service':'الخدمة','Status':'الحالة','Created':'تاريخ الإنشاء','No service requests yet.':'لا توجد طلبات خدمة بعد.','REVIEW QUEUE':'قائمة المراجعة','Open collaborators':'فتح المتعاونين','No applications waiting.':'لا توجد طلبات معلقة.','Driver services':'خدمات السائقين','Guide services':'خدمات المرشدين','Travel packages':'باقات السفر','Save':'حفظ','Edit':'تعديل','Delete':'حذف','Pending':'قيد الانتظار','Approved':'مقبول','Rejected':'مرفوض','Draft':'مسودة','No records found.':'لم يتم العثور على سجلات.'}
};
Object.assign(phraseTranslations.es,{'Customers':'Clientes','Collaborators':'Colaboradores','NEW DRAFT':'NUEVO BORRADOR','Create catalog record':'Crear registro de catálogo','City':'Ciudad','Category':'Categoría','Complete JSON payload':'Contenido JSON completo','Create draft':'Crear borrador','Legal & app content':'Contenido legal y de la app','MOBILE DATA':'DATOS MÓVILES','Record':'Registro','Preview':'Vista previa','Status / content':'Estado / contenido','Updated':'Actualizado','No records yet.':'Aún no hay registros.','DIRECTORY':'DIRECTORIO','Name':'Nombre','Contact':'Contacto','Requests':'Solicitudes','Jobs / review':'Trabajos / revisión','Joined':'Fecha de alta','Verified':'Verificado','Not verified':'No verificado','OPERATIONS':'OPERACIONES','Driver and guide requests':'Solicitudes de conductor y guía','All':'Todos','Collaborator':'Colaborador','Payment':'Pago','Unassigned':'Sin asignar','No service requests found.':'No se encontraron solicitudes de servicio.','Trip enquiries':'Consultas de viaje','Collaborator service drafts':'Borradores de servicios','Owner':'Propietario','Type':'Tipo','Payload preview':'Vista previa','Itinerary':'Itinerario','Revision':'Revisión','Published':'Publicado','Paused':'Pausado','Archived':'Archivado','Review':'Revisión','Submitted':'Enviado','Suspended':'Suspendido','Requested':'Solicitado','Assigned':'Asignado','Accepted':'Aceptado','Declined':'Rechazado','Cancelled':'Cancelado','Completed':'Completado','Closed':'Cerrado','Proposal ready':'Propuesta lista'});
Object.assign(phraseTranslations.ar,{'Customers':'العملاء','Collaborators':'المتعاونون','NEW DRAFT':'مسودة جديدة','Create catalog record':'إنشاء سجل في الكتالوج','City':'المدينة','Category':'الفئة','Complete JSON payload':'محتوى JSON الكامل','Create draft':'إنشاء مسودة','Legal & app content':'المحتوى القانوني ومحتوى التطبيق','MOBILE DATA':'بيانات التطبيق','Record':'السجل','Preview':'المعاينة','Status / content':'الحالة / المحتوى','Updated':'آخر تحديث','No records yet.':'لا توجد سجلات بعد.','DIRECTORY':'الدليل','Name':'الاسم','Contact':'الاتصال','Requests':'الطلبات','Jobs / review':'المهام / المراجعة','Joined':'تاريخ الانضمام','Verified':'موثّق','Not verified':'غير موثّق','OPERATIONS':'العمليات','Driver and guide requests':'طلبات السائقين والمرشدين','All':'الكل','Collaborator':'المتعاون','Payment':'الدفع','Unassigned':'غير معيّن','No service requests found.':'لم يتم العثور على طلبات خدمة.','Trip enquiries':'استفسارات الرحلات','Collaborator service drafts':'مسودات خدمات المتعاونين','Owner':'المالك','Type':'النوع','Payload preview':'معاينة المحتوى','Itinerary':'خط سير الرحلة','Revision':'المراجعة','Published':'منشور','Paused':'متوقف مؤقتاً','Archived':'مؤرشف','Review':'مراجعة','Submitted':'مُرسل','Suspended':'موقوف','Requested':'مطلوب','Assigned':'مُعيّن','Accepted':'مقبول','Declined':'مرفوض','Cancelled':'ملغى','Completed':'مكتمل','Closed':'مغلق','Proposal ready':'العرض جاهز'});

Object.assign(phraseTranslations.es,{'Availability calendar':'Calendario de disponibilidad','control center.':'Centro de control.','Home slides':'Slides principales','Home offers':'Ofertas destacadas','Title':'Título','Subtitle':'Descripción','Optional tap link':'Enlace opcional (HTTPS)','Display order':'Orden de aparición','Slide image':'Foto del slide','Offer card image':'Foto de la oferta','Visible in mobile app':'Visible en la app','Save changes':'Guardar cambios','Create item':'Crear contenido','Add new item':'Nuevo contenido','Edit item':'Editar contenido','APP CONTENT':'CONTENIDO DE LA APP','PUBLISHED CONTENT':'CONTENIDO DE LA SECCIÓN','Toggle':'Publicar / ocultar'});
const originalText=new WeakMap();
Object.assign(phraseTranslations.es,{'HALA SPAIN control center.':'Centro de control de HALA SPAIN.'});
Object.assign(phraseTranslations.ar,{'HALA SPAIN control center.':'مركز تحكم HALA SPAIN.'});
function applyLanguage(language){
  const lang=translations[language]?language:'en';
  localStorage.setItem('askus.admin.language',lang);
  document.documentElement.lang=lang;
  document.documentElement.dir=lang==='ar'?'rtl':'ltr';
  document.body.classList.toggle('rtl',lang==='ar');
  document.querySelectorAll('[data-i18n]').forEach(element=>{const key=element.dataset.i18n;element.textContent=translations[lang][key]||translations.en[key]||element.textContent});
  document.querySelectorAll('[data-i18n-title]').forEach(element=>{const key=element.dataset.i18nTitle;element.title=translations[lang][key]||translations.en[key]||element.title;element.setAttribute('aria-label',element.title)});
  document.querySelectorAll('[data-language]').forEach(select=>select.value=lang);
  const languageNames={en:'English',es:'Español',ar:'العربية'};
  document.querySelectorAll('[data-language-name]').forEach(element=>element.textContent=languageNames[lang]);
  const walker=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT,{acceptNode(node){return node.parentElement&&!node.parentElement.closest('[data-i18n], [data-editorial-text]')&&!['SCRIPT','STYLE'].includes(node.parentElement.tagName)&&node.textContent.trim()?NodeFilter.FILTER_ACCEPT:NodeFilter.FILTER_REJECT}});
  const nodes=[];while(walker.nextNode())nodes.push(walker.currentNode);
  nodes.forEach(node=>{if(!originalText.has(node))originalText.set(node,node.textContent);const original=originalText.get(node);const clean=original.trim();const translated=lang==='en'?clean:(phraseTranslations[lang]?.[clean]||clean);node.textContent=original.replace(clean,translated)});
}

document.querySelectorAll('[data-language]').forEach(select=>select.addEventListener('change',event=>applyLanguage(event.target.value)));
applyLanguage(localStorage.getItem('askus.admin.language')||'es');

const modal=document.querySelector('[data-profile-modal]');
const openProfile=document.querySelector('[data-profile-open]');
let previousFocus=null;
function setModal(open){if(!modal)return;modal.hidden=!open;document.body.classList.toggle('modal-open',open);if(open){previousFocus=document.activeElement;modal.querySelector('.modal-close')?.focus()}else previousFocus?.focus()}
openProfile?.addEventListener('click',()=>setModal(true));
document.querySelectorAll('[data-profile-close]').forEach(element=>element.addEventListener('click',()=>setModal(false)));
document.addEventListener('keydown',event=>{if(event.key==='Escape'&&modal&&!modal.hidden)setModal(false)});

const apiLink=document.querySelector('.top-actions a[href*="api/"]');
if(apiLink)apiLink.href='https://api.askustours.com/v1/home';

const currentFile=location.pathname.split('/').pop()||'index.php';
const currentParams=new URLSearchParams(location.search);
document.querySelectorAll('.sidebar nav a').forEach(link=>{
  const target=new URL(link.href,location.href);
  const targetFile=target.pathname.split('/').pop();
  let active=targetFile===currentFile;
  if(active&&currentFile==='home-content.php')active=target.searchParams.get('section')===(currentParams.get('section')||'tripIdeas');
  if(active&&currentFile==='content.php')active=target.searchParams.get('type')===(currentParams.get('type')||'banners');
  if(active&&currentFile==='catalog.php')active=target.searchParams.get('view')==='content'?currentParams.get('view')==='content':currentParams.get('view')!=='content';
  if(active&&currentFile==='users.php')active=target.searchParams.get('view')===(currentParams.get('view')||'customers');
  link.classList.toggle('active',active);
  if(active)link.setAttribute('aria-current','page');
});

const sidebar=document.querySelector('.sidebar');
const topbarHeading=document.querySelector('.topbar>div:first-child');
if(sidebar&&topbarHeading){
  const menuButton=document.createElement('button');
  menuButton.type='button';
  menuButton.className='menu-toggle';
  menuButton.setAttribute('aria-label','Open navigation');
  menuButton.setAttribute('aria-expanded','false');
  menuButton.innerHTML='<span></span><span></span><span></span>';
  topbarHeading.prepend(menuButton);
  const scrim=document.createElement('button');
  scrim.type='button';
  scrim.className='mobile-nav-scrim';
  scrim.setAttribute('aria-label','Close navigation');
  document.body.append(scrim);
  const setNavigation=open=>{
    sidebar.classList.toggle('mobile-open',open);
    scrim.classList.toggle('visible',open);
    menuButton.setAttribute('aria-expanded',String(open));
    document.body.classList.toggle('nav-open',open);
  };
  menuButton.addEventListener('click',()=>setNavigation(!sidebar.classList.contains('mobile-open')));
  scrim.addEventListener('click',()=>setNavigation(false));
  sidebar.querySelectorAll('nav a').forEach(link=>link.addEventListener('click',()=>setNavigation(false)));
  addEventListener('resize',()=>{if(innerWidth>820)setNavigation(false)});
  document.addEventListener('keydown',event=>{if(event.key==='Escape')setNavigation(false)});
}
