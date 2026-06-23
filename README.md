# Books REST API — Chapter 12 Solution (Hardened)

SCSM2223 — Cross-Platform Application Development
Faculty of Computing, Universiti Teknologi Malaysia

This is the **complete reference solution** for Chapter 12. It hardens the Chapter 11 API with five categories of defenses.

## What changed since Chapter 11

| Defense                    | How                                                              |
|----------------------------|------------------------------------------------------------------|
| Strict input validation     | Reusable `Validator` class with whitelist rules                  |
| Output encoding (XSS)       | `JSON_HEX_TAG | JSON_HEX_AMP | …` flags on every JSON response   |
| Security HTTP headers       | `SecurityHeaders` middleware (HSTS, CSP, X-Frame-Options, …)     |
| Rate limiting               | `RateLimit` middleware applied to `POST /auth/login` (5 / 60s)   |
| IDOR protection             | `created_by` column + owner-or-admin check on `PUT /api/books/{id}` |
| Tighter CORS                | Allow-list via `CORS_ALLOWED_ORIGINS` env var                    |
| Audit log                   | `audit_log` table + `AuditLog` repository for security events    |

## Project Structure

```
Ch12_BooksAPI_Solution/
├── public/
│   ├── index.php
│   └── .htaccess
├── sql/
│   └── schema.sql                  # users + books(created_by) + audit_log
├── src/
│   ├── Database.php
│   ├── routes.php
│   ├── Validation/
│   │   └── Validator.php           # NEW — reusable validator
│   ├── Auth/
│   │   └── JwtService.php
│   ├── Controllers/
│   │   ├── AuthController.php       # uses Validator + AuditLog
│   │   └── BookController.php       # IDOR check + AuditLog
│   ├── Repositories/
│   │   ├── BookRepository.php       # adds created_by
│   │   ├── UserRepository.php
│   │   └── AuditLog.php             # NEW
│   └── Middleware/
│       ├── AuthMiddleware.php
│       ├── JsonBodyParser.php
│       ├── SecurityHeaders.php      # NEW — HSTS, CSP, X-Frame-Options
│       ├── RateLimit.php            # NEW — file-backed sliding window
│       └── Cors.php                 # NEW — origin allow-list
├── composer.json
├── .env.example                    # adds CORS_*, LOGIN_RATE_LIMIT, etc.
├── requests.http
└── README.md
```

## Setup

1. `composer install`
2. `mysql -u root < sql/schema.sql`
3. `copy .env.example .env` (and set a real `JWT_SECRET`)
4. `php -S localhost:8000 -t public`

Seeded users — `admin@books.test` / `password` (admin) and `member@books.test` / `password` (member).

## Things to Try

| Try…                                                        | Expect                                              |
|-------------------------------------------------------------|-----------------------------------------------------|
| `POST /auth/login` with wrong password 6 times              | 6th call → 429 + `Retry-After` header               |
| `PUT /api/books/1` as a member who doesn't own it           | 403 (IDOR check)                                    |
| `PUT /api/books/1` as admin                                 | 200                                                 |
| `POST /api/books` with `year: 9999`                         | 400 with field error                                |
| Inspect any response's headers                              | HSTS, CSP, X-Frame-Options, X-RateLimit-…           |
| Pre-flight from `http://attacker.example`                   | No `Access-Control-Allow-Origin: attacker`          |
| Insert a `<script>` tag as a book title                     | Stored, but rendered as `<` etc. in JSON        |

## Notes

- The XSS escape via `JSON_HEX_TAG` etc. ensures JSON is safe to interpolate into HTML even if a frontend bypasses Vue's auto-escape — but **always render via Vue's `{{ }}` interpolation, never `v-html`**.
- The rate limiter is in-process (file-backed); for real deployments, swap for Redis.
- The audit log writes to `audit_log` for `auth.login.success`, `auth.login.fail`, `book.create`, `book.update`, `book.update.forbidden`, `book.delete`, and `user.register`.
- HTTPS is required for `Strict-Transport-Security` to do anything useful — deploy with HTTPS.
