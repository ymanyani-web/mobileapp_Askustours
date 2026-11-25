# Ampliación del registro profesional existente

## Arquitectura

El usuario confirmó conservar Firebase Authentication y el backend PHP/MySQL. Se amplían `partner_profiles.profile_json`, `uploads`, `content_documents`, `home_config` y `audit_log`; no hay otro registro, nuevas colecciones de Firestore, migración SQL ni borrado de cuentas de producción. `users.firebase_uid` conserva la vinculación con la identidad verificada.

## API aditiva

- `GET /metadata/catalog` añade `partnerRegistrationVersion: 1`.
- `GET /me/partner-registration-config` exige sesión profesional; devuelve actividades, ciudades iniciales, requisitos por actividad/tipo/ciudad y versiones de textos legales activos. Omite reglas privadas de comisión.
- `GET /me/partner-profile` conserva `schemaVersion: 1` y campos históricos. Añade `onboarding`, `reviewStatus`, documentos con decisión administrativa, vehículos, avisos internos, `savedAt`, `canPublish` y proyección pública limitada.
- `PUT /me/partner-profile` recibe `{schemaVersion: 1, expectedRevision: int, onboarding: object}`. `onboarding.step` es 0–6. Una revisión antigua devuelve `409 VERSION_CONFLICT`. Campos, listas, formatos y propiedad/propósito de archivos se validan en servidor; el cuerpo no admite aprobación ni permisos.
- Los documentos editables solo contienen `requirementId`, `uploadId`, `expiresAt`; estado/motivo pertenecen a administración.
- `POST /me/partner-profile/submission` mantiene `Idempotency-Key`. Exige perfil completo, requisitos configurados, documentos obligatorios vigentes, versiones actuales de colaboración/privacidad/condiciones económicas e `informationCorrect: true`. Registra fecha UTC, versiones, revisión/digest del perfil y UID de Firebase. No aprueba el registro.
- `PUT /me/partner-vehicles/{uuid}` recibe `{expectedRevision, vehicle}`. Admite hasta 20 vehículos. Cada uno necesita aprobación independiente; editar uno aprobado vuelve a dejarlo pendiente y pausa sus servicios publicados asociados.
- `GET /collaborators/{uuid}` y `/collaborators/{uuid}/media/{uploadId}` exponen solo perfiles aprobados/operativos y las imágenes permitidas. No exponen contactos personales, datos fiscales, documentos o información bancaria.

Por compatibilidad, `status: pending` sigue representando el estado SQL `submitted`. El campo nuevo `reviewStatus` usa `draft`, `pending_review`, `under_review`, `changes_requested`, `approved`, `rejected`, `suspended`.

## Seguridad

El endpoint de subidas existente añade `professional-cover`, `professional-gallery`, `professional-document`, `vehicle-photo`, `vehicle-document`. Documentos: PDF/JPG/PNG, firma MIME real y máximo 5 MiB. Fotografías: JPEG/PNG/WebP y mismo límite.

Los documentos se guardan fuera de la raíz pública, con directorios 0700 y archivos 0600 donde el sistema lo permite. `PRIVATE_DOCUMENT_STORAGE_PATH` permite indicar una carpeta persistente. En Hostinger, el valor predeterminado se sitúa antes del segmento `public_html`; verificar permisos y copias de seguridad.

`GET /uploads/{id}` exige al propietario y devuelve `private, no-store`. La revisión administrativa exige rol `admin`, comprueba propiedad y registra la lectura. No se eliminan archivos referenciados. `owned_upload` se comparte entre API y administración; no existe un segundo sistema de subida. `.htaccess` bloquea acceso directo a `includes/`.

Las decisiones administrativas requieren CSRF, rol autorizado, revisión actual y auditoría. El colaborador no puede cambiar comisiones, aprobación, liquidaciones o permisos. Cambiar información crítica aprobada vuelve a revisión. Los empleados no pueden operar/facturar por cuenta propia. Publicación, cotización y reserva validan autorización y vehículo; documentación requerida caducada impide operar.

No se añaden reglas de Firestore/Storage: esos productos no forman parte de la arquitectura acordada. La protección efectiva está en PHP, propiedad/roles y almacenamiento privado.

## Administración

Se amplía la ficha actual de `users.php?view=collaborators`: filtros por actividad/ciudad/estado, revisión individual, correcciones, aprobación/rechazo/suspensión, vehículos y auditoría. El bloque nuevo sustituye al resumen antiguo cuando hay un perfil de siete pasos.

`users.php?view=collaborators&registration=settings` configura requisitos, ámbitos, caducidad, conservación y comisiones. Admite requisitos nuevos sin actualizar Flutter. Las comisiones se editan como porcentajes con selectores de actividad, colaborador y servicio, con control de versión.

La comisión inicial acordada del 25 % se conserva como configuración en `includes/partner-registration-defaults.json`, sin porcentaje fijo en la fórmula. Prioridad: servicio, colaborador, actividad, general. Importes/netos usan unidades menores. Impuestos sin configuración continúan explícitamente pendientes.

El gestor existente `catalog.php?view=content` permite crear/publicar los textos revisados `collaboration-terms`, `privacy` y `partner-economic-terms`. No se inventa contenido legal definitivo.

## Pendientes externos y límites

1. Revisar requisitos aplicables y marcar su revisión en administración; publicar los tres textos legales con versiones. Antes se permiten borradores, pero no un envío ficticio.
2. Verificar almacenamiento privado persistente y copias de seguridad en Hostinger.
3. FCM, mensajería, avisos programados de caducidad y purga automática por conservación no están conectados. Sí hay avisos internos de envío y decisiones.
4. Pagos marketplace, KYC, impuestos definitivos y liquidaciones necesitan proveedor/configuración. La billetera permanece contable/de lectura; no se guardan tarjetas.
5. Vinculación operativa de empleados a una empresa y cobro delegado requieren ampliar su contrato. Mientras tanto no pueden operar por cuenta propia.
6. Se reutilizan los editores reales de chófer y guía. Las otras cuatro actividades admiten registro/revisión; sus editores comerciales específicos siguen pendientes. Las localidades del perfil tienen identificador estable; no se inventa un catálogo nacional geocodificado ni se amplía el catálogo comercial sin integración.
7. iOS necesita compilación/prueba física en macOS/Xcode. No se han añadido accesos de prueba ni se ha simulado SMS.

## Evidencia

Pruebas HTTP sobre MariaDB aislada 127.0.0.1:33387 con datos sintéticos: autónomo, empresa, empleado, actividades múltiples, recuperación, revisiones, PDF/PNG, propiedad, rechazo/sustitución, aprobación, varios vehículos, proyección pública, configuración y permisos. Pruebas de política: vehículos pendientes, empleados, caducidad, revisión inválida tras sustituir archivo y prioridad de comisiones. Incluye sintaxis PHP, smoke de Release 1 y diff.

Las pruebas aisladas no prueban alta real de Firebase, pagos, FCM o iOS físico. La entrega móvil recoge la evidencia de Flutter, APK, web y publicación.
