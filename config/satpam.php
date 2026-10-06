<?php

return [
    /*
    | Satu blok per pabrik. Aplikasi mengirim X-Api-Key; server mencocokkannya ke blok di bawah,
    | lalu memakai user pos dan 3 barang milik pabrik itu.
    | Cari ID barang: php artisan tinker
    |   App\Models\Barang::where('nama_barang', 'like', '%telur%')->get(['id','nama_barang','harga_jual']);
    */
    'pabrik' => [
        'wijaya' => [
            'api_key' => env('SATPAM_WIJAYA_API_KEY'),
            'user_id' => env('SATPAM_WIJAYA_USER_ID'),
            'barang'  => [
                'petian' => env('SATPAM_WIJAYA_BARANG_PETIAN'),
                'kiloan' => env('SATPAM_WIJAYA_BARANG_KILOAN'),
                'bentes' => env('SATPAM_WIJAYA_BARANG_BENTES'),
            ],
        ],
        'wahana' => [
            'api_key' => env('SATPAM_WAHANA_API_KEY'),
            'user_id' => env('SATPAM_WAHANA_USER_ID'),
            'barang'  => [
                'petian' => env('SATPAM_WAHANA_BARANG_PETIAN'),
                'kiloan' => env('SATPAM_WAHANA_BARANG_KILOAN'),
                'bentes' => env('SATPAM_WAHANA_BARANG_BENTES'),
            ],
        ],
    ],

    // Label tombol di aplikasi
    'label' => [
        'petian' => 'Telur Petian',
        'kiloan' => 'Telur Kiloan',
        'bentes' => 'Telur Bentes',
    ],

    // Tolak transaksi kalau stok buku besar tidak cukup (sama seperti POS Filament).
    'cek_stok' => env('SATPAM_CEK_STOK', true),

    'nama_customer_default' => 'Pelanggan Umum',
];