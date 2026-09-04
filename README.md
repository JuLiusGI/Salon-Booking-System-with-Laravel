# Salon Booking System

A salon management and online booking application built as a Laravel monolith with
React rendered through Inertia.

> **Status:** V1 complete. Customers book online and are notified; the salon runs
> its diary, keeps customer records, checks people in, and has a dashboard and
> reports. The system has been through a security pass and final QA, and the
> deployment steps below have been run end to end from a clean clone.

## Stack

| Layer | Technology |
| --- | --- |
| Backend | PHP 8.2+, Laravel 12 |
| Frontend | React 19, Inertia.js 2, TypeScript |
| Build | Vite 7, Tailwind CSS 4 |
| Database | MySQL 8 (MariaDB via XAMPP) |
| QR codes | bacon/bacon-qr-code (pure PHP, SVG output) |
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

### 5. Link the storage directory

Uploaded service images and staff photos are served from `public/storage`, which
is a symlink. Create it once:

```bash
php artisan storage:link
```

Uploaded files themselves are git-ignored; only the directory placeholders are
tracked.

### 6. Build frontend assets

```bash
npm run build     # production build
npm run dev       # Vite dev server with hot reload
```

### 7. Start the application

```bash
php artisan serve
```

The application is then available at <http://127.0.0.1:8000>.

Run `npm run dev` in a second terminal while developing so assets hot-reload.

## If the page loads blank

A beige page with nothing on it means the CSS loaded but React did not mount.
Two causes, both quick:

**A stale Vite marker.** Starting `npm run dev` writes `public/hot`; if that
process is killed without cleaning up, the file remains and Laravel keeps pointing
every script tag at a dev server that is no longer running. Delete it:

```bash
rm public/hot
```

**A stale compiled view.** Blade compiles to a cache, so upgrading a package that
ships Blade directives can leave the old output in place. Clear it:

```bash
php artisan optimize:clear
npm run build
```

The Inertia client and server majors must also match: `@inertiajs/react` and
`inertiajs/inertia-laravel` are both on v3. A mismatch there produces exactly the
same blank page, because the client cannot read the payload the server sends.

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
It sends guests to registration, remembering the destination, and signed in
customers straight into the booking flow. Because every call to action points at
this one route, the flow behind it changed without a single link changing.

## Notifications

Customers are told about their appointments through Laravel notifications, on the
`database` channel for the in-app list at `/notifications` and `mail` for the
record. Locally `MAIL_MAILER=log`, so mail lands in `storage/logs/laravel.log`
rather than leaving the machine.

| Event | Sent |
| --- | --- |
| Booked | On every successful booking, including a reschedule's replacement |
| Confirmed | When the salon confirms |
| Cancelled | However the cancellation happened |
| Reminder | The day before, by scheduled command |

Two rules matter here:

- **Nothing is sent until the transaction commits.** Telling a customer about an
  appointment that then rolled back cannot be undone.
- **The payload is deliberately thin** — reference, date, time, stylist, service
  names. Never allergies, internal notes, contact details, or the QR token. A
  stored notification is rendered in a browser and mirrored into mail, so its
  shape is pinned by a test.

Being marked in progress or completed sends nothing. The customer was standing
there for it.

### Reminders

```bash
php artisan appointments:remind            # send
php artisan appointments:remind --dry-run  # list who would be reminded
```

Scheduled hourly. Sending is idempotent — an appointment that already has a
reminder is skipped — so running twice, or catching up after the scheduler was
down, never double-sends. Locally the scheduler needs `php artisan schedule:work`;
in production it is the usual one-line cron calling `schedule:run`.

## Dashboard and reports

`/dashboard` is role-aware: the desk gets the operational picture, a stylist gets
their own day, a customer gets their own bookings. `/manage/reports` holds the
analytics, over a date range, and is restricted to admins and receptionists — a
stylist's own numbers belong on their dashboard, not in a salon-wide report.

**Nothing is described as revenue.** The system takes no payments, so money is
only ever "booked value" or "completed value", meaning work scheduled and work
carried out. A test asserts the word does not appear.

Anything grouped by day or hour is grouped in PHP against the salon timezone.
Appointments are stored in UTC, so grouping in SQL would file a 9am Manila
appointment under the wrong day, and hard-coding an offset would break the moment
the salon's timezone changed.

Chart colour follows the visualization guidance rather than taste. Single-series
marks use the brand primary, since bar length carries the magnitude and colour
only has to clear contrast. The one two-series chart uses a pair validated for
colourblind separation; the raw brand colours failed that check, so those two are
the nearest passing steps in the same hue families. Every chart carries a
visually hidden table of the same numbers.

## Customer records and check-in

| Route | Purpose |
| --- | --- |
| `/manage/customers` | Customer directory (desk only) |
| `/manage/customers/{id}` | Record, history, and salon notes |
| `/manage/check-in` | Today's arrivals and reference lookup |
| `/qr/{token}` | Where a scanned code resolves |
| `/appointments/{reference}/qr` | The customer's own code, as an image |

### Who may see a customer record

Read access is deliberately wider than write access. A stylist needs to know about
an allergy with someone in the chair; the record itself is the desk's to maintain.

| Role | Record | Desk notes | History | Edit |
| --- | --- | --- | --- | --- |
| Admin, receptionist | Any | Yes | All visits | Yes |
| Stylist | Only customers they have treated | No | Only visits they worked | No |
| Customer | Their own profile, at `/profile` | No | Their own | Their own |

The directory flags that a customer has an allergy noted **without showing what it
is**, so the desk knows to open the record rather than reading health data off a
list. Every edit is audited, but the values themselves are never written to the
log.

### QR check-in

The code encodes **nothing but a URL containing a random opaque token** — no name,
no date, no reference, no id. A photographed or forwarded code reveals nothing on
its own.

- **The code is a shortcut, never a credential.** Resolving one needs an
  authenticated staff session; a customer scanning their own code gets a 403.
- **An unknown code and a code for an appointment you may not see give the same
  answer**, so scanning cannot be used to probe for valid tokens.
- **Codes expire against the appointment's own time** rather than storing an
  expiry: active from a week before until a day after, and never once the
  appointment reaches a terminal status.
- Resolution is rate limited, and the image is served `private` so no shared cache
  can hand one customer's code to another.
- The image lives on its own endpoint, so the raw token never appears in an
  Inertia prop or the page source.
- **Everything works without a code.** Reference lookup is the fallback, and
  check-in is the same audited status transition either way.

## Running the diary

| Route | Purpose |
| --- | --- |
| `/manage/calendar` | Day, week, and month views |
| `/manage/appointments` | Filterable list |
| `/manage/appointments/new` | Book on a customer's behalf |
| `/manage/appointments/{reference}` | Detail, status actions, internal notes |
| `/appointments/{reference}/reschedule` | Move an appointment |

### Who sees what

`Appointment::visibleTo()` defines this once, and every list, calendar, and export
uses it rather than re-deriving the rule:

| Role | Sees |
| --- | --- |
| Admin, receptionist | The whole diary |
| Stylist | Only appointments assigned to them |
| Customer | Only their own |

A filter is applied **on top of** that scope, never instead of it, so a stylist
asking for another stylist's appointments gets an empty list rather than a leak.

### The appointment lifecycle

Permitted moves live in `AppointmentStatus::allowedTransitions()`, declared in
one place and never duplicated:

```
Pending    -> Confirmed, Cancelled, No Show
Confirmed  -> Checked In, In Progress, Cancelled, No Show
Checked In -> In Progress, Cancelled, No Show
In Progress-> Completed, Cancelled
Completed / Cancelled / No Show -> nothing (terminal)
```

Whether a move is *valid* and whether an actor *may make it* are separate
questions. The enum answers the first, `AppointmentPolicy::transition()` the
second: the desk may make any valid move, while a stylist may start, complete, or
no-show their own work but not check a customer in, which is a front-desk job.

Each step records its own timestamp, and every change is audited.

**Only cancellation frees a slot.** A completed appointment still occupies the
diary; that is history, not availability.

### Cancelling and rescheduling

A customer is held to the notice period. The desk is not, because a phone call is
a legitimate way to cancel late.

Rescheduling **creates a new appointment and cancels the original**, linked by
`appointments.rescheduled_from_id`. Two reasons: the salon can still see that a
booking moved and when, which an in-place edit would erase; and the replacement
goes through exactly the same locked revalidation as any other booking, so a
reschedule cannot sidestep the double-booking protection. The reference changes,
which the interface says up front.

If a service has since left the menu, the appointment cannot be moved
automatically. Rebooking it would quietly change what the customer is getting, so
the screen explains that and asks for a cancel-and-rebook instead.

### Editing

Only the internal notes. Changing services would change the duration and therefore
the slot, so that goes through rescheduling rather than a quiet edit. Internal
notes are never sent to the customer.

## Customer booking

| Route | Purpose |
| --- | --- |
| `/book` | Entry point every call to action uses |
| `/book/new` | The booking flow |
| `/appointments` | A customer's upcoming and past appointments |
| `/appointments/{reference}` | Appointment detail and booking confirmation |

The flow is one page: choose services, choose a stylist, choose a time. Stylists
are filtered to those who can perform **every** chosen service, and availability
is fetched by Inertia partial reload rather than a separate API, so slots always
come from the same engine that will revalidate the booking.

### What makes a booking safe

`BookingService` runs in a fixed order, and the order is the point:

1. Cheap checks first, outside the transaction, so a hopeless request never takes
   a lock.
2. Open a transaction and lock the stylist row.
3. **Revalidate after taking the lock.** The slot was true when it was rendered
   and may not be true now.
4. Write the appointment and its items together, or write nothing.

Step 3 does two separate checks: a conflict check against other appointments, and
a full re-run of the availability engine. The second catches a break, closure, or
roster change made while the customer was deciding, which is not an appointment
clash and so would otherwise slip through.

The stylist is also re-read inside the lock, in case they were deactivated between
the form loading and the request arriving.

### Other guarantees

- **Appointments are addressed by reference**, not by id, so a customer cannot
  walk the table by incrementing a number.
- **The QR token is never sent to the browser**, on any page.
- **`customer_id` and `status` are ignored if posted.** The appointment belongs to
  whoever is signed in and always starts as pending.
- **Items snapshot the service** as booked, so a later price change never rewrites
  what a past appointment cost.
- A refused booking is shown as a form error, not a server error, because being
  beaten to a slot is an expected outcome rather than a fault.

Staff do not book through this flow. Booking on a customer's behalf is a
front-desk job with its own screen at `/manage/appointments/new`, so `/book`
sends staff to their dashboard instead.

## Scheduling and availability

Availability is **derived, never stored**. Nothing writes a list of free slots;
`AvailabilityService` works them out on demand from the current schedule, so a
change to opening hours or a new booking takes effect immediately.

Constraints are applied in this order, matching `MASTER_SPEC` section 10:

1. Salon opening hours, including special hours, holidays, and closures
2. The staff member's rostered shifts
3. Whether they can perform every chosen service
4. Existing appointments that still hold their slot
5. Breaks, leave, and days off
6. Booking rules and buffer time

### Admin screens

| Route | Purpose |
| --- | --- |
| `/admin/schedule/hours` | Weekly opening hours |
| `/admin/schedule/rules` | Booking rules |
| `/admin/schedule/exceptions` | Leave, breaks, holidays, closures, special hours |
| `/admin/staff/{id}/schedule` | One staff member's recurring shifts |

### Rules worth knowing

- **Availability is the overlap** of salon hours and staff hours. A shift outside
  opening hours has no effect.
- **Cancelled and no-show appointments release their slot.** Every other status
  holds it.
- **Buffer is applied to existing appointments, not to the candidate slot**, so
  turnaround time never pushes the first appointment of the day later.
- **Comparison is half-open**, so back-to-back appointments do not collide.
- **Special hours replace** the day's opening times; every other exception type
  blocks time out of them.
- Offered times sit on the slot-interval grid, measured from salon-local
  midnight, so they read as 9:00, 9:15 rather than drifting.

### Double booking

`ConflictDetector` locks the staff row with `SELECT ... FOR UPDATE` inside the
booking transaction, then re-checks conflicts. A second request for the same
stylist waits for the first to commit and then sees its appointment.

The alternative, a range lock relying on InnoDB gap locks, is more precise but its
correctness depends on the isolation level and the index the planner picks.
Locking the stylist serialises bookings for that one stylist only; two customers
booking different stylists never contend, which at salon scale costs nothing and
is far easier to prove correct.

## Catalogue and team management

Admins manage the catalogue at `/admin/categories`, `/admin/services`, and the
team at `/admin/staff`. Everything the public site shows is driven from here.

Rules worth knowing:

- **Slugs are generated, and de-duplicated automatically.** Naming a second
  category "Hair" produces `hair-2` rather than failing.
- **Admin URLs bind by id, not slug.** Renaming a record must not change the URL
  of the page you are editing.
- **A category holding services cannot be deleted.** Move or delete its services
  first, so services never lose their grouping silently.
- **Services and staff are soft deleted.** Past appointments keep their foreign
  key, and their price and duration snapshot keeps history readable.
- **Editing a service never rewrites history.** Price and duration changes apply
  to new bookings only.
- **Only bookable, active staff can be assigned to a service**, checked again at
  submit time in case someone was deactivated while the form was open.
- **Receptionists cannot be marked bookable.** They manage the diary rather than
  appearing in it.
- **Adding a team member creates their login and their salon profile together**,
  in one transaction. Removing one soft deletes the profile and disables the
  login, keeping their history.
- **Changing a role on the users screen keeps the staff record in step.** A
  promotion creates one; a demotion stands it down rather than deleting it.
- Every create, update, and delete is written to `audit_logs`.

### Image handling

Service images, category images, and staff photos are stored on the `public`
disk through Laravel's filesystem.

- **The original filename is never used.** Laravel generates a random hash name
  and derives the extension from the detected type, so `invoice.php.jpg` cannot
  land on disk as something executable, and two uploads of `photo.jpg` cannot
  overwrite each other.
- Accepted types are JPEG, PNG, and WebP, up to 4 MB and 5000 pixels per side.
- Replacing an image writes the new file first and only then deletes the old one,
  so a failed write never destroys the existing image.
- A missing or deleted file renders a caption placeholder rather than a broken
  image.

## Authentication and roles

Authentication uses Laravel's built-in session guard and password broker directly
rather than a starter kit, so it fits the existing TypeScript and Inertia setup.

Roles are a single `role` column on `users`, cast to the `UserRole` enum:

| Role | Can reach |
| --- | --- |
| `admin` | Everything, including `/admin/*` |
| `receptionist` | The whole diary, customer records, reports, check-in |
| `stylist` | The diary, but scoped to their own appointments |
| `customer` | Booking, their own appointments, notifications, profile |

`tests/Feature/Security/RouteAuthorizationTest.php` holds this table as a matrix
and asserts every protected route against every role, so the documentation and
the enforcement cannot drift apart.

Authorization is enforced server-side in three layers:

1. `auth` and `active` middleware on every protected route group.
2. The `role:` middleware as a coarse gate on whole groups, e.g. `role:admin`.
3. `UserPolicy` for per-record decisions inside controllers.

Navigation is filtered by role in `AppLayout.tsx` for usability only. **Hiding a
link is never a security boundary** — every route is independently protected.

Security behaviour worth knowing:

- Login is rate limited to 5 attempts per email and IP combination, with a
  further 30 per minute per IP to catch a spray across many addresses.
- Registration is capped at 10 per hour per IP.
- Password reset and the reset request endpoint are throttled to 6 per minute.
- QR resolution is capped at 20 per minute, so a token cannot be guessed at speed.
- The session id is regenerated on login and invalidated on logout.
- A password reset rotates the remember token, killing old "remember me" cookies.
- Forgot-password returns the same response for unknown addresses, so it cannot
  be used to discover which emails have accounts.
- `role` and `is_active` are not mass assignable; both are administrative actions.
- Role changes, account activation, and password changes are written to
  `audit_logs`, never with the password itself.
- Sessions are bound to the password they were opened with
  (`AuthenticateSession`), so changing a password signs out every other session.
  This matters because changing a password is what someone does when they think
  another person is already in the account.
- Filter parameters in the query string are treated as untrusted: an unreadable
  date or an unknown enum drops the filter rather than raising a 500.
- An admin cannot change their own role, deactivate themselves, or remove the
  last remaining active administrator.

Email verification is **not** enabled. `MASTER_SPEC` section 16 makes it
conditional and the salon does not require it, so registration completes without
it. `User` does not implement `MustVerifyEmail`; enabling it later means
adding that interface and the verification routes.

In local development `MAIL_MAILER=log`, so password reset links appear in
`storage/logs/laravel.log` rather than being delivered.

## Errors and production settings

Failures are answered two ways, because a page can be reached two ways. A plain
browser request gets a Blade page from `resources/views/errors`; an Inertia XHR
gets the `Error` page component, so the person stays inside the app instead of
being handed a raw HTML document in a modal. The status code is preserved either
way, and 419 is turned into a "your session expired" message rather than an
error screen.

The Blade error pages carry their **own inlined CSS**. An error page is exactly
the moment the built stylesheet may be missing — a failed deploy, a cleared
`public/build` — so it must not depend on Vite having produced anything. That is
also why the brand colours appear there as literal hex values.

Before deploying, set these in the production `.env`:

| Variable | Value | Why |
| --- | --- | --- |
| `APP_DEBUG` | `false` | With it on, a stack trace and the database credentials are shown to whoever triggers the error. |
| `APP_ENV` | `production` | |
| `SESSION_SECURE_COOKIE` | `true` | Over HTTPS, keeps the session cookie off plaintext requests. |
| `APP_URL` | the real URL | Used to build links in notification mail. |

`tests/Feature/Security/ErrorHandlingTest.php` asserts that with `APP_DEBUG` off
an exception's message, class, and trace all stay out of the response.

`tests/Feature/Security/ErrorHandlingTest.php` asserts that with `APP_DEBUG` off
an exception's message, class, and trace all stay out of the response.

## Deployment

### Checklist

Run in this order. Steps 6 and 7 are what make the new code live, so anything
that can fail should fail before them.

```bash
# 1. Get the code
git pull --ff-only

# 2. Dependencies, without dev packages
composer install --no-dev --optimize-autoloader
npm ci

# 3. Build the frontend
npm run build

# 4. Database
php artisan migrate --force          # --force: production refuses to migrate interactively

# 5. Storage symlink (first deploy only, or if public/storage is missing)
php artisan storage:link

# 6. Cache configuration, routes, and views
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Confirm
php artisan about                    # check env=production, debug=false
curl -f https://your-domain/up       # health endpoint, non-zero exit if unhealthy
```

**After changing `.env`, re-run `php artisan config:cache`.** A cached config
ignores the file, which makes an edited variable look like it did nothing.

To undo a bad release: check out the previous tag, re-run steps 2, 3 and 6, and
roll the database back only if that release added migrations
(`php artisan migrate:rollback --step=1`). Migrations are the part that does not
undo cleanly, so take the backup in the next section first.

### The scheduler

One cron entry, which is what drives appointment reminders:

```cron
* * * * * cd /path/to/salon-booking-system && php artisan schedule:run >> /dev/null 2>&1
```

Without it, nothing else breaks — customers simply stop receiving day-before
reminders, silently. `php artisan schedule:list` shows what is registered.

Locally, `php artisan schedule:work` does the same job in the foreground.

### Queue workers

**None are needed.** Notifications use the `Queueable` trait but do not implement
`ShouldQueue`, so they are sent during the request that triggers them. That is a
deliberate simplification for V1: nothing is lost when no worker is running,
which is one less thing to forget.

The trade-off is that a slow mail server slows the request that sends the mail.
If that becomes noticeable, add `implements ShouldQueue` to
`AppointmentNotification` **and** run `php artisan queue:work` as a supervised
process — one without the other means notifications stop arriving.

### Caches

`CACHE_STORE=database` and `SESSION_DRIVER=database`, so both use the tables
created by the migrations and need no extra service. The rate limiters in
`routes/auth.php` count through the cache store, so clearing the cache also
clears anyone's throttle.

## Backup and recovery

Three things carry state. Losing any one of them loses real data.

| What | Where | Why it matters |
| --- | --- | --- |
| The database | MySQL `salon_booking` | Every appointment, customer, and audit record. |
| Uploaded images | `storage/app/public` | Service photos and staff portraits. Not in Git. |
| `.env` | project root | Holds `APP_KEY`. **Lose it and encrypted session data cannot be read.** |

`public/storage` is only a symlink to `storage/app/public`; back up the target,
not the link. `vendor/`, `node_modules/` and `public/build` are all rebuildable
and need no backup.

```bash
# Back up
mysqldump -u root salon_booking > backup-$(date +%F).sql
tar -czf uploads-$(date +%F).tar.gz storage/app/public

# Restore
mysql -u root salon_booking < backup-2026-09-04.sql
tar -xzf uploads-2026-09-04.tar.gz
php artisan storage:link       # if public/storage is missing
php artisan optimize:clear     # drop caches built against the old data
```

Verify a backup by restoring it into a scratch database and running
`php artisan migrate:status` against it — an untested backup is a guess. Take one
before every deployment that includes a migration.


## Time and timezone

All datetimes are **stored in UTC**. `config/salon.php` holds the salon's own
timezone (`SALON_TIMEZONE`, default `Asia/Manila`), which is the only timezone
that opening hours, staff schedules, and availability slots are interpreted in.
Changing it changes presentation and schedule resolution; it does not rewrite
stored data.

Two kinds of time are stored differently, deliberately:

- **Recurring hours** (opening hours, staff shifts) are stored as wall clock.
  "We open at nine" stays nine o'clock, and is anchored to a date in the salon's
  timezone when the engine reads it.
- **One-off instants** (appointments, schedule exceptions) are stored as UTC,
  because they refer to a specific moment. Admin forms accept salon wall-clock
  and convert on the way in.

The MySQL session is pinned to UTC in `config/database.php`. MySQL otherwise
takes its clock from the host, which here is Manila, so a raw `NOW()` would sit
eight hours from the UTC values it was compared against. Nothing relies on the
database clock; the pin is there so nothing can.
