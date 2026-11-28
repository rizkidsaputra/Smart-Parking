<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · MyParkir TULT</title>
    <link href="/assets/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/app.css" rel="stylesheet">
</head>
<body class="login-page">
<main class="login-layout">
    <aside class="login-sign" aria-hidden="true"><div class="parking-mark"><span>P</span></div></aside>
    <div class="login-content">
    <div class="brand"><span class="brand-icon" aria-hidden="true">P</span> MyParkir</div>
    <h1>Masuk ke MyParkir</h1>
    <p class="text-secondary">Area Parkir TULT · Telkom University</p>
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif
    <form method="post" action="/login">
        @csrf
        <label for="email" class="form-label">Email</label>
        <input id="email" class="form-control mb-3" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required>
        <label for="password" class="form-label">Kata sandi</label>
        <input id="password" class="form-control mb-4" type="password" name="password" autocomplete="current-password" required>
        <button class="btn btn-danger w-100">Masuk</button>
    </form>
    <p class="login-help">Belum punya akun atau lupa kata sandi? Hubungi admin parkir.</p>
    <a class="text-link" href="/area-parkir">Lihat ketersediaan parkir</a>
    </div>
</main>
</body>
</html>
