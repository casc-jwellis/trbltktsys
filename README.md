# trbltktsys

A simple PHP trouble ticket system.

- **Ticket submission** (`submit-ticket.php`) — public, no login required. Each submission
  upserts a `requesters` record (keyed by email, with name and optional phone) so submitters
  can be tracked across tickets.
- **Helpdesk login** (`login.php`) — session-based auth for staff.
- **Ticket queue & management** (`dashboard.php`, `ticket.php`) — staff-only, requires login. New
  tickets are auto-assigned to whichever groups are configured for their category; staff can
  reassign a ticket to any combination of groups from the ticket page. Agents only see tickets
  assigned to one of their groups; administrators see everything. Each ticket has a chronological
  conversation thread — responses meant for the submitter, plus internal notes (added via a
  separate modal so they can't be mixed up with a submitter-facing response) that only staff can
  see. A response can also be inserted from a saved canned response.
- **Admin settings** (`admin-settings.php`) — Administrator-only. Create, edit, disable, or remove
  helpdesk accounts (with an Administrator flag) and assign them to groups, assign ticket
  categories to groups (category permissions are group-based only), manage the category list
  itself, maintain a library of canned responses (Responses tab), configure outgoing SMTP mail and
  inbound IMAP mail and test either connection (Email tab), and (from the Database tab) purge all
  data.
- **Inbound mail** (`bin/imap-poll.php`) — a CLI script, run on whatever schedule you set up
  separately (Task Scheduler, cron, ...), that pulls in submitter replies sent directly to the
  configured mailbox instead of through a ticket link. It matches each reply to a ticket via the
  thread `Message-ID` every outbound ticket email already carries (see
  `ticket_thread_message_id()` in `includes/functions.php`), verifies it against the ticket's
  public token before accepting it, reopens a Resolved/Closed ticket the same way a web reply
  does, and stores any attachments alongside the new comment. See the Email tab in Admin Settings
  to configure it.
- **Database migrations** (`migrate.php`) — applies any pending schema changes after a `git pull`,
  from the browser.

## linux setup
```bash
sudo apt update
sudo apt upgrade -y
sudo apt install nginx php-fpm php-mbstring php-sqlite3 php-curl php-json php-xml mariadb-server git -y
```

nginx never reads `.htaccess` files (that's an Apache mechanism), so anything that shouldn't be
web-reachable — logs, `config/`, `storage/`, `migrations/`, `lib/`, and PHP execution inside
`uploads/` — needs denying explicitly in the server block instead:

```nginx
# Deny anything that isn't meant to be served directly.
location ~ /\.(?!well-known) {
    deny all;
}

location ~* \.(log|sql|md)$ {
    deny all;
}

location ^~ /storage/ {
    deny all;
}

location ^~ /config/ {
    deny all;
}

location ^~ /migrations/ {
    deny all;
}

location ^~ /lib/ {
    deny all;
}

# uploads/ must stay servable (ticket screenshots/attachments), but never executed as PHP.
location ^~ /uploads/ {
    location ~ \.php$ {
        deny all;
    }
}
```

Reload nginx after editing (`sudo nginx -t && sudo systemctl reload nginx`).


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

Plain PHP (PDO) + MySQL, Bootstrap 5 for styling. No build step or Composer required. Mail is
handled by two hand-vendored libraries (not via Composer): [PHPMailer](https://github.com/PHPMailer/PHPMailer)
in `lib/phpmailer/` for outgoing SMTP, and a zero-dependency, raw-socket IMAP4rev1 client in
`lib/tehimap/` for inbound mail (`bin/imap-poll.php`). Both are configured under Admin Settings ->
Email.

## Inbound mail setup

1. Under Admin Settings -> Email, configure and test the IMAP connection. The **Processed** and
   **Unmatched** folders must already exist in the mailbox — create them with your mail provider
   first.
2. Schedule `php bin/imap-poll.php` to run periodically (e.g. every few minutes) from the project
   root, using whatever job scheduler your server has (Windows Task Scheduler, cron, ...). It's
   safe to run as often as you like — it's a no-op the moment inbound mail is disabled, and only
   processes messages newer than its last run otherwise.
3. Only STARTTLS is unsupported — use implicit SSL/TLS (typically port 993) or plaintext.
