// Extend the existing dashboard language selector; never translate customer content.
const peopleLabels={
  'Buscar':['Search','بحث'],'Estado de cuenta':['Account status','حالة الحساب'],
  'Revisión profesional':['Professional review','مراجعة الملف المهني'],'Actividad':['Activity','النشاط'],
  'Ciudad de trabajo':['Working city','مدينة العمل'],'Ordenar por':['Sort by','ترتيب حسب'],
  'Contacto':['Contact','التواصل'],'Estado':['Status','الحالة'],'Servicios':['Services','الخدمات'],
  'Gestión':['Actions','الإجراءات'],'Aplicar filtros':['Apply filters','تطبيق الفلاتر'],
  'Limpiar filtros':['Clear filters','مسح الفلاتر'],'Ver todas las cuentas':['View all accounts','عرض جميع الحسابات'],
  'Cuentas activas':['Active accounts','الحسابات النشطة'],'Bloqueadas':['Blocked','الحسابات المحظورة'],
  'Pendientes de revisión':['Awaiting review','بانتظار المراجعة'],
  'Todas las cuentas':['All accounts','جميع الحسابات'],'Todos los registros':['All registrations','جميع التسجيلات'],
  'Todas las actividades':['All activities','جميع الأنشطة'],
  'Activa':['Active','نشط'],'Desactivada':['Inactive','غير نشط'],'Bloqueada':['Blocked','محظور'],
  'Incompleto':['Incomplete','غير مكتمل'],'Pendiente de revisión':['Awaiting review','بانتظار المراجعة'],
  'Verificado':['Verified','تم التحقق'],'Correcciones solicitadas':['Changes requested','مطلوب تعديلات'],
  'Rechazado':['Rejected','مرفوض'],'Suspendido':['Suspended','موقوف'],
  'Chófer privado':['Private driver','سائق خاص'],'Guía turístico':['Tour guide','مرشد سياحي'],
  'Alquiler de vehículos':['Vehicle rental','تأجير السيارات'],'Experiencias':['Experiences','تجارب'],
  'Alojamiento':['Accommodation','الإقامة'],'Otra actividad':['Other activity','نشاط آخر'],
  'Altas recientes':['Newest first','الأحدث أولاً'],'Más antiguos':['Oldest first','الأقدم أولاً'],
  'Nombre A–Z':['Name A–Z','الاسم'],'Más servicios':['Most services','الأكثر خدمات']
};
for(const [source,[en,ar]] of Object.entries(peopleLabels)){
  const key='people_'+source;
  translations.es[key]=source;translations.en[key]=en;translations.ar[key]=ar;
}
document.querySelectorAll('[data-people-text]').forEach(element=>{
  const source=element.dataset.peopleText;
  if(peopleLabels[source])element.dataset.i18n='people_'+source;
});
applyLanguage(localStorage.getItem('askus.admin.language')||'es');
