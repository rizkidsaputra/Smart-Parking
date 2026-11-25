<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · MyParkir TULT</title>
    <link href="/assets/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/app.css" rel="stylesheet">
    <script src="/assets/app.js" defer></script>
</head>
<body>
<a class="skip-link" href="#main-content">Ke konten utama</a>
@php
    $user = auth()->user();
    $isAdmin = $user->role === 'admin';
    $menu = ['/' => 'Dashboard', '/riwayat' => 'Riwayat', '/area-parkir' => 'Area parkir', '/denda' => 'Denda'];
    if ($isAdmin) {
        $menu += ['/mahasiswa' => 'Mahasiswa', '/perangkat' => 'Perangkat', '/kelola-area' => 'Kelola area'];
    }
@endphp
<aside class="sidebar">
    <header class="sidebar-header">
        <a class="brand" href="/"><span class="brand-icon" aria-hidden="true">P</span> MyParkir</a>
        <span class="site-label">Area Parkir TULT</span>
    </header>
    <nav class="main-nav {{ $isAdmin ? 'admin-nav' : 'student-nav' }}" aria-label="Menu utama">
        @foreach ($menu as $url => $label)
            <a href="{{ $url }}" @if (request()->is($url === '/' ? '/' : trim($url, '/'))) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <div class="user-card">
        <div>
            <strong>{{ $user->name }}</strong>
            <small>{{ $isAdmin ? 'Admin parkir' : 'Mahasiswa' }}</small>
        </div>
        <form action="/logout" method="post">
            @csrf
            <button class="logout" type="submit">Keluar</button>
        </form>
    </div>
</aside>
<main class="page-content" id="main-content" tabindex="-1">
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>Periksa kembali data berikut.</strong>
            <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    @yield('content')
    <footer class="page-footer">MyParkir TULT <span>Semua waktu dalam WIB</span></footer>
</main>
</body>
</html>
