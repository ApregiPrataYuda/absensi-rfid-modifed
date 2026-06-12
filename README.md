<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

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

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Dokumentasi Implementasi

- Panduan instalasi untuk user awam di cPanel dan Laragon: [`docs/panduan-instalasi-cpanel-dan-laragon.html`](docs/panduan-instalasi-cpanel-dan-laragon.html)
- Versi PDF panduan instalasi cPanel dan Laragon: [`docs/panduan-instalasi-cpanel-dan-laragon.pdf`](docs/panduan-instalasi-cpanel-dan-laragon.pdf)
- Panduan penggunaan untuk guru dan orang tua: [`docs/panduan-penggunaan-guru-dan-orang-tua.md`](docs/panduan-penggunaan-guru-dan-orang-tua.md)
- Versi HTML siap cetak: [`docs/panduan-penggunaan-guru-dan-orang-tua.html`](docs/panduan-penggunaan-guru-dan-orang-tua.html)
- Versi PDF dengan screenshot: [`docs/panduan-penggunaan-guru-dan-orang-tua.pdf`](docs/panduan-penggunaan-guru-dan-orang-tua.pdf)
- Panduan fitur lengkap versi HTML: [`docs/panduan-fitur-lengkap-absensindo.html`](docs/panduan-fitur-lengkap-absensindo.html)
- Panduan fitur lengkap versi PDF: [`docs/panduan-fitur-lengkap-absensindo.pdf`](docs/panduan-fitur-lengkap-absensindo.pdf)

## Struktur Blade (Pemisahan Layout/Partial)

Template utama sudah dipisah agar setiap halaman/section berada di file terpisah.

### Entry Point

- [`resources/views/app.blade.php`](resources/views/app.blade.php:1)
- [`resources/views/scanner.blade.php`](resources/views/scanner.blade.php:1)

Keduanya hanya me-render layout utama:

```blade
@include('layouts.app')
@include('layouts.scanner')
```

### Layout

- [`resources/views/layouts/app.blade.php`](resources/views/layouts/app.blade.php:1)
- [`resources/views/layouts/scanner.blade.php`](resources/views/layouts/scanner.blade.php:1)

Layout berisi `<head>`, `<style>`, `<script>` global dan include partials + view-section.

### Partial Bersama

- [`resources/views/partials/login.blade.php`](resources/views/partials/login.blade.php:1)
- [`resources/views/partials/sidebar.blade.php`](resources/views/partials/sidebar.blade.php:1)
- [`resources/views/partials/header.blade.php`](resources/views/partials/header.blade.php:1)

### View Section

Setiap `div.view-section` dipisah ke file sendiri di folder:

- [`resources/views/views/`](resources/views/views:1)

Contoh:

- [`resources/views/views/view-admin-dashboard.blade.php`](resources/views/views/view-admin-dashboard.blade.php:1)
- [`resources/views/views/view-data-siswa.blade.php`](resources/views/views/view-data-siswa.blade.php:1)
- [`resources/views/views/view-scanner.blade.php`](resources/views/views/view-scanner.blade.php:1)

### Catatan Penting

- Pastikan ID/kelas elemen tidak diubah agar JS tetap bekerja.
- Urutan include view-section di layout dipertahankan untuk kompatibilitas script.
