

CREATE DATABASE IF NOT EXISTS arms_db;
USE arms_db;


CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin','Accountant') DEFAULT 'Accountant',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- =========================================
-- CUSTOMERS TABLE
-- =========================================

CREATE TABLE customers (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,

    customer_name VARCHAR(100) NOT NULL,

    company_name VARCHAR(150),

    email VARCHAR(100),

    phone VARCHAR(20),

    address TEXT,

    city VARCHAR(100),

    state VARCHAR(100),

    country VARCHAR(100),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- =========================================
-- INVOICES TABLE
-- =========================================

CREATE TABLE invoices (

    invoice_id INT AUTO_INCREMENT PRIMARY KEY,

    customer_id INT NOT NULL,

    invoice_number VARCHAR(50) UNIQUE,

    invoice_date DATE,

    due_date DATE,

    total_amount DECIMAL(10,2),

    status ENUM('Pending','Paid','Overdue') DEFAULT 'Pending',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY(customer_id)

    REFERENCES customers(customer_id)

    ON DELETE CASCADE

);
-- =========================================
-- PAYMENTS TABLE
-- =========================================

CREATE TABLE payments (

    payment_id INT AUTO_INCREMENT PRIMARY KEY,

    invoice_id INT NOT NULL,

    payment_date DATE,

    amount DECIMAL(10,2) NOT NULL,

    payment_method ENUM('Cash','UPI','Bank Transfer','Cheque') DEFAULT 'Cash',

    reference_no VARCHAR(100),

    remarks TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (invoice_id)
    REFERENCES invoices(invoice_id)
    ON DELETE CASCADE

);
-- =========================================
-- INVOICE ITEMS TABLE
-- =========================================

CREATE TABLE invoice_items (

    item_id INT AUTO_INCREMENT PRIMARY KEY,

    invoice_id INT NOT NULL,

    item_name VARCHAR(150) NOT NULL,

    quantity INT NOT NULL,

    unit_price DECIMAL(10,2) NOT NULL,

    total DECIMAL(10,2) NOT NULL,

    FOREIGN KEY (invoice_id)
    REFERENCES invoices(invoice_id)
    ON DELETE CASCADE

);
-- =========================================
-- ACTIVITY LOGS TABLE
-- =========================================

CREATE TABLE activity_logs (

    log_id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL,

    activity VARCHAR(255) NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id)
    REFERENCES users(id)
    ON DELETE CASCADE

);
INSERT INTO users (
    full_name,
    email,
    password,
    role
    )
VALUES
(
    'Administrator',
    'mohit.an.1431@gmail.com',
    'mohit2912',
    'Admin'
);