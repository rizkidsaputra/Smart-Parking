<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ketersediaan parkir · MyParkir TULT</title>
    <link href="/assets/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/app.css" rel="stylesheet">
</head>
<body>
<header class="public-header">
    <div class="public-wrap">
        <a class="brand" href="/area-parkir"><span class="brand-icon" aria-hidden="true">P</span> MyParkir</a>
        <a class="btn btn-light" href="{{ auth()->check() ? '/' : '/login' }}">{{ auth()->check() ? 'Dashboard' : 'Masuk' }}</a>
    </div>
</header>
<main class="public-wrap">
    <header class="page-heading public-intro">
        <div><p class="location-label">TELKOM UNIVERSITY</p><h1>Ketersediaan parkir</h1><p>Bandingkan kapasitas dan status slot sebelum menuju kampus.</p></div>
        <div class="parking-mark" aria-hidden="true"><span>P</span></div>
    </header>
    <div class="area-grid">
        <section class="surface area-card tult-card">
            <span class="source-label"><span class="live-dot {{ $tult['online'] ? '' : 'offline' }}"></span> Data gerbang IoT</span>
            <h2>TULT</h2>
            <p class="text-secondary">Telkom University Landmark Tower</p>
            <dl class="area-stats">
                <div><dt>Tersedia</dt><dd>{{ $tult['available'] }}</dd></div>
                <div><dt>Terisi</dt><dd>{{ $tult['occupied'] }}</dd></div>
                <div><dt>Kapasitas</dt><dd>{{ $tult['capacity'] }}</dd></div>
            </dl>
            <p class="mb-0 text-secondary">{{ $tult['online'] ? 'Perangkat terhubung' : 'Perangkat belum terhubung · menampilkan catatan terakhir' }}</p>
        </section>
        @foreach ($areas as $area)
            @php
                $areaSlots = $slots->get($area->id, collect());
                $occupied = $areaSlots->where('occupied', true)->count();
                $unknown = $area->capacity - $areaSlots->count();
            @endphp
            <section class="surface area-card">
                <span class="source-label">Diperbarui admin</span>
                <h2>{{ $area->name }}</h2>
                <p class="text-secondary">{{ $area->location }}</p>
                <dl class="area-stats">
                    <div><dt>Tersedia</dt><dd>{{ $areaSlots->count() - $occupied }}</dd></div>
                    <div><dt>Terisi</dt><dd>{{ $occupied }}</dd></div>
                    <div><dt>Kapasitas</dt><dd>{{ $area->capacity }}</dd></div>
                </dl>
                @if ($unknown > 0)
                    <p class="text-secondary">{{ $unknown }} slot belum memiliki data status.</p>
                @endif
                @if ($areaSlots->isNotEmpty())
                    <details>
                        <summary>Lihat status per slot</summary>
                        <ul class="slot-list">
                            @foreach ($areaSlots as $slot)
                                <li><strong>{{ $slot->label }}</strong><span class="status-badge {{ $slot->occupied ? 'full' : '' }}">{{ $slot->occupied ? 'Terisi' : 'Tersedia' }}</span></li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </section>
        @endforeach
    </div>
    <footer class="page-footer">MyParkir · Telkom University</footer>
</main>
</body>
</html>
