-- TELA Online Hoodie Store Database Schema
-- Compatible with MySQL/MariaDB in XAMPP/phpMyAdmin and DigitalOcean.
-- Re-import note: this file drops and recreates the project tables for development.
-- Do not run DROP TABLE statements against production data unless you have a backup.

CREATE DATABASE IF NOT EXISTS tela_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE tela_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS cart;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------
-- Table: users
-- Stores buyer and admin accounts.
-- Passwords must be created in PHP with password_hash().
-- --------------------------------------------------------
CREATE TABLE users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    address TEXT NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    role ENUM('admin', 'buyer') NOT NULL DEFAULT 'buyer',
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    verification_token VARCHAR(100) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_users_email UNIQUE (email),
    CONSTRAINT uq_users_verification_token UNIQUE (verification_token),
    CONSTRAINT chk_users_role CHECK (role IN ('admin', 'buyer'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: categories
-- Stores product categories. TELA starts with Hoodies only.
-- --------------------------------------------------------
CREATE TABLE categories (
    category_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_categories_category_name UNIQUE (category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: products
-- Stores Hoodie products for the storefront and admin product management.
-- --------------------------------------------------------
CREATE TABLE products (
    product_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    product_name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    price DECIMAL(10,2) NOT NULL,
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    image_path VARCHAR(255) NULL,
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_category
        FOREIGN KEY (category_id) REFERENCES categories(category_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT chk_products_price CHECK (price > 0),
    CONSTRAINT chk_products_stock CHECK (stock >= 0),
    CONSTRAINT chk_products_status CHECK (status IN ('Active', 'Inactive'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: cart
-- Stores cart rows for authenticated buyers.
-- --------------------------------------------------------
CREATE TABLE cart (
    cart_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_cart_user_product UNIQUE (user_id, product_id),
    CONSTRAINT fk_cart_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_cart_product
        FOREIGN KEY (product_id) REFERENCES products(product_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT chk_cart_quantity CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: orders
-- Stores checkout transaction summaries.
-- --------------------------------------------------------
CREATE TABLE orders (
    order_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    order_number VARCHAR(50) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    order_status ENUM('Pending', 'Processing', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_orders_order_number UNIQUE (order_number),
    CONSTRAINT fk_orders_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT chk_orders_total_amount CHECK (total_amount > 0),
    CONSTRAINT chk_orders_status CHECK (order_status IN ('Pending', 'Processing', 'Completed', 'Cancelled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: order_items
-- Stores product snapshots inside each order.
-- --------------------------------------------------------
CREATE TABLE order_items (
    order_item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    product_name VARCHAR(150) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id) REFERENCES orders(order_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product
        FOREIGN KEY (product_id) REFERENCES products(product_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT chk_order_items_quantity CHECK (quantity > 0),
    CONSTRAINT chk_order_items_price CHECK (price > 0),
    CONSTRAINT chk_order_items_subtotal CHECK (subtotal > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: audit_logs
-- Records important buyer and admin activities.
-- --------------------------------------------------------
CREATE TABLE audit_logs (
    audit_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    activity VARCHAR(100) NOT NULL,
    description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_logs_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Seed Data: category
-- Hoodies is the only starting category.
-- --------------------------------------------------------
INSERT INTO categories (category_name)
VALUES ('Hoodies')
ON DUPLICATE KEY UPDATE category_name = VALUES(category_name);

-- --------------------------------------------------------
-- Seed Data: sample Hoodie products
-- Includes active, out-of-stock, and inactive examples.
-- --------------------------------------------------------
INSERT INTO products (category_id, product_name, description, price, stock, image_path, status)
SELECT category_id, 'Classic Black Hoodie', 'A comfortable black hoodie for everyday wear.', 899.00, 20, NULL, 'Active'
FROM categories
WHERE category_name = 'Hoodies';

INSERT INTO products (category_id, product_name, description, price, stock, image_path, status)
SELECT category_id, 'Oversized Cream Hoodie', 'An oversized cream hoodie for a relaxed fit.', 1099.00, 0, NULL, 'Active'
FROM categories
WHERE category_name = 'Hoodies';

INSERT INTO products (category_id, product_name, description, price, stock, image_path, status)
SELECT category_id, 'Minimalist Gray Hoodie', 'A simple gray hoodie for clean casual styling.', 999.00, 12, NULL, 'Inactive'
FROM categories
WHERE category_name = 'Hoodies';

-- --------------------------------------------------------
-- Optional Development Admin Setup
-- No plaintext password is stored in this SQL file.
--
-- Secure setup method:
-- 1. Generate a password hash in PHP:
--    php -r "$password = readline('Admin password: '); echo password_hash($password, PASSWORD_DEFAULT);"
-- 2. Copy the generated hash into the INSERT below.
-- 3. Replace the email and contact details if needed.
-- 4. Run the INSERT manually in phpMyAdmin.
--
-- INSERT INTO users
--     (full_name, email, password_hash, address, contact_number, role, is_verified)
-- VALUES
--     ('Development Admin', 'admin@example.com', 'PASTE_GENERATED_PASSWORD_HASH_HERE', 'Development Address', '09000000000', 'admin', 1);
