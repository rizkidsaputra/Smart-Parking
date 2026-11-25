@extends('layout')
@section('title', 'Denda')
@section('content')
<header class="page-heading">
    <div><h1>{{ $isAdmin ? 'Denda parkir' : 'Denda saya' }}</h1><p>{{ $isAdmin ? 'Catat denda mahasiswa berdasarkan pelanggaran parkir.' : 'Lihat denda parkir yang dicatat admin.' }}</p></div>
    @if ($isAdmin && ! $editing)
        <a class="btn btn-danger" href="#fine-form">Tambah denda</a>
    @endif
</header>
@if ($isAdmin)
    <section class="surface mb-3" id="fine-form">
        <div class="section-heading">
            <h2>{{ $editing ? 'Edit denda' : 'Tambah denda' }}</h2>
            @if ($editing)<a href="/denda">Batal edit</a>@endif
        </div>
        @if ($students->isEmpty())
            <p class="mb-0">Daftarkan mahasiswa sebelum mencatat denda. <a href="/mahasiswa">Buka data mahasiswa</a></p>
        @else
            <form method="post" action="/denda{{ $editing ? '/'.$editing->id : '' }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="fine-student">Mahasiswa</label><select class="form-select" id="fine-student" name="user_id" required><option value="">Pilih mahasiswa</option>@foreach ($students as $student)<option value="{{ $student->id }}" @selected((string) old('user_id', $editing?->user_id) === (string) $student->id)>{{ $student->name }} · {{ $student->nim }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label class="form-label" for="fine-amount">Nominal (Rp)</label><input class="form-control" id="fine-amount" name="amount" type="number" min="1" max="100000000" value="{{ old('amount', $editing?->amount) }}" required></div>
                    <div class="col-md-3"><label class="form-label" for="fine-date">Tanggal</label><input class="form-control" id="fine-date" name="issued_on" type="date" value="{{ old('issued_on', $editing?->issued_on ?? now()->toDateString()) }}" required></div>
                    <div class="col-12"><label class="form-label" for="fine-reason">Pelanggaran</label><textarea class="form-control" id="fine-reason" name="reason" maxlength="250" rows="2" required>{{ old('reason', $editing?->reason) }}</textarea></div>
                </div>
                <button class="btn btn-danger mt-3">Simpan denda</button>
            </form>
        @endif
    </section>
@endif
<section class="surface">
    <h2>Catatan denda</h2>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th scope="col">Tanggal</th>@if ($isAdmin)<th scope="col">Mahasiswa</th>@endif<th scope="col">Pelanggaran</th><th scope="col">Nominal</th>@if ($isAdmin)<th scope="col">Tindakan</th>@endif</tr></thead>
            <tbody>
                @forelse ($fines as $fine)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($fine->issued_on)->format('d M Y') }}</td>
                        @if ($isAdmin)<td>{{ $fine->name }}<small class="d-block text-secondary">{{ $fine->nim }}</small></td>@endif
                        <td>{{ $fine->reason }}</td>
                        <td class="text-nowrap">Rp{{ number_format($fine->amount, 0, ',', '.') }}</td>
                        @if ($isAdmin)
                            <td>
                                <a href="/denda?edit={{ $fine->id }}#fine-form">Edit</a>
                                <details class="mt-2">
                                    <summary class="text-danger">Hapus</summary>
                                    <form method="post" action="/denda/{{ $fine->id }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-light mt-2">Ya, hapus denda</button>
                                    </form>
                                </details>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $isAdmin ? 5 : 3 }}" class="text-secondary">Belum ada catatan denda.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $fines->links() }}
</section>
@endsection
