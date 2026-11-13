# Pustaka Mini

Aplikasi pencatat daftar bacaan: tambah buku, atur status (ingin dibaca, sedang dibaca, selesai), dan beri rating 1-5.
Proyek UTS Konstruksi dan Evolusi Perangkat Lunak 2026/2027.

## Teknologi

- Backend: Laravel 12 + Sanctum (REST API, login token), dijalankan dengan FrankenPHP
- Frontend: Vue 3 + Vue Router + Axios, disajikan Nginx
- Basis data: MySQL 8.0 (named volume)
- CI/CD: GitHub Actions, image dipublikasikan ke GitHub Container Registry (ghcr.io)

## Alur data

Vue (Axios, header `Authorization: Bearer <token>`) -> Laravel (`auth:sanctum`, validasi, controller) -> Eloquent -> MySQL, lalu respons JSON kembali ke Vue.

## Endpoint

| Method | Path | Auth | Status |
|---|---|---|---|
| POST | /api/register | - | 201, 422 |
| POST | /api/login | - | 200, 401, 422 |
| POST | /api/logout | ya | 200, 401 |
| GET, POST | /api/books | ya | 200 / 201, 401, 422 |
| GET, PUT, DELETE | /api/books/{id} | ya | 200, 401, 404, 422 |

Postman Collection ada di folder `docs/`.

## Menjalankan dengan Docker Compose

```bash
cp .env.example .env
# isi APP_KEY (php artisan key:generate --show) dan password basis data
docker compose up -d --build
docker compose ps
```

- Frontend: http://localhost:8080
- API: http://localhost:8000/api/health

Data basis data disimpan di volume `db_data` dan tetap ada setelah `docker compose down` (jangan memakai `-v`).

## Pemantauan (Prometheus, Grafana, cAdvisor, node-exporter)

Tambahkan `GRAFANA_ADMIN_PASSWORD` (bukan `admin`) ke file `.env`, lalu jalankan `docker compose up -d --build`.

| Layanan | Alamat | Fungsi |
|---|---|---|
| Prometheus | http://localhost:9090 (`/targets`, `/alerts`) | mengumpulkan metrik dan mengevaluasi alarm |
| Grafana | http://localhost:3000 | dashboard "Pustaka Mini - Kesehatan Aplikasi" |
| cAdvisor / node-exporter | hanya di jaringan compose | CPU dan memori container / host |

Konfigurasi ada di folder `monitoring/` (`prometheus.yml`, `alerts.yml`, `grafana/`). Aplikasi mengekspos jumlah permintaan di `GET /api/metrics`.

## Menjalankan test backend

```bash
cd backend
composer install
cp .env.example .env && php artisan key:generate
php artisan test
```

## Alur Git dan CI/CD

- Branch: `main` (dilindungi), `dev`, dan branch fitur (`feat/...`)
- Perubahan ke `main` hanya lewat Pull Request dengan dua status check wajib lulus
- Workflow `.github/workflows/ci.yml`: job `backend-test` dan `frontend-build`, lalu `publish-images` (hanya pada push ke `main`) yang membangun dan mengunggah image ke ghcr.io
Repository: uts-pl-24-542303-sv-25029
