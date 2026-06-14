# Qbits Vault API

A Laravel 12 REST API backend with JWT authentication, role-based permissions, and queue-driven notifications.

## Requirements

- PHP 8.2+
- Composer
- Node.js & npm
- SQLite (default) or MySQL/PostgreSQL

## Setup

### 1. Clone and install dependencies

```bash
git clone <repo-url>
cd qbits-vault-api
composer install
npm install
```

### 2. Environment configuration

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` as needed. By default the project uses SQLite — no database server required.

**For MySQL**, uncomment and fill in the DB block:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=qbits_vault
DB_USERNAME=root
DB_PASSWORD=your_password
```

**Mail** — set `MAIL_MAILER=log` for local development (emails go to `storage/logs/laravel.log`).

### 3. Generate JWT secret

```bash
php artisan jwt:secret
```

### 4. Run migrations

```bash
php artisan migrate
```

Optionally seed the database:

```bash
php artisan db:seed
```

### 5. Build frontend assets

```bash
npm run build
```

## Running locally

Start all services (server, queue worker, log watcher, Vite) in one command:

```bash
composer dev
```

Or start them individually:

```bash
php artisan serve          # API server at http://localhost:8000
php artisan queue:listen   # Process queued jobs
```

## One-command setup

The `composer setup` script runs all of the above steps in sequence:

```bash
composer setup
```

## Testing

```bash
composer test
```

## Key packages

| Package | Purpose |
|---|---|
| `tymon/jwt-auth` | JWT authentication |
| `spatie/laravel-permission` | Roles & permissions |
| `laravel/sanctum` | API token auth |
| `barryvdh/laravel-dompdf` | PDF generation |
| `pippa/notification-sdk-laravel` | Push/email notifications |

## License

MIT
