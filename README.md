# AskUs Tours Admin Dashboard

Native PHP/MySQL control panel for the AskUs Tours mobile application. It manages home slides, featured offer cards, categories, services, destinations, hotels, chauffeured vehicles, customers and bookings.

## Requirements

- PHP 8.1+ with PDO MySQL and Fileinfo
- MySQL 8+ or MariaDB 10.5+
- Apache/Nginx, or PHP's built-in development server

## Install

1. For a new database, import `database/schema.sql`, followed by `database/seed.sql`. For an existing installation, back up the database and apply the ordered migrations listed in `RELEASE1_DEPLOYMENT.md`.
2. Adjust the database values in `config.php`, or set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS` environment variables.
3. Ensure the web server can write to the `uploads/` directory (it is created automatically on first upload).
4. From this directory, run `php -S localhost:8000` for development.
5. Open `http://localhost:8000/login.php`.

Initial login: `admin@askustours.com` / `password`. Change it immediately in production.

## Mobile API

Existing installations must apply the ordered migrations in `RELEASE1_DEPLOYMENT.md`; do not re-import the full seed into production.

The active API is versioned under `/api/v1` and documented in `RELEASE1_API.md`. It provides public catalogs, verified sessions, owner-scoped profiles/media, quotes, reservation requests, favorites, basket data, collaborator drafts/jobs, calendars, and read-only wallets.

Large carousel images are managed from **Home slides**. The smaller **Ofertas destacadas** cards are managed independently from **Home offers**. `/api/v1/home` returns them as `banners` and `offers`, each in ascending display order.

The former `create-booking.php` and `booking-status.php` routes return HTTP 410. Mobile clients must use quotes, authenticated reservation requests, and reservation history.

For production, use HTTPS, change the initial administrator password, restrict CORS to the mobile app's origin, and keep secrets in environment variables.
