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
    'attendance_correction' => [
        'label' => 'Pengajuan Presensi',
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
    'leave_balance' => [
        'label' => 'Saldo Cuti',
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
    'audit_trail' => [
        'label' => 'Audit Trail',
        'group' => 'Admin Panel',
    ],
    'import_history' => [
        'label' => 'History Import',
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
            'scope_label' => 'Beberapa departemen/divisi',
            'description' => 'Mengakses self service dan mereview data karyawan pada satu atau beberapa departemen/divisi yang ditugaskan.',
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
            'data_karyawan',
            'data_user',
            'resign',
            'surat_peringatan',
            'data_presensi',
            'distribusi_wilayah',
            'approval_hod',
            'approval_hr',
            'setting_hari_off',
            'master_tanggal_merah',
            'jadwal_kerja',
            'master_shift',
            'pengaturan_shift',
            'lembur',
            'perusahaan',
            'leave_balance',
            'audit_trail',
            'import_history',
        ],
        'HOD' => [
            'dashboard_karyawan',
            'data_karyawan',
            'slip_gaji_user',
            'cuti',
            'izin',
            'presensi',
            'attendance_correction',
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
            'izin',
            'presensi',
            'attendance_correction',
            'lembur',
        ],
        'Supervisor' => [
            'dashboard_karyawan',
            'data_karyawan',
            'slip_gaji_user',
            'cuti',
            'izin',
            'presensi',
            'attendance_correction',
            'lembur',
        ],
        'Staff' => [
            'dashboard_karyawan',
            'slip_gaji_user',
            'cuti',
            'izin',
            'presensi',
            'attendance_correction',
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
            'attendance_correction',
            'lembur',
        ],
        'Admin Divisi' => [
            'dashboard_karyawan',
            'data_karyawan',
            'slip_gaji_user',
            'cuti',
            'izin',
            'presensi',
            'attendance_correction',
            'setting_hari_off',
            'jadwal_kerja',
            'master_shift',
            'pengaturan_shift',
            'lembur',
        ],
    ],
];
