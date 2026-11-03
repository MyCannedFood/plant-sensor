# Verdant: IoT Environmental Monitoring

Proyek ini terdiri dari dua bagian terpisah:

| Folder | Isi | Jalan di |
|---|---|---|
| `backend/` | Backend Laravel 13: API untuk perangkat ESP dan untuk dashboard | `http://localhost:8000` |
| `frontend/` | Dashboard Vue 3 + Vite | `http://localhost:5173` |

```
ESP8266 / ESP32 ──POST /api/readings (X-Device-Token)──▶ Laravel API ◀──/api/* (cookie sesi Sanctum)── Vue dashboard
                                                            │
                                                          MySQL / SQLite
```

## Autentikasi & Otorisasi (Selesai)

| Tipe | Metode | Detail |
|---|---|---|
| **User (Dashboard)** | Email/password + Google OAuth | Sanctum SPA (cookie HttpOnly + CSRF) |
| **Device (ESP)** | API Token (`X-Device-Token` header) | Stateless, SHA-256 hash di DB, 30 req/menit |

**User**: Hanya bisa akses tanaman miliknya sendiri (404 untuk milik orang lain).  
**Device**: Hanya bisa kirim reading untuk device-nya sendiri (token = identitas).

## Menjalankan pertama kali

### 1. Backend

```bash
cd backend

cp .env.example .env        # lewati kalau .env sudah ada
php artisan key:generate    # hanya kalau .env baru

# Konfigurasi Google OAuth (opsional, untuk login Google)
# Isi GOOGLE_CLIENT_ID & GOOGLE_CLIENT_SECRET di .env
# Redirect URI sudah di set di config/service.php. 
# Jika masih tidak bisa, tambah Redirect URI={APP_URL}/api/auth/google/callback di .env

php artisan migrate --seed
php artisan serve
```

`migrate --seed` membuat tabel + data demo:
- 2 akun (password: `password`)
- 5 tanaman dengan 7 hari data
- Token perangkat di `storage/app/private/device-tokens.json`

**Akun demo:**
- `test@example.com` — Monstera, Cactus, Fiddle Leaf Fig
- `second@example.com` — Calathea, Boston Fern

> **Catatan**: Sanctum & Socialite sudah terpasang. Tidak perlu `composer require` lagi.
- lakukan `composer require sacntum && composer require Socialite` jika belum ada 

#### Troubleshooting Backend

| Masalah | Solusi |
|---|---|
| `Class "Laravel\Sanctum\SanctumServiceProvider" not found` | `composer install` (vendor hilang) |
| `No application encryption key has been specified` | `php artisan key:generate` |
| Migration gagal: `SQLSTATE[HY000]: General error: 1 no such table` | `php artisan migrate:fresh --seed` (reset DB) |
| Google OAuth: `Error 400: redirect_uri_mismatch` | Pastikan `GOOGLE_REDIRECT_URI` di `.env` sama persis di Google Cloud Console (termasuk trailing slash) |
| Google OAuth: `invalid_client` | `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` salah atau project Google Cloud tidak aktif |
| `SESSON_DOMAIN` error di production | Set `SESSION_DOMAIN=.domain.com` dan `SESSION_SECURE_COOKIE=true` |
| Cookie tidak terkirim (Safari/Incognito) | Pastikan `SESSION_SECURE_COOKIE=true` di HTTPS, `SANCTUM_STATEFUL_DOMAINS` benar |

### 2. Frontend

```bash
cd frontend
cp .env.example .env
npm install
npm run dev
```

Buka `http://localhost:5173`, login dengan akun di atas **atau** klik "Continue with Google".

#### Troubleshooting Frontend

| Masalah | Solusi |
|---|---|
| Login 502 / "Cannot reach server" | `VITE_BACKEND_URL=http://127.0.0.1:8000` (bukan `localhost`), restart `npm run dev` |
| CSRF token mismatch / 419 | Hapus cookie `XSRF-TOKEN` & `laravel_session`, reload halaman |
| "Continue with Google" redirect ke halaman putih | `GOOGLE_CLIENT_ID` belum di-set di backend `.env` |
| Logout lalu login lagi gagal | Hard refresh (Ctrl+Shift+R) — cache service worker |
| Hot reload tidak jalan | `npm run dev -- --force` atau cek port 5173 tidak dipakai lain |
| TypeScript error di `import.meta.env` | Restart TS server di VS Code (Cmd+Shift+P → "TypeScript: Restart TS Server") |

### 3. Menjalankan test backend

```bash
cd plant-sensor
php artisan test
```

Tiga test di `ReadingSchemaGuardTest` mencocokkan pesan error khas MySQL
(`Duplicate entry`, `cannot be null`), sehingga hanya lulus kalau test dijalankan dengan MySQL.

#### Troubleshooting Test

| Masalah | Solusi |
|---|---|
| Test gagal: `SQLSTATE[HY000] [2002] Connection refused` | `DB_CONNECTION=sqlite` di `.env.testing` atau jalankan MySQL |
| Test `ReadingSchemaGuardTest` gagal di SQLite | Normal — test itu butuh MySQL. Skip: `php artisan test --exclude-group=mysql` |
| `refreshDatabase` lambat | Pakai SQLite in-memory: `DB_CONNECTION=sqlite` `DB_DATABASE=:memory:` |

## Endpoint API

### Untuk perangkat (header `X-Device-Token`)

| Method | URL | Keterangan |
|---|---|---|
| GET | `/api/ping` | Cek server hidup |
| POST | `/api/readings` | Kirim data sensor. 201 data baru, 200 kiriman ulang identik, 409 konflik |

### Untuk dashboard (login dengan cookie sesi Sanctum)

Alurnya: `GET /sanctum/csrf-cookie`, lalu `POST /api/login`. Setelah itu semua request
membawa cookie sesi secara otomatis.

| Method | URL | Keterangan |
|---|---|---|
| POST | `/api/login` | Login (dibatasi 5 percobaan/menit) |
| POST | `/api/logout` | Logout |
| GET | `/api/user` | User yang sedang login |
| GET | `/api/plants` | Kartu tanaman untuk Overview |
| POST | `/api/plants` | Tambah tanaman |
| GET | `/api/plants/{id}` | Detail untuk halaman monitor: metrik, device, alert terbaru |
| PATCH | `/api/plants/{id}` | Ubah detail tanaman |
| DELETE | `/api/plants/{id}` | Hapus tanaman beserta device, reading, dan alert-nya |
| GET | `/api/plants/{id}/readings?metric=co2&range=24h` | Data grafik (`range`: 1h, 24h, 7d) |
| PUT | `/api/devices/{id}/thresholds` | Simpan batas min/max per parameter |
| GET | `/api/alerts?filter=all\|alert\|watch\|resolved&plant={id}` | Riwayat alert |
| GET | `/api/alerts/summary` | Jumlah alert aktif (angka di ikon lonceng) |

Setiap user hanya bisa melihat dan mengubah tanaman miliknya. Tanaman milik orang lain dijawab 404.

## Struktur kode

### Backend (`plant-sensor/app`)

| Lokasi | Isi |
|---|---|
| `Http/Controllers/ReadingController.php` | Ingest data dari ESP (tidak berubah) |
| `Http/Controllers/Dashboard/` | Controller API untuk dashboard |
| `Http/Requests/` | Validasi input (plant, threshold, login, reading) |
| `Http/Resources/` | Bentuk JSON yang dikirim ke frontend |
| `Policies/PlantPolicy.php` | Aturan kepemilikan tanaman |
| `Support/MetricSnapshot.php` | Nilai terbaru + status (good/watch/alert/offline) per parameter |

### Frontend (`frontend/src`)

| Lokasi | Isi |
|---|---|
| `api/` | Klien axios dan daftar semua endpoint |
| `composables/` | Status login, pesan sementara, jam berjalan |
| `views/` | Halaman: Login, Overview, Plant Monitor, Alert History |
| `components/` | Kartu tanaman, grafik, form threshold, daftar device, dll. |
| `assets/css/` | Desain "Verdant" dari versi Livewire, plus `spa.css` |
| `utils/format.js` | Format angka, waktu, dan kalimat alert |

Data di dashboard diperbarui otomatis (polling): halaman tanaman setiap 5 detik,
Overview setiap 10 detik, dan alert setiap 15 detik.

## Deploy ke server

Cara paling sederhana adalah satu domain untuk keduanya: Nginx menyajikan `frontend/dist`
dan meneruskan `/api` serta `/sanctum` ke Laravel. Dengan cara ini `VITE_API_URL` dibiarkan kosong.

Kalau dipisah per subdomain (misalnya `app.domain.com` dan `api.domain.com`):

- Frontend: `VITE_API_URL=https://api.domain.com`, lalu `npm run build`
- Backend: `FRONTEND_URL=https://app.domain.com`, `SANCTUM_STATEFUL_DOMAINS=app.domain.com`,
  `SESSION_DOMAIN=.domain.com`, dan `SESSION_SECURE_COOKIE=true`

## Yang belum ada

- **Alert otomatis.** Status merah di dashboard sudah muncul saat nilai melewati batas, tetapi
  record di tabel `alerts` belum dibuat otomatis ketika reading masuk. Data alert yang terlihat
  berasal dari seeder.
- **Sensor lain di diagram** (pH, EC, suhu air, cahaya, level air) belum didukung backend.
- **Pendaftaran user dan manajemen device** belum ada di dashboard. User dan device masih dibuat
  lewat seeder atau `php artisan tinker`.
