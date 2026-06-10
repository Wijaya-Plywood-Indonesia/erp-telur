# 🥚 ERP Telur — PT. Wijaya Plywood Indonesia

> Sistem ERP (Enterprise Resource Planning) internal untuk manajemen produksi dan distribusi **telur**, dikembangkan oleh tim IT PT. Wijaya Plywood Indonesia.

---

## 📋 Deskripsi

**ERP Telur** adalah aplikasi web berbasis Laravel yang dibangun untuk mengelola operasional bisnis telur secara terintegrasi. Aplikasi ini menggunakan panel admin berbasis **FilamentPHP** untuk antarmuka yang modern dan responsif.

---

## 🛠️ Tech Stack

| Komponen | Teknologi |
|----------|-----------|
| Backend Framework | Laravel 12 |
| PHP Version | PHP ^8.2 |
| Admin Panel | FilamentPHP 4.0 |
| Role & Permission | Filament Shield 4.0 |
| Export Excel | Maatwebsite Excel 3.1 |
| Frontend Build | Vite + Tailwind CSS |
| Package Manager (JS) | Bun / npm |
| Database Default | SQLite (dapat dikonfigurasi ke MySQL) |

---

## ⚙️ Requirements

Sebelum instalasi, pastikan environment memenuhi persyaratan berikut:

- PHP >= 8.2
- Composer
- Node.js & npm (atau Bun)
- SQLite / MySQL / MariaDB
- Extension PHP: `pdo`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`

---

## 🚀 Instalasi

### 1. Clone Repositori

```bash
git clone https://github.com/Wijaya-Plywood-Indonesia/erp-telur.git
cd erp-telur
```

### 2. Setup Otomatis (Rekomendasi)

```bash
composer run setup
```

Perintah ini akan secara otomatis menjalankan:
- `composer install`
- Menyalin `.env.example` → `.env`
- Generate application key
- Menjalankan migrasi database
- `npm install` & `npm run build`

### 3. Setup Manual (Langkah demi Langkah)

```bash
# Install dependensi PHP
composer install

# Salin file konfigurasi environment
cp .env.example .env

# Generate application key
php artisan key:generate

# Jalankan migrasi database
php artisan migrate

# Install dependensi JavaScript
npm install

# Build asset frontend
npm run build
```

---

## 🔧 Konfigurasi Environment

Buka file `.env` dan sesuaikan konfigurasi berikut:

```env
APP_NAME="ERP Telur"
APP_URL=http://localhost

# Konfigurasi Database (default: SQLite)
DB_CONNECTION=sqlite

# Untuk MySQL, uncomment dan isi berikut:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=erp_telur
# DB_USERNAME=root
# DB_PASSWORD=
```

---

## ▶️ Menjalankan Aplikasi

### Mode Development

```bash
composer run dev
```

Perintah ini akan menjalankan secara bersamaan:
- PHP development server (`php artisan serve`)
- Queue listener
- Log watcher (Pail)
- Vite dev server

Aplikasi akan tersedia di: **http://localhost:8000**

### Mode Production

```bash
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan serve
```

---

## 🧪 Testing

```bash
composer run test
```

Atau langsung menggunakan PHPUnit:

```bash
php artisan test
```

---

## 📁 Struktur Direktori

```
erp-telur/
├── app/                    # Logic utama aplikasi (Models, Controllers, Filament)
│   ├── Filament/           # Resource, Pages, dan Widgets panel admin
│   ├── Models/             # Eloquent Models
│   └── ...
├── config/                 # File konfigurasi Laravel
├── database/
│   ├── migrations/         # Migrasi database
│   ├── seeders/            # Data awal / seeder
│   └── factories/          # Factory untuk testing
├── public/                 # Asset publik & entry point web
├── resources/
│   ├── views/              # Blade templates
│   └── css/ & js/          # Asset frontend
├── routes/                 # Definisi routing aplikasi
├── storage/                # Log, cache, file upload
├── tests/                  # Unit & Feature tests
├── .env.example            # Template konfigurasi environment
├── composer.json           # Dependensi PHP
└── package.json            # Dependensi JavaScript
```

---

## 🔐 Akses Panel Admin

Panel admin dibangun dengan **FilamentPHP** dan dapat diakses di:

```
http://localhost:8000/admin
```

Manajemen role dan permission menggunakan **Filament Shield**. Pastikan untuk menjalankan perintah berikut setelah instalasi:

```bash
php artisan shield:generate --all
```

---

## 📦 Releases

| Versi | Keterangan |
|-------|------------|
| V.1.0.3 | Release Versi Beta (Juni 2026) |
| V.1.0.2 | *(lihat halaman Releases)* |
| V.1.0.1 | *(lihat halaman Releases)* |
| V.1.0.0 | *(lihat halaman Releases)* |

Lihat semua rilis di: [GitHub Releases](https://github.com/Wijaya-Plywood-Indonesia/erp-telur/releases)

---

## 👥 Tim Pengembang

Dikembangkan oleh tim IT **PT. Wijaya Plywood Indonesia**.

---

## 📄 Lisensi

Proyek ini bersifat **privat** dan hanya digunakan untuk keperluan internal PT. Wijaya Plywood Indonesia.

---

*Dokumentasi ini akan terus diperbarui seiring perkembangan aplikasi.*
