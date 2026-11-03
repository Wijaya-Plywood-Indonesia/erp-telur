<?php

return [
    /*
    | Satu blok per pabrik. Aplikasi mengirim X-Api-Key; server mencocokkannya ke blok di bawah,
    | lalu memakai user pos dan 3 barang milik pabrik itu.
    | Jenis yang barangnya dikosongkan di .env tidak tampil di aplikasi (mis. bentes Wijaya).
    | Cari ID barang: php artisan tinker
    |   App\Models\Barang::where('nama_barang', 'like', '%telur%')->get(['id','nama_barang','harga_jual']);
    */
    'pabrik' => [
        'wijaya' => [
            'api_key'       => env('SATPAM_WIJAYA_API_KEY'),
            'user_id'       => env('SATPAM_WIJAYA_USER_ID'),
            'nama_customer' => env('SATPAM_WIJAYA_NAMA_CUSTOMER', 'Wijaya'),
            'barang'        => [
                'petian' => env('SATPAM_WIJAYA_BARANG_PETIAN'),
                'kiloan' => env('SATPAM_WIJAYA_BARANG_KILOAN'),
                'bentes' => env('SATPAM_WIJAYA_BARANG_BENTES'),
            ],
        ],
        'wahana' => [
            'api_key'       => env('SATPAM_WAHANA_API_KEY'),
            'user_id'       => env('SATPAM_WAHANA_USER_ID'),
            'nama_customer' => env('SATPAM_WAHANA_NAMA_CUSTOMER', 'Wahana'),
            'barang'        => [
                'petian' => env('SATPAM_WAHANA_BARANG_PETIAN'),
                'kiloan' => env('SATPAM_WAHANA_BARANG_KILOAN'),
                'bentes' => env('SATPAM_WAHANA_BARANG_BENTES'),
            ],
        ],
    ],

    // Label tombol di aplikasi. "ceprot" bukan kartu sendiri: ia muncul sebagai pilihan
    // di dalam layar input Bentes (hanya pabrik yang punya barang bentes).
    'label' => [
        'petian' => 'Telur Petian',
        'kiloan' => 'Telur Kiloan',
        'bentes' => 'Telur Bentes',
        'ceprot' => 'Ceprot',
    ],

    /*
    | Ceprot = telur pecah dalam kantong plastik, stoknya mengikuti Telur Bentes.
    |  - harga_plastik  : harga jual per plastik (tetap)
    |  - kg_per_plastik : berapa kg stok bentes yang berkurang per plastik
    |  - harga_kg       : harga per kg ceprot; selisih dari harga bentes dicatat sebagai potongan
    */
    'ceprot' => [
        'aktif'          => env('SATPAM_CEPROT', true),
        'harga_plastik'  => (float) env('SATPAM_CEPROT_HARGA', 6000),
        'kg_per_plastik' => (float) env('SATPAM_CEPROT_KG', 0.5),
        'harga_kg'       => (float) env('SATPAM_CEPROT_HARGA_KG', 12000),
    ],

    // Jam tombol setoran dibuka otomatis tiap hari (WIB). Kosongkan untuk mematikan.
    'buka_otomatis' => env('SATPAM_BUKA_OTOMATIS', '07:30'),

    // Tolak transaksi kalau stok tersedia tidak cukup (sama seperti POS Filament).
    'cek_stok' => env('SATPAM_CEK_STOK', true),

    // true = nota dari aplikasi tanpa token satpam ditolak. Aktifkan setelah semua HP
    // sudah memakai versi baru dan diaktivasi. Selama false, aplikasi lama tetap jalan.
    'wajib_token' => env('SATPAM_WAJIB_TOKEN', false),

    // Dipakai kalau pabrik tidak punya 'nama_customer' sendiri
    'nama_customer_default' => 'Pelanggan Umum',
];