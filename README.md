# Salon Booking System

A salon management and online booking application built as a Laravel monolith with
React rendered through Inertia.

> **Status:** Phase 0 (foundation) complete. No business modules are implemented yet.

## Stack

| Layer | Technology |
| --- | --- |
| Backend | PHP 8.2+, Laravel 12 |
| Frontend | React 19, Inertia.js 2, TypeScript |
| Build | Vite 7, Tailwind CSS 4 |
| Database | MySQL 8 (MariaDB via XAMPP) |
| Local environment | XAMPP (Apache + PHP + MySQL) |

## Requirements

- PHP 8.2 or newer with `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`,
  `ctype`, `json`, `bcmath`, `fileinfo`, `curl`, `zip`, `gd`
- Composer 2
- Node.js 20+ and npm
- MySQL (bundled with XAMPP)

## Local setup

Clone or place the project inside your XAMPP `htdocs` directory, then:

### 1. Install dependencies

```bash
composer install
npm install
```

### 2. Configure the environment

```bash
cp .env.example .env
php artisan key:generate
```

`.env` is git-ignored and must never be committed. Update the `DB_*` values if your
local MySQL credentials differ from the XAMPP defaults.

### 3. Create the database

Start **Apache** and **MySQL** from the XAMPP control panel, then create the schema:

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS salon_booking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Or create `salon_booking` through phpMyAdmin at <http://localhost/phpmyadmin>.

### 4. Run migrations

```bash
php artisan migrate
```

Seed data is introduced in Phase 1:

```bash
php artisan db:seed
```

### 5. Build frontend assets

```bash
npm run build     # production build
npm run dev       # Vite dev server with hot reload
```

### 6. Start the application

```bash
php artisan serve
```

The application is then available at <http://127.0.0.1:8000>.

Run `npm run dev` in a second terminal while developing so assets hot-reload.

## Commands

| Command | Purpose |
| --- | --- |
| `php artisan test` | Run the PHPUnit test suite |
| `./vendor/bin/pint` | Format PHP to Laravel style |
| `./vendor/bin/pint --test` | Check PHP formatting without writing |
| `npm run types` | TypeScript type check (`tsc --noEmit`) |
| `npm run build` | Production asset build |
| `npm run dev` | Vite dev server |
| `php artisan migrate:fresh` | Drop all tables and re-migrate |

Tests run against an in-memory SQLite database (see `phpunit.xml`) and never touch
the local MySQL data.

## Project structure

```
app/Http/Middleware/HandleInertiaRequests.php   Inertia shared props (kept minimal)
resources/views/app.blade.php                   Inertia root template
resources/js/app.tsx                            React + Inertia entry point
resources/js/Pages/                             Inertia page components
resources/js/types/                             Shared TypeScript definitions
routes/web.php                                  Web routes
tests/Feature/, tests/Unit/                     Test suites
```

The `@/` import alias resolves to `resources/js/` in both Vite and TypeScript.

## Conventions

- Business logic belongs in service classes, not controllers or React components.
- Validation and authorization are enforced server-side.
- Inertia shared props are deliberately scoped and must not carry personal data.
- Migrations are the source of schema truth; do not edit the schema by hand.
