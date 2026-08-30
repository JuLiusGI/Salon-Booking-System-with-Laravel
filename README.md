# Salon Booking System

A salon management and online booking application built as a Laravel monolith with
React rendered through Inertia.

> **Status:** Phase 3 complete. Schema, authentication, authorization, the public
> salon website, and the design system exist. Booking is built in later phases.

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

Then load realistic development data:

```bash
php artisan migrate:fresh --seed
```

Seeded accounts all use the password `password`:

| Role | Email |
| --- | --- |
| Admin | `admin@salon.test` |
| Receptionist | `front.desk@salon.test` |
| Stylist | `marisol@salon.test` |
| Customer | `customer@salon.test` |

All seeded names, contact details, and notes are invented for development. Never
replace them with real personal data.

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

### Test database

Tests run against **MySQL**, not SQLite, so that foreign keys, schema constraints,
and row-level locking behave exactly as they do in the real application. Booking
conflict protection cannot be proven on SQLite.

Create the test database once:

```bash
mysql -u root -e "CREATE DATABASE salon_booking_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

The suite migrates and rolls back inside transactions, so it never touches your
development data in `salon_booking`.

## Project structure

```
app/Http/Middleware/HandleInertiaRequests.php   Inertia shared props (kept minimal)
resources/views/app.blade.php                   Inertia root template
resources/js/app.tsx                            React + Inertia entry point
resources/js/Pages/                             Inertia page components
resources/js/types/                             Shared TypeScript definitions
routes/web.php                                  Web routes
tests/Feature/, tests/Unit/                     Test suites

app/Enums/                                      Domain enums, including the
                                                appointment lifecycle rules
app/Models/                                     Eloquent models and relationships
database/migrations/                            Schema source of truth
database/factories/, database/seeders/          Development data
config/salon.php                                Salon timezone and booking defaults
```

The `@/` import alias resolves to `resources/js/` in both Vite and TypeScript.

## Conventions

- Business logic belongs in service classes, not controllers or React components.
- Validation and authorization are enforced server-side.
- Inertia shared props are deliberately scoped and must not carry personal data.
- Migrations are the source of schema truth; do not edit the schema by hand.

## Design system

Colour lives in exactly one place: the `@theme` block in `resources/css/app.css`.
The five brand colours from `MASTER_SPEC` section 13 are declared there once and
then referenced only through semantic tokens, so components never contain a raw
hex value. `DesignSystemTest` enforces this.

| Token | Colour | Used for |
| --- | --- | --- |
| `primary` | Dark Green `#0A3323` | Primary buttons, footer, headings |
| `secondary` | Midnight Green `#105666` | Links, eyebrow labels, focus ring |
| `accent` | Rosy Brown `#D3968C` | Decorative rules and shapes only |
| `support` | Moss Green `#839958` | Decorative, hover borders only |
| `canvas` | Beige `#F7F4D5` | Page background |
| `surface` | White | Cards, headers, panels |
| `ink` / `ink-muted` / `ink-inverted` | | Text |
| `line` / `line-strong` | | Borders and dividers |

**Moss Green and Rosy Brown are decorative only.** At 2.9:1 and 2.2:1 against
white they fail WCAG AA for body text, so they are never used as a text colour on
a light surface. Every contrast ratio is documented in the stylesheet.

Status is never signalled by colour alone. Flash messages carry a "Done"/"Error"
word, today's row on the opening hours table is labelled "Today", and form errors
always render their message.

Typography uses `font-display` (a serif stack) for headings and `font-sans` for
body text. Both are system stacks, so no webfont is downloaded and there is no new
dependency; swapping in a licensed face later means changing one token.

Accessibility built into the base layer: a skip link on every layout, a visible
`:focus-visible` ring, and a `prefers-reduced-motion` block that disables
animation and smooth scrolling.

## Public website

| Route | Page |
| --- | --- |
| `/` | Home |
| `/services` | Full service menu, grouped by category |
| `/team` | Bookable stylists |
| `/gallery` | Salon gallery |
| `/about` | About |
| `/contact` | Address, phone, and opening hours |
| `/book` | Booking call-to-action target |

All public pages read live data and hide anything inactive. The team page exposes
only name, title, and bio; staff email addresses, phone numbers, and hire dates
are never sent to the browser.

`/book` is the single stable target for every "Book appointment" call to action.
It currently sends guests to registration, remembering the destination, and signed
in users onward. **Phase 6 replaces its controller with the real booking flow**,
so no link needs to change.

## Authentication and roles

Authentication uses Laravel's built-in session guard and password broker directly
rather than a starter kit, so it fits the existing TypeScript and Inertia setup.

Roles are a single `role` column on `users`, cast to the `UserRole` enum:

| Role | Can reach |
| --- | --- |
| `admin` | Everything, including `/admin/*` |
| `receptionist` | Dashboard and profile (operational screens come later) |
| `stylist` | Dashboard and profile (schedule views come later) |
| `customer` | Dashboard and profile |

Authorization is enforced server-side in three layers:

1. `auth` and `active` middleware on every protected route group.
2. The `role:` middleware as a coarse gate on whole groups, e.g. `role:admin`.
3. `UserPolicy` for per-record decisions inside controllers.

Navigation is filtered by role in `AppLayout.tsx` for usability only. **Hiding a
link is never a security boundary** — every route is independently protected.

Security behaviour worth knowing:

- Login is rate limited to 5 attempts per email and IP combination.
- Password reset and the reset request endpoint are throttled to 6 per minute.
- The session id is regenerated on login and invalidated on logout.
- A password reset rotates the remember token, killing old "remember me" cookies.
- Forgot-password returns the same response for unknown addresses, so it cannot
  be used to discover which emails have accounts.
- `role` and `is_active` are not mass assignable; both are administrative actions.
- Role changes and account activation are written to `audit_logs`.
- An admin cannot change their own role, deactivate themselves, or remove the
  last remaining active administrator.

Email verification is **not** enabled. `MASTER_SPEC` section 16 makes it
conditional, and it is not in the Phase 2 task list, so registration completes
without it. `User` does not implement `MustVerifyEmail`; enabling it later means
adding that interface and the verification routes.

In local development `MAIL_MAILER=log`, so password reset links appear in
`storage/logs/laravel.log` rather than being delivered.

## Time and timezone

All datetimes are **stored in UTC**. `config/salon.php` holds the salon's own
timezone (`SALON_TIMEZONE`, default `Asia/Manila`), which is the only timezone
that opening hours, staff schedules, and availability slots are interpreted in.
Changing it changes presentation and schedule resolution; it does not rewrite
stored data.
