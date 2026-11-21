# Gestión editorial de la portada — 21/11/2026

## Alcance

El panel conserva los gestores `content.php?type=banners` y `content.php?type=offers` y añade navegación común para las cinco secciones de la portada. Las tres secciones editoriales restantes se editan desde `home-content.php?section=tripIdeas|destinations|experiences`.

Permite foto JPG/PNG/WebP de hasta 5 MB, vista previa local, título y descripción en español/inglés/árabe, orden entero, publicación/ocultación, edición y eliminación con confirmación. Las ideas de viaje admiten el estilo previsto en el contrato existente. Se preservan los IDs y los campos ajenos a la edición. Cada mutación y su auditoría se guardan en una transacción. El gestor nuevo rechaza formularios obsoletos para evitar sobreescrituras silenciosas.

## Compatibilidad y almacenamiento

- No hay tablas nuevas ni migraciones. Se usa la fila existente `home_config.id=1`, bloqueada durante cada escritura; se preservan atajos, tarifa de planificación y todas las demás secciones.
- Los slides y ofertas siguen en sus tablas independientes, con los mismos IDs y respuestas.
- Las fotos se guardan mediante `upload_image` en el almacenamiento persistente configurado, nunca como parte del despliegue Git.
- El catálogo comercial de destinos y servicios se mantiene separado de las tarjetas editoriales de portada.
- No se borran archivos físicos al eliminar una tarjeta: pueden estar compartidos o ser necesarios para recuperación.

## API móvil

`GET /v1/home` conserva `banners`, `offers`, `tripIdeas`, `destinations`, `experiences` y todas sus claves anteriores. Las tres últimas colecciones se devuelven ordenadas por `displayOrder` ascendente con desempate estable por posición original. Los registros antiguos sin orden explícito conservan su posición. Se omiten tarjetas ocultas o sin imagen disponible. Los campos internos con prefijo `_` no se publican.

Cada tarjeta conserva `id`, `title`, `imageUrl`, `action`, `prefillStyle`, `coordinates` si existía, y añade `description: {es,en,ar}` y `displayOrder`. La traducción vacía permite el fallback del cliente a español. Las ideas usan `action: designTrip`; destinos y experiencias usan `placeholder`, sin introducir una reserva o cobro inexistente.

Las nuevas imágenes locales se sirven por `media.php?type=home_tripIdeas|home_destinations|home_experiences&id=<entero>`. El ID de imagen cambia al reemplazar la foto para evitar reutilizar una imagen antigua de la caché. Es una lista cerrada de contenido público; no se expone ninguna subida privada. El puente web local debe permitir únicamente estos tres tipos adicionales.

La app actual ya consume las tres colecciones. Por ahora sus tarjetas muestran foto y título; guardar descripciones y estilos no implementa por sí mismo una nueva pantalla de detalle o un cambio del formulario móvil.

## Despliegue

Desplegar los archivos modificados del repositorio junto con los nuevos `home-content.php`, `includes/home_content.php` y `assets/home-content.{css,js}`. No reimportar seeds, reemplazar configuraciones locales ni subir herramientas o bases temporales. La fila `home_config.id=1` debe existir como en la instalación actual. El enlace de navegación aparece en el panel tras desplegar.

La autorización del usuario permite actualizar el panel. La publicación debe verificarse con una lectura del login y de `/v1/home`, y después revisando el gestor con sesión de administrador. Un push a GitHub no acredita despliegue de Hostinger.

## Verificación requerida

- Lint PHP y JavaScript, comprobaciones del contrato editorial, pruebas aisladas de alta/edición/ocultación/eliminación/CSRF/conflictos y lectura de imágenes.
- Comprobar con datos diferentes que slides y ofertas no se mezclan ni cambian sus IDs.
- Comprobar que los cambios de una sección no alteran la tarifa de planificación ni otras colecciones.
- Pruebas del puente local: acceso solo a imágenes públicas, sin credenciales ni escrituras.
- Tras desplegar, verificar datos y medios existentes en producción sin crear inventario de prueba.

El informe final de entrega debe distinguir pruebas locales, publicación en GitHub y despliegue real en Hostinger.

## Evidencia local completada

- PHP 8.3.37: todos los archivos PHP pasan validación de sintaxis.
- MariaDB 11.4.5 temporal en loopback: esquema y seed canónicos importados; `api/release1-smoke.php` aprobado. Ninguna migración o fixture se aplicó a producción.
- `tests/home-content-test.php`: aprobado, incluyendo orden, compatibilidad, filtrado de imágenes/visibilidad, traducciones y validación.
- `tests/home-content-http-test.mjs`: 100 aserciones aprobadas con administrador y datos desechables. Incluye slides y ofertas con órdenes deliberadamente distintos, imágenes reales servidas, aislamiento entre secciones, auditoría transaccional, CSRF y conflictos de edición.
- Puente de vista previa: cinco pruebas aprobadas, incluidas las tres nuevas colecciones públicas y el rechazo de rutas privadas/escrituras.
- Revisión visual del escritorio y del editor a ancho móvil de 390 px. Se corrigió el encabezado móvil y se comprobó el menú desplegable.
- APK debug y vista web recompilados. No hay cambio del contrato de autenticación ni de pagos en el cliente móvil.

Pendiente operativo hasta verificar el despliegue: comprobar el gestor con una sesión del administrador de Hostinger. Las pruebas locales no acreditan publicación en el servidor real.
