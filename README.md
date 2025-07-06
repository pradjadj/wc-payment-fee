# WC Payment Fee

Plugin WooCommerce untuk menambahkan biaya tambahan pembayaran yang dihitung dari total harga barang, ongkos kirim, dan biaya lain (seperti platform fee dari plugin lain).

## Fitur

- Menambahkan biaya tambahan pembayaran per metode pembayaran.
- Biaya dapat diatur sebagai persentase (%) atau nominal tetap.
- Pengaturan enable/disable biaya per metode pembayaran.
- Label biaya dapat disesuaikan.
- Biaya otomatis dihitung ulang dan diperbarui secara dinamis saat metode pembayaran diubah di halaman checkout.
- Pengaturan plugin tersedia di menu WooCommerce > Payment Fee.
- Link pengaturan tersedia di sebelah tombol Deactivate pada halaman plugin.
- File uninstall untuk menghapus semua pengaturan saat plugin dihapus.
- Pada tipe persentase (%) biasa dipakai untuk MDR Payment Gateway agar merchant dapat utuh, biaya tambahan dihitung bertingkat, contoh jika Subtotal = 100.000 + fee 0.7% (700) = 100.700 (nominal ini menjadi dasar perhitungan fee persentase) + fee 0.7% (lagi untuk perhitungan Fee yang akan tampil, sehingga fee yang tampil sebesar 705 pembulatan keatas dari 704.9 dari perhitungan 100.700) = Grand Total menjadi 100.705.

## Cara Instalasi

1. Salin folder `wc-payment-fee` ke direktori plugin WordPress Anda (`wp-content/plugins/`) atau upload file zip pada halaman Plugin.
2. Aktifkan plugin melalui menu Plugins di WordPress.
3. Atur biaya pembayaran melalui menu WooCommerce > Payment Fee.

## Cara Penggunaan

- Pada halaman pengaturan, aktifkan biaya untuk metode pembayaran yang diinginkan.
- Pilih tipe biaya: persentase atau nominal tetap.
- Masukkan jumlah biaya.
- Masukkan label biaya yang ingin ditampilkan di halaman checkout.
- Saat pelanggan memilih metode pembayaran di halaman checkout, biaya akan otomatis dihitung dan ditampilkan.

## Penghapusan

Saat plugin dihapus, semua pengaturan akan dihapus secara otomatis.

## Author

Pradja DJ  
[https://sgnet.co.id](https://sgnet.co.id)
