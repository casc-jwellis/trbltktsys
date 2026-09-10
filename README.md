# trbltktsys

A simple PHP trouble ticket system.

- **Ticket submission** (`submit-ticket.php`) — public, no login required.
- **Helpdesk login** (`login.php`) — session-based auth for staff.
- **Ticket queue & management** (`dashboard.php`, `ticket.php`) — staff-only, requires login.

## Setup

1. Create a MySQL database and import `schema.sql`.
2. Copy `config/config.example.php` to `config/config.php` and fill in your database credentials.
3. Create a helpdesk staff account:
   ```
   php create-user.php <username> "<Full Name>"
   ```
4. Point your web server (or `php -S localhost:8000`) at the project root.

## Stack

Plain PHP (PDO) + MySQL, Bootstrap 5 for styling. No build step or dependencies required.
