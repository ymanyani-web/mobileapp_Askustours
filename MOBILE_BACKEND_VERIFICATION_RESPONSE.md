# Mobile verification response to backend handoff

Date: 18/11/2026 (Europe/Madrid)
Mobile commit: no disponible; el repositorio local todavía no tiene HEAD. Cambios locales sin commit. Huellas de los archivos Dart: build/handoff-source-sha256.json.
Backend commit tested: 5b55f3a como referencia del handoff. Se comprobaron las respuestas desplegadas; el SHA exacto del servidor no puede acreditarse con la API disponible.
API base URL tested: https://api.askustours.com/v1

## Result summary

- Overall result: **PARTIAL**. Cambios móviles compilados y verificados con pruebas aisladas. Lecturas públicas reales realizadas. Flujos privados reales y entrega OTP no verificados.
- Analyze result: **PASS**, No issues found (build/handoff-analyze.txt).
- Test result: **PASS**, 102 aprobadas, 0 fallidas, 0 omitidas (build/handoff-test-results.jsonl). Hay 14 pruebas adicionales respecto a las 88 del informe anterior; incluyen tres colecciones paginadas, referencias de medios, calendario, inicio y estados HTTP.
- Android build result: **PASS**, APK debug de revisión en build/app/outputs/flutter-apk/app-debug.apk. No es una publicación firmada para tienda (build/handoff-apk-build.txt).
- iOS/web result: web release **PASS** en build/web (build/handoff-web-build.txt); iOS **NOT TESTED** desde Windows. Safari/iPhone real tampoco probado.
- Producción: únicamente lecturas públicas y comprobaciones de medios. No se enviaron OTP, no se crearon sesiones ni se modificaron reservas, colaboradores, servicios o disponibilidad en producción. No hay entorno/cuentas de staging facilitados.
- Hallazgos reales: una imagen de banner devuelve 404; la ficha pública del paquete demo todavía no contiene los nuevos campos; los legales siguen en draft-1. La configuración de proveedor OTP y rotación de credenciales siguen pendientes según el handoff, sin verificación independiente de su estado.

## Verification matrix

El resultado de cada fila se limita a la evidencia descrita. PASS aislado nunca significa PASS de un flujo privado desplegado.

| Area | Result (PASS/FAIL/NOT TESTED) | Evidence | Remaining work | Owner | Priority |
|---|---|---|---|---|---|
| Home banners | FAIL | GET /home real: 3 banners en orden 1,2,3 con imageUrl HTTPS; mapper/carrusel aislados PASS. GET y HEAD de la primera URL observada: 404. | B-MEDIA: reponer archivo o actualizar referencia; verificar imágenes completas en dispositivos y web. | Backend / Infrastructure | High |
| Featured offers | PASS | GET /home real: 1 oferta, independiente de 3 banners. Imagen de oferta comprobada con HEAD 200 image/png. Prueba de ofertas vacías y ninguna reutilización de banners PASS. | Validar descarga visual en dispositivo/Safari y cargar contenido comercial aprobado. | Mobile / Product/Operations | High |
| Authentication/OTP | NOT TESTED | No se dispone de cuenta/proveedor de pruebas. Tests aislados de 401, refresh concurrente, 429 y 503 sin falso código enviado PASS. | H1/H2/M1; proveedor, caducidad y matriz real cliente/colaborador. | Backend / Mobile | Blocking |
| Pagination | NOT TESTED | Implementación y tests aislados PASS para reservas, borradores y trabajos: cursor opaco, filtros conservados, duplicados actualizados por ID y rechazo de cursor repetido. Sin acceso privado real. | Probar más de 20 registros por colección, cambios concurrentes y cambio de cuenta contra staging. | Mobile / Backend | High |
| Partner media | NOT TESTED | Pruebas aisladas PASS: uploadId/url, vista previa URL, conservación del ID al editar chófer/guía; fotos de perfil y servicios con Bearer solo al origen API (test de no filtración a CDN). Sin lecturas privadas reales. | Confirmar payloads privados exactos y probar GET /uploads/{uploadId} con propietario/no propietario. | Mobile / Backend | High |
| Calendar | PASS | GET real con from=2026-11-18 y to=2026-12-18 devuelve range, horizonDays=120, explicitDatesOnly=true y dos fechas. Cliente usa from/to del mes; prueba de febrero bisiesto PASS. | Mantener fechas operativas; probar cierre/rango desde dashboard y dispositivo. Fechas ausentes permanecen no disponibles. | Product/Operations / Mobile | High |
| Catalog metadata | PASS | GET /metadata/catalog real schemaVersion=1: ciudades madrid/barcelona/malaga, categorías executive/van/standard, idiomas es/en/ar y EUR. Bootstrap lo consume. Desconocidos no se convierten en Madrid; editor rechaza borrador no soportado. | Si se amplía catálogo, ampliar enums/etiquetas antes de permitir edición; probar publicación desde dashboard. | Mobile / Dashboard | Medium |
| Wallet/profile | NOT TESTED | Perfil básico preservado. Etiquetas conocidas de billetera localizadas; test árabe PASS. Handoff declara schemaVersion=1, pero no aporta ejemplos completos de sus respuestas privadas y no se leyó ninguna cuenta. | Confirmar claves/unidades/tipos de movimientos y foto profesional en el contrato; verificar con staging. Sin pagos ni saldos calculados por el móvil. | Backend / Mobile | High |
| Travel packages | FAIL | Lista y ficha reales demo devuelven 200 sin destinations, image/imageUrl ni itinerary. Mapper y presentación de esos campos probados aisladamente; preservan orden y traducciones cuando están presentes. | B-PACKAGE: actualizar fixture/datos desplegados y entregar payload documentado. No se inventa ruta para el ejemplo incompleto. | Backend / Product/Operations | High |
| Error handling | PASS | Tests aislados de estados UI 401/403/409/422/429/503; suite anterior cubre rotación/timeout de respuesta/idempotencia/conflicto. Legal/body y requestId tratados defensivamente. | Vincular errores a todos los controles y probar estados reales de permisos, revisión y proveedor en staging; H4 para conflicto de editor. | Mobile | High |

### Cambios móviles aplicados

- lib/api_mappings.dart: mapBanners y mapOffers separados, imageUrl (compatible con image), subtítulo/descripción, detalles y enlace opcional. El cliente conserva el orden recibido, sin reordenar por título ni mezclar colecciones.
- lib/home_screen.dart: carrusel superior solo banners; tarjetas inferiores solo offers; estado vacío explícito; resto de secciones/shortcuts preservados. Enlaces HTTPS sin credenciales en URL abren navegador; si no se puede abrir, se conserva la ficha existente. No se ejecutan enlaces javascript ni URLs con credenciales.
- lib/api_repository.dart: bootstrap añade metadata/catalog; rangos from/to; cursores opacos en reservas, borradores y trabajos; deduplicación por ID, detección de cursor repetido y comprobación de propietario entre páginas. Actualmente carga todas las páginas antes de entregar la lista, sin ocultar fallos parciales como éxito.
- lib/partner_service_draft.dart, lib/guide_models.dart, lib/partner_services_screen.dart y lib/api_partner_screens.dart: lectura de referencias URL/ID, conservación del identificador al guardar y presentación de imágenes de red. La referencia profesional se muestra si el servidor la devuelve.
- lib/driver_calendar_screen.dart: límite de navegación según horizonte del servidor y disponibilidad únicamente de fechas explícitas. El presupuesto final del servidor sigue siendo autoridad y no se bloquea capacidad por consultar.
- lib/travel_package.dart y lib/trip_entry_screen.dart: conserva/presenta itinerario localizado y destinos ordenados recibidos; planificación sigue local y envío no operativo.
- lib/api_widgets.dart y lib/api_screens.dart: estados HTTP apropiados, etiquetas conocidas de billetera en es/en/ar. No se convierte una solicitud en cobro/confirmación ni se muestran importes inventados.
- Las dependencias siguen resueltas, el modo normal usa API real y los fixtures requieren inyección explícita. No se añadió fallback de catálogo local por fallo de red.

## Backend issues still found

### B-MEDIA

- ID: B-MEDIA
- Priority: High
- Endpoint: imageUrl del primer elemento data.banners observado en GET /v1/home; host público admin.askustours.com.
- Exact request shape, with secrets removed: GET /v1/home sin Authorization; después GET <data.banners[0].imageUrl>, sin Authorization ni cuerpo. También HEAD de esa URL.
- HTTP status and error code: inicio 200; medio 404 para GET y HEAD, sin envelope JSON de API.
- Expected behavior: la URL publicada permite descargar una imagen válida con HTTP 200.
- Actual behavior: el archivo del primer banner observado no existe en esa URL. La oferta comprobada sí responde 200 image/png. No se afirma que fallen todos los banners.
- Reproduction steps: consultar home, comprobar su primera referencia y descargarla. Durante la sesión una relectura posterior de home agotó el tiempo; no pudo confirmarse si un administrador había cambiado la referencia entre las lecturas. El 404 directo del archivo observado sí está confirmado con ambos métodos.
- Mobile file/symbol affected: lib/home_screen.dart: _OfferCarousel; lib/api_widgets.dart: ApiImage. La app muestra fallback, sin sustituirlo por oferta/demo.
- Requested backend change: reponer el medio o actualizar la referencia activa; validar existencia de archivo antes de publicar. Confirmar nuevamente la referencia actual tras resolver la incidencia.
- Evidence: build/handoff-banner-read-check.json, build/handoff-public-media-checks.json. Las URLs de archivos se omiten del informe; se derivan de la respuesta pública, no de datos privados.

### B-PACKAGE

- ID: B-PACKAGE
- Priority: High
- Endpoint: GET /v1/travel-packages y GET /v1/travel-packages/{id obtenido de la lista}.
- Exact request shape, with secrets removed: GET, sin body, sin Authorization; ID tomado de data.items[0].id, no constante incorporada al móvil.
- HTTP status and error code: 200, sin código de error.
- Expected behavior: el fixture descrito por el handoff demuestra destinos ordenados, imagen y contenido de itinerario, con su forma documentada.
- Actual behavior: en lista y ficha se observaron únicamente id, demo, title, description, durationDays, priceFromMinor, currency, active, includes y excludes. Faltan los tres campos anunciados. No se deduce que todo el código backend esté sin desplegar: puede ser un dato/fixture previo no actualizado.
- Reproduction steps: listar paquetes, abrir ficha del primero y comparar claves con el handoff.
- Mobile file/symbol affected: lib/api_mappings.dart: mapPackages; lib/trip_entry_screen.dart: TravelPackageDetailScreen.
- Requested backend change: actualizar el fixture/datos aplicables y proporcionar un ejemplo canónico de destinations, image/imageUrl e itinerary; verificar validación de publicación con el dashboard.
- Evidence: build/handoff-public-verification.json (sin IDs ni URLs privadas).

### B-LEGAL

- ID: B-LEGAL
- Priority: Blocking (publicación comercial)
- Endpoint: GET /v1/content/privacy y GET /v1/content/terms.
- Exact request shape, with secrets removed: GET sin body ni Authorization.
- HTTP status and error code: 200, sin error; version=draft-1.
- Expected behavior: textos aprobados en es/en/ar antes de aceptar condiciones comerciales.
- Actual behavior: contenido pendiente de aprobación. Se confirma el bloqueo de contenido, no un fallo del transporte.
- Reproduction steps: leer ambos slugs y revisar version/body.
- Mobile file/symbol affected: lib/account_screens.dart: enlaces legales; lib/api_screens.dart: RemoteReadOnlyScreen.
- Requested backend change: publicar contenido aprobado/versionado desde administración; responsable principal Product/Operations.

### Pendientes operativos declarados, no presentados como fallos HTTP comprobados

- Blocking — Backend: configurar proveedor OTP y verificar entrega real. No se envió desafío a números/correos arbitrarios durante esta revisión.
- Blocking — Infrastructure: el handoff exige rotar la credencial de base de datos expuesta en historial y mover su reemplazo a configuración no versionada. No se leyó ni reprodujo ninguna credencial; cumplimiento no comprobado desde este repositorio móvil.
- Blocking — Product/Operations: catálogo observado sigue siendo demo. No usarlo como oferta comercial; disponibilidad observada solo contiene dos fechas. No se probó publicación de demos ni mutaciones administrativas en producción.
- High — Infrastructure: comprobar CORS del origen web definitivo, acceso a medios, alertas, TLS, backups/restauración y rotación. Una petición posterior a home agotó el tiempo; eso por sí solo no acredita caída de servidor ni identifica su causa.
- High — Dashboard/Backend: ciclo compartido aprobación → publicación → disponibilidad → asignación → aceptación sigue sin probarse con roles reales. No se revisó código/BD del backend local ni se aplicaron migraciones de producción desde la app.

## Mobile work still required

| ID | Priority | File/symbol | Concrete next action |
|---|---|---|---|
| H1 | High | lib/api_client.dart: SecureCredentials; Android/iOS | Probar Keychain/almacenamiento seguro real, restauración, rotación, logout, actualización y cambio de cuenta en ambos dispositivos. |
| H2 | High | test/api_integration_test.dart; test/backend_handoff_test.dart; ApiRepository | Ejecutar matriz privada en staging con cuentas cliente/colaborador pendiente/aprobado/suspendido, incluyendo >20 registros, permisos y uploads de otro propietario. |
| H3 | High | lib/api_client.dart: IdempotentAction; lib/api_screens.dart: QuoteReviewScreen | Persistir acción incierta y cuerpo cifrados por usuario y reconciliar tras muerte del proceso. En memoria sí se conserva clave/cuerpo al reintentar. |
| H4 | High | lib/partner_services_screen.dart: _save; lib/guide_screens.dart: editor save | Incorporar comparar/recargar explícitos tras SERVICE_REVISION_CONFLICT, conservando edición local hasta decisión del usuario. El repositorio ya impide sobrescritura silenciosa. |
| M1 | Medium | lib/auth_screen.dart; lib/api_widgets.dart: ApiFailure | Asociar todos los fields del servidor a controles, traducir etiquetas y mostrar expiresAt del OTP; comprobar reenvío/caducidad real. |
| M3 | High | lib/api_screens.dart: RemoteReadOnlyScreen; ApiRepository.wallet | Con ejemplos canónicos v1, completar etiquetas/tipos/estados de movimientos y verificar unidades y ausencia de importes; probar /me y /me/partner-profile v1 sin perder campos. Las etiquetas actuales no acreditan compatibilidad completa del DTO. |
| M4 | Medium | lib/api_screens.dart: RemoteReviewsScreen; lib/driver_reviews_screen.dart | Reutilizar experiencia completa de reseñas remotas, histograma/filtros sin inventar agregados o verificación. |
| M5 | High | pantallas privadas; lib/api_widgets.dart; test/backend_handoff_test.dart | Auditar Android/iPhone/Safari real en es/en/ar, RTL, lector, teclado, texto grande, offline y reintento; probar descarga visual de medios y apertura de enlaces. |
| M2 | Medium | lib/api_mappings.dart: apiCity; DriverCity/VehicleCategory; editores | Si metadata introduce códigos nuevos, ampliar enums/traducciones o catálogo dinámico; conservar rechazo explícito de edición desconocida. Los códigos publicados actuales sí coinciden con los conocidos. |
| M6-UX | Medium | lib/api_repository.dart: collection | Si crece mucho el historial, sustituir carga completa por carga bajo demanda/estado de progreso, preservando cursor, deduplicación y aislamiento de cuenta ya implementados. No hay truncamiento silencioso a 20. |
| F4 | Future | lib/api_client.dart: MemoryCredentials en web | Acordar cookie segura/BFF si se requiere sesión persistente en navegador. No mover refresh tokens a preferencias/localStorage. |
| RELEASE | Blocking | android/app/build.gradle.kts; ios/Runner/Keychain.entitlements | Preparar firma comercial y ejecutar compilación/instalación iOS con Mac y perfiles; APK actual es debug y no valida distribución por tiendas. |

Los pendientes anteriores son específicos; no deben interpretarse como integración completa solo porque las pruebas aisladas pasan. La planificación, cobros, recargas, hoteles y operaciones sin endpoint mantienen su alcance anterior local/deshabilitado. No se ha intentado resolverlos inventando endpoints.

## Verification evidence

- build/handoff-analyze.txt: dart analyze, No issues found.
- build/handoff-test-results.jsonl: flutter test --no-pub --dart-define=ASKUS_ALLOW_HTTP=true --reporter json, 102/102. HTTP opt-in es solo para servidor de tests; no se usó en los builds.
- test/backend_handoff_test.dart: separación y vacío de colecciones; orden del servidor; imageUrl/subtitle; HTTPS seguro; metadata; tres rutas paginadas/filtros/duplicados; cursor repetido; rango bisiesto; medios propios y revisiones; desconocidos sin Madrid; destinos/itinerario localizado; widget de inicio; billetera árabe; errores HTTP.
- build/handoff-apk-build.txt: flutter build apk --debug --no-pub, correcto. APK: build/app/outputs/flutter-apk/app-debug.apk.
- build/handoff-web-build.txt: flutter build web --release --no-pub, correcto. No acredita despliegue web ni pruebas reales de Safari.
- build/handoff-public-verification.json: lecturas 200 reales y resumen sin IDs de clientes, tokens, códigos ni URLs privadas: home 3/1, metadata v1, rango/horizonte y paquete incompleto.
- build/handoff-public-media-checks.json: un medio de oferta 200 image/png; primer banner inicialmente no verificado por fallo de petición. build/handoff-banner-read-check.json confirma luego GET 404 del archivo de banner observado; también se observó HEAD 404.
- Las llamadas que agotaron el tiempo se distinguen de respuestas HTTP. No se atribuye un timeout local automáticamente al backend.
- No hay commit móvil ni staging acreditados; no se inventa un SHA ni se afirma que se haya probado el backend privado real.

## Final question

Is the backend contract sufficient for the next mobile integration stage? **No**, para verificar de extremo a extremo todas las áreas privadas anunciadas. **Sí** es suficiente para la adaptación aplicada de home, metadata y rangos/cursor, con sus límites de prueba descritos.

Para cerrar la siguiente etapa se necesitan exactamente:

1. Ejemplos canónicos/OpenAPI de /me/wallet y /me/partner-wallet v1: nombres, unidades/nullability de saldos y campos/tipos/estados de cada movimiento. No basta con indicar que es schemaVersion 1.
2. Payload completo de /me/partner-profile y /me/partner-services/{id} v1, incluido nombre y forma exactos del contenedor de fotos (uploadId/id, url, propósito), y formato de respuesta de GET /uploads/{uploadId}. El móvil ya admite referencias url/ID y conserva ID; falta acreditar correspondencia real.
3. Paquete publicado de prueba con destinations ordenado, image/imageUrl e itinerary y definición exacta de sus formas (códigos/objetos, traducciones, estructura por días). El fixture accesible no los devuelve.
4. URL de staging/cuentas y proveedor OTP de prueba para verificar permisos, cursores, medios y revisión. Esto es acceso/configuración de pruebas, no petición de secretos en código ni de endpoints inventados.

No se solicita un endpoint nuevo para estas áreas: se pide completar documentación/datos y acceso de prueba de los endpoints ya anunciados.
