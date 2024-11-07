# Laravel 11 FilamentPHP Starter Kit

Clone this repository, run this command on root project directory:

```bash
composer install
npm install
cp .env.example .env
```

Make some ajdustment on database configuration.

```bash
php artisan storage:link
php artisan key:generate
php artisan optimize:clear
php artisan permission:cache-reset
php artisan migrate
php artisan db:seed
```

Then run it

```bash
npm run dev
```

Open another terminal tab and run

```bash
php artisan serve
```

Open Web Broser and go to `http://localhost:8000` and the filamentphp admin panel address is `http://localhost:8000/fadmin`