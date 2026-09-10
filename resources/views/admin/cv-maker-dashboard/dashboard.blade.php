<section class="cv-dashboard mb-4" id="cvDashboard" aria-labelledby="cvDashboardTitle" aria-busy="true">
    <div class="cv-dashboard-heading">
        <div>
            <span class="cv-dashboard-eyebrow">{{ __('MONITORING PROFIL KARYAWAN') }}</span>
            <h5 id="cvDashboardTitle">{{ __('Dari kelengkapan CV ke tindak lanjut HR') }}</h5>
            <p>{{ __('Seluruh angka mengikuti filter dashboard. Hanya karyawan VDNI dan VDNIP.') }}</p>
        </div>
        <button type="button" class="btn btn-light border" id="cvDashboardRefresh" disabled><i class="fas fa-sync-alt me-1" aria-hidden="true"></i> {{ __('Muat ulang') }}</button>
    </div>
    <p id="cvDashboardStatus" class="small" role="status" aria-live="polite">{{ __('Memuat ringkasan...') }}</p>
    <div id="cvDashboardContent" hidden>
        <div class="cv-dashboard-cards" id="cvDashboardCards"></div>
        <p class="cv-dashboard-note" id="cvDashboardSync"></p>
        <div class="cv-dashboard-grid">
            <article class="cv-dashboard-panel">
                <h6>{{ __('Kelengkapan per departemen') }}</h6>
                <p>{{ __('10 unit dengan persentase CV lengkap terendah. Klik untuk memfilter departemen.') }}</p>
                <div id="cvDashboardDepartments"></div>
            </article>
            <article class="cv-dashboard-panel">
                <h6>{{ __('Status pemeriksaan') }}</h6>
                <p>{{ __('CV lengkap dan selesai diperiksa merupakan dua indikator terpisah.') }}</p>
                <div id="cvDashboardReviews"></div>
            </article>
            <article class="cv-dashboard-panel">
                <h6>{{ __('Tahap pengisian yang tertahan') }}</h6>
                <p>{{ __('Tahap pertama yang belum lengkap pada setiap profil. Klik untuk melihat daftar.') }}</p>
                <div id="cvDashboardSteps"></div>
            </article>
            <article class="cv-dashboard-panel">
                <h6>{{ __('Prioritas tindak lanjut') }}</h6>
                <p>{{ __('Maksimal 8 karyawan perlu reminder atau konfirmasi, diurutkan dari aktivitas terlama; tanggal kosong di awal.') }}</p>
                <div id="cvDashboardPriorities"></div>
            </article>
        </div>
        <p class="cv-dashboard-note mt-3">{{ __('Kelengkapan profil berbeda dengan hasil pemeriksaan HR. Angka berasal dari snapshot lokal, bukan pemeriksaan CV secara langsung.') }}</p>
    </div>
</section>
