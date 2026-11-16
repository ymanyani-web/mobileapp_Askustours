-- ASKUS TOURS Release 1 demo data.
-- FOR DEVELOPMENT / STAGING ONLY. Do not import into a live production database.
-- Select the target database in phpMyAdmin before importing this file.

SET NAMES utf8mb4;

-- Verified demo customer and approved demo collaborator.
INSERT INTO users(id,first_name,last_name,email,phone,role,partner_intent,verified_at,avatar,profile_json,preferences_json)
VALUES
('10000000-0000-4000-8000-000000000001','Sara','Ahmed','demo.customer@askustours.test','+34600000001','customer',0,NOW(),'womanClassic',JSON_OBJECT('address','Calle Mayor 10','city','Madrid','postalCode','28013','country','ES'),JSON_OBJECT('nightMode',false,'language','es')),
('10000000-0000-4000-8000-000000000002','Omar','Demo Driver','demo.partner@askustours.test','+34600000002','partner',1,NOW(),NULL,JSON_OBJECT('city','Madrid','country','ES'),JSON_OBJECT('nightMode',false,'language','en'))
ON DUPLICATE KEY UPDATE first_name=VALUES(first_name),last_name=VALUES(last_name),role=VALUES(role),partner_intent=VALUES(partner_intent),verified_at=VALUES(verified_at),profile_json=VALUES(profile_json),preferences_json=VALUES(preferences_json);

INSERT INTO partner_profiles(user_id,status,profile_json,revision,submitted_at,reviewed_at)
VALUES ('10000000-0000-4000-8000-000000000002','approved',JSON_OBJECT(
 'businessName','ASKUS Demo Mobility','contactName','Omar Demo Driver','phone','+34600000002',
 'email','demo.partner@askustours.test','activity','driver','cities',JSON_ARRAY('madrid'),
 'citiesText','Madrid','professionalPhotoUploadId','90000000-0000-4000-8000-000000000001'
),1,DATE_SUB(NOW(),INTERVAL 10 DAY),DATE_SUB(NOW(),INTERVAL 9 DAY))
ON DUPLICATE KEY UPDATE status='approved',profile_json=VALUES(profile_json),reviewed_at=VALUES(reviewed_at);

-- Public driver service with packages understood by the quote endpoint.
INSERT INTO driver_services(id,owner_user_id,status,city,category,title_json,service_json,rating,review_count,revision)
VALUES ('20000000-0000-4000-8000-000000000001','10000000-0000-4000-8000-000000000002','published','madrid','executive',
 JSON_OBJECT('es','Traslado ejecutivo de demostración','en','Demo executive transfer','ar','نقل تنفيذي تجريبي'),
 JSON_OBJECT(
  'id','20000000-0000-4000-8000-000000000001','demo',true,'city','madrid','category','executive',
  'title',JSON_OBJECT('es','Traslado ejecutivo de demostración','en','Demo executive transfer','ar','نقل تنفيذي تجريبي'),
  'description',JSON_OBJECT('es','Servicio ficticio para probar Release 1.','en','Synthetic service for Release 1 testing.','ar','خدمة تجريبية لاختبار الإصدار الأول.'),
  'model','Mercedes-Benz E-Class (demo)','seats',3,'bags',2,'languages',JSON_ARRAY('es','en','ar'),
  'priceMinor',8500,'currency','EUR','freeCancellation',true,'instantConfirmation',false,
  'packages',JSON_ARRAY(
   JSON_OBJECT('option','airportHotel','priceMinor',8500),
   JSON_OBJECT('option','hotelAirport','priceMinor',8500),
   JSON_OBJECT('option','intercity','priceMinor',15000),
   JSON_OBJECT('option','fourHours','priceMinor',22000),
   JSON_OBJECT('option','eightHours','priceMinor',44000)
  ),'photos',JSON_ARRAY()
 ),4.80,12,1)
ON DUPLICATE KEY UPDATE owner_user_id=VALUES(owner_user_id),status='published',service_json=VALUES(service_json),updated_at=CURRENT_TIMESTAMP;

-- Public guide service.
INSERT INTO guide_services(id,owner_user_id,status,city,title_json,service_json,rating,review_count,revision)
VALUES ('30000000-0000-4000-8000-000000000001','10000000-0000-4000-8000-000000000002','published','madrid',
 JSON_OBJECT('es','Madrid histórico de demostración','en','Demo historic Madrid','ar','مدريد التاريخية التجريبية'),
 JSON_OBJECT(
  'id','30000000-0000-4000-8000-000000000001','demo',true,'city','madrid',
  'title',JSON_OBJECT('es','Madrid histórico de demostración','en','Demo historic Madrid','ar','مدريد التاريخية التجريبية'),
  'description',JSON_OBJECT('es','Visita ficticia para pruebas.','en','Synthetic guided visit for testing.','ar','جولة إرشادية تجريبية للاختبار.'),
  'basePriceMinor',9500,'currency','EUR','hours',2,'maxPeople',8,
  'languages',JSON_ARRAY('es','en','ar'),'freeCancellation',true,'photo',NULL
 ),4.70,8,1)
ON DUPLICATE KEY UPDATE owner_user_id=VALUES(owner_user_id),status='published',service_json=VALUES(service_json),updated_at=CURRENT_TIMESTAMP;

-- Calendar dates remain in the future whenever the seed is imported.
INSERT INTO service_calendar(service_type,service_id,service_date,available,price_minor,details_json)
VALUES
('driver','20000000-0000-4000-8000-000000000001',DATE_ADD(CURDATE(),INTERVAL 14 DAY),1,9000,JSON_OBJECT('demo',true,'note','Demo daily supplement')),
('driver','20000000-0000-4000-8000-000000000001',DATE_ADD(CURDATE(),INTERVAL 15 DAY),0,NULL,JSON_OBJECT('demo',true,'reason','Demo unavailable day')),
('guide','30000000-0000-4000-8000-000000000001',DATE_ADD(CURDATE(),INTERVAL 14 DAY),1,9500,JSON_OBJECT('demo',true))
ON DUPLICATE KEY UPDATE available=VALUES(available),price_minor=VALUES(price_minor),details_json=VALUES(details_json);

INSERT INTO reviews(id,service_type,service_id,rating,author_name,comment,verified,active)
VALUES ('40000000-0000-4000-8000-000000000001','driver','20000000-0000-4000-8000-000000000001',5,'Demo Reviewer','Synthetic review for interface testing only.',0,1)
ON DUPLICATE KEY UPDATE rating=VALUES(rating),comment=VALUES(comment),verified=0,active=1;

INSERT INTO travel_packages(id,status,revision,package_json)
VALUES ('50000000-0000-4000-8000-000000000001','published',1,JSON_OBJECT(
 'id','50000000-0000-4000-8000-000000000001','demo',true,
 'title',JSON_OBJECT('es','Madrid esencial — demostración','en','Essential Madrid — demo','ar','مدريد الأساسية — تجريبي'),
 'description',JSON_OBJECT('es','Paquete ficticio para pruebas.','en','Synthetic package for testing.','ar','باقة تجريبية للاختبار.'),
 'durationDays',3,'priceFromMinor',39900,'currency','EUR','active',true,
 'includes',JSON_ARRAY('Demo itinerary','Demo guide'),'excludes',JSON_ARRAY('Flights','Real payment')
))
ON DUPLICATE KEY UPDATE status='published',package_json=VALUES(package_json),updated_at=CURRENT_TIMESTAMP;

-- Customer favorites and mixed basket.
INSERT IGNORE INTO favorites(user_id,driver_service_id)
VALUES ('10000000-0000-4000-8000-000000000001','20000000-0000-4000-8000-000000000001');

INSERT INTO basket_items(id,user_id,item_type,item_json,revision)
VALUES
('60000000-0000-4000-8000-000000000001','10000000-0000-4000-8000-000000000001','driver',JSON_OBJECT('type','driver','driverServiceId','20000000-0000-4000-8000-000000000001','packageOption','airportHotel','search',NULL,'demo',true),1),
('60000000-0000-4000-8000-000000000002','10000000-0000-4000-8000-000000000001','guide',JSON_OBJECT('type','guide','guideServiceId','30000000-0000-4000-8000-000000000001','guideBooking',JSON_OBJECT('hours',2,'people',2,'language','en'),'demo',true),1)
ON DUPLICATE KEY UPDATE item_json=VALUES(item_json),revision=revision+1;

-- An unexpired quote and assigned request make both customer history and partner jobs visible.
INSERT INTO quotes(id,user_id,service_type,service_id,request_json,quote_json,currency,total_minor,expires_at)
VALUES ('70000000-0000-4000-8000-000000000001','10000000-0000-4000-8000-000000000001','driver','20000000-0000-4000-8000-000000000001',
 JSON_OBJECT('demo',true,'type','driver','driverServiceId','20000000-0000-4000-8000-000000000001'),
 JSON_OBJECT('id','70000000-0000-4000-8000-000000000001','demo',true,'type','driver','partnerId','10000000-0000-4000-8000-000000000002','driverServiceId','20000000-0000-4000-8000-000000000001','guideServiceId',NULL,'packageOption','airportHotel','search',JSON_OBJECT('departure',DATE_FORMAT(DATE_ADD(NOW(),INTERVAL 14 DAY),'%Y-%m-%dT10:30:00+01:00'),'pickup','Demo Hotel','destination','Madrid Airport','passengers',2),'guideSearch',NULL,'price',JSON_OBJECT('currency','EUR','subtotalCents',8500,'taxCents',NULL,'taxIncluded',NULL,'discountCents',0,'totalCents',8500,'pricingStatus','illustrative','taxStatus','unconfigured'),'commission',JSON_OBJECT('policyVersion',1,'basisPoints',2500,'commissionCents',2125,'partnerNetCents',6375),'confirmationMode','providerAcceptance'),
 'EUR',8500,DATE_ADD(NOW(),INTERVAL 30 DAY))
ON DUPLICATE KEY UPDATE quote_json=VALUES(quote_json),expires_at=VALUES(expires_at);

INSERT INTO reservations(id,customer_user_id,partner_user_id,quote_id,service_type,service_id,status,payment_status,snapshot_json,version,idempotency_key)
VALUES ('80000000-0000-4000-8000-000000000001','10000000-0000-4000-8000-000000000001','10000000-0000-4000-8000-000000000002','70000000-0000-4000-8000-000000000001','driver','20000000-0000-4000-8000-000000000001','requested','notCollected',JSON_OBJECT(
 'customer',JSON_OBJECT('firstName','Sara','lastName','Ahmed','email','demo.customer@askustours.test','phone','+34600000001'),
 'quote',JSON_OBJECT('demo',true,'partnerId','10000000-0000-4000-8000-000000000002','packageOption','airportHotel','search',JSON_OBJECT('departure',DATE_FORMAT(DATE_ADD(NOW(),INTERVAL 14 DAY),'%Y-%m-%dT10:30:00+01:00'),'pickup','Demo Hotel','destination','Madrid Airport','passengers',2),'price',JSON_OBJECT('currency','EUR','totalCents',8500,'pricingStatus','illustrative'),'commission',JSON_OBJECT('policyVersion',1,'basisPoints',2500,'commissionCents',2125,'partnerNetCents',6375))
),1,'demo-reservation-request-001')
ON DUPLICATE KEY UPDATE partner_user_id=VALUES(partner_user_id),status='requested',payment_status='notCollected',snapshot_json=VALUES(snapshot_json),version=1;

-- Owned partner drafts and a customer itinerary request for admin workflow screens.
INSERT INTO partner_service_drafts(id,user_id,service_type,status,service_json,revision)
VALUES
('a0000000-0000-4000-8000-000000000001','10000000-0000-4000-8000-000000000002','driver','review',JSON_OBJECT('demo',true,'title','Demo airport transfer draft','city','madrid','seats',3,'bags',2,'languages',JSON_ARRAY('es','en'),'pricesCents',JSON_OBJECT('airportHotel',8500),'currency','EUR'),1),
('a0000000-0000-4000-8000-000000000002','10000000-0000-4000-8000-000000000002','guide','draft',JSON_OBJECT('demo',true,'title','Demo Madrid walking draft','city','madrid','priceCents',9500,'currency','EUR','hours',2,'maxPeople',8,'languages',JSON_ARRAY('es','en')),1)
ON DUPLICATE KEY UPDATE status=VALUES(status),service_json=VALUES(service_json),revision=VALUES(revision);

INSERT INTO trip_requests(id,user_id,status,answers_json,revision,privacy_version,submitted_at)
VALUES ('b0000000-0000-4000-8000-000000000001','10000000-0000-4000-8000-000000000001','submitted',JSON_OBJECT('demo',true,'cities',JSON_ARRAY('madrid','toledo'),'adults',2,'budget','mid-range','notes','Synthetic itinerary enquiry for Release 1 testing.'),1,'demo-v1',NOW())
ON DUPLICATE KEY UPDATE status='submitted',answers_json=VALUES(answers_json),submitted_at=NOW();

INSERT INTO wallet_ledger(id,user_id,wallet_type,entry_type,amount_minor,currency,status,reference_type,reference_id,description)
VALUES
('c0000000-0000-4000-8000-000000000001','10000000-0000-4000-8000-000000000001','customer','demo_credit',2500,'EUR','available','demo',NULL,'Synthetic customer wallet entry'),
('c0000000-0000-4000-8000-000000000002','10000000-0000-4000-8000-000000000002','partner','demo_earning',6375,'EUR','pending','reservation','80000000-0000-4000-8000-000000000001','Synthetic pending earning')
ON DUPLICATE KEY UPDATE amount_minor=VALUES(amount_minor),status=VALUES(status),description=VALUES(description);
