# Panel: equipo, permisos y recuperación

## Cambios

- Accesos directos del resumen a clientes, colaboradores, trabajadores y recuperación.
- `staff.php` reutiliza la tabla `admins` y sus roles existentes: administrador, gestor y solo consulta. No crea un sistema de usuarios paralelo.
- Solo administradores pueden crear o editar trabajadores, asignar roles, desactivar accesos o establecer contraseñas. Se exige CSRF, contraseña de al menos 12 caracteres y revisión de formulario vigente.
- Cada petición del panel comprueba de nuevo el rol y el estado en la base de datos. Desactivar corta el siguiente acceso de una sesión existente; cambiar permisos modifica lo permitido sin esperar a cerrar sesión. Las sesiones nuevas guardan una marca de credencial y se invalidan después de cambiar la contraseña.
- No se puede retirar el propio acceso de administrador ni dejar el panel sin un administrador activo. Las operaciones quedan auditadas sin registrar contraseñas.
- El gestor puede operar catálogo, contenido, calendario y solicitudes. No elimina, recupera, aprueba colaboradores, bloquea cuentas ni gestiona trabajadores. El rol de consulta no admite escrituras.
- `deletions.php` concentra eliminación y recuperación. Clientes, colaboradores, servicios, paquetes, solicitudes, presupuestos, documentos y tarjetas de portada disponen de revisión previa. Las fichas y el catálogo enlazan al resumen de eliminación.
- Solo el administrador elimina o recupera. Se guarda en `audit_log` una copia de los registros afectados dentro de la misma transacción. No se desactivan claves foráneas ni se hacen operaciones parciales. Los archivos físicos permanecen para recuperación. Las sesiones nunca se respaldan ni se restauran.
- Las eliminaciones existentes del editor de slides y de tarjetas editoriales guardan ahora una copia mediante el mismo mecanismo. Mantienen su comprobación de revisión y su confirmación.
- Los registros con pagos o liquidaciones ya registrados se conservan. Para esos casos se usan las operaciones de cancelar o desactivar.
- Recuperar no sobrescribe un registro existente. Si hay un conflicto de identificadores o relaciones, se revierte íntegramente la recuperación y se informa al administrador.

## Contrato y despliegue

No cambian respuestas móviles ni el esquema. No requiere migración SQL, reimportar semillas ni modificar configuración de Firebase, base de datos o almacenamiento. No se eliminan cuentas reales durante el despliegue.

Las copias son recuperables por el administrador en el propio panel. No se exportan públicamente ni se incorporan a Git. El límite de copia por operación es 2 MB; las operaciones mayores requieren un procedimiento de respaldo específico.

## Validación aislada

- 49 comprobaciones HTTP nuevas: roles, formularios obsoletos, CSRF, desactivación inmediata, cambio de contraseña que cierra sesiones, prohibición de autoexclusión, eliminación de cliente, recuperación y no restauración de sesiones.
- 8 comprobaciones de integridad: rechazo de revisión obsoleta, protección de pagos y recuperación exacta de colaborador, servicios y tarjeta editorial.
- 67 comprobaciones de gestión operativa y 159 de portada existentes.
- PHP lint y `git diff --check`.

Todas las mutaciones de prueba se realizan contra `127.0.0.1:33387`, base desechable. La verificación de producción será de lectura y no creará trabajadores ni modificará cuentas.
