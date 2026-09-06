<section class="cv-dashboard mb-4" id="cvDashboard" aria-labelledby="cvDashboardTitle" aria-busy="true">
    <div class="cv-dashboard-heading">
        <div>
            <span class="cv-dashboard-eyebrow">MONITORING PROFIL KARYAWAN</span>
            <h5 id="cvDashboardTitle">Dari kelengkapan CV ke tindak lanjut HR</h5>
            <p>Seluruh angka mengikuti filter dashboard. Hanya karyawan VDNI dan VDNIP.</p>
        </div>
        <button type="button" class="btn btn-light border" id="cvDashboardRefresh" disabled><i class="fas fa-sync-alt me-1" aria-hidden="true"></i> Muat ulang</button>
    </div>
    <p id="cvDashboardStatus" class="small" role="status" aria-live="polite">Memuat ringkasan...</p>
    <div id="cvDashboardContent" hidden>
        <div class="cv-dashboard-cards" id="cvDashboardCards"></div>
        <p class="cv-dashboard-note" id="cvDashboardSync"></p>
        <div class="cv-dashboard-grid">
            <article class="cv-dashboard-panel">
                <h6>Kelengkapan per departemen</h6>
                <p>10 unit dengan persentase CV lengkap terendah. Klik untuk memfilter departemen.</p>
                <div id="cvDashboardDepartments"></div>
            </article>
            <article class="cv-dashboard-panel">
                <h6>Status pemeriksaan HR</h6>
                <p>CV lengkap dan selesai diperiksa merupakan dua indikator terpisah.</p>
                <div id="cvDashboardReviews"></div>
            </article>
            <article class="cv-dashboard-panel">
                <h6>Tahap pengisian yang tertahan</h6>
                <p>Tahap pertama yang belum lengkap pada setiap profil. Klik untuk melihat daftar.</p>
                <div id="cvDashboardSteps"></div>
            </article>
            <article class="cv-dashboard-panel">
                <h6>Prioritas tindak lanjut</h6>
                <p>Maksimal 8 karyawan perlu reminder atau konfirmasi, diurutkan dari aktivitas terlama; tanggal kosong di awal.</p>
                <div id="cvDashboardPriorities"></div>
            </article>
        </div>
        <p class="cv-dashboard-note mt-3">Kelengkapan profil berbeda dengan hasil pemeriksaan HR. Angka berasal dari snapshot lokal, bukan pemeriksaan CV secara langsung.</p>
    </div>
</section>
