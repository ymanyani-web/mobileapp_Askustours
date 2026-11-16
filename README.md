# AskUs Tours Admin Dashboard

Native PHP/MySQL control panel for the AskUs Tours mobile application. It manages home banners, categories, services, destinations, hotels, chauffeured vehicles, customers and bookings.

## Requirements

- PHP 8.1+ with PDO MySQL and Fileinfo
- MySQL 8+ or MariaDB 10.5+
- Apache/Nginx, or PHP's built-in development server

## Install

1. For a new database, import `database/schema.sql`, followed by `database/seed.sql`. For an existing installation, import `database/migration_2026_11_15_operations.sql` once.
2. Adjust the database values in `config.php`, or set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS` environment variables.
3. Ensure the web server can write to the `uploads/` directory (it is created automatically on first upload).
4. From this directory, run `php -S localhost:8000` for development.
5. Open `http://localhost:8000/login.php`.

Initial login: `admin@askustours.com` / `password`. Change it immediately in production.

## Mobile API

- `GET /api/v1/home.php` returns all visible app content.
- `POST /api/v1/create-booking.php` creates or updates a client and stores a mobile reservation.
- `GET /api/v1/booking-status.php?booking_code=...&phone=...` returns the current reservation status.

Example booking body:

```json
{"full_name":"Sara Ahmed","phone":"+966500000000","booking_type":"vehicle","item_id":1,"start_date":"2026-12-01","end_date":"2026-12-03","pickup_location":"King Khalid Airport","dropoff_location":"Riyadh Boulevard","total_amount":900}
```

For production, use HTTPS, change the initial administrator password, restrict CORS to the mobile app's origin, and keep secrets in environment variables.
