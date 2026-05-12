# Multi-Tenant User Authentication System — Setup

This project is a **pure PHP** backend with **MySQL** (credentials), **MongoDB** (profile documents), and **Redis** (opaque session tokens). The frontend uses **Bootstrap 5**, **jQuery AJAX** (no `fetch`), and **localStorage** for the session token after login.

## Prerequisites

- **PHP 8.0+** with extensions enabled in `php.ini`:
  - `mysqli`
  - `mongodb` ([PECL mongodb](https://www.php.net/manual/en/mongodb.installation.php))
  - `redis` ([PECL redis](https://github.com/phpredis/phpredis))
- **MySQL** 5.7+ / 8.x  
- **MongoDB** 4.x+  
- **Redis** 5.x+  

### Windows quick checks

```powershell
php -v
php -m | findstr /i "mysqli mongodb redis"
```

Enable extensions by uncommenting or adding in `php.ini`:

```ini
extension=mysqli
extension=mongodb
extension=redis
```

Restart Apache or reuse `php.exe` after edits.

## Database setup

### MySQL

Create schema and table:

```bash
mysql -u root -p < database/schema_mysql.sql
```

Or paste `database/schema_mysql.sql` into MySQL Workbench / phpMyAdmin.

Default database name: **`mt_auth`**.

### MongoDB

1. Start `mongod`.
2. Create the unique index (once), e.g. in **mongosh**:

```javascript
use mt_auth
db.profiles.createIndex({ user_id: 1, tenant_id: 1 }, { unique: true })
```

Document shape is described in `database/schema_mongodb.md`.

### Redis

Start Redis with default host **`127.0.0.1`** and port **`6379`**. No extra schema is required; keys look like `session:<64-hex-token>` with TTL 24 hours.

## Configuration

Edit **`php/config.php`** or set environment variables:

| Variable       | Purpose              | Default        |
|----------------|----------------------|----------------|
| `MYSQL_HOST`   | MySQL host           | `127.0.0.1`    |
| `MYSQL_PORT`   | MySQL port           | `3306`         |
| `MYSQL_DB`     | Database name        | `mt_auth`      |
| `MYSQL_USER`   | MySQL user           | `root`         |
| `MYSQL_PASS`   | MySQL password       | *(empty)*      |
| `MONGO_URI`    | MongoDB connection URI | `mongodb://127.0.0.1:27017` |
| `MONGO_DB_NAME`| MongoDB database     | `mt_auth`      |
| `REDIS_HOST`   | Redis host           | `127.0.0.1`    |
| `REDIS_PORT`   | Redis port           | `6379`         |
| `REDIS_PASS`   | Redis password       | *(null)*       |

## Run the application

From the project root (folder that contains `index.html`):

```powershell
cd c:\Users\ADMIN\OneDrive\pro5
php -S localhost:8080
```

Open in a browser:

- **Landing:** [http://localhost:8080/index.html](http://localhost:8080/index.html)  
- **Auth (sliding login/register):** [http://localhost:8080/login.html](http://localhost:8080/login.html)  
- **Dedicated register:** [http://localhost:8080/register.html](http://localhost:8080/register.html)  
- **Profile:** [http://localhost:8080/profile.html](http://localhost:8080/profile.html)  

Use **`Tenant ID`** `default` unless you are testing multi-tenant isolation (same email can exist under different tenants).

### Important

- Serve over **`http://localhost:...`** so relative URLs like `php/login.php` resolve correctly. Opening HTML as `file://` will break AJAX paths.
- After login, the app stores **`session_token`** and **`session_user`** in `localStorage`.

## API summary

| Endpoint          | Method | Purpose |
|-------------------|--------|---------|
| `php/register.php`| POST   | JSON body: `tenant_id`, `name`, `email`, `password` |
| `php/login.php`   | POST   | JSON body: `tenant_id`, `email`, `password` → returns `token` |
| `php/profile.php` | GET  | Header `X-Session-Token: <token>` (optional query `token` for debugging) |
| `php/profile.php` | POST | JSON body: `token`, `name`, `email`, `age`, `dob`, `contact`, `bio` |

Passwords are stored with **`password_hash()`**; APIs use **prepared statements** for MySQL.

## Folder layout (deliverables)

```
assets/js/login.js
assets/js/register.js
assets/js/profile.js
js/login.js
js/register.js
js/profile.js
css/               (placeholder — styles are embedded per HTML page)
assets/css/
php/config.php
php/login.php
php/register.php
php/profile.php
database/schema_mysql.sql
database/schema_mongodb.md
index.html
login.html
register.html
profile.html
```

Embedded **HTML + CSS + JS** live in each `.html` file per specification; `assets/js/*.js` mirrors the same logic for the required structure.

## Troubleshooting

- **401 on profile:** Token missing from Redis (expired Redis data, wrong Redis instance, or clock issues). Sign in again.
- **MongoDB class not found:** Install/enable the **mongodb** PHP extension.
- **Redis connection refused:** Start the Redis service and check host/port in `config.php`.
- **Duplicate email:** Unique constraint is per `(email, tenant_id)` — use another tenant or email.
