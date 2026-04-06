# Appifylab Backend

Laravel 13 REST API for the Appifylab feed application.

## Stack

- PHP 8.4+
- Laravel 13
- PostgreSQL in production
- `php-open-source-saver/jwt-auth` 2.9

## What This Backend Does

- Issues JWT access tokens for the frontend.
- Supports refresh token rotation.
- Exposes post, comment, reply, like, and follow APIs.
- Returns feed data for the React frontend.
- Supports lazy comment loading so the feed does not ship full nested comment trees up front.

## Implemented Features

- Authentication
	- Register
	- Login
	- Current user (`me`)
	- Refresh token rotation
	- Logout
- Feed and social actions
	- Public and private posts
	- Create, update, and delete posts
	- Post image upload and removal
	- Like / unlike posts
	- Comment on posts
	- Reply to top-level comments
	- Like / unlike comments and replies
	- Follow / unfollow users
	- List followed users
- Performance and API shape
	- Feed posts return `comments_count` instead of loading the full comment tree
	- Comments are fetched on demand from a dedicated endpoint
	- New comment and reply responses are returned immediately so the frontend can update without a full refetch

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
	- `PUT /posts/{post}` (auth required)
	- `DELETE /posts/{post}` (auth required)
	- `POST /posts/{post}/like` (auth required)
	- `DELETE /posts/{post}/like` (auth required)
	- `GET /posts/{post}/likes` (auth required)
- Comments
	- `GET /posts/{post}/comments` (auth required, lazy-loaded)
	- `POST /posts/{post}/comments` (auth required)
	- `POST /comments/{comment}/replies` (auth required)
	- `POST /comments/{comment}/like` (auth required)
	- `DELETE /comments/{comment}/like` (auth required)
	- `GET /comments/{comment}/likes` (auth required)
- Follows
	- `GET /users/following` (auth required)
	- `POST /users/{user}/follow` (auth required)
	- `DELETE /users/{user}/follow` (auth required)

## Database and Production Setup

The production server uses PostgreSQL and an isolated database for this app.

Production values used on the server:

```env
APP_URL=https://api-appifylab.faisal.one
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=appifylab
DB_USERNAME=appifylab
FRONTEND_URL=https://appifylab.faisal.one
CORS_ALLOWED_ORIGINS=https://appifylab.faisal.one
```

## Local Setup

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

6. Start the local server

```bash
php artisan serve
```

Backend base URL locally:

```text
http://localhost:8000
```

## Production Deployment

- Live backend domain: `https://api-appifylab.faisal.one`
- Deployed from its own GitHub repository.
- Runs from `/var/www/appifylab-backend` on the server.
- Uses Nginx + PHP-FPM.
- Frontend origin is allowed through CORS so the React app can authenticate and call the API.

## Seed Data

- The seeders create a demo user and sample feed content so the frontend has data immediately after deployment.
- Factory data was made deterministic for server seeding reliability.
- Seeding is safe to run with:

```bash
php artisan db:seed --force
```

## Auth Notes

- Access token is returned in auth responses and should be sent as a `Bearer` token.
- Refresh token is stored as an HTTP-only cookie by default.
- `POST /auth/refresh` rotates the refresh token and issues a new access token.

## Testing

```bash
php artisan test
```

Feature tests cover auth and feed workflows.
