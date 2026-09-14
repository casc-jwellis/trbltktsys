# trbltktsys

A simple PHP trouble ticket system.

- **Ticket submission** (`submit-ticket.php`) — public, no login required.
- **Helpdesk login** (`login.php`) — session-based auth for staff.
- **Ticket queue & management** (`dashboard.php`, `ticket.php`) — staff-only, requires login.
- **Admin settings** (`admin-settings.php`) — Administrator-only. Create, edit, lock, or remove
  helpdesk accounts, assign roles (Administrator / Helpdesk Agent) and groups.

1. Create an empty MySQL database and a database user that can access it.
2. Make sure `config/` and `uploads/` are writable by the web server — `config/` so the installer can save `config/config.php`, `uploads/` to store screenshots attached to submitted tickets.
3. Point your web server (or `php -S localhost:8000`) at the project root and open it in a browser.

Any page will redirect you into `install.php` until setup is finished. The installer walks through three steps:

1. **Database credentials** — enter your host/port/database/username/password; the connection is tested before anything is saved to `config/config.php`.
2. **Tables** — creates the `tickets`, `users`, `roles`, and `agent_groups` tables (and their join tables) from `schema.sql` with one click.
3. **First admin account** — create the helpdesk staff login you'll use going forward. This account is granted both the Administrator and Helpdesk Agent roles, so it can manage tickets and access Admin Settings immediately.

Once an admin account exists, `install.php` locks itself out (it redirects to `login.php`) so it can't be used to re-run setup or create more accounts later. Additional staff accounts — and their roles (Administrator / Helpdesk Agent) and groups — are managed from the **Admin Settings** panel (Administrator-only).

Prefer to set it up by hand instead? Copy `config/config.example.php` to `config/config.php`, import `schema.sql` yourself, and create your first administrator from the CLI:
```
php create-user.php <username> "<Full Name>" --admin
```
Additional staff accounts can be created from the Admin Settings panel once you've logged in, or via the same command with `--agent` (the default if no flag is given).

Upgrading an existing database created before admin settings or screenshot attachments existed? Run:
```
migrations/001_admin_roles_groups.sql
```
```sql
ALTER TABLE tickets ADD COLUMN attachment_path VARCHAR(255) NULL AFTER internal_notes;
```

## Stack

Plain PHP (PDO) + MySQL, Bootstrap 5 for styling. No build step or dependencies required.
