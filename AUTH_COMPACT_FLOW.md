# Registro compacto y verificación antes de reservar

Se mantiene Firebase Auth y el backend PHP/MySQL. No hay nuevas tablas ni proveedor de identidad paralelo.

- Firebase confirma cada identificador mediante `auth/firebase-session`. El servidor conserva los contactos verificados en el campo privado `_verifiedContacts` del JSON existente.
- `public_user` compara cada email/móvil actual con su identificador confirmado. Añadir o cambiar un número por PATCH no lo verifica. Las cuentas Firebase antiguas sin esta evidencia deben volver a confirmar su contacto.
- Crear una solicitud en `reservation-requests` exige móvil verificado y devuelve `403 PHONE_VERIFICATION_REQUIRED` si falta. Tener solo un correo verificado ya no es suficiente para reservar.
- La información privada de comprobación no se expone por la API pública de usuario. PATCH /me conserva su lista blanca existente.
- `metadata/catalog` añade `bookingPhoneVerificationRequired: true` para comprobar despliegue.
- No se cambia la configuración de Firebase, facturación, proveedores ni longitud de los SMS: Firebase usa seis cifras. El correo conserva contraseña y enlace de verificación; el OTP numérico por email necesita un sistema de envío aún no conectado.

Comprobaciones: 90 aserciones HTTP sobre la base aislada, 17 de política profesional y 7 de contactos verificados. No se han creado cuentas ni enviado SMS/correos reales en producción para estas pruebas.
