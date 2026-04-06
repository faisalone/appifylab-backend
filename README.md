# Appifylab Backend

Laravel 13 + PostgreSQL REST API with JWT authentication (access token + rotating refresh token).

## Stack

- PHP 8.4+
- Laravel 13
- PostgreSQL
- `php-open-source-saver/jwt-auth` 2.9

## Features Implemented

- Authentication
	- Register
	- Login
	- Current user (`me`)
	- Refresh token rotation
	- Logout
- Feed
	- Public/private posts
	- Comment on posts
	- Reply to top-level comments
	- Like/unlike posts
	- Like/unlike comments and replies
	- Visibility enforcement for private posts

## Setup

1. Install dependencies

```bash
composer install
```

2. Copy environment file

```bash
cp .env.example .env
```

3. Generate app key and JWT secret

```bash
php artisan key:generate
php artisan jwt:secret
```

4. Configure database and app URLs in `.env`

```env
APP_URL=http://localhost:8000
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=appifylab
DB_USERNAME=postgres
DB_PASSWORD=postgres

FRONTEND_URL=http://localhost:3000
CORS_ALLOWED_ORIGINS=http://localhost:3000
```

5. Run migrations and create public storage symlink

```bash
php artisan migrate
php artisan storage:link
```

6. Start server

```bash
php artisan serve
```

Backend base URL: `http://localhost:8000`

## API Routes

All routes are prefixed with `/api`.

- Auth
	- `POST /auth/register`
	- `POST /auth/login`
	- `POST /auth/refresh`
	- `GET /auth/me` (auth required)
	- `POST /auth/logout` (auth required)
- Posts
	- `GET /posts` (auth required)
	- `POST /posts` (auth required)
	- `POST /posts/{post}/like` (auth required)
	- `DELETE /posts/{post}/like` (auth required)
	- `GET /posts/{post}/likes` (auth required)
- Comments
	- `POST /posts/{post}/comments` (auth required)
	- `POST /comments/{comment}/replies` (auth required)
	- `POST /comments/{comment}/like` (auth required)
	- `DELETE /comments/{comment}/like` (auth required)
	- `GET /comments/{comment}/likes` (auth required)

## Auth Notes

- Access token is returned in auth responses and should be sent as `Bearer` token.
- Refresh token is stored as an HTTP-only cookie by default.
- `POST /auth/refresh` rotates refresh token and issues a new access token.

## Testing

```bash
php artisan test
```

Feature tests cover auth and feed workflows.
