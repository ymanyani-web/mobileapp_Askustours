# Mobile backend re-verification response

Fecha: 19/11/2026 (Europe/Madrid).
Lecturas públicas verificadas: **2026-11-19 00:04:25 +01:00**.
API: https://api.askustours.com/v1
Commit de referencia del backend: **9cf564a**, según BACKEND_RESPONSE_TO_MOBILE_VERIFICATION_2026_11_18.md. El SHA desplegado no está expuesto por la API y no se ha verificado por Git/servidor.
Commit móvil: cambios locales sin commit; el repositorio no tiene HEAD. Huellas: build/private-v1-source-sha256.json.

## 1. Resultado

**Integración móvil: PASS en código, pruebas aisladas y compilación web. Verificación privada desplegada: NOT TESTED.** No se dispone de cuentas de staging ni proveedor OTP verificado; no se hicieron escrituras privadas en producción.

- dart analyze: PASS, sin incidencias.
- Suite completa: PASS, **126 pruebas**, 0 fallidas y 0 omitidas.
- Contrato privado v1: PASS en **17 pruebas nuevas**, incluidas en la suite.
- APK con ejemplos recuperados: **PASS**, APK debug compilado: build/app/outputs/flutter-apk/askus-api-v1-integrada.apk.
- Pruebas del APK de ejemplos: PASS, 7/7; precios, detalles, normas y acceso a las fichas completas de chófer conservados.
- Web release: PASS. Safari real no probado.
- iOS/dispositivos reales: NOT TESTED desde Windows.

Los ejemplos de las dos fotos del carrusel y los dos coches/guías siguen siendo una configuración explícita del APK de revisión (ASKUS_PRESENTATION_EXAMPLES=true). No se han restaurado archivos en el servidor ni modificado las colecciones del administrador. El contenido remoto normal continúa separando banners y ofertas.

## 2. GET /home

**PASS. HTTP 200.** Respuesta real: **0 banners y 1 oferta**.

La lista de banners vacía es ahora el resultado esperado: el backend omite los tres archivos ausentes. No se clasifica como fallo de API ni se vuelven a introducir URLs 404 en el cliente. Sigue pendiente re-subir los archivos deseados desde administración. La tarjeta de oferta continúa independiente del carrusel superior.

La prueba aislada del modo remoto verifica que una colección vacía no reutiliza otra colección. El APK de ejemplos mantiene las dos imágenes locales que pidió el titular, de forma independiente y explícita.

## 3. Imágenes devueltas y paquetes

Se realizó GET de **cada referencia de imagen devuelta por /home**. No se intentó descargar antiguos banners omitidos. Además, se verificó la imagen del paquete en colección y ficha.

| Procedencia | Método | HTTP | Content-Type | Resultado |
|---|---|---|---|---|
| /home → offers[0].imageUrl | GET | 200 | image/png | PASS |
| /home → banners | — | — | — | No devuelve imágenes: colección vacía esperada |
| /travel-packages → items[0].imageUrl | GET | 200 | image/svg+xml | PASS |
| /travel-packages/{id de la lista} → imageUrl | GET | 200 | image/svg+xml | PASS |

Las URLs de los archivos se omiten de este informe. Se obtienen de las propiedades indicadas; no hay URLs de subidas privadas, tokens, códigos ni datos personales en la evidencia resumida.

**Paquetes: PASS** en lista HTTP 200 y ficha HTTP 200. Ambas respuestas contienen destinations, image, imageUrl, itinerary, terms y availability, además de los campos anteriores.

Adaptación móvil aplicada:

- destinations acepta objetos con cityCode, nights y position; conserva el orden recibido y las noches por ciudad. Las noches se conservan al convertir el paquete a borrador del formulario existente.
- itinerary conserva títulos y descripciones localizados por día.
- image/imageUrl admite la imagen SVG real mediante flutter_svg; conserva fallback ante error.
- terms.cancellation se muestra en condiciones y terms.version se conserva como metadato.
- availability.requestOnly, from y to se representan como ventana de disponibilidad y solicitud sujeta a confirmación. Esa ventana no se convierte en fechas elegidas de viaje ni en reserva confirmada.
- El paquete sigue marcado demo=true. No se presenta como inventario comercial ni se habilitan cobros/hoteles/envío de planificación sin API.

B-MEDIA deja de ser una incidencia de URLs rotas devueltas por /home; resta la tarea operativa de re-subir imágenes. B-PACKAGE queda cerrado en las lecturas públicas comprobadas. B-LEGAL continúa bloqueado: privacy y terms siguen en draft-1.

## 4. Contrato privado v1 integrado

Fuente: MOBILE_PRIVATE_API_V1_EXAMPLES.md. No se copiaron identificadores de ejemplo como constantes de aplicación ni se guardó ningún token de los documentos.

| Flujo | Contrato/pruebas locales aisladas | Staging/producción privada | Evidencia y límite |
|---|---|---|---|
| GET /me/wallet | PASS | NOT TESTED | balance.amountMinor/currency, saldo firmado, movimientos, campos nullable y schemaVersion=1. No se calcula saldo sumando las últimas 50 filas. |
| GET /me/partner-wallet | PASS | NOT TESTED | pendingMinor, availableMinor, paidMinor y voidMinor separados; importes negativos y estados pending/available/paid/void. Sin pagos, recargas ni retirada. |
| GET/PUT /me/partner-profile | PASS de DTO y guardas de esquema | NOT TESTED | Proyección profile/top-level v1, foto profesional nullable, conservación del ID aunque falte el archivo. PROFILE_SCHEMA_CHANGED bloquea continuar guardando ese formato. No se afirma haber verificado alta/envío real. |
| GET/PUT /me/partner-services/{id}, driver | PASS | NOT TESTED | photoUploadIds como fuente, service.media como referencias, expectedRevision, conservación de traducciones al editar y ausencia de media en el cuerpo editable. |
| GET/PUT /me/partner-services/{id}, guide | PASS | NOT TESTED | photoUploadId y búsqueda de la referencia por ese ID en service.media, sin perder el ID ni traducciones al guardar. |
| POST /uploads | PASS | NOT TESTED | Propósitos exactos, dataUri completo, MIME JPG/PNG/WebP y límite de 5 MiB. Respuesta id/uploadId compatible. No se enviaron fotos privadas a producción durante esta revisión. |
| GET /uploads/{uploadId}, binario | PASS | NOT TESTED | Bytes de imagen sin envelope JSON; Bearer solo a origen/ruta API de medios, sin seguir redirecciones; 401 renueva sesión una vez, 404 JSON se maneja como ausencia, logout descarta respuesta/caché de imagen. |
| Errores de permisos/esquema | PASS aislado | NOT TESTED | PARTNER_REQUIRED, MEDIA_NOT_OWNED y PROFILE_SCHEMA_CHANGED tienen estados localizados. Las guardas HTTP existentes 401/403/409/422/429/503 se conservan. |

No se marcará un flujo privado como validado en servidor por pasar estos mocks. Los perfiles con distintos roles/estados, propiedad cruzada de subidas y el efecto real de guardar documentos se deben comprobar con staging.

## 5. Cambios móviles concretos

- lib/api_wallet.dart y lib/api_screens.dart: DTO v1 y vista de billetera de solo lectura, importes firmados del servidor, estados y etiquetas es/en/ar, movimientos sin descripción y categorías/estados desconocidos tratados de forma segura. Campos inválidos o esquema desconocido producen error, nunca saldo 0 inventado. La carga de billetera se reinicia al cambiar de propietario.
- lib/api_repository.dart: perfil básico v1 normalizado, cacheService consume service.media y mantiene relación URL/ID; elimina media de las escrituras; valida nombres de propósitos/MIME de upload. Se conserva paginación y control de revisión existentes.
- lib/partner_service_draft.dart y lib/guide_models.dart: lectura de referencias por los IDs fuente, conservación explícita de IDs sin archivo mediante marcador local, y preservación de los idiomas no editados. No se elimina automáticamente una foto porque el backend omita su preview.
- lib/api_draft_media.dart, lib/api_partner_screens.dart y lib/app_state.dart: profile-photo, professional-photo, driver-service-photo y guide-service-photo; identificación PNG/WebP/JPEG y nombres de archivo coherentes con MIME. El perfil profesional usa professional-photo.
- lib/api_client.dart: readMedia binario autenticado, límite de bytes, timeout, rechazo de origen/redirecciones, renovación 401 de vuelo único y comprobación de sesión/propietario antes de devolver bytes.
- lib/api_widgets.dart y lib/profile_screen.dart: imágenes privadas desde el cliente autenticado, caché de imagen de memoria desalojada al cerrar/cambiar propietario y soporte SVG público. Ningún Bearer se envía a CDN/host externo.
- lib/api_mappings.dart, lib/travel_package.dart y lib/trip_entry_screen.dart: forma publicada del paquete, destinos/noches, SVG, términos y ventana de solicitud, sin duplicar el formulario ni añadir endpoints.
- No se cambió el formato recuperado de las fichas de chófer ni sus tarifas orientativas, paquetes, condiciones y fotos. Las siete pruebas de presentación siguen pasando con la misma opción de compilación del APK.

## 6. Todo lo que sigue pendiente

| ID | Prioridad | Responsable | Endpoint/pantalla exacto | Evidencia | Acción solicitada al backend o siguiente paso |
|---|---|---|---|---|---|
| STAGING | High | Backend + Mobile | /me/wallet, /me/partner-wallet, /me/partner-profile, /me/partner-services/{id}, /uploads y /uploads/{id} | Solo contrato/mocks, sin cuentas entregadas | Facilitar URL/cuentas cliente y colaborador por canal privado; ejecutar matriz real y propiedad cruzada sin compartir secretos en Markdown. |
| OTP | Blocking | Backend | POST /auth/challenges; POST /auth/sessions | Configuración/entrega aún pendiente según handoff; no se enviaron códigos de producción | Configurar proveedor y probar entrega, expiración, reenvío, límites y revocación con destinatarios autorizados. |
| LEGAL | Blocking | Product/Operations | GET /content/privacy y /content/terms | Producción devuelve draft-1 | Entregar/publicar textos aprobados y versiones en es/en/ar. No se inventa texto legal en móvil. |
| INVENTORY | Blocking | Product/Operations | /driver-services, /guide-services y /travel-packages | Paquete comprobado demo=true; ejemplos locales explícitos | Cargar y revisar inventario, precios, imágenes autorizadas y condiciones comerciales antes del lanzamiento. |
| BANNERS | High | Product/Operations | /home.data.banners; admin content.php?type=banners | 0 banners, coherente con omisión de tres archivos ausentes | Editar y re-subir los tres archivos deseados; comprobar orden y GET 200. No requiere restaurar URLs 404 ni otro cambio móvil. |
| CREDENTIALS | Blocking | Infrastructure | Configuración de BD del backend | Rotación sigue pendiente según documento del backend; no se accedió a credenciales | Rotar secreto expuesto, confirmar variables/configuración no versionada y retirar fallback temporal. No enviar valores en este informe. |
| RELEASE | Blocking | Mobile + Infrastructure | Android firma de publicación; iOS Runner/Keychain | Build actual es debug; iOS no probado desde Windows | Configurar firma/store y probar instalación/actualización con Mac y perfiles; no publicar este APK como versión comercial. |
| DEVICES | High | Mobile | lib/api_client.dart: SecureCredentials; ApiImage; wallets | Tests en Flutter, no dispositivos con cuentas reales | Probar almacenamiento seguro/rotación/logout, fotos privadas y saldos en Android e iPhone. |
| OPS | High | Infrastructure | API/origen web autorizado y medios; backups/alertas | Lecturas públicas HTTPS correctas; operación completa no verificada | Confirmar CORS real de web, monitorización, alertas, backups/restauración y control de acceso a imágenes. |
| OUTBOX | High | Mobile | IdempotentAction; QuoteReviewScreen | Reintento idéntico en memoria; cierre de proceso no reconciliado | Persistir acción incierta cifrada por propietario y reconciliar antes de ofrecer otra solicitud. Sin endpoint nuevo inventado. |
| CONFLICTS | High | Mobile | PartnerServiceEditor._save; editor de GuideDraft | expectedRevision impide sobreescribir, pero UX de comparación/recarga pendiente | Comparar/recargar explícitamente después de SERVICE_REVISION_CONFLICT manteniendo edición hasta decisión del usuario. |
| FIELDS | Medium | Mobile | AuthScreen; ApiFailure | Errores se muestran; asociación/localización de todos los fields y expiresAt incompleta | Completar validación por control y estado de expiración OTP; comprobar con respuestas reales. |
| REVIEWS | Medium | Mobile | RemoteReviewsScreen | Reseñas remotas de lectura conservadas; no histograma/filtro completo | Reutilizar experiencia completa sin crear reseñas ni inventar agregados/verificación. |
| ACCESSIBILITY | Medium | Mobile | Billeteras, perfiles, editores y navegación es/en/ar | Pruebas de widgets/árabe pasan; auditoría real pendiente | Revisar teclado, lector, RTL, texto grande, Safari, offline y reintentos en dispositivos. |
| HISTORY | Medium | Mobile | ApiRepository.collection | Cursores/deduplicación correctos; carga todas las páginas antes de mostrar | Valorar carga bajo demanda/progreso para historiales grandes, manteniendo aislamiento y sin truncar a 20. |
| WEB_SESSION | Future | Mobile + Backend | MemoryCredentials web | Sesión solo en memoria, sin tokens en preferencias/localStorage | Acordar cookie segura/BFF si se necesita persistencia tras recargar. |
| REGULATED_DOCS | Future | Product/Operations + Backend | Futuro esquema documental de colaborador, fuera de /me/partner-profile v1 | No aprobado ni habilitado | Definir permisos, retención, borrado y esquema antes de recolectar documentos privados. |

Los endpoints de pagos/recargas, hoteles, envío operativo de planificación, cancelación/modificación y escritura de reseñas siguen fuera de Release 1. No se han añadido ni simulado como operaciones reales.

## 7. Evidencia y límites

- build/private-v1-public-verification.json: producción pública, fecha/hora, conteos, nombres de campos y estados/MIME. Sin URLs privadas ni identificadores personales.
- build/private-v1-analyze.txt: análisis sin incidencias.
- build/private-v1-test-results.jsonl: 126/126, sin fallos ni omitidas; HTTP opt-in solamente para servidor aislado de tests.
- test/private_api_v1_test.dart: 17 pruebas nuevas, con credenciales/IDs sintéticos generados en memoria. No depende de producción.
- build/private-v1-preview-test.txt: 7/7 del APK con ejemplos, incluidas fichas completas de chófer.
- build/private-v1-web-build.txt: web release compilado; no acredita despliegue real/CORS/Safari.
- build/private-v1-apk-build.txt: flutter build apk --debug --no-pub --dart-define=ASKUS_PRESENTATION_EXAMPLES=true, correcto; artefacto de revisión build/app/outputs/flutter-apk/askus-api-v1-integrada.apk.
- SDK: se repararon archivos ausentes del SDK temporal desde su misma revisión. Los errores de compilación iniciales eran del entorno y no evidencia de fallo del backend; el resultado final de Android se detalla arriba.
- No hay pruebas de staging ni escrituras/OTP privados en producción. No se incluyen tokens, contraseñas, datos personales ni URLs de subidas privadas.

**Conclusión del contrato:** los ejemplos aportados resuelven las dudas de forma del DTO v1 para esta integración. El siguiente bloqueo de verificación privada es el acceso/configuración de staging, no la falta de esos campos o endpoints. Esto no es aprobación de lanzamiento comercial.


## Customer traveler details — 25 November 2026

Additive, owner-scoped extension; no SQL migration. `GET /me`, `PATCH /me` and authenticated session user include `travelerDetails` for customers only. Common names/contact/billing fields retain their existing flat keys. Extra keys: birthDate, gender (male/female/unspecified), nationality, residenceCountry, documentType (passport/id), documentNumber, documentCountry, documentIssueDate, documentExpiryDate, region, taxNumber. Values are strings, optional when editing a profile, maximum 100 characters. Dates use YYYY-MM-DD; invalid/future birth or issue dates and expiry <= issue are rejected. Empty email/phone becomes SQL NULL. Unknown extra keys are rejected; collaborators cannot write this customer-only object. No verification flag or UID can be changed through it.

Quotes add `requiredTravelerFields`. Driver and guide defaults: firstName, lastName, phone. A trusted service JSON may declare an allowlisted `travelerRequirements` list; unrelated document data is never required by default. Reservation creation revalidates the current service policy and saved owner profile on the server, returning 422 TRAVELER_DETAILS_REQUIRED with travelerDetails.FIELD errors when missing. Existing verified-phone enforcement remains. Reservation snapshots copy only required traveler fields, not the entire optional document profile. Profiles can be cleared by the owner; account deletion follows existing account deletion/backup retention rather than pretending to erase backups immediately.

Flutter reuses the existing traveler form route, lets customers save incomplete details, and opens that form before a booking when required fields are missing. Customer profile displays the entry; professional onboarding is unchanged. Document numbers are excluded from local preview preference serialization. Phone strings and their prefixes use LTR and ASCII digits under Arabic RTL.

Validation: 13 isolated PHP policy/date/ownership-boundary checks, syntax checks, and focused Flutter tests. Production persistence and deployment require verification; passing a local helper test is not proof of live MySQL behavior.
