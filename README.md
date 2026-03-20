# Business Portal

Business Portal is a modular multi-tenant PHP 8+ web application for client management, services, billing, and support. Every tenant-owned table includes a `company_id`, and the application filters all tenant queries by the authenticated user's company except for the `super_admin` role.

## Features

- Multi-tenant company isolation for users, clients, services, invoices, tickets, and settings.
- Role-based access for `super_admin`, `company_admin`, `company_staff`, and `client`.
- PDO + prepared statements throughout.
- Bootstrap 5 admin layout with sidebar, topbar, flash messages, and CRUD forms.
- Dashboard with tenant-filtered statistics and company-configurable widgets.
- Custom reports for sales, revenue per client, unpaid invoices, and ticket statistics.
- CSV exports and print / PDF-ready exports for invoices, tickets, and client lists.
- Client portal access for invoices and support tickets.


## Installation Guide

1. **Create a PHP/MySQL environment**
   - PHP 8.1+ with extensions: `pdo`, `pdo_mysql`, `mbstring`, `openssl`.
   - MySQL 8+ or MariaDB 10.5+.
   - Configure your web root to point at the project directory.

2. **Import the schema**
   ```bash
   mysql -u root -p < database/schema.sql
   ```

3. **Update database settings**
   - Edit `config/database.php`.
   - Set `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` to match your environment.

4. **Serve the application**
   - Apache/Nginx with document root set to the repository root, or start PHP's built-in server:
   ```bash
   php -S 127.0.0.1:8000 -t .
   ```

5. **Open the portal**
   - Visit `http://127.0.0.1:8000`.
   - Log in with the default accounts below.

## Default Users

The schema seeds three users. Their bcrypt hashes are already included in `database/schema.sql`.

- **Super Admin**
  - Email: `superadmin@example.com`
  - Password: `ChangeMe123!`
- **Company Admin**
  - Email: `companyadmin@example.com`
  - Password: `Password123!`
- **Client User**
  - Email: `client@example.com`
  - Password: `Password123!`

> Change these passwords immediately in production.

## Creating Additional Super Admin or Company Admin Users

### Create a new super admin

1. Generate a password hash:
   ```bash
   php -r "echo password_hash('YourStrongPassword!', PASSWORD_DEFAULT), PHP_EOL;"
   ```
2. Insert the record:
   ```sql
   INSERT INTO users (company_id, client_id, role, full_name, email, password, is_active)
   VALUES (NULL, NULL, 'super_admin', 'New Super Admin', 'new-super-admin@example.com', 'PASTE_HASH_HERE', 1);
   ```

### Create a new company admin

1. Make sure the company exists in the `companies` table.
2. Generate a password hash:
   ```bash
   php -r "echo password_hash('YourStrongPassword!', PASSWORD_DEFAULT), PHP_EOL;"
   ```
3. Insert the user:
   ```sql
   INSERT INTO users (company_id, client_id, role, full_name, email, password, is_active)
   VALUES (1, NULL, 'company_admin', 'Tenant Admin', 'tenant-admin@example.com', 'PASTE_HASH_HERE', 1);
   ```

## Multi-Tenant Development Notes

When you add a new module, preserve tenant isolation with these rules:

1. Add a `company_id` foreign key to every tenant-owned table.
2. Restrict every `SELECT`, `UPDATE`, and `DELETE` using `company_id = :company_id` unless the current user is `super_admin`.
3. If the role is `client`, further restrict access by `client_id = :client_id` for portal-only resources.
4. Use PDO prepared statements for every database call.
5. Escape all output with `h()` and validate every POST action with `verify_csrf()`.
6. Reuse `require_role()`, `require_staff()`, `company_scope_params()`, and `require_company_access()` from the shared includes.

## Module Extension Checklist

- Create a new folder under `modules/<your-module>`.
- Add `index.php` plus any CRUD actions.
- Require `config/config.php` and `includes/auth.php` at the top of each file.
- Gate access with `require_login()` / `require_role()`.
- Use shared layout partials through `includes/header.php` and `includes/footer.php`.
- Add a navigation link in `includes/sidebar.php` when appropriate.
- Add schema changes to `database/schema.sql` and include tenant indexes.

## Security Notes

- Sessions regenerate on login.
- Passwords are hashed with PHP's `password_hash()`.
- All DB operations use prepared statements.
- Flash messaging, output escaping, and CSRF helpers are included centrally.
- Production deployments should disable `display_errors` and enforce HTTPS.
