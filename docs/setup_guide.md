# TELA Setup Guide

## Local Setup

1. Place the `TELA` folder inside `C:\xampp\htdocs`.
2. Start Apache and MySQL in XAMPP.
3. Create or import the database using `database/tela_schema.sql` when the schema is finalized.
4. Configure database credentials in `config/database.php`.
5. Open the project in your browser:

```text
http://localhost/TELA/
```

## Notes

- This project uses procedural PHP and MySQLi.
- Bootstrap 5 is loaded through CDN.
- TELA sells Hoodies only.
- Real email delivery is deferred to the Authentication/Email Verification milestone.
