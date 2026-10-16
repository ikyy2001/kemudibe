<?php

return [
    /*
    |--------------------------------------------------------------------------
    | NaraRouter AI Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for connecting to the NaraRouter AI service,
    | an OpenAI-compatible gateway.
    |
    */
    'base_url' => env('NARAROUTER_BASE_URL', 'https://router.bynara.id/v1'),
    'api_key' => env('NARAROUTER_API_KEY'),
    'model' => env('AI_MODEL', 'openai/gpt-4o-mini'),
    'max_tokens' => (int) env('AI_MAX_TOKENS', 300),
    'temperature' => (float) env('AI_TEMPERATURE', 0.3),
    'timeout' => (int) env('AI_TIMEOUT', 30),
    'max_input_chars' => (int) env('AI_MAX_INPUT_CHARS', 300),
    'history_limit' => (int) env('AI_HISTORY_LIMIT', 3),
    'cache_ttl' => (int) env('AI_CACHE_TTL', 86400),
    'contact_whatsapp_url' => env('AI_CONTACT_WHATSAPP_URL', 'https://wa.me/6287897600086'),

    /*
    |--------------------------------------------------------------------------
    | Quota & Rate Limits per Tier
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'guest'   => ['per_minute' => 5,  'per_day' => 20],
        'student' => ['per_minute' => 3,  'per_day' => 30],
        'teacher' => ['per_minute' => 10, 'per_day' => 100],
        'admin'   => ['per_minute' => 20, 'per_day' => null], // null = tanpa batas
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug & Usage Options
    |--------------------------------------------------------------------------
    */
    'show_usage' => (bool) env('AI_SHOW_USAGE', true),

    /*
    |--------------------------------------------------------------------------
    | Kemudi Facts for AI Prompts (Editable)
    |--------------------------------------------------------------------------
    */
    'facts' => [
        'product_name' => 'Kemudi LMS & CBT',
        'company' => 'PT CIPTA CERITA BERSAMA GROUP (Nusa Garuda Studio)',
        'description' => 'Platform Ujian Online (CBT) dan Learning Management System (LMS) modern, anti-curang, dan terjangkau untuk sekolah (SD/SMP/SMA/SMK) dan bimbingan belajar.',
        'whatsapp' => '+62 878-9760-0086',
        'whatsapp_url' => 'https://wa.me/6287897600086',
        'email' => 'nusagarudastudio@gmail.com',
        'office' => 'Jl. Kelurahan No.15, Pd. Rajeg, Kec. Cibinong, Kabupaten Bogor, Jawa Barat 16914',
        'demo_info' => 'Demo gratis 100% tanpa dipungut biaya. Tim KEMUDI siap presentasi online via Google Meet/Zoom dan memberikan pendampingan teknis.',
        'features' => [
            'CBT Anti-Curang: Kunci jendela ujian/anti tab-switch, full screen proctoring, pengacakan soal & opsi, token pengawas dinamis (Supervisor PIN), alarm audio deteksi kecurangan, dan pembekuan layar (freeze) oleh pengawas.',
            'Tipe Soal Lengkap: Pilihan Ganda (PG), Esai mandiri, dan Campuran (PG + Esai). Dilengkapi rumus matematika KaTeX dan simbol sains.',
            'Import Soal Excel Cepat: Upload ratusan butir soal sekaligus via file template Microsoft Excel (.xlsx) siap pakai.',
            'Buku Nilai & Analisis: Koreksi otomatis PG, penilaian esai cepat, kalkulasi nilai akhir pembobotan, dan ekspor 1-klik ke Microsoft Excel (XLSX) untuk e-Rapor.',
            'E-Learning & Tugas: Distribusi modul/materi (PDF, video, dokumen) serta manajemen tugas berbatas waktu transparan.',
            'Presensi & Data Siswa: Absensi digital harian/per mapel dan manajemen rombel/jurusan terpadu.',
            'Multi-Tenant Terisolasi: Database dan enkripsi akun tiap sekolah aman terpisah.',
        ],
        'packages' => [
            'Paket Reguler (Fleksibel Per Siswa)' => [
                'price' => 'Rp 4.000 / siswa / bulan (Normal)',
                'promo' => 'Promo 50 siswa: Rp 175.000 / bulan (Hemat dari normal Rp 200.000)',
                'semester_annual' => 'Diskon bayar semester/tahunan: Rp 3.000 / siswa / bulan',
                'specs' => 'Shared High-Speed Cloud Server, subdomain gratis selamanya (namasekolah.kemudi.id)',
                'target' => 'Bimbel rintisan atau sekolah dengan anggaran bertahap',
            ],
            'Paket Elite (Dedicated VPS)' => [
                'price' => 'Rp 175.000 / bulan atau Rp 1.750.000 / tahun (Hemat 2 bulan)',
                'specs' => 'Dedicated VPS Privat (2 vCPU, 4GB RAM, 50GB SSD)',
                'capacity' => '100 – 150 siswa ujian serentak/sesi (maks. 200/sesi), 300 – 500 siswa LMS reguler',
                'domain' => 'Gratis domain kustom (.my.id atau .biz.id) tahun pertama',
                'maintenance' => 'Garansi perbaikan sistem gratis hingga 6 bulan',
            ],
            'Paket Platinum (Dedicated VPS - Terpopuler & Best Value)' => [
                'price' => 'Rp 390.000 / bulan atau Rp 3.900.000 / tahun (Hemat 2 bulan)',
                'specs' => 'Dedicated VPS Privat (8 vCPU, 16GB RAM, 200GB SSD High Speed)',
                'capacity' => '600 – 900 siswa ujian serentak/sesi (maks. 1.200/sesi), 2.500 – 4.000 siswa LMS (maks. 6.000+)',
                'bonus' => 'Free redesign tampilan brand (logo & warna lembaga), antrean bantuan teknis diprioritaskan',
                'maintenance' => 'Garansi pemeliharaan sistem 12 bulan penuh',
            ],
            'Paket Signature (Dedicated VPS - Enterprise Data Center Jakarta)' => [
                'price' => 'Rp 590.000 / bulan atau Rp 5.900.000 / tahun (Hemat 2 bulan)',
                'specs' => 'Data Center Jakarta (IIX Low Latency < 15ms), 8 vCPU, 16GB RAM, 200GB SSD',
                'capacity' => '400 – 500 siswa ujian serentak/sesi (maks. 750/sesi), 3.000 – 4.500 siswa LMS (maks. 7.000+)',
                'service' => 'Didampingi & dimonitoring langsung secara real-time oleh tim lead engineer saat ujian massal',
                'maintenance' => 'Garansi pemeliharaan sistem terpanjang hingga 24 bulan',
            ],
        ],
        'addons' => [
            'Upgrade Domain .com / .id' => '+Rp 220.000 / tahun',
            'Upgrade Domain .academy' => '+Rp 519.000 / tahun',
            'Big Redesign Tampilan Skala Besar' => 'Konsultasi Sales',
        ],
    ],
];

