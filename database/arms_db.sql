CREATE DATABASE IF NOT EXISTS arms_db;
USE arms_db;

-- Drop tables in reverse dependency order for clean re-import
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS invoice_items;
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS entities;
DROP TABLE IF EXISTS users;

-- =========================================
-- USERS TABLE (With RBAC roles)
-- =========================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('System Admin','Finance Manager','Collector','Auditor') DEFAULT 'Finance Manager',
    entity_id INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =========================================
-- SUBSIDIARIES / ENTITIES TABLE (Multi-Company)
-- =========================================
CREATE TABLE entities (
    entity_id INT AUTO_INCREMENT PRIMARY KEY,
    entity_name VARCHAR(150) NOT NULL,
    country VARCHAR(100) NOT NULL,
    base_currency VARCHAR(10) DEFAULT 'INR',
    tax_id VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =========================================
-- CUSTOMERS TABLE (With Credit Risk Controls)
-- =========================================
CREATE TABLE customers (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    entity_id INT NOT NULL DEFAULT 1,
    customer_name VARCHAR(100) NOT NULL,
    company_name VARCHAR(150),
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    country VARCHAR(100),
    currency_code VARCHAR(10) DEFAULT 'INR',
    credit_limit DECIMAL(12,2) DEFAULT 100000.00,
    payment_terms VARCHAR(50) DEFAULT 'Net 30',
    risk_level ENUM('Low','Medium','High') DEFAULT 'Low',
    credit_hold TINYINT(1) DEFAULT 0, -- 1 = Credit Hold (New Invoices Blocked)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (entity_id) REFERENCES entities(entity_id) ON DELETE CASCADE
);

-- =========================================
-- INVOICES TABLE (Multi-Currency & Late Fees)
-- =========================================
CREATE TABLE invoices (
    invoice_id INT AUTO_INCREMENT PRIMARY KEY,
    entity_id INT NOT NULL DEFAULT 1,
    customer_id INT NOT NULL,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    currency_code VARCHAR(10) DEFAULT 'INR',
    fx_rate DECIMAL(10,4) DEFAULT 1.0000, -- Exchange rate relative to base currency
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    late_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('Pending','Partially Paid','Paid','Overdue') DEFAULT 'Pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(entity_id) REFERENCES entities(entity_id) ON DELETE CASCADE,
    FOREIGN KEY(customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE
);

-- =========================================
-- INVOICE ITEMS TABLE
-- =========================================
CREATE TABLE invoice_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    item_name VARCHAR(150) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id) ON DELETE CASCADE
);

-- =========================================
-- PAYMENTS TABLE (With FX Gain/Loss)
-- =========================================
CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency_code VARCHAR(10) DEFAULT 'INR',
    fx_rate DECIMAL(10,4) DEFAULT 1.0000,
    payment_method ENUM('Wire Transfer','ACH','Credit Card','UPI','Cheque') DEFAULT 'Wire Transfer',
    reference_no VARCHAR(100),
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id) ON DELETE CASCADE
);

-- =========================================
-- AUDIT TRAIL LOGS TABLE
-- =========================================
CREATE TABLE activity_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    user_name VARCHAR(100),
    activity VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) DEFAULT '127.0.0.1',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =========================================
-- MNC INITIAL DEMO DATA
-- =========================================

-- Entities / Subsidiaries
INSERT INTO entities (entity_id, entity_name, country, base_currency, tax_id) VALUES
(1, 'ARMS Global HQ (India)', 'India', 'INR', 'GSTIN29AAAAA0000A1Z5'),
(2, 'ARMS North America Inc.', 'United States', 'USD', 'EIN-987654321'),
(3, 'ARMS Europe GmbH', 'Germany', 'EUR', 'DE123456789');

-- Users
INSERT INTO users (id, full_name, email, password, role, entity_id) VALUES
(1, 'Administrator', 'mohit.an.1431@gmail.com', 'mohit2912', 'System Admin', 1),
(2, 'Sarah Jenkins', 'sarah.j@arms-global.com', 'admin123', 'Finance Manager', 2);

-- Customers with Risk Levels & Currencies
INSERT INTO customers (customer_id, entity_id, customer_name, company_name, email, phone, address, city, state, country, currency_code, credit_limit, payment_terms, risk_level, credit_hold) VALUES
(1, 1, 'Rahul Sharma', 'Acme Tech Solutions', 'rahul@acmetech.com', '+91 98765 43210', '102 Tech Park, MG Road', 'Bengaluru', 'Karnataka', 'India', 'INR', 200000.00, 'Net 30', 'Low', 0),
(2, 2, 'David Miller', 'Apex Cloud USA Inc', 'david@apexcloud.com', '+1 (555) 234-5678', '500 Technology Way', 'Austin', 'Texas', 'United States', 'USD', 50000.00, 'Net 15', 'Medium', 0),
(3, 3, 'Hans Mueller', 'Bavaria Logistics GmbH', 'hans@bavaria-logistics.de', '+49 89 123456', 'Industriestrasse 12', 'Munich', 'Bavaria', 'Germany', 'EUR', 40000.00, 'Net 30', 'Low', 0),
(4, 1, 'Priya Patel', 'Global Enterprise Ltd', 'priya@globallogistics.in', '+91 98123 45678', 'Commercial Complex', 'Mumbai', 'Maharashtra', 'India', 'INR', 150000.00, 'Net 30', 'High', 1);

-- Invoices with FX & Multi-Currency
INSERT INTO invoices (invoice_id, entity_id, customer_id, invoice_number, invoice_date, due_date, currency_code, fx_rate, subtotal, tax_amount, total_amount, paid_amount, status, notes) VALUES
(1, 1, 1, 'INV-2026-001', '2026-06-01', '2026-07-01', 'INR', 1.0000, 25000.00, 0.00, 25000.00, 25000.00, 'Paid', 'Enterprise Software Support'),
(2, 2, 2, 'INV-2026-002', '2026-06-15', '2026-06-30', 'USD', 85.0000, 12000.00, 0.00, 12000.00, 3000.00, 'Overdue', 'Cloud Migration Consulting'),
(3, 3, 3, 'INV-2026-003', '2026-07-10', '2026-08-10', 'EUR', 92.5000, 8500.00, 0.00, 8500.00, 0.00, 'Pending', 'SAP ERP Maintenance'),
(4, 1, 4, 'INV-2026-004', '2026-05-10', '2026-06-10', 'INR', 1.0000, 45000.00, 0.00, 45000.00, 0.00, 'Overdue', 'Data Center Colocation');

-- Invoice Items
INSERT INTO invoice_items (invoice_id, item_name, quantity, unit_price, total) VALUES
(1, 'Enterprise Software Annual SLA', 1, 25000.00, 25000.00),
(2, 'Cloud AWS Architecture Consulting (Hours)', 60, 200.00, 12000.00),
(3, 'SAP S/4HANA Module Implementation', 1, 8500.00, 8500.00),
(4, 'Server Rack Colocation & Managed Security', 1, 45000.00, 45000.00);

-- Payments
INSERT INTO payments (invoice_id, payment_date, amount, currency_code, fx_rate, payment_method, reference_no, remarks) VALUES
(1, '2026-06-20', 25000.00, 'INR', 1.0000, 'Wire Transfer', 'NEFT-8839210', 'Full Payment Settled'),
(2, '2026-06-28', 3000.00, 'USD', 85.0000, 'ACH', 'ACH-99482103', 'Part Payment Received');

-- Activity Log Initial entries
INSERT INTO activity_logs (user_id, user_name, activity, ip_address) VALUES
(1, 'Administrator', 'MNC System Initialized', '127.0.0.1'),
(1, 'Administrator', 'Imported Multi-Currency Schema', '127.0.0.1');