# DualStack API Documentation

Dokumentasi ini merangkum API backend DualStack berdasarkan daftar route Laravel yang diperiksa pada 30 Oktober 2026 serta implementasi controller dan alur yang telah dibangun selama pengembangan.

> **Catatan penting:** dokumentasi ini menggambarkan kontrak API yang diketahui dari route dan implementasi yang telah dibahas. Untuk field respons yang bergantung pada controller atau Resource Laravel, respons aktual dari aplikasi tetap menjadi acuan. Base URL production, rate limit, mekanisme refund, dan beberapa detail gateway belum ditetapkan.

## 1. Informasi Umum

### Base URL

| Lingkungan | Base URL |
|---|---|
| Local | `http://127.0.0.1:8000/api` |
| Sandbox callback (contoh pengembangan) | `https://<domain-ngrok>/api/midtrans/notification` |
| Production | Belum ditetapkan |

### Format request

Untuk request JSON, gunakan header:

```http
Accept: application/json
Content-Type: application/json
```

Untuk upload file gunakan `multipart/form-data`. Biarkan Postman/Thunder Client membuat boundary secara otomatis.

### Autentikasi

Endpoint yang dilindungi menggunakan Laravel Sanctum. Setelah login, gunakan token pada header:

```http
Authorization: Bearer <SANCTUM_TOKEN>
```

Jangan pernah mengirim token, Server Key, Client Key, atau Signature Secret Key ke GitHub, screenshot publik, atau dokumentasi yang dibagikan.

### Role

- `customer`: pengguna marketplace.
- `admin`: administrator.

Route admin dilindungi `auth:sanctum` dan middleware `admin` (`EnsureUserIsAdmin`). Registrasi publik tidak menerima field `role`; akun baru mendapat role default `customer`.

### Status HTTP umum

| Status | Makna |
|---|---|
| `200` | Request berhasil. |
| `201` | Resource berhasil dibuat. |
| `400` | Request tidak lengkap/tidak valid pada endpoint tertentu. |
| `401` | Token tidak ada, tidak valid, atau sudah dicabut. |
| `403` | Pengguna tidak memiliki hak akses. |
| `404` | Resource tidak ditemukan atau tidak boleh diakses oleh pengguna tersebut. |
| `409` | Konflik, misalnya review duplikat. |
| `422` | Validasi gagal atau aturan bisnis tidak terpenuhi. |
| `502` / `503` | Layanan eksternal atau konfigurasi gateway tidak tersedia. |

Bentuk JSON error mengikuti handler Laravel dan controller masing-masing; tidak semua endpoint menggunakan envelope yang identik.

---

## 2. Ringkasan Endpoint

Daftar berikut mencerminkan route yang terlihat pada `php artisan route:list --path=api`.

### Authentication

| Method | Endpoint | Akses | Fungsi |
|---|---|---|---|
| `POST` | `/auth/register` | Publik | Registrasi pelanggan |
| `POST` | `/auth/login` | Publik | Login dan memperoleh token |
| `GET` | `/auth/me` | Login | Profil akun aktif |
| `POST` | `/auth/logout` | Login | Mencabut token aktif |

### Katalog dan kategori publik

| Method | Endpoint | Akses | Fungsi |
|---|---|---|---|
| `GET` | `/categories` | Publik | Daftar kategori aktif |
| `GET` | `/products` | Publik | Katalog, pencarian, filter, sorting, pagination |
| `GET` | `/products/{slug}` | Publik | Detail produk berdasarkan slug |
| `GET` | `/products/{productId}/reviews` | Publik | Daftar ulasan produk |

### Pelanggan

| Method | Endpoint | Akses | Fungsi |
|---|---|---|---|
| `POST` | `/checkout` | Login | Membuat pesanan |
| `GET` | `/orders` | Login | Riwayat pesanan milik sendiri |
| `GET` | `/orders/{id}` | Login | Detail pesanan milik sendiri |
| `POST` | `/orders/{id}/payment` | Login | Membuat transaksi Midtrans Snap |
| `GET` | `/products/{id}/download` | Login | Mengunduh template jika berhak |
| `GET` | `/customer/dashboard` | Login | Ringkasan dashboard pelanggan |
| `GET` | `/customer/profile` | Login | Melihat profil sendiri |
| `PUT` | `/customer/profile` | Login | Memperbarui nama dan email sendiri |
| `PUT` | `/customer/profile/password` | Login | Mengganti password sendiri |
| `GET` | `/customer/wishlist` | Login | Daftar wishlist sendiri |
| `POST` | `/customer/wishlist` | Login | Menambah produk ke wishlist |
| `DELETE` | `/customer/wishlist/{productId}` | Login | Menghapus produk dari wishlist sendiri |
| `POST` | `/customer/reviews` | Login | Membuat review atas produk yang memenuhi syarat |
| `PUT` | `/customer/reviews/{id}` | Login | Memperbarui review sendiri |
| `DELETE` | `/customer/reviews/{id}` | Login | Menghapus review sendiri |

### Administrator

Seluruh route di bawah menggunakan `auth:sanctum` dan middleware `admin`.

| Method | Endpoint | Fungsi |
|---|---|---|
| `GET` | `/admin/dashboard` | Statistik dashboard admin |
| `GET` | `/admin/categories` | Daftar kategori |
| `POST` | `/admin/categories` | Membuat kategori |
| `GET` | `/admin/categories/{category}` | Detail kategori |
| `PUT`, `PATCH` | `/admin/categories/{category}` | Memperbarui kategori |
| `DELETE` | `/admin/categories/{category}` | Menghapus kategori jika tidak memiliki produk terkait |
| `GET` | `/admin/products` | Daftar produk admin, termasuk draft |
| `POST` | `/admin/products` | Membuat produk |
| `GET` | `/admin/products/{product}` | Detail produk admin |
| `PUT`, `PATCH` | `/admin/products/{product}` | Memperbarui produk |
| `DELETE` | `/admin/products/{product}` | Menghapus produk (soft delete pada model) |
| `POST` | `/admin/products/{productId}/media` | Upload thumbnail, preview, dan ZIP template |
| `GET` | `/admin/orders` | Daftar seluruh pesanan |
| `GET` | `/admin/orders/{id}` | Detail pesanan |
| `PATCH` | `/admin/orders/{id}/status` | Memperbarui status pesanan dengan aturan bisnis |
| `GET` | `/admin/users` | Daftar pengguna |
| `GET` | `/admin/users/{id}` | Detail pengguna |
| `PUT` | `/admin/users/{id}` | Memperbarui profil pengguna |
| `PATCH` | `/admin/users/{id}/role` | Mengubah role pengguna |
| `GET` | `/admin/reports/sales` | Laporan penjualan berdasarkan periode |

### Integrasi Midtrans

| Method | Endpoint | Akses | Fungsi |
|---|---|---|---|
| `POST` | `/midtrans/notification` | Server-to-server, tanpa Sanctum | Menerima notifikasi pembayaran Midtrans; validasi wajib di controller |

### Route bawaan Laravel

`GET /user` masih terdaftar dengan `auth:sanctum` pada route list. Endpoint ini merupakan route bawaan yang tumpang tindih fungsi dengan `GET /auth/me`. Untuk frontend, gunakan `/auth/me`; hapus route `/user` jika sudah dipastikan tidak dipakai.

---

## 3. Authentication

### 3.1 Registrasi

`POST /auth/register`

Request:

```json
{
  "name": "Pelanggan Baru",
  "email": "customer@example.test",
  "password": "Customer123!",
  "password_confirmation": "Customer123!"
}
```

Aturan:
- `name`, `email`, `password`, dan konfirmasi password divalidasi.
- `role` tidak boleh dikirim sebagai input registrasi; pengguna baru menjadi `customer` secara default.
- Contoh email dan password di atas hanya data pengujian.

Respons sukses yang pernah diuji: `201 Created`.

### 3.2 Login

`POST /auth/login`

Request:

```json
{
  "email": "customer@example.test",
  "password": "Customer123!"
}
```

Respons sukses memuat token Sanctum dan data pengguna. Simpan token di sisi frontend secara aman dan gunakan sebagai Bearer Token untuk endpoint terlindungi.

### 3.3 Pengguna aktif

`GET /auth/me`

Header: `Authorization: Bearer <token>`.

Mengembalikan identitas pengguna aktif (ID, nama, email sesuai implementasi controller).

### 3.4 Logout

`POST /auth/logout`

Header: `Authorization: Bearer <token>`.

Mencabut token aktif. Request berikutnya dengan token yang sama seharusnya mendapat `401`.

---

## 4. Kategori Publik dan Admin

### 4.1 Daftar kategori publik

`GET /categories`

Mengembalikan kategori aktif. Skema kategori mencakup `id`, `name`, `slug`, `description`, `image`, `is_active`, dan `sort_order` sesuai migration.

### 4.2 CRUD kategori admin

Semua endpoint menggunakan Bearer Token admin.

Contoh membuat kategori:

`POST /admin/categories`

```json
{
  "name": "Landing Page",
  "slug": "landing-page",
  "description": "Template landing page modern",
  "is_active": true,
  "sort_order": 1
}
```

Endpoint detail, update, dan delete menggunakan ID kategori pada path. Penghapusan kategori yang masih memiliki produk ditolak oleh controller (respons konflik `409` pada implementasi yang dibahas).

---

## 5. Katalog Produk

### 5.1 Daftar produk

`GET /products`

Katalog publik hanya menampilkan produk dengan `status = published`.

Parameter query yang tersedia pada implementasi katalog:

| Parameter | Tipe | Keterangan |
|---|---|---|
| `search` | string | Mencari nama, deskripsi singkat, atau deskripsi produk |
| `category` | string | Filter berdasarkan slug kategori |
| `category_id` | integer | Filter berdasarkan ID kategori |
| `technology` | string | Filter teknologi (pencocokan sebagian) |
| `is_free` | boolean | Filter produk gratis/berbayar, contoh `1` atau `0` |
| `is_featured` | boolean | Filter produk unggulan, contoh `1` atau `0` |
| `min_price` | number | Harga minimum |
| `max_price` | number | Harga maksimum |
| `min_rating` | number `0–5` | Rating minimum |
| `sort` | enum | `latest`, `price_asc`, `price_desc`, `popular`, `rating` |
| `per_page` | integer `1–24` | Jumlah item per halaman; default `12` pada implementasi katalog terbaru |
| `page` | integer | Nomor halaman pagination Laravel |

Contoh:

```http
GET /products?category=admin-dashboard&min_price=50000&max_price=300000&min_rating=3&sort=rating&per_page=12
```

Jika `min_price` lebih besar daripada `max_price`, endpoint mengembalikan `422`.

Catatan: filter harga saat ini membandingkan kolom `price` (harga dasar), bukan harga efektif setelah diskon. Pastikan hal ini disesuaikan bila frontend menampilkan harga diskon sebagai acuan filter.

### 5.2 Detail produk

`GET /products/{slug}`

Contoh:

```http
GET /products/obsidian-admin-dashboard
```

Detail produk memuat informasi produk dan kategori. Jika helper media URL sudah diterapkan, respons juga memuat `thumbnail_url` serta `preview_image_urls`.

Informasi file privat (`file_path`, `file_name`, `file_size`) tidak boleh ditampilkan melalui endpoint katalog publik.

---

## 6. Produk Admin dan Upload Media

### 6.1 CRUD produk

Semua endpoint memakai Bearer Token admin.

`POST /admin/products` menerima data produk, di antaranya:

```json
{
  "category_id": 1,
  "name": "DualStack Landing Page",
  "slug": "dualstack-landing-page",
  "short_description": "Template landing page modern",
  "description": "Deskripsi lengkap template",
  "demo_url": "https://example.com/demo",
  "price": 150000,
  "discount_price": 120000,
  "technology": "React, Tailwind CSS",
  "is_free": false,
  "is_featured": true,
  "status": "draft"
}
```

Catatan:
- `category_id` harus merujuk kategori yang ada.
- `slug` harus unik.
- `discount_price` tidak boleh lebih tinggi dari `price` pada validasi pembuatan produk.
- Status produk yang didukung pada controller: `draft` dan `published`.
- Jangan mengisi `file_path` dari request publik atau membiarkan pelanggan menentukan path file privat. File template diunggah melalui endpoint media admin.

### 6.2 Upload media produk

`POST /admin/products/{productId}/media`

Authorization: Bearer Token admin.

Body: `multipart/form-data`.

| Field | Tipe | Aturan |
|---|---|---|
| `thumbnail` | File | JPG/JPEG/PNG/WEBP, maks. 5 MB |
| `preview_images[]` | File array | JPG/JPEG/PNG/WEBP, maks. 5 MB per file; batas total 10 gambar preview sesuai controller |
| `template_file` | File | ZIP, maks. 100 MB |

Penyimpanan:
- Thumbnail dan preview di disk `public` pada `products/thumbnails` dan `products/previews`.
- ZIP template di disk privat `template_downloads`, root `storage/app/private/templates`.

Jangan mengatur `Content-Type` multipart secara manual di Postman/Thunder Client; biarkan klien membuat boundary.

Respons upload dapat memuat URL publik gambar, nama file unduhan, dan ukuran file. Path ZIP privat tidak boleh dikembalikan ke frontend publik.

---

## 7. Checkout, Pesanan, dan Pembayaran

### 7.1 Checkout

`POST /checkout`

Authorization: Bearer Token pelanggan.

Request:

```json
{
  "items": [
    { "product_id": 1 },
    { "product_id": 2 }
  ]
}
```

Aturan yang diterapkan:
- `items` wajib berupa array, minimal 1 dan maksimal 50 item.
- `product_id` wajib valid dan tidak boleh duplikat.
- Produk harus berstatus `published`.
- Harga dihitung dari database, bukan dari harga yang dikirim pelanggan.
- Implementasi checkout saat ini menetapkan kuantitas setiap produk menjadi `1`.
- Pesanan gratis langsung dibuat dengan `payment_status = paid` dan `status = completed`.
- Pesanan berbayar dibuat dengan `payment_status = pending` dan `status = pending`.

Checkout menyimpan snapshot nama, slug, harga, diskon, dan total pada `order_items`.

Respons sukses: `201 Created`.

### 7.2 Riwayat dan detail pesanan

`GET /orders` — hanya pesanan pengguna yang sedang login.

`GET /orders/{id}` — detail pesanan hanya dapat dibaca pemiliknya. Pengguna lain seharusnya tidak dapat mengetahui pesanan dengan mengganti ID.

### 7.3 Membuat transaksi Midtrans Snap

`POST /orders/{id}/payment`

Authorization: Bearer Token pemilik pesanan.

Body kosong. Endpoint mengambil total dari database dan membuat transaksi Snap. Respons sukses memuat:

- `order_number`
- `gross_amount`
- `token` Snap
- `redirect_url` Snap
- `client_key`

HTTP sukses yang telah diuji: `200`.

Jangan menandai pesanan sebagai paid berdasarkan redirect browser. Status pembayaran harus ditentukan oleh notifikasi Midtrans yang diverifikasi atau mekanisme verifikasi server-to-server yang tepercaya.

### 7.4 Webhook Midtrans

`POST /midtrans/notification`

Endpoint tidak memakai Bearer Token pelanggan karena dipanggil server Midtrans. Endpoint wajib memverifikasi notifikasi sebelum memperbarui pesanan.

Payload notifikasi yang pernah diterima memuat antara lain:

- `order_id`
- `status_code`
- `gross_amount`
- `signature_key`
- `transaction_status`
- `fraud_status`
- `transaction_id`

Alur status yang digunakan controller:
- `settlement`, atau `capture` dengan fraud status yang diterima: pembayaran ditandai `paid`; pesanan `pending` berubah menjadi `processing`.
- `pending`: pembayaran tetap pending.
- `expire`: pembayaran expired dan pesanan cancelled.
- `cancel`: pembayaran cancelled dan pesanan cancelled.
- `deny`: pembayaran failed.

**Peringatan implementasi:** selama pengembangan, signature notifikasi sempat tidak cocok dengan key yang dibaca Laravel. Sebuah mekanisme fallback verifikasi melalui Midtrans Status API kemudian ditambahkan dan payload uji manual berhasil memperbarui pesanan. Sebelum production, audit dan uji kembali handler tersebut menggunakan callback otomatis Midtrans. Jangan mengandalkan request buatan sendiri dari Postman sebagai bukti callback otomatis berfungsi.

### 7.5 Status pesanan admin

`PATCH /admin/orders/{id}/status`

Contoh request:

```json
{
  "status": "processing"
}
```

Status yang diterima controller: `pending`, `processing`, `completed`, `cancelled`.

Controller menolak perubahan ke `completed` jika `payment_status` belum `paid`. Pesanan yang sudah cancelled tidak dapat diaktifkan kembali melalui endpoint ini.

---

## 8. Unduhan Template

`GET /products/{id}/download`

Authorization: Bearer Token pelanggan.

Ketentuan akses pada controller:
- Produk harus `published`.
- Produk gratis dapat diunduh setelah pengguna login.
- Produk berbayar hanya dapat diunduh jika pengguna memiliki item pesanan dengan `payment_status = paid` dan status pesanan `processing` atau `completed`.
- File diambil dari disk privat `template_downloads`.
- Path file harus berupa nama file yang aman, bukan path direktori yang dikirim pengguna.

Contoh:

```http
GET /products/2/download
Authorization: Bearer <SANCTUM_TOKEN>
```

Jika berhasil, server mengirim respons file (contoh `application/zip`), bukan JSON biasa. Untuk menguji, gunakan browser atau PowerShell `Invoke-WebRequest`; Thunder Client versi gratis dapat membatasi penanganan respons biner.

---

## 9. Dashboard dan Profil Pelanggan

### 9.1 Dashboard pelanggan

`GET /customer/dashboard`

Authorization: Bearer Token pelanggan.

Ringkasan yang tersedia:
- `total_orders`
- `pending_orders`
- `paid_orders`
- `total_spent`
- `currency`
- `recent_orders` (maksimal lima pesanan terakhir)

Semua data dibatasi ke pengguna yang sedang login.

### 9.2 Profil pelanggan

`GET /customer/profile` — profil pengguna aktif.

`PUT /customer/profile`:

```json
{
  "name": "Nama Baru",
  "email": "email-baru@example.test"
}
```

`PUT /customer/profile/password`:

```json
{
  "current_password": "PasswordLama123!",
  "password": "PasswordBaru123!",
  "password_confirmation": "PasswordBaru123!"
}
```

Password lama harus cocok. Setelah pergantian password, seluruh token Sanctum pengguna dicabut sehingga pengguna perlu login kembali.

---

## 10. Wishlist

Semua endpoint menggunakan Bearer Token pelanggan.

### 10.1 Daftar wishlist

`GET /customer/wishlist`

Mengembalikan wishlist milik pengguna yang sedang login, termasuk informasi produk yang masih published.

### 10.2 Tambah wishlist

`POST /customer/wishlist`

```json
{
  "product_id": 2
}
```

- `201 Created`: berhasil menambahkan item baru.
- `200 OK`: item sudah ada, tidak membuat duplikasi.

### 10.3 Hapus wishlist

`DELETE /customer/wishlist/{productId}`

Hanya item milik pengguna aktif yang dapat dihapus. Item yang tidak ditemukan mengembalikan `404`.

---

## 11. Review dan Rating

### 11.1 Daftar review publik

`GET /products/{productId}/reviews`

Menampilkan ulasan produk published, nama reviewer, rating, komentar, dan pagination.

### 11.2 Membuat review

`POST /customer/reviews`

Authorization: Bearer Token pelanggan.

```json
{
  "product_id": 2,
  "rating": 5,
  "comment": "Template mudah digunakan dan tampilannya modern."
}
```

Aturan:
- Rating integer `1` sampai `5`.
- Komentar opsional, maksimum 2.000 karakter.
- Pelanggan harus memiliki pesanan produk tersebut dengan `payment_status = paid` serta status pesanan `processing` atau `completed`.
- Satu pelanggan hanya boleh memiliki satu review per produk.
- Review duplikat mengembalikan `409`; pelanggan tanpa hak review mendapat `403`.
- `average_rating` produk dihitung ulang setelah review dibuat, diperbarui, atau dihapus.

### 11.3 Memperbarui review

`PUT /customer/reviews/{id}`

```json
{
  "rating": 4,
  "comment": "Setelah digunakan, template cukup baik."
}
```

Hanya pemilik review yang dapat mengubahnya.

### 11.4 Menghapus review

`DELETE /customer/reviews/{id}`

Hanya pemilik review yang dapat menghapusnya.

---

## 12. Dashboard Administrator

Seluruh endpoint memakai Bearer Token admin.

### 12.1 Dashboard

`GET /admin/dashboard`

Data ringkasan:
- Jumlah pelanggan (`customers.total`).
- Jumlah kategori total dan aktif.
- Jumlah produk total, published, dan draft.
- Jumlah pesanan total, pending, paid, dan cancelled.
- Pendapatan dari pesanan berstatus paid.
- Lima pesanan terbaru beserta pengguna dan item pesanan.

### 12.2 Manajemen pengguna

| Method | Endpoint | Fungsi |
|---|---|---|
| GET | `/admin/users` | Daftar pengguna, filter/pencarian |
| GET | `/admin/users/{id}` | Detail pengguna |
| PUT | `/admin/users/{id}` | Ubah nama dan email |
| PATCH | `/admin/users/{id}/role` | Ubah role `customer` atau `admin` |

Filter daftar pengguna yang dibuat: `search`, `role`, `per_page` (maksimum 50).

Aturan role:
- Admin tidak dapat mengubah role akunnya sendiri melalui endpoint role.
- Sistem menolak penurunan role administrator terakhir.
- Tidak ada endpoint delete user pada route list saat dokumentasi ini dibuat.

### 12.3 Laporan penjualan

`GET /admin/reports/sales`

Parameter:

| Parameter | Format | Keterangan |
|---|---|---|
| `start_date` | `YYYY-MM-DD` | Awal periode |
| `end_date` | `YYYY-MM-DD` | Akhir periode |

Jika tidak diisi, laporan menggunakan 30 hari terakhir sampai hari ini.

Data yang dikembalikan:
- `period`
- `summary`: pesanan dibuat, pending, cancelled, paid, total revenue, currency
- `daily_revenue`: jumlah pembayaran dan pendapatan per tanggal pembayaran
- `top_products`: maksimal lima produk teratas berdasarkan pendapatan item
- `recent_paid_orders`: maksimal sepuluh pembayaran terbaru pada periode

Pendapatan dihitung berdasarkan `payment_status = paid` dan `paid_at` yang tidak null. Tanggal laporan menggunakan timezone aplikasi/server; pastikan `APP_TIMEZONE` dan timezone database sesuai kebutuhan bisnis.

---

## 13. Contoh Penggunaan dari Frontend

Contoh request dengan Fetch API untuk katalog:

```javascript
const response = await fetch(
  'http://127.0.0.1:8000/api/products?sort=latest&per_page=12',
  {
    headers: {
      Accept: 'application/json',
    },
  }
);

if (!response.ok) {
  throw new Error(`API error: ${response.status}`);
}

const result = await response.json();
console.log(result.data);
```

Contoh request dengan Bearer Token:

```javascript
const response = await fetch(
  'http://127.0.0.1:8000/api/customer/profile',
  {
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    },
  }
);

const result = await response.json();
```

Untuk request JSON berautentikasi yang memakai body, tambahkan `Content-Type: application/json` dan `JSON.stringify(body)`. Untuk upload file gunakan `FormData`; jangan menetapkan header `Content-Type` secara manual.

---

## 14. Variabel Environment Penting

Contoh nama variabel yang dipakai backend (isi nilainya secara lokal; jangan menyimpan secret ke repository):

```dotenv
APP_URL=http://127.0.0.1:8000
APP_TIMEZONE=Asia/Jakarta

MIDTRANS_IS_PRODUCTION=false
MIDTRANS_SERVER_KEY=
MIDTRANS_CLIENT_KEY=
MIDTRANS_NOTIFICATION_URL=
MIDTRANS_SIGNATURE_SECRET_KEY=
```

Keterangan:
- `MIDTRANS_SERVER_KEY` hanya di backend.
- `MIDTRANS_CLIENT_KEY` boleh dipakai frontend Snap sesuai konfigurasi merchant.
- `MIDTRANS_SIGNATURE_SECRET_KEY` hanya digunakan bila memang relevan dengan konfigurasi integrasi yang dipilih; jangan otomatis menggantikannya dengan Server Key.
- Jangan commit `.env` ke Git.
- URL ngrok gratis dapat berubah saat tunnel dimulai ulang; Payment Notification URL pada dashboard Sandbox harus diperbarui bila URL berubah.

---

## 15. Catatan Sebelum Production

1. **Audit webhook Midtrans.** Uji notifikasi otomatis dari Midtrans pada transaksi Sandbox baru. Uji manual dengan Thunder Client tidak membuktikan callback otomatis sudah berfungsi. Validasi signature dan/atau verifikasi Status API harus konsisten dengan dokumentasi Midtrans dan menggunakan kredensial merchant yang tepat.
2. **Audit status order.** Pastikan hanya proses pembayaran terverifikasi yang mengubah `payment_status` menjadi `paid`. Endpoint admin untuk status order tidak boleh dapat memalsukan status pembayaran.
3. **Audit checkout.** Pertimbangkan idempotency, penguncian produk, dan penanganan callback berulang. Saat ini kuantitas setiap item checkout ditetapkan `1`.
4. **Audit file upload.** Verifikasi MIME dan isi file, batas ukuran, kapasitas storage, pembersihan file lama, dan otorisasi admin. Jangan jadikan direktori ZIP privat sebagai symlink publik.
5. **Audit katalog.** Pastikan `file_path`, `file_name`, dan informasi privat lainnya tidak pernah dikembalikan oleh endpoint publik.
6. **Audit review.** Migration memiliki unique constraint user-product; tangani konflik database agar race condition tidak menghasilkan HTTP `500`.
7. **Audit upload preview.** Periksa pembersihan file lama ketika preview diganti atau dihapus.
8. **CORS dan frontend.** Atur origin frontend yang diizinkan secara spesifik; jangan menggunakan konfigurasi wildcard bersama kredensial.
9. **Rate limiting.** Tetapkan pembatasan untuk login, register, checkout, pembuatan transaksi, dan endpoint publik yang rawan abuse.
10. **Environment production.** Gunakan HTTPS, kredensial Production, domain callback tetap, `APP_DEBUG=false`, serta logging dan backup yang memadai.

---

## 16. Pemeriksaan Route

Perintah untuk menampilkan route:

```powershell
php artisan route:list --path=api
php artisan route:list --path=api -v
```

Daftar route yang menjadi dasar dokumentasi ini menampilkan 45 route API. Jika route berubah selama pengembangan, perbarui bagian ringkasan endpoint dan pengujian ini berdasarkan output `route:list` terbaru.
