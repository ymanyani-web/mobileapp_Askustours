# Chófer personalizado — 2026-11-28

El formulario reutiliza el acceso, perfil del viajero y controles profesionales existentes. Las consultas a medida se almacenan en trip_requests con requestType=chauffeur, sin nuevas tablas ni cambios en Firebase/OTP/SMTP.

## API
- GET/POST /me/driver-requests, GET/PATCH /me/driver-requests/{id}, siempre autenticados y limitados al cliente propietario.
- POST: Idempotency-Key obligatorio, perfil completo, usuario verificado, máximos de tamaño/capacidad/fechas y 20 solicitudes por cliente cada 24 horas. Bloqueo del usuario durante el alta para serializar el límite y los reintentos. No acepta precios ni estados suministrados por el cliente.
- GET colección usa cursores opacos; Flutter recorre la colección con el repositorio existente.
- PATCH solo permite aceptar un presupuesto vigente concreto (versión y revisión), o cancelar antes de confirmar. Aceptar exige móvil verificado. No cobra.

## Estados
new → in_review → proposal_sent → awaiting_acceptance → accepted → confirmed → completed, con cancelación controlada. proposal_sent ya permite aceptar; awaiting_acceptance es un paso opcional de seguimiento. Solo el cliente acepta. Solo administración/gestor revisa, presupuesta y confirma. Revisores solo consultan.

Los presupuestos incluyen importe total EUR, condiciones, caducidad y versión. Una nueva propuesta invalida la aceptación anterior. No puede reenviarse un presupuesto después de confirmar.

La confirmación exige un servicio publicado, proveedor profesional operativo, actividad y servicio aprobados, vehículo aprobado con documentación vigente, requisitos comerciales/legales/de cobro y disponibilidad publicada para ambos trayectos. También comprueba capacidad, cuenta del cliente habilitada y pasaporte privado cuando el servicio lo exige. Crea quotes y reservations existentes dentro de la misma transacción; una aceptación del cliente por sí sola no crea una reserva confirmada. Las reservas quedan payment_status=notCollected. Cambios finales desde el panel de reservas se reflejan también al consultar la solicitud.

## Panel
reservations.php enlaza a driver-requests.php. Filtros independientes, datos del trayecto, presupuesto, condiciones, revisión y selección del servicio para confirmar. CSRF, control de rol y revisión optimista. Audit log para cada transición. El resumen contabiliza solicitudes aún no convertidas sin duplicar reservas; las consultas de itinerario excluyen chófer.

## Validación
- 27 comprobaciones de validación y transiciones: tests/chauffeur-policy-test.php.
- 27 comprobaciones HTTP autenticadas sobre MariaDB aislada: alta, reintento, conflicto, propietario, aprobación prohibida al cliente, condiciones/versiones y cancelación.
- 13 comprobaciones adicionales del flujo completo con proveedor y vehículo sintéticos aprobados, creando una reserva real en la base local, confirmando paymentStatus=notCollected y finalizándola.
- Pruebas Flutter del formulario RTL a 320 px, reutilización del perfil y ausencia de éxito cuando falla el servidor.

No se han enviado solicitudes de prueba con cuentas reales de producción ni contratado servicios externos. No hay integración Redsys activa.
