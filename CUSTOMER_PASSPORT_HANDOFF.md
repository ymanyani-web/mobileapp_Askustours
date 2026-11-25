# Entrega de pasaporte privado — API v2

El formulario de clientes conserva el perfil existente. Permite adjuntar una foto de pasaporte (JPG/PNG/WebP, hasta 5 MiB) mediante el propósito customer-passport de uploads. No crea otro perfil ni modifica colaboradores.

La foto se almacena fuera del directorio público y exige propiedad y autenticación para consultar. PATCH /me valida propósito y propietario. Las reservas solo exigen passportUploadId cuando el servicio lo requiere; los antiguos requisitos documentales se normalizan a esa clave. No se permite eliminar una foto referenciada por un perfil o una reserva. Sin migraciones ni seeds.

metadata/catalog devuelve customerTravelerDetailsVersion: 2. Pruebas locales: 13 comprobaciones de validación y política PHP; sintaxis PHP correcta. No se han subido pasaportes reales durante las pruebas.
