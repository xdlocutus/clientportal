CREATE DATABASE IF NOT EXISTS business_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE business_portal;

CREATE TABLE companies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) DEFAULT NULL,
    contact_name VARCHAR(150) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    address_line1 VARCHAR(255) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    state VARCHAR(100) DEFAULT NULL,
    postal_code VARCHAR(30) DEFAULT NULL,
    country VARCHAR(100) DEFAULT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_companies_email (email),
    KEY idx_companies_status (status)
) ENGINE=InnoDB;

CREATE TABLE clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    company_name VARCHAR(150) NOT NULL,
    contact_name VARCHAR(150) DEFAULT NULL,
    email VARCHAR(190) DEFAULT NULL,
    billing_email VARCHAR(190) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    address_line1 VARCHAR(255) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    state VARCHAR(100) DEFAULT NULL,
    postal_code VARCHAR(30) DEFAULT NULL,
    country VARCHAR(100) DEFAULT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_clients_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
    UNIQUE KEY uq_clients_company_email (company_id, email),
    KEY idx_clients_company_status (company_id, status)
) ENGINE=InnoDB;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED DEFAULT NULL,
    client_id INT UNSIGNED DEFAULT NULL,
    role ENUM('super_admin','company_admin','company_staff','client') NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
    CONSTRAINT fk_users_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE SET NULL,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_company_role (company_id, role),
    KEY idx_users_client (client_id)
) ENGINE=InnoDB;

CREATE TABLE user_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    permission_key VARCHAR(120) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_permissions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    UNIQUE KEY uq_user_permission (user_id, permission_key),
    KEY idx_user_permissions_key (permission_key)
) ENGINE=InnoDB;

CREATE TABLE services (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    service_name VARCHAR(150) NOT NULL,
    description TEXT DEFAULT NULL,
    price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    billing_cycle ENUM('one_time','monthly','quarterly','yearly') NOT NULL DEFAULT 'monthly',
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,
    status ENUM('active','suspended','cancelled') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_services_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
    CONSTRAINT fk_services_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE,
    KEY idx_services_company_client (company_id, client_id),
    KEY idx_services_status (company_id, status)
) ENGINE=InnoDB;


CREATE TABLE products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    product_name VARCHAR(150) NOT NULL,
    sku VARCHAR(80) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
    UNIQUE KEY uq_products_company_sku (company_id, sku),
    KEY idx_products_company_status (company_id, status)
) ENGINE=InnoDB;

CREATE TABLE invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    invoice_number VARCHAR(50) NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    billing_type ENUM('once_off','recurring') NOT NULL DEFAULT 'once_off',
    recurring_profile_id INT UNSIGNED DEFAULT NULL,
    status ENUM('draft','sent','unpaid','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
    notes TEXT DEFAULT NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_invoices_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
    CONSTRAINT fk_invoices_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE,
    UNIQUE KEY uq_invoice_company_number (company_id, invoice_number),
    KEY idx_invoices_company_client_status (company_id, client_id, status),
    KEY idx_invoices_billing_type (company_id, billing_type, invoice_date)
) ENGINE=InnoDB;

CREATE TABLE invoice_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    description TEXT NOT NULL,
    quantity DECIMAL(12,2) NOT NULL DEFAULT 1.00,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    source_type ENUM('manual','product','service') NOT NULL DEFAULT 'manual',
    source_id INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_invoice_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE,
    KEY idx_invoice_items_invoice (invoice_id)
) ENGINE=InnoDB;

CREATE TABLE invoice_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    invoice_id INT UNSIGNED NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    reference VARCHAR(120) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_invoice_payments_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
    CONSTRAINT fk_invoice_payments_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE,
    CONSTRAINT fk_invoice_payments_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE,
    KEY idx_invoice_payments_invoice (invoice_id),
    KEY idx_invoice_payments_client_date (company_id, client_id, payment_date)
) ENGINE=InnoDB;

CREATE TABLE recurring_billing_profiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED DEFAULT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT DEFAULT NULL,
    billing_cycle ENUM('weekly','monthly','quarterly','yearly') NOT NULL DEFAULT 'monthly',
    quantity DECIMAL(12,2) NOT NULL DEFAULT 1.00,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    due_days INT NOT NULL DEFAULT 7,
    start_date DATE NOT NULL,
    next_invoice_date DATE NOT NULL,
    end_date DATE DEFAULT NULL,
    last_invoiced_at DATETIME DEFAULT NULL,
    status ENUM('active','paused','completed') NOT NULL DEFAULT 'active',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_recurring_profiles_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
    CONSTRAINT fk_recurring_profiles_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE,
    CONSTRAINT fk_recurring_profiles_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE SET NULL,
    KEY idx_recurring_profiles_due (company_id, status, next_invoice_date),
    KEY idx_recurring_profiles_client (company_id, client_id, status)
) ENGINE=InnoDB;

CREATE TABLE tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    subject VARCHAR(190) NOT NULL,
    message TEXT NOT NULL,
    priority ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    status ENUM('open','closed') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tickets_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
    CONSTRAINT fk_tickets_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE,
    CONSTRAINT fk_tickets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    KEY idx_tickets_company_client_status (company_id, client_id, status),
    KEY idx_tickets_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE jobcards (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    created_by_user_id INT UNSIGNED NOT NULL,
    assigned_user_id INT UNSIGNED DEFAULT NULL,
    job_number VARCHAR(50) NOT NULL,
    title VARCHAR(190) NOT NULL,
    job_type VARCHAR(120) DEFAULT NULL,
    scheduled_for DATETIME NOT NULL,
    status ENUM('scheduled','in_progress','completed','on_hold','cancelled') NOT NULL DEFAULT 'scheduled',
    priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    site_contact_name VARCHAR(150) DEFAULT NULL,
    site_contact_phone VARCHAR(50) DEFAULT NULL,
    service_address TEXT DEFAULT NULL,
    scope_of_work TEXT DEFAULT NULL,
    access_instructions TEXT DEFAULT NULL,
    materials_required TEXT DEFAULT NULL,
    internal_notes TEXT DEFAULT NULL,
    client_signature_name VARCHAR(150) DEFAULT NULL,
    client_signature_notes TEXT DEFAULT NULL,
    client_signed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_jobcards_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
    CONSTRAINT fk_jobcards_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE,
    CONSTRAINT fk_jobcards_created_by_user FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_jobcards_assigned_user FOREIGN KEY (assigned_user_id) REFERENCES users (id) ON DELETE SET NULL,
    UNIQUE KEY uq_jobcards_company_number (company_id, job_number),
    KEY idx_jobcards_company_client_status (company_id, client_id, status),
    KEY idx_jobcards_assigned_user (assigned_user_id),
    KEY idx_jobcards_scheduled_for (company_id, scheduled_for)
) ENGINE=InnoDB;

CREATE TABLE jobcard_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    jobcard_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    note TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_jobcard_notes_jobcard FOREIGN KEY (jobcard_id) REFERENCES jobcards (id) ON DELETE CASCADE,
    CONSTRAINT fk_jobcard_notes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    KEY idx_jobcard_notes_jobcard (jobcard_id),
    KEY idx_jobcard_notes_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE ticket_replies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ticket_replies_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE,
    CONSTRAINT fk_ticket_replies_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    KEY idx_ticket_replies_ticket (ticket_id)
) ENGINE=InnoDB;

CREATE TABLE settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    setting_key VARCHAR(120) NOT NULL,
    setting_value TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_settings_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
    UNIQUE KEY uq_settings_company_key (company_id, setting_key),
    KEY idx_settings_company (company_id)
) ENGINE=InnoDB;

INSERT INTO companies (id, name, email, contact_name, phone, status) VALUES
(1, 'Default Company', 'admin@defaultcompany.test', 'Default Admin', '+1-555-1000', 'active');

INSERT INTO clients (id, company_id, company_name, contact_name, email, billing_email, phone, status) VALUES
(1, 1, 'Acme Client', 'Acme Contact', 'client@acme.test', 'billing@acme.test', '+1-555-2000', 'active');

INSERT INTO users (company_id, client_id, role, full_name, email, password, is_active) VALUES
(NULL, NULL, 'super_admin', 'Super Admin', 'superadmin@example.com', '$2y$12$59BjbtW9ho.bfoitq0lTU.bhjdCTajhU6ulEWnnZ9ttAxv1CQDsda', 1),
(1, NULL, 'company_admin', 'Company Admin', 'companyadmin@example.com', '$2y$12$CNIxmp5WRw1we0FlFamqSeSNB3cXNt3ubMijVxhJxhoZ8ppVo7x72', 1),
(1, 1, 'client', 'Client User', 'client@example.com', '$2y$12$CNIxmp5WRw1we0FlFamqSeSNB3cXNt3ubMijVxhJxhoZ8ppVo7x72', 1);

INSERT INTO settings (company_id, setting_key, setting_value) VALUES
(1, 'company_name', 'Default Company'),
(1, 'company_email', 'admin@defaultcompany.test'),
(1, 'invoice_prefix', 'INV-'),
(1, 'currency', 'USD'),
(1, 'timezone', 'UTC');
