<?php

$menus = [
    'dashboard_admin' => [
        'label' => 'Dashboard Admin',
        'group' => 'Dashboard',
    ],
    'dashboard_karyawan' => [
        'label' => 'Dashboard Karyawan',
        'group' => 'Dashboard',
    ],
    'data_karyawan' => [
        'label' => 'Data Karyawan',
        'group' => 'Data Master',
    ],
    'data_user' => [
        'label' => 'Data User',
        'group' => 'Data Master',
    ],
    'slip_gaji_admin' => [
        'label' => 'Slip Gaji Admin',
        'group' => 'Data Master',
    ],
    'resign' => [
        'label' => 'Resign',
        'group' => 'Data Master',
    ],
    'surat_peringatan' => [
        'label' => 'Pelanggaran',
        'group' => 'Data Master',
    ],
    'data_presensi' => [
        'label' => 'Data Presensi',
        'group' => 'Data Master',
    ],
    'distribusi_wilayah' => [
        'label' => 'Distribusi Wilayah',
        'group' => 'Data Master',
    ],
    'slip_gaji_user' => [
        'label' => 'Slip Gaji Karyawan',
        'group' => 'Self Service',
    ],
    'cuti' => [
        'label' => 'Cuti Tahunan',
        'group' => 'Self Service',
    ],
    'roster' => [
        'label' => 'Cuti Roster',
        'group' => 'Self Service',
    ],
    'off_roster' => [
        'label' => 'Pengajuan OFF Roster',
        'group' => 'Self Service',
    ],
    'izin' => [
        'label' => 'Izin (Paid & Unpaid)',
        'group' => 'Self Service',
    ],
    'presensi' => [
        'label' => 'Presensi Karyawan',
        'group' => 'Self Service',
    ],
    'approval_hod' => [
        'label' => 'Approval HOD',
        'group' => 'Approval',
    ],
    'approval_hr' => [
        'label' => 'Approval HR',
        'group' => 'Approval',
    ],
    'setting_hari_off' => [
        'label' => 'Setting Hari Off',
        'group' => 'Operasional',
    ],
    'master_tanggal_merah' => [
        'label' => 'Master Tanggal Merah',
        'group' => 'Operasional',
    ],
    'jadwal_kerja' => [
        'label' => 'Master Jadwal Kerja',
        'group' => 'Operasional',
    ],
    'master_shift' => [
        'label' => 'Master Shift',
        'group' => 'Operasional',
    ],
    'pengaturan_shift' => [
        'label' => 'Pengaturan Shift',
        'group' => 'Operasional',
    ],
    'lembur' => [
        'label' => 'Perintah Lembur',
        'group' => 'Self Service',
    ],
    'perusahaan' => [
        'label' => 'Perusahaan & Organisasi',
        'group' => 'Operasional',
    ],
    'setting_lokasi_presensi' => [
        'label' => 'Lokasi Presensi',
        'group' => 'Admin Panel',
    ],
    'setting_role' => [
        'label' => 'Peran dan Akses',
        'group' => 'Admin Panel',
    ],
    'exit_portal' => [
        'label' => 'Exit Portal',
        'group' => 'Admin Panel',
    ],
];

return [
    'roles' => [
        'Super Admin' => [
            'aliases' => ['Administrator'],
            'scope_label' => 'Semua data',
            'description' => 'Akses penuh ke seluruh menu, data, dan pengaturan aplikasi.',
        ],
        'HR' => [
            'aliases' => [],
            'scope_label' => 'Semua data karyawan',
            'description' => 'Mengelola data kepegawaian, approval HR, dan operasional HR.',
        ],
        'HOD' => [
            'aliases' => [],
            'scope_label' => 'Departemen yang sama',
            'description' => 'Mengakses dan mereview data karyawan dalam departemen yang sama.',
        ],
        'Manager' => [
            'aliases' => [],
            'scope_label' => 'Departemen yang sama',
            'description' => 'Mengakses data karyawan sesuai departemen pemilik akun.',
        ],
        'Supervisor' => [
            'aliases' => [],
            'scope_label' => 'Divisi yang sama',
            'description' => 'Mengakses data karyawan sesuai divisi pemilik akun.',
        ],
        'Staff' => [
            'aliases' => ['User'],
            'scope_label' => 'Akun sendiri',
            'description' => 'Akses self service untuk kebutuhan karyawan.',
        ],
        'Staff Roster' => [
            'aliases' => ['User Roster'],
            'scope_label' => 'Akun sendiri',
            'description' => 'Akses self service untuk kebutuhan karyawan.',
        ],
        'Admin Divisi' => [
            'aliases' => [],
            'scope_label' => 'Divisi yang ditugaskan',
            'description' => 'Mengelola kebutuhan operasional sesuai satu atau beberapa divisi yang ditugaskan.',
        ],
    ],
    'menus' => $menus,
    'default_menu_permissions' => [
        'Super Admin' => array_keys($menus),
        'HR' => [
            'dashboard_admin',
            'dashboard_karyawan',
            'data_karyawan',
            'data_user',
            'resign',
            'surat_peringatan',
            'data_presensi',
            'distribusi_wilayah',
            'slip_gaji_user',
            'approval_hod',
            'approval_hr',
            'setting_hari_off',
            'master_tanggal_merah',
            'jadwal_kerja',
            'master_shift',
            'pengaturan_shift',
            'lembur',
            'perusahaan',
            'cuti',
            'roster',
            'izin',
            'presensi',
        ],
        'HOD' => [
            'dashboard_karyawan',
            'data_karyawan',
            'slip_gaji_user',
            'cuti',
            'roster',
            'izin',
            'presensi',
            'approval_hod',
            'setting_hari_off',
            'jadwal_kerja',
            'master_shift',
            'pengaturan_shift',
            'lembur',
        ],
        'Manager' => [
            'dashboard_karyawan',
            'data_karyawan',
            'slip_gaji_user',
            'cuti',
            'roster',
            'izin',
            'presensi',
            'lembur',
        ],
        'Supervisor' => [
            'dashboard_karyawan',
            'data_karyawan',
            'slip_gaji_user',
            'cuti',
            'roster',
            'izin',
            'presensi',
            'lembur',
        ],
        'Staff' => [
            'dashboard_karyawan',
            'slip_gaji_user',
            'cuti',
            'izin',
            'presensi',
            'lembur',
        ],
        'Staff Roster' => [
            'dashboard_karyawan',
            'slip_gaji_user',
            'cuti',
            'roster',
            'off_roster',
            'izin',
            'presensi',
            'lembur',
        ],
        'Admin Divisi' => [
            'dashboard_karyawan',
            'data_karyawan',
            'slip_gaji_user',
            'cuti',
            'roster',
            'izin',
            'presensi',
            'setting_hari_off',
            'jadwal_kerja',
            'master_shift',
            'pengaturan_shift',
            'lembur',
        ],
    ],
];
