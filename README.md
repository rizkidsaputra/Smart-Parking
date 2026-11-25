# MyParkir TULT

Sistem monitoring dan manajemen parkir untuk area TULT yang menggabungkan perangkat IoT berbasis ESP32 dengan aplikasi web Laravel 12. Gerbang parkir menggunakan pembacaan KTM (RFID) dan sensor halangan IR untuk mencatat keluar-masuk kendaraan secara otomatis, sementara data ringkasan juga dapat dikirim ke ThingSpeak.

## Struktur Repository

- `firmware/tult_online/` — Kode firmware ESP32 untuk gerbang masuk/keluar, konfigurasi contoh (`config.example.h`), dan daftar library Arduino.
- `website/` — Aplikasi web Laravel 12 (dashboard, API perangkat IoT, manajemen mahasiswa/KTM, area & slot parkir, denda, serta audit transaksi gerbang).

## Fitur Utama

- **Integrasi Gerbang IoT (ESP32)**: Otorisasi kartu RFID masuk/keluar lewat API, kontrol servo palang dengan sensor IR, serta mekanisme pemulihan transaksi jika koneksi terputus.
- **Dashboard & Riwayat Parkir**: Menampilkan jumlah kendaraan aktif, slot tersedia, dan total masuk/keluar harian. Mahasiswa dapat melihat status parkir dan riwayat pribadinya.
- **Notifikasi Lewat Pukul 22.00**: Kendaraan yang masih tercatat parkir melewati jam 22.00 WIB akan memunculkan notifikasi di dashboard mahasiswa.
- **Manajemen Mahasiswa & Kapasitas**: Admin dapat mendaftarkan akun mahasiswa, UID KTM, nomor plat, kendaraan, serta mengatur batas kapasitas parkir.
- **Area Parkir, Slot & Denda**: Halaman publik `/area-parkir` menampilkan ketersediaan area TULT dan area parkir tambahan tanpa login. Admin dapat mengelola area, slot, serta catatan denda.
- **Monitoring Perangkat & Audit**: Admin dapat melihat log transaksi gerbang, melakukan koreksi jika terjadi kendala di lapangan, atau mencatat keluar manual beserta alasannya.
- **Sinkronisasi ThingSpeak**: Mengirim ringkasan statistik parkir TULT secara berkala ke channel ThingSpeak.

## Kebutuhan

**Website:**
- PHP >= 8.2 (`mbstring`, `xml`, `curl`, `zip`, `sqlite3` atau `pdo_mysql`)
- Composer 2
- Node.js tidak wajib untuk menjalankan tampilan karena aset CSS/JS sudah disertakan lokal di `website/public/assets/`.

**Firmware ESP32:**
- Arduino IDE dengan board ESP32
- Library: `MFRC522`, `ESP32Servo`, `ArduinoJson` (v7)

## Menjalankan Website (Linux / Lokal)

1. Pasang dependensi sistem jika belum tersedia (contoh di Linux Mint / Ubuntu):
   ```bash
   sudo apt update
   sudo apt install unzip composer php-cli php-mbstring php-xml php-curl php-zip php-sqlite3
   ```

2. Masuk ke folder `website` dan siapkan konfigurasi:
   ```bash
   cd website
   cp -n .env.example .env
   touch database/database.sqlite
   composer install --no-interaction
   php artisan key:generate
   php artisan migrate
   ```

3. Isi token perangkat di `website/.env` pada variabel `IOT_DEVICE_TOKEN` (bebas buat string acak, misalnya lewat `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'`).

4. (Opsional) Buat akun demo lokal atau akun admin baru:
   ```bash
   # Akun demo lokal (admin@myparkir.test & mahasiswa@myparkir.test)
   php artisan db:seed --class=DemoSeeder

   # Atau buat akun admin sendiri
   php artisan parking:admin
   ```

5. Jalankan server lokal:
   ```bash
   php artisan serve
   ```
   Buka `http://127.0.0.1:8000` (halaman login di `/login`, ringkasan publik di `/area-parkir`).

   Jika ingin diakses dari HP atau ESP32 dalam satu jaringan Wi-Fi, jalankan:
   ```bash
   php artisan serve --host=0.0.0.0 --port=8000
   ```
   Cek IP laptop dengan `hostname -I`, lalu gunakan IP tersebut alih-alih `127.0.0.1`.

## Setup Firmware ESP32

1. Buka folder `firmware/tult_online/`.
2. Salin `config.example.h` menjadi `config.h` (jangan commit `config.h` ke Git).
3. Sesuaikan `WIFI_SSID`, `WIFI_PASSWORD`, `API_BASE` (pakai IP laptop, misal `http://192.168.1.10:8000/api/device`), dan `DEVICE_TOKEN` (samakan dengan `IOT_DEVICE_TOKEN` di `website/.env`).
4. Upload `tult_online.ino` ke ESP32 dan pantau log melalui Serial Monitor (`115200` baud).

**Pemetaan Pin ESP32:**

| Komponen | Pin ESP32 |
| --- | --- |
| SPI (SCK / MISO / MOSI) | 18 / 19 / 23 |
| RFID Reader Masuk (CS) | 5 |
| RFID Reader Keluar (CS) | 17 |
| RFID Reset (RST) | 22 |
| Sensor Obstacle IR (Active LOW) | 32 |
| Servo Palang | 25 |
| Buzzer | 14 |
| LED Hijau / Merah | 26 / 27 |

## Integrasi ThingSpeak (Opsional)

1. Buat channel baru di ThingSpeak dengan 4 field:
   - Field 1: Kendaraan sedang parkir
   - Field 2: Slot tersedia
   - Field 3: Masuk hari ini
   - Field 4: Keluar hari ini
2. Salin **Write API Key** ke `THINGSPEAK_WRITE_API_KEY` di `website/.env`.
3. Uji kirim satu kali dari folder `website`:
   ```bash
   php artisan parking:thingspeak
   ```
4. Untuk pengiriman otomatis setiap menit selama demo, jalankan scheduler di terminal terpisah:
   ```bash
   php artisan schedule:work
   ```

## Endpoint API Perangkat

Semua request memakai metode `POST` dengan header `Authorization: Bearer <IOT_DEVICE_TOKEN>` dan `Accept: application/json`.

| Endpoint | Body JSON | Fungsi |
| --- | --- | --- |
| `/api/device/heartbeat` | `{}` | Menandai perangkat ESP32 sedang online |
| `/api/device/authorize` | `{"uid":"86:C9:10:4E","direction":"entry"}` | Validasi KTM & membuka transaksi (`entry` / `exit`) |
| `/api/device/confirm` | `{"transaction_id":"UUID","outcome":"passed"}` | Konfirmasi kendaraan lewat (`passed`) atau batal (`cancelled`) |

## Pengujian

Jalankan seluruh automated test dari folder `website`:

```bash
php artisan test
```
