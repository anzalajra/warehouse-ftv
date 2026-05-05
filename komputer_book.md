# Perencanaan Fitur Booking Komputer (Computer Lab/Studio Booking)

## 1. Ringkasan & Tujuan
Fitur ini bertujuan untuk mengelola dan memfasilitasi pemesanan (booking) komputer (misal: PC Editing, Workstation) yang ada di Warehouse FTV. 
Booking komputer akan **terpisah sepenuhnya** dari data rental/peminjaman peralatan (equipment), namun tetap menggunakan sistem akun (user/customer) dan Admin Panel (Filament) yang sama.

## 2. Struktur Database (Usulan Skema)
Karena data dipisah dari sistem rental utama, kita membutuhkan tabel-tabel baru khusus komputer:

*   **`computers`**
    *   `id`, `name` (misal: PC Editing 1)
    *   `brand` (misal: Apple, Asus)
    *   `specs` (text/json, misal: CPU, RAM, GPU)
    *   `status` (enum: `available`, `maintenance`, `retired`)
    *   `image_path` (foto komputer)
    *   `notes` (catatan internal)
    *   `timestamps`, `softDeletes`

*   **`computer_booking_slots` (Pengaturan Konfigurasi Slot)**
    *   *Opsional jika slot dibuat dinamis. Namun karena diminta admin bisa mengatur jam dan durasi:*
    *   `id`, `day_of_week` (Senin-Minggu, atau tanggal spesifik)
    *   `start_time` (misal: 08:00)
    *   `end_time` (misal: 10:00)
    *   `is_active` (boolean)

*   **`computer_bookings`**
    *   `id`, `user_id` (relasi ke user/customer yang sama dengan website)
    *   `computer_id` (relasi ke tabel computers)
    *   `booking_date` (tanggal booking)
    *   `start_time`, `end_time` 
    *   `purpose` (text, alasan / kegunaan peminjaman)
    *   `status` (enum: `pending`, `confirmed`, `active`, `completed`, `cancelled`)
    *   `admin_notes` (catatan dari admin ketika mengubah jadwal)
    *   `timestamps`, `softDeletes`

## 3. Fitur Admin Panel (Filament)
Akan dibuat dalam satu Cluster baru, misalnya **`Computer Booking Cluster`**, agar rapi dan tidak bercampur dengan Rental Equipment.

1.  **Computer Resource (Manajemen Komputer)**
    *   CRUD data komputer (Nama, Spesifikasi, Brand, Foto).
    *   Toggle untuk mengubah status menjadi `Maintenance` (otomatis memblokir booking di tanggal saat maintenance berlangsung) atau `Available`.
2.  **Computer Booking Resource (Manajemen Pemesanan)**
    *   Melihat daftar seluruh booking yang masuk.
    *   Aksi untuk **Membatalkan (Cancel)** atau **Menggeser (Reschedule)** jadwal booking.
    *   Saat digeser, sistem akan mengecek ketersediaan (clash detection) terlebih dahulu.
3.  **Booking Schedule / Timeline**
    *   Halaman berupa Kalender (FullCalendar) atau Timeline View.
    *   Bisa difilter per komputer (Melihat hari ini siapa saja yang pakai PC 1, PC 2, dll).
    *   Tampilan visual jika ada jadwal yang kosong dan terisi.
4.  **Slot Management**
    *   Halaman pengaturan (Settings/Resource) untuk menentukan jam operasional / slot booking per hari dan durasi maksimal.

## 4. Fitur Storefront (Halaman Publik/Customer)
Akan ada route baru, misal `/computers`.

1.  **Halaman Daftar Komputer**
    *   Menampilkan grid komputer yang tersedia beserta spesifikasinya.
    *   Menampilkan badge status (Tersedia / Sedang Maintenance).
2.  **Alur Booking (Booking Flow)**
    *   **Langkah 1:** User login menggunakan akun SSO/Storefront existing.
    *   **Langkah 2:** Pilih Komputer.
    *   **Langkah 3:** Pilih Tanggal. Sistem akan menampilkan slot waktu yang tersedia berdasarkan jadwal admin dan slot yang belum dibooking orang lain.
    *   **Langkah 4:** Input Kegunaan (`purpose`). Contoh: "Editing video tugas akhir MK Dokumenter".
    *   **Langkah 5:** Submit. Menjadi status `confirmed` atau `pending` (tergantung kebutuhan approve admin).
3.  **Halaman "My Computer Bookings"**
    *   Di dashboard customer, ada tab baru untuk melihat jadwal komputer yang sudah mereka book.

## 5. Improvisasi & Fitur Tambahan (Agar Lebih Robust)
Untuk membuat sistem ini lebih mantap (Enterprise-level logic), berikut usulan pengembangannya:

*   **Collision Detection Service:** Mirip seperti `RentalValidationService`, dibuat `ComputerValidationService` untuk memastikan 1 komputer di 1 rentang waktu yang sama tidak mungkin di-book dua kali.
*   **Quota & Fair Usage Policy (FUP):** 
    *   Membatasi 1 mahasiswa/user maksimal hanya boleh booking komputer X jam per minggu, atau maksimal 1 slot sehari. Ini mencegah monopoli komputer oleh 1 orang.
*   **Auto-Check-in / No-Show Policy:**
    *   Sistem mengharuskan admin melakukan *Check-in* saat mahasiswa datang, atau mahasiswa scan QR code di meja komputer.
    *   Jika dalam 15-30 menit dari jam mulai mahasiswa tidak datang (No-Show), status booking di-cancel otomatis oleh sistem agar slot bisa diambil orang lain (`schedule command / job`).
*   **Email & WhatsApp Notification:** 
    *   Notifikasi pengingat 1 jam sebelum jadwal booking dimulai.
    *   Pemberitahuan kepada user jika admin menggeser atau membatalkan secara sepihak jadwal mereka.
*   **Laporan Perawatan (Maintenance Logs):**
    *   Setiap kali komputer masuk mode *Maintenance*, admin wajib mengisi alasannya (misal: "Install ulang Adobe", "Ganti RAM"). Data ini berguna untuk melihat history kesehatan tiap komputer.
*   **Terms and Conditions (Syarat & Ketentuan):**
    *   Checkbox persetujuan "Dilarang makan & minum di meja", "Wajib backup mandiri", dll saat proses submit booking.

---
*Dokumen ini merupakan kerangka dasar (blueprint) untuk pengembangan fitur. Jika sudah sesuai, implementasi dapat dimulai dari pembuatan Migration & Models.*