-- TELA database reset and production-ready seed data
-- WARNING: This permanently removes every existing TELA record.
-- Back up the database before importing this file.
-- The database schema must already exist.

USE tela_db;

-- --------------------------------------------------------
-- Reset all application data in foreign-key-safe order.
-- TRUNCATE also resets AUTO_INCREMENT counters.
-- --------------------------------------------------------
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE audit_logs;
TRUNCATE TABLE order_items;
TRUNCATE TABLE orders;
TRUNCATE TABLE cart;
TRUNCATE TABLE products;
TRUNCATE TABLE categories;
TRUNCATE TABLE users;
SET FOREIGN_KEY_CHECKS = 1;

START TRANSACTION;

-- --------------------------------------------------------
-- Users
-- Both accounts are verified and ready for login.
-- The password_hash value was generated with PHP password_hash().
-- No plaintext password is stored in this file.
-- --------------------------------------------------------
INSERT INTO users
    (full_name, email, password_hash, address, contact_number, role, is_verified, verification_token)
VALUES
    (
        'Kyl Aldric Valencia',
        'kcykael@gmail.com',
        '$2y$10$PbBlIMmp3XRP5Rn7oCbdQ.5kHpK8pRo258TyJK7zRkqNSIvkWGMNe',
        'TELA Administration Address',
        '09123456789',
        'admin',
        1,
        NULL
    ),
    (
        'Kyl Aldric Valencia',
        'kylaldric07@gmail.com',
        '$2y$10$PbBlIMmp3XRP5Rn7oCbdQ.5kHpK8pRo258TyJK7zRkqNSIvkWGMNe',
        'TELA Buyer Address',
        '09987654321',
        'buyer',
        1,
        NULL
    );

-- --------------------------------------------------------
-- Sole product category
-- --------------------------------------------------------
INSERT INTO categories (category_name)
VALUES ('Hoodies');

SET @hoodies_category_id = LAST_INSERT_ID();

-- --------------------------------------------------------
-- Hoodie products
-- Image paths are NULL so missing deployment files cannot
-- produce broken images. The storefront uses its safe fallback.
-- --------------------------------------------------------
INSERT INTO products
    (category_id, product_name, description, price, stock, image_path, status)
VALUES
    (@hoodies_category_id, 'TELA Obsidian Essential Hoodie', 'A heavyweight black essential with a clean silhouette and understated TELA detailing.', 1299.00, 20, NULL, 'Active'),
    (@hoodies_category_id, 'TELA Aureus Crest Hoodie', 'A premium Hoodie featuring refined gold-inspired crest detailing.', 1499.00, 15, NULL, 'Active'),
    (@hoodies_category_id, 'TELA Midnight Loom Hoodie', 'A deep midnight Hoodie designed for comfortable everyday layering.', 1399.00, 18, NULL, 'Active'),
    (@hoodies_category_id, 'TELA Urban Weave Hoodie', 'A structured urban Hoodie combining practical comfort with modern styling.', 1349.00, 16, NULL, 'Active'),
    (@hoodies_category_id, 'TELA Emberline Hoodie', 'A dark Hoodie accented by warm ember-inspired design details.', 1449.00, 12, NULL, 'Active'),
    (@hoodies_category_id, 'TELA Cloudform Hoodie', 'A soft light-toned Hoodie with a relaxed fit and smooth finish.', 1299.00, 20, NULL, 'Active'),
    (@hoodies_category_id, 'TELA Mossbound Hoodie', 'An earthy Hoodie created for a grounded contemporary wardrobe.', 1399.00, 14, NULL, 'Active'),
    (@hoodies_category_id, 'TELA Crimson Thread Hoodie', 'A statement Hoodie finished with restrained crimson thread accents.', 1499.00, 10, NULL, 'Active'),
    (@hoodies_category_id, 'TELA Slate Horizon Hoodie', 'A slate-toned Hoodie balancing clean construction and daily versatility.', 1349.00, 0, NULL, 'Active'),
    (@hoodies_category_id, 'TELA Ivory Signal Hoodie', 'An ivory Hoodie with a crisp minimalist appearance and premium feel.', 1499.00, 8, NULL, 'Active');

COMMIT;

-- Cart, orders, order_items, and audit_logs intentionally remain empty.
