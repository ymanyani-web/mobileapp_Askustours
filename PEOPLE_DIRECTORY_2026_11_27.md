# Directorios de clientes y colaboradores

Se mejora `users.php` conservando las rutas de entrada y los controladores existentes. No se crean tablas ni se alteran usuarios, Firebase, reservas, billeteras o aprobaciones.

- Resumen de resultados, cuentas activas, bloqueadas y colaboradores pendientes de revisión.
- Búsqueda por nombre, correo o móvil; filtro de cuenta; revisión profesional, actividad y ciudad para colaboradores.
- Actividades legibles en lugar de códigos internos. Filtro específico de correcciones solicitadas reutilizando `_reviewState`.
- Orden por alta, nombre o número de solicitudes. Páginas de 25 resultados, filtros conservados y página ajustada al total real.
- Nombre enlazado a la ficha, iniciales, datos de contacto LTR, estados diferenciados de cuenta y revisión y resumen de servicios.
- Eliminación reutilizada con el diálogo y permiso actuales. No hay nuevas acciones de aprobación, bloqueo ni eliminación.
- Accesos a las secciones existentes de las fichas: contacto, perfil profesional, historial de servicios, billetera, control e historial de gestión.
- Etiquetas principales y opciones de filtro conectadas al selector existente español/inglés/árabe; CSS con propiedades lógicas para RTL. Los nombres y contactos no se traducen.

## Verificación

PHP sin errores de sintaxis, JavaScript válido y `git diff --check` correcto. Prueba MySQL aislada en 127.0.0.1:33388/askustours con 31 cuentas sintéticas dentro de una transacción revertida: 19 comprobaciones de filtros, correcciones, ciudad, búsqueda literal con comodines, paginación y visibilidad/confirmación de eliminación.

Se revisaron visualmente ambos directorios mediante HTML generado con esas cuentas ficticias en localhost. Esa vista no modifica el panel real ni permite operar sobre cuentas. No se han borrado ni creado cuentas en producción para probar el diseño.

No requiere APK nuevo: no se modificó Flutter ni su contrato de API. La publicación de este cambio no incluye los archivos locales de WhatsApp pendientes de configuración.
