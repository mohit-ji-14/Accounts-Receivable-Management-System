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
    role ENUM('System Admin','Finance Manager','Collector','Auditor','Customer') DEFAULT 'Finance Manager',
    entity_id INT DEFAULT 1,
    customer_id int null,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
    

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
alter TABLE users 
add CONSTRAINT fk_users_customers
FOREIGN KEY (customer_id)
REFERENCES customers(customer_id)
ON DELETE SET NULL;


-- =========================================
-- MNC INITIAL DEMO DATA
-- =========================================

-- Entities / Subsidiaries
INSERT INTO entities (entity_id, entity_name, country, base_currency, tax_id) VALUES
(1, 'ARMS Global HQ (India)', 'India', 'INR', 'GSTIN29AAAAA0000A1Z5'),
(2, 'ARMS North America Inc.', 'United States', 'USD', 'EIN-987654321'),
(3, 'ARMS Europe GmbH', 'Germany', 'EUR', 'DE123456789');

-- Users
INSERT INTO users (id, full_name, email, password, role, entity_id, customer_id) VALUES
(1, 'Administrator', 'mohit.an.1431@gmail.com', 'mohit2912', 'System Admin', 1, NULL),
(2, 'Sarah Jenkins', 'sarah.j@arms-global.com', 'admin123', 'Finance Manager', 2, NULL),
(3, 'Rahul Sharma', 'rahul@acmetech.com', 'customer123', 'Customer', 1, 1),
(4, 'David Miller', 'david@apexcloud.com', 'customer123', 'Customer', 2, 2);
