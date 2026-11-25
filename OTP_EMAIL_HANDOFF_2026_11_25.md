# Ask Us — SMTP Hostinger y control OTP

Cloud Functions desplegadas en hala-spain. SMTP y secretos configurados; envío desactivado hasta completar App Check y la prueba real.

La app verifica SMS en Firebase Auth y correo en Cloud Functions `requestEmailOtp` / `verifyEmailOtp`, proyecto hala-spain. La prueba de identidad sigue pasando por el endpoint existente `POST /auth/firebase-session`; no se cambia el contrato privado PHP ni se duplica la identidad. Firestore solo conserva desafíos, cuotas, configuración y auditoría.

El propietario del panel dispone de `otp-settings.php`, integrado en el menú existente. Reutiliza `require_admin`, `require_owner` y CSRF. No cambia trabajadores, permisos, esquema SQL ni datos de clientes. Los cambios de cuota usan un puente HMAC de servidor a `https://europe-west1-hala-spain.cloudfunctions.net/adminOtpSettings` (timestamp, nonce y cuerpo firmados). Firebase valida el HMAC, rechaza replay, exige revisión y audita. No hay credenciales en HTML o Flutter.

OTP_ADMIN_BRIDGE_SECRET ya existe en Secret Manager. Falta configurarlo en el entorno seguro/untracked config.local.php de Hostinger, clave otp_admin_bridge.secret. No es una contraseña SMTP ni se comparte con clientes. No registrar su valor. Remitente no-reply@askustours.com debe estar creado y autorizado; respuesta info@askustours.com. Credenciales SMTP solamente en Secret Manager del proyecto Firebase. El correo queda desactivado por defecto.

Conectar/desplegar Cloud Functions y el puente antes de habilitar envíos. La página muestra un aviso de configuración pendiente si no existe conexión. Revisar límites reales del plan en hPanel y permisos/App Check antes de activar. README de `functions/` en el proyecto móvil contiene contrato, límites, riesgos y despliegue completo.

No ejecutar seeds ni migraciones; no se requieren cambios de base de datos para este panel. La cuenta de ejecución de Functions necesita acceso limitado a secretos, Firestore, Auth Admin, firma de custom tokens y validación de App Check. No activar servicios nuevos de pago sin autorización.

Límites de servidor: máximo absoluto 80 reservas de envío en 24 horas móviles, 3 por cuenta/correo/dispositivo en una hora móvil; IP 10/hora móvil; mensual 2000 adicional. Las reservas de envío son atómicas y un fallo SMTP no devuelve cuota. El administrador puede reducir el límite global, sin superar 80 mientras el plan sea de 100.
