# trbltktsys

A simple PHP trouble ticket system.

- **Ticket submission** (`submit-ticket.php`) — public, no login required.
- **Helpdesk login** (`login.php`) — session-based auth for staff.
- **Ticket queue & management** (`dashboard.php`, `ticket.php`) — staff-only, requires login.
- **Admin settings** (`admin-settings.php`) — Administrator-only. Create, edit, lock, or remove
  helpdesk accounts, assign roles (Administrator / Helpdesk Agent) and groups, and (from the
  Database tab) purge all data.
- **Database migrations** (`migrate.php`) — applies any pending schema changes after a `git pull`,
  from the browser.

## Setup

1. Create an empty MySQL database and a database user that can access it.
2. Make sure `config/` and `uploads/` are writable by the web server — `config/` so the installer can save `config/config.php`, `uploads/` to store screenshots attached to submitted tickets.
3. Point your web server (or `php -S localhost:8000`) at the project root and open it in a browser.

Any page will redirect you into `install.php` until setup is finished. The installer walks through three steps:

1. **Database credentials** — enter your host/port/database/username/password; the connection is tested before anything is saved to `config/config.php`.
2. **Tables** — creates the `tickets`, `users`, `roles`, and `agent_groups` tables (and their join tables) from `schema.sql` with one click.
3. **First admin account** — create the helpdesk staff login you'll use going forward, with an optional email and phone number. This account is granted both the Administrator and Helpdesk Agent roles, so it can manage tickets and access Admin Settings immediately.

Once an admin account exists, `install.php` locks itself out (it redirects to `login.php`) so it can't be used to re-run setup or create more accounts later. Additional staff accounts — and their roles (Administrator / Helpdesk Agent) and groups — are managed from the **Admin Settings** panel (Administrator-only). There's no CLI for creating accounts; the web installer and Admin Settings panel are the only ways in.

Pulled new code onto an existing install? Open **`migrate.php`** in a browser — it detects any schema
changes that haven't been applied yet (new columns/tables from `migrations/*.sql`) and applies them
with one click. No `mysql` client needed. It's unauthenticated (like `install.php`) since a pending
migration can otherwise break login, but it only ever *adds* columns/tables — it never touches data.
Once nothing is pending it just links to the login page.

## Stack

Plain PHP (PDO) + MySQL, Bootstrap 5 for styling. No build step or dependencies required.
