# To-do List

Simple multi-user to-do list built with plain PHP and SQLite. Users get personal lists, admins can create shared lists and grant view/edit access, and tasks can optionally repeat. Interface is responsive for mobile devices.

## Requirements
- PHP 8.1+ with the SQLite3 extension enabled.
- Optional: Docker and Docker Compose (for the provided container setup).

## Quick start (PHP built-in server)
1. Create the SQLite DB (and default admin user) by running: `php private_todo/migrations/migrate.php`
2. Start the built-in server: `php -S 127.0.0.1:8000 -t public_html/todo`
3. Visit `http://127.0.0.1:8000`.
4. Log in with the default admin user: `admin / changeme` (change this password right away via the Admin page).
5. Data lives in `private_todo` (`todo.sqlite` database and `sessions/`); ensure the PHP process can write there.

## Quick start (Docker)
1. `docker compose up --build` (or `docker-compose up --build`)
2. Open `http://localhost:8080`
3. Create the SQLite DB (and default admin user): `docker compose exec web php private_todo/migrations/migrate.php` (or `docker-compose exec web php private_todo/migrations/migrate.php`)
4. The `private_todo` directory on the host holds the SQLite database and session files, so data persists across container restarts.

## Using the app
- Main page: log in, add tasks with optional due dates, mark them done, or delete them (when you have edit access).
- Click a task due date to change it (edit access required).
- Tasks can optionally repeat (daily/weekly/weekdays/monthly/yearly).
- Completing a repeating task automatically creates the next occurrence (and unchecking it removes the auto-generated next one if it’s still pending).
- Tasks more than 3 days overdue show a warning icon.
- Settings page (`/settings.php`): change your password, create/rename/delete your own lists, clear completed tasks, share lists with other users, and set your list order (per-user).
- Admin page (`/admin.php`): create users, toggle admin status, reset passwords, create lists, grant/revoke access (view or edit), clear completed tasks, or delete lists and users.
- Login attempts page (`/admin_attempts.php`): view recent failed login attempts (admin only).
- Every user starts with a "Personal list" and can rename/delete/share it like any other list.
- Login security: CSRF token on all mutating actions and simple per-IP rate limiting (5 attempts per 5 minutes) to slow brute force attempts.

## List ordering
- The home page orders lists as: personal list pinned first → your per-user order (set in Settings) → list name.
- Ordering is per-user and stored on the `list_access` row, so different users can pick different list orders (including for shared lists).
- After pulling updates, re-run `php private_todo/migrations/migrate.php` so schema updates (like list ordering) are applied to your existing database.

## UI
- UIkit for layout/components with a small custom theme in `public_html/todo/css/theme.css`.

## Project layout
- `public_html/todo` — web root (entry points, views).
- `public_html/todo/js/todo.js` — progressive-enhancement AJAX for the main page.
- `public_html/todo/js/settings.js` — progressive-enhancement AJAX for list reordering in Settings.
- `private_todo/app/bootstrap.php` — shared bootstrap (session/cookie settings, DB, helpers).
- `private_todo/app/src` — PHP classes (controllers, Query, Security, View).
- `private_todo/app/src/Recurrence.php` — recurrence rule parsing/scheduling helpers.
- `public_html/todo/views` — templates and partials (included by PHP, not meant to be served directly).
- `private_todo` — SQLite database (`todo.sqlite`), migrations, and session storage.

## Configuration (env vars / .env)
The app reads configuration from environment variables (via `getenv()`), and also supports an optional `private_todo/.env` file (useful on shared hosting where setting real env vars is hard).

- Create `private_todo/.env` (see `private_todo/.env.example`) and keep it out of git.
- Prefer real env vars when possible (cPanel / Apache config), because they avoid reading a file on each request.
- Useful knobs:
  - `TODO_ADMIN_IP_ALLOWLIST`: restrict admin pages to specific IPs/CIDRs.
  - `TODO_LOGIN_ATTEMPTS_RETENTION_SECONDS`: keep failed login attempts longer than the default 5-minute window.
  - `TODO_SESSION_SAVE_PATH`: store PHP sessions in a custom (writable) directory.

## Performance notes
- Run migrations occasionally (`php private_todo/migrations/migrate.php`) to apply schema updates as the app evolves and the dataset grows.
- SQLite tuning knobs (optional env vars): `TODO_SQLITE_BUSY_TIMEOUT_MS` (default `5000`), `TODO_SQLITE_JOURNAL_MODE` (e.g. `WAL`), `TODO_SQLITE_SYNCHRONOUS` (e.g. `NORMAL`).
