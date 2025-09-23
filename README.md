# Upskill E-learning System

## Setup

1. Copy `.env.example` to `.env` and configure your environment variables:

   ```bash
   cp .env.example .env
   ```

2. Update the `.env` file with your database credentials and other configuration settings.

## Running the Application

Start the PHP development server:

```bash
composer install     # to download dependencies
composer run migrate # to create database tables
composer run seed    # to seed data
composer run start   # to start the application
```

The application will be available at `http://localhost:8000`

## Prerequisites

- PHP 7.4 or higher
- Database (MySQL/PostgreSQL/SQLite)
