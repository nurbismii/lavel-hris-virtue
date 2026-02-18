<?php

use Carbon\Carbon;

if (!function_exists('cutOffPeriode')) {
    function cutOffPeriode($tahun, $bulan)
    {
        $start = Carbon::create($tahun, $bulan, 16)->subMonth();
        $end   = Carbon::create($tahun, $bulan, 15);

        return [$start->toDateString(), $end->toDateString()];
    }
}

if (!function_exists('formatDateIndonesia')) {
    function formatDateIndonesia($date)
    {
        if (empty($date)) {
            return '-';
        }

        try {
            return Carbon::parse($date)
                ->locale('id')
                ->isoFormat('D MMM Y');
        } catch (\Exception $e) {
            return '-';
        }
    }
}

if (!function_exists('formatDate')) {
    function formatDate($date)
    {
        if (!$date) return null;

        try {
            return \Carbon\Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}

if (!function_exists('bulan_romawi')) {
    function bulan_romawi($bln)
    {
        $romawi = [
            1  => 'I',
            2  => 'II',
            3  => 'III',
            4  => 'IV',
            5  => 'V',
            6  => 'VI',
            7  => 'VII',
            8  => 'VIII',
            9  => 'IX',
            10 => 'X',
            11 => 'XI',
            12 => 'XII',
        ];

        $bln = (int) $bln;

        return $romawi[$bln] ?? null;
    }
}

if (!function_exists('no_urut_surat')) {
    function no_urut_surat($nomor)
    {
        return str_pad((int) $nomor, 4, '0', STR_PAD_LEFT);
    }
}
