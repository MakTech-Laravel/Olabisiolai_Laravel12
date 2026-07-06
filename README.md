<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Production (Docker / Coolify + Reverb)

This app ships with **nginx + Reverb** in one image (`Dockerfile`, `docker/nginx.conf`). WebSockets use **`wss://` on the same public host/port as the API (443)**; Reverb’s **8089** is internal only. See **[docs/COOLIFY_REVERB.md](docs/COOLIFY_REVERB.md)** and `.env.production.example` before deploying.

## API Documentation (Swagger / OpenAPI)

The API is documented with [darkaonline/l5-swagger](https://github.com/DarkaOnLine/L5-Swagger) using PHP 8 attributes
(`OpenApi\Attributes`), not YAML/JSON files.

- **Swagger UI:** `/api/documentation`
- **Raw OpenAPI JSON:** `/docs`
- **Auth:** the API uses Laravel Passport, not Sanctum. Personal access tokens are issued by
  `POST /api/v1/auth/login` (and the OTP/2FA/device-verification steps that follow it) and sent as
  `Authorization: Bearer <token>`. In Swagger UI, click **Authorize** and paste the token.

### Regenerating docs

```bash
composer docs
# or
php artisan l5-swagger:generate
```

With `L5_SWAGGER_GENERATE_ALWAYS=true` (set in `.env` for local dev), the spec also regenerates automatically on
every request to `/api/documentation`, so you rarely need to run this by hand locally.

### Adding annotations to a new endpoint

1. Add `use OpenApi\Attributes as OA;` to the controller.
2. Add an `#[OA\Get(...)]` / `#[OA\Post(...)]` / etc. attribute directly above the controller method, matching the
   route registered in `routes/api/v1/*.php`. Required fields: `path` (include the `/v1/...` prefix but not
   `/api`), `summary`, `tags` (group by resource: `Auth`, `Users`, `Admin`, `Billing`, ...), `responses`.
3. Add `security: [['passport' => []]]` on every operation that sits behind `auth:api` / `auth:admin_api`
   middleware. Omit it on public routes (register, login, forgot-password, etc.).
4. Reference shared response shapes instead of redefining them inline:
   - `#/components/schemas/User`, `Admin`, `Payment`, `Subscription`, `AccessToken` — domain objects
   - `#/components/schemas/ApiResponse` — generic `{success, message, data}` success envelope
   - `#/components/schemas/ErrorResponse` — generic `{success: false, message, data}` failure envelope
   - `#/components/schemas/ValidationError` — Laravel's default 422 shape (`{message, errors}`), used when a
     `FormRequest` fails validation before the controller runs
5. To add a new reusable schema, create a class in `app/OpenApi/Schemas/` with an `#[OA\Schema(schema: '...')]`
   attribute (see the existing files for the pattern), then reference it via `ref: '#/components/schemas/<name>'`.
6. Run `composer docs` and check `/api/documentation` to confirm the new endpoint renders correctly.

See `app/Http/Controllers/Api/V1/AuthController.php` (e.g. the `login` method) for a fully-worked example
covering request bodies, multiple response shapes, and error responses.

Base API info (title, server URL, security scheme) lives in `app/OpenApi/BaseInfo.php`, kept separate from
controllers.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
