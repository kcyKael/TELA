# TELA Online Hoodie Store

TELA is a procedural PHP and MySQL online hoodie store created as a college final project for CCS0043 - Applications Development and Emerging Technologies.

## Technology Stack

- PHP 8+
- MySQL / MariaDB
- MySQLi
- Bootstrap 5
- HTML5
- CSS3
- Vanilla JavaScript
- Apache / XAMPP

## Product Scope

TELA sells one apparel product category only: Hoodies.

## Project Structure

- `admin/` - Seller and admin pages
- `auth/` - Registration, login, logout, and email verification pages
- `buyer/` - Buyer storefront, cart, checkout, payment, orders, and about pages
- `assets/` - CSS, JavaScript, images, icons, and logo files
- `config/` - Project and database configuration files
- `database/` - SQL files for database structure and seed data
- `docs/` - Setup guide, screenshots, sample accounts, and defense documents
- `includes/` - Shared header, navbar, footer, helper functions, and auth checks
- `uploads/` - Uploaded product images

## Local Setup

1. Place this folder inside `C:\xampp\htdocs`.
2. Start Apache and MySQL in XAMPP.
3. Import `database/tela_schema.sql` through phpMyAdmin.
4. Copy `config/config.example.php` to the ignored `config/config.local.php` file.
5. Configure the database, URL, and Brevo verification-email values.
6. Confirm `uploads/products/` exists and is writable.
7. Visit `http://localhost/TELA/`.

## Group Details

Group member names and contributions must replace the About-page placeholders before final screenshots.

## Disclaimer

This website is for educational purposes only and was created as a final project requirement.
