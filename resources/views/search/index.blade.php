<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>PT VDNI | Pencarian Karyawan Resign</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Bootstrap 5 (KaiAdmin base) --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.min.css" rel="stylesheet">

    <style>
        body {
            background: #f4f6f9;
        }

        .hero {
            background: linear-gradient(135deg, #0d6efd, #198754);
            color: white;
            padding: 60px 20px;
        }

        .search-box {
            max-width: 650px;
            margin: auto;
        }

        .card-hover:hover {
            transform: translateY(-4px);
            transition: 0.2s ease;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .card-accent {
            height: 4px;
            background: #198754;
        }

        mark {
            background: #d1f7e3;
            color: #198754;
            padding: 0 4px;
            border-radius: 4px;
        }

        .foto-wrapper {
            width: 450px;
            height: 450px;
            overflow: hidden;
            border-radius: 12px;
            background: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .foto-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            /* isi penuh, crop otomatis */
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark shadow-sm"
        style="background: linear-gradient(90deg, #0d6efd, #198754);">
        <div class="container">

            <div class="d-flex align-items-center">
                <span class="fw-bold fs-5">
                    HR<span class="text-warning"> </span>System
                </span>
                <span class="ms-3 small opacity-75">
                    Human Resources Portal
                </span>
            </div>

        </div>
    </nav>
    {{-- ================= HERO ================= --}}
    <section class="hero text-center">
        <h6 class="text-uppercase fw-bold opacity-75 mb-2">
            Direktori Karyawan <br> 员工名录
        </h6>
        <h2 class="fw-bold mb-3">
            Pencarian Data Karyawan Tidak Aktif <br> 辞职员工数据搜索
        </h2>
        <p class="opacity-75 mb-4">
            Masukkan NIK atau nama karyawan untuk melihat detail data <br> 输入员工共号或员工姓名以查看数据详情
        </p>

        <div class="search-box">
            <form method="GET" action="{{ route('search.by.security') }}">
                <div class="input-group input-group-lg shadow">
                    <span class="input-group-text bg-white">
                        <i class="fa fa-search text-primary"></i>
                    </span>
                    <input
                        type="text"
                        name="q"
                        class="form-control"
                        placeholder="Contoh 例如 : 230337694 atau 或 Andi Pratama..."
                        value="{{ request('q') }}"
                        autocomplete="off"
                        autofocus>
                    <button class="btn btn-success fw-bold px-4" type="submit">
                        Cari 寻找
                    </button>
                </div>
                <small class="text-white-50 d-block mt-2">
                    Pencarian tidak membedakan huruf besar/kecil <br> 寻找不区分大小写
                </small>
            </form>
        </div>
    </section>


    {{-- ================= CONTENT ================= --}}
    {{-- ================= CONTENT ================= --}}
    <div class="container py-5">

        @php
        $q = request('q');
        $hasSearch = filled($q);
        $escapedQ = $hasSearch ? preg_quote($q, '/') : null;
        @endphp

        {{-- META (hanya muncul saat search) --}}
        @if($hasSearch)
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h5 class="fw-bold mb-1">
                    Hasil untuk 结果
                    <span class="text-primary">"{{ $q }}"</span>
                </h5>
                <span class="badge bg-success-subtle text-success border">
                    {{ $resign->total() }} data 数据
                </span>
            </div>

            <a href="{{ route('search.by.security') }}"
                class="btn btn-outline-secondary btn-sm">
                <i class="fa fa-times me-1"></i> Hapus 删除
            </a>
        </div>
        @endif


        @if($resign->isNotEmpty())

        <div class="row">
            @foreach($resign as $emp)
            <div class="col-md-4 mb-4">
                <div class="card border-0 shadow-sm h-100 card-hover">
                    <div class="card-accent"></div>

                    <div class="card-body">

                        {{-- FOTO --}}
                        @php
                        $fotoPath = null;
                        $extensions = ['png', 'jpg', 'jpeg'];

                        $nama = trim(preg_replace('/\s+/', ' ', $emp->nama_karyawan));
                        $nik = trim($emp->nik);

                        foreach ($extensions as $ext) {

                        $file1 = public_path("foto-karyawan/{$nik}.{$ext}");
                        if (file_exists($file1)) {
                        $fotoPath = asset("foto-karyawan/{$nik}.{$ext}");
                        break;
                        }

                        $file2 = public_path("foto-karyawan/{$nik} {$nama}.{$ext}");
                        if (file_exists($file2)) {
                        $fotoPath = asset("foto-karyawan/{$nik} {$nama}.{$ext}");
                        break;
                        }
                        }
                        @endphp

                        <div class="d-flex align-items-center mb-3">
                            <div class="me-3" style="width:45px;height:45px;">
                                @if($fotoPath)
                                <img src="{{ $fotoPath }}"
                                    alt="Foto {{ $emp->nama_karyawan }}"
                                    data-bs-toggle="modal"
                                    data-bs-target="#previewFotoModal"
                                    data-foto="{{ $fotoPath }}"
                                    style="width:45px;height:45px;border-radius:8px;object-fit:cover;cursor:pointer;">
                                @else
                                <div class="bg-primary text-white fw-bold"
                                    style="width:45px;height:45px;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                                    {{ strtoupper(mb_substr($emp->nama_karyawan, 0, 2)) }}
                                </div>
                                @endif
                            </div>

                            <div>
                                <h6 class="fw-bold mb-1">
                                    @if($hasSearch)
                                    {!! preg_replace('/('.$escapedQ.')/iu', '<mark>$1</mark>', e($emp->nama_karyawan)) !!}
                                    @else
                                    {{ $emp->nama_karyawan }}
                                    @endif
                                </h6>

                                <small class="text-muted">
                                    NIK 工号 ·
                                    @if($hasSearch)
                                    {!! preg_replace('/('.$escapedQ.')/iu', '<mark>$1</mark>', e($emp->nik)) !!}
                                    @else
                                    {{ $emp->nik }}
                                    @endif
                                </small>
                            </div>
                        </div>

                        <hr>

                        {{-- INFO --}}
                        <div class="row small g-2">
                            <div class="col-6">
                                <div class="text-muted text-uppercase fw-bold">Departement 部门</div>
                                <div class="fw-semibold">
                                    {{ $emp->departemen->departemen ?? '—' }}
                                </div>
                            </div>

                            <div class="col-6">
                                <div class="text-muted text-uppercase fw-bold">Divisi 科室</div>
                                <div class="fw-semibold">
                                    {{ $emp->divisi->nama_divisi ?? '—' }}
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="text-muted text-uppercase fw-bold">Position 岗位</div>
                                <div class="fw-semibold">
                                    {{ $emp->posisi ?? '—' }}
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="text-muted text-uppercase fw-bold">Alamat 地址</div>
                                <div class="fw-semibold">
                                    {{ collect([
                                        optional($emp->kelurahan)->kelurahan,
                                        optional($emp->kecamatan)->kecamatan,
                                        optional($emp->kabupaten)->kabupaten,
                                        optional($emp->provinsi)->provinsi,
                                    ])
                                    ->filter()
                                    ->map(fn($v) => ucwords(strtolower($v)))
                                    ->implode(', ') ?: '—' }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 text-end">
                            <span class="badge bg-danger-subtle text-danger px-3 py-2">
                                Tidak Aktif 辞职
                            </span>
                        </div>

                    </div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- PAGINATION --}}
        @if($resign->hasPages())
        <div class="d-flex justify-content-center mt-4">
            {{ $resign->appends(request()->except('page'))->links('pagination::bootstrap-4') }}
        </div>
        @endif

        @else
        <div class="card shadow-sm border-0 text-center py-5">
            <div class="card-body">
                <i class="fa fa-search fa-3x text-muted mb-3"></i>
                <h5 class="fw-bold">Tidak Ada Data Ditemukan 未找到数据</h5>
            </div>
        </div>
        @endif

    </div>

    <div class="modal fade" id="previewFotoModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body d-flex justify-content-center align-items-center p-4">

                    <div class="foto-wrapper">
                        <img id="previewImage" src="">
                    </div>

                </div>
            </div>
        </div>
    </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        setTimeout(function() {
            let alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                let bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 20000);
    </script>

    <script>
        document.addEventListener('show.bs.modal', function(event) {
            const trigger = event.relatedTarget;
            if (trigger && trigger.dataset.foto) {
                document.getElementById('previewImage').src = trigger.dataset.foto;
            }
        });
    </script>
</body>

</html>