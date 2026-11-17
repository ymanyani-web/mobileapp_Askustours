# Release 1 — integración móvil y trabajo pendiente

Fecha: 17/11/2026. Estado: integración conectada y compilada; no es autorización de lanzamiento comercial. Inventario único: **24 pendientes** (4 Blocking, 8 High, 8 Medium, 4 Future). Responsables: Backend 10, Mobile 9, Infrastructure 2, Product/Operations 2, Dashboard 1. Los identificadores siguientes no se cuentan dos veces aunque aparezcan en varias secciones.

## 1. Integration status

- Conectado: cliente HTTPS configurable, envelopes, Bearer, almacenamiento seguro nativo, rotación de refresh de vuelo único, reintento una vez, cierre y aislamiento por usuario. Web usa memoria; nunca localStorage para tokens. Credenciales, OTP y cuerpos privados no se registran.
- Catálogos públicos: inicio, chóferes, ficha, calendario explícito, reseñas, guías/ficha y paquetes/ficha. Traducción solicitada → español → primera disponible; estados vacíos sin sustituirlos por ejemplos locales. HTTP solo con opt-in de desarrollo y nunca en release; TLS sin bypass.
- Autenticación conectada: desafío, verificar, reenvío y cooldown; rol devuelto por servidor; no hay acceso de prueba en ejecución normal. FEATURE_NOT_CONFIGURED no se representa como código enviado.
- Repositorios privados y pantallas: /me, foto/upload, favoritos, cesta de chófer/guía con UUID estable, presupuestos, solicitudes e historial, perfil y envío de colaborador, borradores de servicios/versiones, servicios asignados/aceptar/rechazar y billeteras de lectura. Conectado significa código y pruebas aisladas, no validación privada en producción.
- Cotización final: fecha con zona, antelación de 12h, céntimos enteros, caducidad y disponibilidad del servidor. Reintento idéntico mantiene Idempotency-Key y cuerpo; no se cobra ni se afirma confirmación. Tras 422 de solicitud se permite corregir datos; después de resultado incierto se conserva la acción.
- Cesta/favoritos invitados: importación explícita, solo coincidencias inequívocas, sin cargar datos de una cuenta previa. Cambios de cuenta y cierre limpian cachés privadas; catálogo público se conserva.
- Parcial: perfil profesional básico según contrato, errores por campo, presentación de billeteras/reseñas, edición tras conflictos y recuperación de acciones después de cerrar el proceso. Detalles en inventario.
- Local permitido: planificación y solicitudes de itinerario, sin envío operativo. Los fixtures locales solo se activan mediante inyección explícita useBackend:false en pruebas; main usa backend. No se usa fallo de red como permiso para entrar en modo local.
- Deshabilitado/no iniciado deliberadamente: cobros, recargas, hoteles, publicación/aprobación desde móvil, cancelación/modificación de reservas y creación de reseñas fuera del contrato. La comisión se lee de términos/precios del servidor; no se impone el 25 % localmente.

## 2. Mobile changes still required

| ID | Prioridad | Responsable | Archivo/símbolo o API | Pendiente e impacto/riesgo | Acción concreta |
|---|---|---|---|---|---|
| H1 | High | Mobile | lib/api_client.dart: SecureCredentials; Android/iOS | Pruebas usan MemoryCredentials: la escritura/rotación real del llavero todavía no está verificada en dispositivos. | Verificar persistencia, logout, reinstalación y actualización en Android e iPhone; confirmar política de copias y Keychain. |
| H2 | High | Mobile | test/api_integration_test.dart; /auth/sessions; /me/* | Flujos privados probados con servidor aislado, no con backend de staging y cuentas reales; podrían existir diferencias de contrato. | Ejecutar cliente nuevo/existente, colaborador pendiente/aprobado/suspendido y cambio de cuenta en staging con OTP real. |
| H3 | High | Mobile | lib/api_client.dart: IdempotentAction; lib/api_screens.dart: QuoteReviewScreen | Clave y cuerpo se conservan durante reintentos en memoria; cerrar el proceso pierde una operación de resultado incierto. | Persistir un outbox cifrado por usuario y reconciliar solicitudes antes de ofrecer nueva acción tras reiniciar. |
| H4 | High | Mobile | lib/partner_services_screen.dart: _save; lib/guide_screens.dart: editor save | Repositorio rechaza revisiones antiguas sin sobrescribir, pero los editores aún muestran error genérico y no permiten comparar/recargar en pantalla. | Mostrar conflicto localizado, conservar cambios y ofrecer recarga explícita con advertencia antes de descartar. |
| M1 | Medium | Mobile | lib/auth_screen.dart; lib/api_widgets.dart: ApiFailure; lib/api_screens.dart | Se muestran campos de error del servidor, pero no todos están asociados al control ni traducidos; expiresAt del desafío depende de rechazo del servidor. | Vincular fields a controles, localizar etiquetas y mostrar caducidad OTP con reenvío según respuesta. |
| M3 | Medium | Mobile | lib/api_screens.dart: RemoteReadOnlyScreen | Billeteras son solo lectura y no inventan saldos; algunas claves de respuesta se presentan como etiquetas técnicas. | Acordar DTO de movimientos/saldos y localizar todas las etiquetas y estados en es/en/ar. |
| M4 | Medium | Mobile | lib/api_screens.dart: RemoteReviewsScreen | Reseñas reales se leen; la pantalla simplificada no conserva todavía histograma/filtro de estrellas del componente local anterior. | Adaptar DriverReviewsScreen a reseñas remotas sin inventar agregados ni verificación. |
| M5 | Medium | Mobile | lib/api_*.dart; test/api_integration_test.dart | Falta prueba manual de lector de pantalla, teclado, RTL y tamaños extremos para todas las ramas privadas. | Auditar Android/iPhone en es/en/ar con texto ampliado, offline y errores de campo; corregir hallazgos antes de publicación. |
| F4 | Future | Mobile | lib/api_client.dart: MemoryCredentials en web | Web conserva sesión solo en memoria para evitar tokens en almacenamiento ordinario; recargar exige entrar de nuevo. | Acordar sesión web mediante cookie segura/BFF u otra solución revisada antes de exigir persistencia en Safari. |

## 3. Backend changes still required

| ID | Prioridad | Responsable | Archivo/símbolo o API | Pendiente e impacto/riesgo | Acción concreta |
|---|---|---|---|---|---|
| B1 | Blocking | Backend | POST /auth/challenges | El documento indica proveedor OTP sin configurar; no se ha probado entrega real. Nadie podrá completar acceso real. | Configurar SMS/correo, límites y cuentas de staging; demostrar entrega, reenvío y verificación. |
| H7 | High | Backend | GET /driver-services/{id}/calendar | La respuesta pública observada solo incluía dos fechas. El cliente deshabilita fechas ausentes; esto limita las reservas disponibles. | Definir horizonte de calendario y cargar disponibilidad suficiente. No se ha supuesto soporte de filtro month. |
| H8 | High | Backend | GET /me/partner-services; GET /me/partner-services/{id} | Si una foto guardada devuelve solo uploadId sin URL, se preserva su ID pero el editor no puede previsualizarla. | Devolver referencias de medios con URL autorizada y uploadId propio; documentar expiración/acceso. |
| M2 | Medium | Backend | GET /driver-services; GET /guide-services; lib/api_mappings.dart | Modelos de ciudades/categorías móviles son enums cerrados; registros desconocidos se omiten conservando JSON, sin inventar equivalencias. | Acordar códigos soportados o catálogo dinámico; añadir cobertura para valores desconocidos antes de ampliar ciudades. |
| M6 | Medium | Backend | GET /me/reservations; /me/partner-services; /me/partner-jobs | El contrato no proporciona paginación real con cursor; limit=20 puede impedir consultar historial completo. | Implementar cursor/hasMore estable y documentarlo antes de añadir carga incremental móvil. |
| M7 | Medium | Backend | PUT /me/partner-profile | Solo se guarda el perfil profesional básico admitido; el asistente local amplio no tiene contrato para metadatos de vehículos/documentos y revisión detallada. | Definir esquema validado de esos datos y acceso privado; no añadir campos arbitrarios a este endpoint. |
| M8 | Medium | Backend | GET /travel-packages/{id}; lib/api_mappings.dart: mapPackages | Paquete observado sin destinos ni imagen; la vista pública funciona con campos existentes, pero no puede precargar un itinerario completo fiable. | Añadir destinos ordenados, medios y contenido de itinerario documentados; mantener solicitudes de planificación locales hasta su API. |
| F1 | Future | Backend | /quotes; /reservation-requests; /me/wallet | Release 1 no cobra ni recarga billetera; solicitud requested/notCollected no es venta confirmada. | Diseñar pagos/recargas, impuestos y conciliación mediante proveedor autorizado antes de habilitar cobros; no enviar tarjetas por esta API. |
| F2 | Future | Backend | lib/trip_planner_screen.dart; TripPlanStore; solicitudes de itinerario | No hay API de planificación/hoteles; borradores e historial permitidos permanecen locales y envío deshabilitado. | Definir endpoints y consentimiento para planificación; separar claramente solicitud, asesoramiento y contratación. |
| F3 | Future | Backend | /me/reservations/{id}; /me/partner-services/{id} | No existen operaciones de cancelar/modificar reserva, publicar/borrar servicio ni escribir reseñas en el contrato. | Mantener controles ausentes/deshabilitados; diseñar permisos, versiones e idempotencia antes de añadir esas operaciones. |

## 4. Contract mismatches

- M2: enums de ciudad/categoría cerrados frente a catálogo ampliable; resolver con códigos acordados/catálogo dinámico, sin elegir Madrid como fallback.
- H8: uploadId no equivale a URL. El ID se preserva al editar; falta URL para vista previa cuando el servidor no la devuelve. Resolver incluyendo ambos campos documentados.
- M7: asistente profesional detallado frente a PUT básico. Se usa el formulario básico remoto; no se finge sincronización de documentación/vehículos.
- M8: paquete observado sin destinos/imagen frente a modelo de planificación. No se inventan paradas: ampliar DTO público antes de precargar ruta.
- M6: meta.pagination sin cursor funcional. Cliente usa limit=20 en listas privadas y no inventa páginas.
- M3: nombres/semántica de saldos necesitan DTO estable y etiquetas completas; lectura no implica saldo gastable ni autorización de retirada.
- Dinero: catálogo convierte minor a presentación; total de solicitud solo entero del servidor. Fechas enviadas con offset; estados desconocidos no se convierten en aprobado/pagado. booleanos 0/1 se interpretan explícitamente. Medios relativos resuelven contra origen, no /v1; Bearer solo se añade a imágenes del mismo origen.

## 5. Production blockers

| ID | Prioridad | Responsable | Archivo/símbolo o API | Pendiente e impacto/riesgo | Acción concreta |
|---|---|---|---|---|---|
| B2 | Blocking | Product/Operations | GET /content/privacy; GET /content/terms | Las respuestas públicas observadas contienen draft-1 y contenido pendiente de aprobación. Riesgo legal de aceptar textos provisionales. | Aprobar y publicar textos definitivos/versionados en los tres idiomas. |
| B3 | Blocking | Product/Operations | GET /driver-services; /guide-services; /travel-packages | Los tres catálogos observados contienen ejemplos demo:true. No hay inventario comercial verificado. | Cargar servicios reales revisados, precios, fotos autorizadas y condiciones; retirar demos del catálogo comercial. |
| B4 | Blocking | Infrastructure | android/app/build.gradle.kts; ios/Runner/Keychain.entitlements | APK entregado de depuración; no se ha validado distribución firmada ni iOS desde Windows. | Preparar firma de publicación, perfiles iOS, entitlements y pruebas de instalación/actualización antes de tiendas. |
| H5 | High | Infrastructure | ASKUS_API_URL; API CORS; TLS | La URL es configurable; no se ha validado despliegue web, CORS con credenciales Bearer, observabilidad ni recuperación de backups. | Preparar staging y origen web autorizado; comprobar TLS, logs sin datos privados, alertas y restauración. |
| H6 | High | Dashboard | GET /me/partner-profile; PUT /me/partner-services/{id}; /me/partner-jobs | Móvil no aprueba ni publica; no se ha verificado el ciclo administrador-aprobación-publicación-asignación con este backend. | Verificar estados compartidos y permisos desde dashboard; publicar/asignar solo tras revisión y comprobar su reflejo móvil. |

Además B1 impide completar registro; H1/H2 exigen validación real antes de publicación. El APK es para revisión técnica. No introducir cobros para resolver estos bloqueos; F1 es un alcance posterior.

## 6. Testing still required

H1: dispositivos/llavero real. H2: matriz privada contra staging con OTP. M5: accesibilidad, RTL, texto ampliado y offline de todas las ramas. H5: CORS/despliegue, alertas/restauración. H6: ciclo administrador completo. H3: añadir recuperación tras muerte de proceso cuando se implemente outbox. H4: añadir prueba de conservar/comparar/recargar conflicto al implementar su UX.

No hay pruebas fallidas en la suite final (88/88), ni pruebas marcadas como omitidas para esconder una dependencia de producción. Lo no ejecutado: entrega SMS/correo, operaciones privadas en servidor real, Safari/iPhone real, build iOS, publicación en tiendas y cobros. Faltan proveedor/cuentas de staging, dispositivos y firma; no se enviaron OTP ni se hicieron escrituras privadas a producción.

## 7. Recommended priority plan

1. **Blocking (4)**: B1 proveedor → B2 legales → B3 inventario → B4 firma/distribución.
2. **High (8)**: H5 staging → H2 matriz de roles → H1 dispositivos → H3 recuperación de acción → H4 conflictos → H6 administración → H7 disponibilidad → H8 medios.
3. **Medium (8)**: M1 errores/OTP → M2 enums → M3 billetera → M4 reseñas → M5 accesibilidad → M6 paginación → M7 perfil ampliado → M8 paquetes.
4. **Future (4)**: F1 pagos → F2 planificación → F3 operaciones adicionales → F4 persistencia web segura. Definir alcance antes de habilitarlas.

## 8. Backend handoff checklist

- [ ] B1: configurar proveedores y compartir cuentas de staging, sin secretos dentro de la app.
- [ ] B2/B3: publicar legales aprobados e inventario no ficticio.
- [ ] H7: cargar calendario operativo y documentar horizonte; no se presupone query month.
- [ ] H8: devolver medios propios con ID y URL consumible/autorizada.
- [ ] M2/M3/M7/M8: acordar enums, wallet DTO, perfil ampliado y paquete con destinos.
- [ ] M6: documentar paginación real antes de implementar siguiente página móvil.
- [ ] H6: comprobar aprobación/publicación/asignación desde dashboard con expectedRevision, expectedVersion y policyVersion.
- [ ] H2/H5: ejecutar matriz privada en staging, CORS, errores 401/403/409/422/429 y revocación/rotación real.
- [ ] F1/F2/F3: mantener sin cobros/envío de planificación ni controles no soportados hasta nuevo contrato.

## 9. Final verification evidence

- SDK Flutter 3.47.9 / Dart 3.13.7. Dependencias resueltas con http y flutter_secure_storage 11.2; Android desactiva backup e iOS declara Keychain entitlements. Firma/ejecución iOS no verificada.
- `dart analyze`: **No issues found**. Evidencia local: build/api-analysis.txt.
- `flutter test --no-pub --dart-define=ASKUS_ALLOW_HTTP=true --reporter json`: **88 aprobadas, 0 fallidas**. build/api-test-results.jsonl. ASKUS_ALLOW_HTTP únicamente habilita servidor aislado en pruebas; no se usa en artefactos de entrega.
- Nueve pruebas nuevas en test/api_integration_test.dart: envelopes/429/cooldown, ocho 401 concurrentes con un refresh, refresh fallido/cachés, refresh tardío tras logout, DTO/minor/offset/medios, idempotencia tras pérdida de respuesta, revisión 409, HTTP aislado con cliente/cotización/solicitud/replay/colaborador, y widget 503 sin falso OTP ni acceso de prueba.
- `flutter build apk --debug --no-pub`: correcto; build/app/outputs/flutter-apk/app-debug.apk. Log build/api-apk-build.txt. Destino predeterminado producción HTTPS, sin credenciales incluidas.
- `flutter build web --release --no-pub`: correcto; build/web. Log build/api-web-build.txt. Compilar no acredita despliegue ni acceso Safari real.
- Lecturas públicas realizadas contra https://api.askustours.com/v1: /home, /driver-services, ficha obtenida del catálogo, /calendar, /reviews, /guide-services, /travel-packages, /content/privacy y /content/terms. Respuestas 200: inicio con colecciones vacías y planningFee de 2000 EUR minor; catálogos demo; legales draft-1 pendientes. Reseña sintética con authorName/comment y verified=0. Calendario con dos fechas explícitas; una consulta exploratoria month no demuestra soporte del filtro y se retiró del cliente por no figurar en contrato.
- No se usó staging: no se proporcionó URL/cuenta. No se incluyeron IDs demo de producción, tokens ni códigos OTP en fuente. No se verificó entrega OTP real ni flujo privado real: la indisponibilidad del proveedor procede del documento, no de un POST de producción realizado durante esta tarea.
