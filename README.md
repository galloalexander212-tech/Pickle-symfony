# PICKLE — Pickleball Court Booking System

"Book. Compete. Repeat."

PICKLE is a Symfony and Docker rewrite of the original plain-PHP/XAMPP application: [Pickleball Booking System](https://github.com/galloalexander212-tech/Pickleball-booking-system). The current Composer configuration targets Symfony 8.1 and PHP 8.4 or newer.

## Tech Stack

- Symfony 8.1, Twig, Doctrine ORM
- MySQL 8.0 with Docker Compose
- phpMyAdmin and Mailpit
- Symfony Security with form login, bcrypt-compatible password hashing, and account lockout

## Requirements

- Docker Desktop
- PHP 8.4 or newer (required by `composer.json`)
- Composer
- Symfony CLI, or PHP's built-in server

## Setup

1. Start the database and supporting services:

   ```sh
   docker compose up -d
   ```

   MySQL is available on port `3306`, phpMyAdmin at `http://localhost:8080`, and Mailpit at `http://localhost:8025` (SMTP port `1025`).

2. Install PHP dependencies:

   ```sh
   composer install
   ```

3. Database initialization:

   On a **fresh Docker volume**, MySQL loads `init_webprog_v2.sql` on its first start, creating the schema and seed data. The entrypoint initialization script runs only when the MySQL data directory is empty. Existing volumes keep their data and do not rerun this script.

   To import the SQL manually into an existing volume, first back up any data you need; the script drops and recreates the application tables before reseeding:

   ```sh
   docker cp init_webprog_v2.sql webprog-db:/tmp/init_webprog_v2.sql
   docker exec webprog-db sh -c "mysql -uroot -psecret webprog < /tmp/init_webprog_v2.sql"
   ```

4. Start the application from the repository root:

   ```sh
   symfony serve
   ```

   Alternatively:

   ```sh
   php -S 127.0.0.1:8000 -t public
   ```

5. Open [http://127.0.0.1:8000](http://127.0.0.1:8000).

## Demo Accounts

| Username | Password | Role |
| --- | --- | --- |
| `admin` | `pickle123` | `ROLE_ADMIN` |
| `juan` | `pickle123` | `ROLE_USER` |
| `maria` | `pickle123` | `ROLE_USER` |

The admin account can manage courts and events at `/admin`.

## Features

- Browse courts, open plays, and tournaments
- Book courts with live slot availability
- Join and leave events
- Member account and profile editing
- Admin court/event CRUD with image uploads
- Five-attempt login lockout for 15 minutes

## Project Structure

- `src/Controller/` — request handling and application flows
- `src/Entity/` — Doctrine entities mapped to the MySQL schema
- `templates/` — Twig pages and shared components
- `init_webprog_v2.sql` — database schema and development seed data
