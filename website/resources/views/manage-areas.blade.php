@extends('layout')
@section('title', 'Kelola area')
@section('content')
<header class="page-heading">
    <div><h1>Kelola area parkir</h1><p>Atur kapasitas dan status slot untuk area yang dikelola manual.</p></div>
    <a class="btn btn-light" href="/area-parkir">Lihat halaman publik</a>
</header>
<div class="area-grid">
    <section class="surface">
        <h2>Daftar area</h2>
        <ul class="manage-list">
            <li><span><strong>TULT</strong><small>Data dari gerbang IoT</small></span><a href="/#capacity">Atur kapasitas</a></li>
            @foreach ($areas as $item)
                <li><span><strong>{{ $item->name }}</strong><small>{{ $item->location }} · {{ $item->capacity }} slot</small></span><a href="{{ route('areas.manage', ['area' => $item->id]) }}" @if ($area?->id === $item->id) aria-current="page" @endif>Kelola</a></li>
            @endforeach
        </ul>
        @if ($area)
            <a class="btn btn-light" href="{{ route('areas.manage') }}">Tambah area lain</a>
        @endif
    </section>
    <section class="surface">
        <h2>{{ $area ? 'Edit area' : 'Tambah area' }}</h2>
        <form method="post" action="/kelola-area{{ $area ? '/'.$area->id : '' }}">
            @csrf
            <label class="form-label" for="area-name">Nama area</label>
            <input class="form-control mb-3" id="area-name" name="name" maxlength="100" value="{{ old('name', $area?->name) }}" required>
            <label class="form-label" for="area-location">Lokasi</label>
            <input class="form-control mb-3" id="area-location" name="location" maxlength="200" value="{{ old('location', $area?->location) }}" required>
            <label class="form-label" for="area-capacity">Kapasitas (slot)</label>
            <input class="form-control mb-3" id="area-capacity" name="capacity" type="number" min="1" max="100000" value="{{ old('capacity', $area?->capacity) }}" required>
            <button class="btn btn-danger">{{ $area ? 'Simpan perubahan' : 'Tambah area' }}</button>
        </form>
        @if ($area)
            <details class="mt-3">
                <summary class="text-danger">Hapus area</summary>
                <p class="my-2">Hapus {{ $area->name }} dan semua slot di area ini?</p>
                <form method="post" action="/kelola-area/{{ $area->id }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger">Ya, hapus area dan slot</button>
                </form>
            </details>
        @endif
    </section>
</div>
@if ($area)
    <section class="surface mt-3">
        <h2>Slot parkir · {{ $area->name }}</h2>
        <p class="text-secondary">{{ $slots->total() }} dari {{ $area->capacity }} slot terdaftar.</p>
        @if ($slots->total() < $area->capacity)
            <form class="slot-form mb-4" method="post" action="/kelola-area/{{ $area->id }}/slot">
                @csrf
                <div><label class="form-label" for="new-slot-label">Kode slot</label><input class="form-control" id="new-slot-label" name="label" maxlength="30" placeholder="A-01" required></div>
                <div><label class="form-label" for="new-slot-status">Status</label><select class="form-select" id="new-slot-status" name="occupied"><option value="0">Tersedia</option><option value="1">Terisi</option></select></div>
                <button class="btn btn-danger">Tambah slot</button>
            </form>
        @endif
        @forelse ($slots as $slot)
            <details class="slot-editor">
                <summary><strong>{{ $slot->label }}</strong> · {{ $slot->occupied ? 'Terisi' : 'Tersedia' }}</summary>
                <form class="slot-form my-3" method="post" action="/kelola-area/{{ $area->id }}/slot/{{ $slot->id }}">
                    @csrf
                    <div><label class="form-label" for="slot-label-{{ $slot->id }}">Kode slot</label><input class="form-control" id="slot-label-{{ $slot->id }}" name="label" maxlength="30" value="{{ $slot->label }}" required></div>
                    <div><label class="form-label" for="slot-status-{{ $slot->id }}">Status</label><select class="form-select" id="slot-status-{{ $slot->id }}" name="occupied"><option value="0" @selected(! $slot->occupied)>Tersedia</option><option value="1" @selected($slot->occupied)>Terisi</option></select></div>
                    <button class="btn btn-danger">Simpan slot</button>
                </form>
                <details>
                    <summary class="text-danger">Hapus slot</summary>
                    <form method="post" action="/kelola-area/{{ $area->id }}/slot/{{ $slot->id }}" class="mt-2">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-light">Ya, hapus {{ $slot->label }}</button>
                    </form>
                </details>
            </details>
        @empty
            <p class="text-secondary mb-0">Belum ada slot terdaftar.</p>
        @endforelse
        {{ $slots->links() }}
    </section>
@endif
@endsection
