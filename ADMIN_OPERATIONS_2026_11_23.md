# Gestión operativa de clientes y colaboradores — 2026-11-23

- Dashboard: contadores por estado con enlaces a filtros. Pendientes = requested + assigned; confirmados = accepted; realizados = completed. Cancelados/rechazados separados. Sin interpretar notCollected como pago.
- users.php es el directorio canónico paginado de clientes/colaboradores, con búsqueda y ficha única. Datos de contacto, historial de reservas paginado, servicios ofrecidos, valoraciones, saldo contable por moneda/estado y últimos movimientos. No se generan liquidaciones ni se modifica el ledger.
- Registro profesional: aprobación/rechazo/suspensión/devolución a revisión por un administrador. Aprobación requiere registro enviado, datos y foto profesional propia existente. Foto servida por users.php bajo sesión admin con no-store, nunca por media pública.
- Restricción de cuenta: activa/inactiva/bloqueada, motivo obligatorio y control de versiones. Se registran decisiones en audit_log, fuera de los JSON editables por el usuario. No cambia el esquema SQL. Restricciones revocan sesiones y pausan servicios publicados; reactivar no los vuelve a publicar.
- Firebase/OTP/refresh y lecturas privadas comprueban el estado administrativo. El proveedor Firebase permanece separado: la restricción se aplica en el backend de Hala Spain.
- Solo aprobados pueden aceptar servicios; se corrige la aceptación de trabajos assigned además de requested. Cotizaciones y reservas rechazan proveedores no operativos. El catálogo impide publicar de nuevo servicios de cuentas restringidas o no aprobadas.
- Historial administrativo con actor, fecha, estado y motivo; CSRF, permisos de administrador, transacción y rechazo de ediciones obsoletas.
- Menú de portada agrupado en desplegable nativo accesible. people.php/bookings.php quedan como redirecciones a las pantallas canónicas, eliminando código antiguo inalcanzable.
- App: mensaje ACCOUNT_RESTRICTED es/en/ar. No cambia Firebase ni el mecanismo de acceso.

Validación local: 67 aserciones HTTP de cuentas/permisos, 159 aserciones HTTP de portada. Fixtures protegidos para localhost:33387. No probar bloqueos ni aprobar personas reales durante la verificación de publicación.
