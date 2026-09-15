# trbltktsys

A simple PHP trouble ticket system.

- **Ticket submission** (`submit-ticket.php`) — public, no login required. Each submission
  upserts a `requesters` record (keyed by email, with name and optional phone) so submitters
  can be tracked across tickets.
- **Helpdesk login** (`login.php`) — session-based auth for staff.
- **Ticket queue & management** (`dashboard.php`, `ticket.php`) — staff-only, requires login. New
  tickets are auto-assigned to whichever users and groups are configured for their category;
  staff can reassign a ticket to any combination of users and groups from the ticket page. Agents
  only see tickets assigned to them (directly or via a group); administrators see everything. Each
  ticket has a chronological conversation thread — responses meant for the submitter, plus
  internal notes (added via a separate modal so they can't be mixed up with a submitter-facing
  response) that only staff can see. A response can also be inserted from a saved canned response.
- **Admin settings** (`admin-settings.php`) — Administrator-only. Create, edit, disable, or remove
  helpdesk accounts (with an Administrator flag), assign groups and ticket categories to both
  users and groups, manage the category list itself, maintain a library of canned responses
  (Responses tab), and (from the Database tab) purge all data.
- **Database migrations** (`migrate.php`) — applies any pending schema changes after a `git pull`,
  from the browser.

## Setup

1. Create an empty MySQL database and a database user that can access it.
2. Make sure `config/` and `uploads/` are writable by the web server — `config/` so the installer can save `config/config.php`, `uploads/` to store screenshots attached to submitted tickets.
3. Point your web server (or `php -S localhost:8000`) at the project root and open it in a browser.

Any page will redirect you into `install.php` until setup is finished. The installer walks through three steps:

1. **Database credentials** — enter your host/port/database/username/password; the connection is tested before anything is saved to `config/config.php`.
2. **Tables** — creates the `tickets`, `users`, and `agent_groups` tables (and their join tables) from `schema.sql` with one click.
3. **First admin account** — create the helpdesk staff login you'll use going forward, with a required email and optional phone number. This account is flagged as an Administrator, so it can manage tickets and access Admin Settings immediately.

Once an admin account exists, `install.php` locks itself out (it redirects to `login.php`) so it can't be used to re-run setup or create more accounts later. Additional staff accounts — including the Administrator flag and groups — are managed from the **Admin Settings** panel (Administrator-only). There's no CLI for creating accounts; the web installer and Admin Settings panel are the only ways in.

Pulled new code onto an existing install? Open **`migrate.php`** in a browser — it detects any schema
changes that haven't been applied yet (new columns/tables from `migrations/*.sql`) and applies them
with one click. No `mysql` client needed. It's unauthenticated (like `install.php`) since a pending
migration can otherwise break login, but it only ever *adds* columns/tables — it never touches data.
Once nothing is pending it just links to the login page.

## Stack

Plain PHP (PDO) + MySQL, Bootstrap 5 for styling. No build step or dependencies required.
