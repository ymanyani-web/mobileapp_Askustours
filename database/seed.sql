USE askustours;

-- Admin login: admin@askustours.com / password
-- Change this password immediately outside local testing.
INSERT INTO admins(name,email,password_hash,role) VALUES
('System Administrator','admin@askustours.com','$2y$12$xhIE2hsq.FzysO1aWA2hi.7b3pxpaF.vgF95RfgG1mePa3pwWuP7a','admin');

INSERT INTO home_config(id,config_json) VALUES (1,JSON_OBJECT(
 'offers',JSON_ARRAY(),
 'shortcuts',JSON_ARRAY(
   JSON_OBJECT('id','drivers','label',JSON_OBJECT('es','Chóferes','en','Drivers','ar','سائقون'),'enabled',TRUE),
   JSON_OBJECT('id','guides','label',JSON_OBJECT('es','Guías','en','Guides','ar','مرشدون'),'enabled',TRUE),
   JSON_OBJECT('id','hotels','label',JSON_OBJECT('es','Hoteles','en','Hotels','ar','فنادق'),'enabled',FALSE)
 ),
 'tripIdeas',JSON_ARRAY(),'destinations',JSON_ARRAY(),'experiences',JSON_ARRAY(),
 'planningFee',JSON_OBJECT('amountMinor',2000,'currency','EUR')
));

INSERT INTO content_documents(slug,version,title_json,body_json) VALUES
('privacy','draft-1',JSON_OBJECT('es','Privacidad','en','Privacy','ar','الخصوصية'),JSON_OBJECT('es','Contenido pendiente de aprobación.','en','Content pending approval.','ar','المحتوى في انتظار الموافقة.')),
('terms','draft-1',JSON_OBJECT('es','Términos','en','Terms','ar','الشروط'),JSON_OBJECT('es','Contenido pendiente de aprobación.','en','Content pending approval.','ar','المحتوى في انتظار الموافقة.')),
('contact','draft-1',JSON_OBJECT('es','Contacto','en','Contact','ar','اتصل بنا'),JSON_OBJECT('es','Configura aquí los canales oficiales.','en','Configure official contact channels here.','ar','قم بإعداد قنوات الاتصال الرسمية هنا.')),
('faq','draft-1',JSON_OBJECT('es','Preguntas frecuentes','en','FAQ','ar','الأسئلة الشائعة'),JSON_OBJECT('items',JSON_ARRAY())),
('planning-information','draft-1',JSON_OBJECT('es','Información importante','en','Important information','ar','معلومات مهمة'),JSON_OBJECT('planningFeeMinor',2000,'currency','EUR'));
