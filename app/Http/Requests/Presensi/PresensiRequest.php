<?php

namespace App\Http\Requests\Presensi;

use Illuminate\Foundation\Http\FormRequest;

class PresensiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && filled($this->user()->nik_karyawan);
    }

    public function rules(): array
    {
        return [
            'lat_user' => ['required', 'numeric', 'between:-90,90'],
            'long_user' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0', 'max:200'],
            'speed' => ['nullable', 'numeric', 'min:0', 'max:80'],
            'device_info' => ['required', 'string', 'max:2500'],
            'selfie_capture' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'selfie_capture_data' => ['nullable', 'string', 'max:7000000', 'required_without:selfie_capture'],
            'face_verified' => ['required', 'boolean'],
            'face_distance' => ['required', 'numeric', 'min:0', 'max:2'],
            'face_detection_count' => ['required', 'integer', 'min:1', 'max:5'],
            'face_verification_meta' => ['required', 'json', 'max:5000'],
            'attendance_challenge_token' => ['required', 'string', 'size:64'],
            'attendance_challenge_id' => ['required', 'uuid'],
            'presensi_challenge_id' => ['required', 'uuid'],
            'presensi_challenge_action' => ['required', 'string', 'in:turn_left_right'],
            'face_liveness_passed' => ['required', 'boolean'],
            'face_liveness_type' => ['required', 'string', 'in:turn_left_right'],
            'face_liveness_score' => ['required', 'numeric', 'min:0', 'max:2'],
            'face_liveness_message' => ['nullable', 'string', 'max:250'],
            'face_liveness_evidence' => ['required', 'json', 'max:1200000'],
            'screen_spoof_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'screen_spoof_reason' => ['nullable', 'json', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'lat_user.required' => 'Lokasi GPS belum terbaca. Aktifkan lokasi lalu coba lagi.',
            'long_user.required' => 'Lokasi GPS belum terbaca. Aktifkan lokasi lalu coba lagi.',
            'accuracy.required' => 'Akurasi GPS belum terbaca. Tunggu sampai indikator GPS valid.',
            'accuracy.max' => 'Akurasi GPS belum cukup stabil. Tunggu sampai indikator GPS valid.',
            'device_info.required' => 'Informasi perangkat tidak terbaca. Muat ulang halaman lalu coba lagi.',
            'selfie_capture.image' => 'File selfie harus berupa gambar.',
            'selfie_capture.max' => 'Ukuran selfie terlalu besar. Ambil ulang selfie lalu coba lagi.',
            'selfie_capture_data.required_without' => 'Selfie wajib diambil dari kamera sebelum presensi.',
            'selfie_capture_data.max' => 'Ukuran selfie terlalu besar. Ambil ulang selfie lalu coba lagi.',
            'face_verified.required' => 'Verifikasi wajah belum selesai.',
            'face_verified.boolean' => 'Status verifikasi wajah tidak valid.',
            'face_distance.required' => 'Jarak kecocokan wajah belum terbaca. Ambil ulang selfie.',
            'face_detection_count.required' => 'Jumlah wajah pada selfie belum terbaca. Ambil ulang selfie.',
            'face_verification_meta.required' => 'Metadata verifikasi wajah tidak lengkap. Ambil ulang selfie.',
            'face_verification_meta.json' => 'Metadata verifikasi wajah tidak valid. Ambil ulang selfie.',
            'attendance_challenge_token.required' => 'Sesi keamanan presensi belum siap. Muat ulang halaman lalu coba lagi.',
            'attendance_challenge_token.size' => 'Sesi keamanan presensi tidak valid. Muat ulang halaman lalu coba lagi.',
            'attendance_challenge_id.required' => 'Sesi keamanan presensi belum siap. Muat ulang halaman lalu coba lagi.',
            'attendance_challenge_id.uuid' => 'Sesi keamanan presensi tidak valid. Muat ulang halaman lalu coba lagi.',
            'face_liveness_passed.required' => 'Liveness belum selesai. Ikuti instruksi gerak wajah pada kamera.',
            'face_liveness_passed.boolean' => 'Status liveness tidak valid.',
            'face_liveness_evidence.required' => 'Bukti liveness belum lengkap. Ulangi verifikasi kamera.',
            'face_liveness_evidence.json' => 'Bukti liveness tidak valid. Ulangi verifikasi kamera.',
            'screen_spoof_score.required' => 'Validasi anti-foto belum selesai. Ulangi verifikasi kamera.',
        ];
    }
}
