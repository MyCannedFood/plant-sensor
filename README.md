# Verdant: IoT Environmental Monitoring

Proyek ini terdiri dari dua bagian terpisah:

| Folder | Isi | Jalan di |
|---|---|---|
| `plant-sensor/` | Backend Laravel 13: API untuk perangkat ESP dan untuk dashboard | `http://localhost:8000` |
| `frontend/` | Dashboard Vue 3 + Vite | `http://localhost:5173` |

```
ESP8266 / ESP32 ──POST /api/readings (X-Device-Token)──▶ Laravel API ◀──/api/* (cookie sesi Sanctum)── Vue dashboard
                                                            │
                                                          MySQL / SQLite
```

## Menjalankan pertama kali

### 1. Backend

```bash
cd plant-sensor

# Urutannya penting: pasang Sanctum DULU. config/sanctum.php memakai class
# Sanctum, jadi perintah composer apa pun gagal selama Sanctum belum terpasang.
composer require laravel/sanctum
composer remove livewire/livewire

cp .env.example .env        # lewati kalau .env sudah ada (lihat catatan di bawah)
php artisan key:generate    # hanya kalau .env baru
php artisan migrate:fresh --seed
php artisan serve
```

Kalau Anda memakai `.env` lama, pastikan dua baris ini ada (sudah ditambahkan di `.env` dan `.env.example`):

```
FRONTEND_URL=http://localhost:5173
SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173,localhost,127.0.0.1,127.0.0.1:8000
```

`migrate:fresh` menghapus semua tabel lalu membuatnya ulang. Kalau database Anda sudah berisi
data yang ingin disimpan, pakai `php artisan migrate` saja (hanya menambah tabel `personal_access_tokens`).

Seeder membuat dua akun (password keduanya `password`), lima tanaman dengan data tujuh hari,
dan menulis token perangkat ke `storage/app/private/device-tokens.json`:

- `test@example.com` memiliki Monstera, Cactus, dan Fiddle Leaf Fig.
- `second@example.com` memiliki Calathea dan Boston Fern.

Panduan demo autentikasi dan otorisasi ada di [`demo/README.md`](demo/README.md).

### 2. Frontend

```bash
cd frontend
cp .env.example .env
npm install
npm run dev
```

Buka `http://localhost:5173`, lalu login dengan akun di atas.

Kalau login gagal dengan **502**, berarti Vite tidak bisa menghubungi Laravel. Pastikan
`php artisan serve` berjalan di terminal lain, dan `VITE_BACKEND_URL` di `frontend/.env` memakai
`http://127.0.0.1:8000`, bukan `localhost`. Di Windows, Node menerjemahkan `localhost` ke IPv6,
sedangkan `php artisan serve` hanya mendengarkan IPv4. Setelah mengubah `.env`, jalankan ulang `npm run dev`.

Saat development, Vite meneruskan `/api` dan `/sanctum` ke Laravel (`VITE_BACKEND_URL`),
jadi browser hanya berbicara dengan satu origin dan tidak perlu pengaturan CORS.

### 3. Menjalankan test backend

```bash
cd plant-sensor
php artisan test
```

Tiga test di `ReadingSchemaGuardTest` mencocokkan pesan error khas MySQL
(`Duplicate entry`, `cannot be null`), sehingga hanya lulus kalau test dijalankan dengan MySQL.

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
