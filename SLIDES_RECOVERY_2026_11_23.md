# Carrusel original — 2026-11-23

- Cinco fotografías recuperadas directamente de assets/offers y lib/offers.dart, sin regenerar imágenes ni inventar textos.
- Editor de slides con foto, título/descripción es/en/ar, vista previa y botón interno de Chóferes, Guías o Crea tu viaje.
- Restauración autenticada por POST con CSRF. Los slides anteriores se ocultan y se conservan. Repetir no duplica ni sobrescribe cambios editoriales posteriores.
- Copias en almacenamiento persistente; no se cambia configuración ni esquema SQL.
- GET /home mantiene banners separado de offers. Añade los detalles originales y destination; no expone _bannerDetails.
- App conserva el detalle original y su botón Reserva ahora. Amplía navegación a Guías y Crea tu viaje mediante lista permitida.
- Firebase: esta actualización no modifica autenticación ni requiere acceso a Firebase Console.
- Pruebas locales: 129 aserciones HTTP sobre DB desechable; regresión de navegación/carrusel Flutter.
