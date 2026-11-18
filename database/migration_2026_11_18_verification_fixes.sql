-- Completes the known synthetic travel-package fixture reported by mobile verification.
-- This changes demo data only. Real packages still require reviewed dashboard publication.
UPDATE travel_packages
SET package_json = JSON_SET(
 package_json,
 '$.destinations', JSON_ARRAY(
  JSON_OBJECT(
   'cityCode','madrid',
   'name',JSON_OBJECT('es','Madrid','en','Madrid','ar','مدريد'),
   'nights',2,
   'position',1
  )
 ),
 '$.image', JSON_OBJECT(
  'url','assets/placeholder.svg',
  'alt',JSON_OBJECT('es','Madrid de demostración','en','Demo Madrid','ar','مدريد التجريبية')
 ),
 '$.itinerary', JSON_ARRAY(
  JSON_OBJECT('day',1,'title',JSON_OBJECT('es','Llegada','en','Arrival','ar','الوصول'),'description',JSON_OBJECT('es','Recepción de demostración.','en','Synthetic welcome.','ar','استقبال تجريبي.')),
  JSON_OBJECT('day',2,'title',JSON_OBJECT('es','Madrid histórico','en','Historic Madrid','ar','مدريد التاريخية'),'description',JSON_OBJECT('es','Ruta ficticia para pruebas.','en','Synthetic route for testing.','ar','مسار تجريبي للاختبار.')),
  JSON_OBJECT('day',3,'title',JSON_OBJECT('es','Salida','en','Departure','ar','المغادرة'),'description',JSON_OBJECT('es','Fin del paquete de demostración.','en','End of the demo package.','ar','نهاية الباقة التجريبية.'))
 ),
 '$.terms', JSON_OBJECT('version','demo-v1','cancellation','No commercial terms; staging fixture only.'),
 '$.availability', JSON_OBJECT('requestOnly',true,'from',DATE_FORMAT(DATE_ADD(CURDATE(),INTERVAL 14 DAY),'%Y-%m-%d'),'to',DATE_FORMAT(DATE_ADD(CURDATE(),INTERVAL 180 DAY),'%Y-%m-%d'))
),
updated_at = CURRENT_TIMESTAMP
WHERE id = '50000000-0000-4000-8000-000000000001'
  AND JSON_EXTRACT(package_json,'$.demo') = true;
