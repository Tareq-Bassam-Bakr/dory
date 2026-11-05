# DORY API

Laravel 13 backend of the DORY platform. Documentation lives in the
repository's [`docs/`](../docs) folder:

- [Local setup](../docs/LOCAL_BACKEND_SETUP.md): install, run, test
- [Architecture](../docs/BACKEND_ARCHITECTURE.md)
- [API conventions](../docs/API_CONVENTIONS.md) and [authentication](../docs/API_AUTHENTICATION.md)
- [Database](../docs/DATABASE_FOUNDATION.md), [multi-tenancy](../docs/MULTI_TENANCY.md), [security](../docs/SECURITY.md)

Quick start (PHP 8.3+):

```bash
composer install
```

```bash
cp .env.example .env
```

```bash
php artisan key:generate
```

```bash
php artisan migrate
```

```bash
php artisan serve
```

```bash
composer test
```
