<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Parking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ParkirController extends Controller
{
    public function __construct(private Parking $parking) {}

    public function login(Request $r)
    {
        $data = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt([...$data, 'enabled' => true])) {
            return back()->withErrors(['email' => 'Email atau kata sandi tidak sesuai.'])->onlyInput('email');
        }
        $r->session()->regenerate();

        return redirect('/');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/login');
    }

    public function dashboard(Request $r, Parking $p)
    {
        $p->notices();
        $active = DB::table('parking_sessions')->where('user_id', $r->user()->id)->whereNull('exited_at')->first();
        $notices = DB::table('parking_notices')->where('user_id', $r->user()->id)->orderByDesc('created_at')->limit(10)->get();

        return view('dashboard', [...$this->stats(), 'active' => $active, 'notices' => $notices]);
    }

    public function stats(): array
    {
        return $this->parking->stats();
    }

    public function history(Request $r)
    {
        $q = DB::table('parking_sessions')->join('users', 'users.id', '=', 'parking_sessions.user_id')->select('parking_sessions.*', 'users.name', 'users.nim');
        if ($r->user()->role !== 'admin') {
            $q->where('user_id', $r->user()->id);
        }
        if ($r->filled('q')) {
            $term = '%'.$r->string('q').'%';
            $q->where(fn ($x) => $x->where('users.name', 'like', $term)->orWhere('parking_sessions.plate', 'like', $term)->orWhere('users.nim', 'like', $term));
        }
        if ($r->filled('date')) {
            $r->validate(['date' => 'date']);
            $q->whereDate('entered_at', $r->date);
        }

        return view('history', ['sessions' => $q->orderByDesc('entered_at')->paginate(15)->withQueryString()]);
    }

    public function students(Request $r)
    {
        $q = User::where('role', 'student');
        if ($r->filled('q')) {
            $term = '%'.$r->string('q').'%';
            $q->where(fn ($x) => $x->where('name', 'like', $term)->orWhere('nim', 'like', $term)->orWhere('plate', 'like', $term));
        }

        return view('students', ['students' => $q->orderBy('name')->paginate(15)->withQueryString(), 'editing' => $r->filled('edit') ? User::where('role', 'student')->findOrFail($r->edit) : null]);
    }

    public function saveStudent(Request $r, ?int $id = null)
    {
        $student = $id ? User::where('role', 'student')->findOrFail($id) : new User;
        $r->merge(['uid' => strtoupper(trim($r->input('uid', ''))), 'plate' => strtoupper(trim($r->input('plate', '')))]);
        $data = $r->validate(['name' => 'required|string|max:100', 'nim' => ['required', 'string', 'max:30', Rule::unique('users')->ignore($id)], 'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($id)], 'uid' => ['required', 'regex:/^([0-9A-F]{2}:){3}([0-9A-F]{2})(:[0-9A-F]{2}){0,6}$/', Rule::unique('users')->ignore($id)], 'plate' => ['required', 'string', 'max:20', Rule::unique('users')->ignore($id)], 'vehicle' => 'required|string|max:100', 'password' => [$id ? 'nullable' : 'required', 'string', 'min:8', 'max:100']]);
        DB::transaction(function () use ($student, $data, $r, $id) {
            DB::table('parking_settings')->where('id', 1)->lockForUpdate()->first();
            if ($id) {
                abort_if(DB::table('parking_sessions')->where('user_id', $id)->whereNull('exited_at')->exists() || DB::table('gate_transactions')->where('user_id', $id)->where('state', 'pending')->where('expires_at', '>', now())->exists(), 422, 'Akhiri sesi/transaksi parkir sebelum mengubah mahasiswa.');
            }
            if (empty($data['password'])) {
                unset($data['password']);
            } else {
                $data['password'] = Hash::make($data['password']);
            }
            $student->forceFill([...$data, 'role' => 'student', 'enabled' => $r->boolean('enabled')])->save();
        });

        return redirect('/mahasiswa')->with('success', 'Data mahasiswa dan KTM berhasil disimpan.');
    }

    public function settings(Request $r)
    {
        $r->validate(['capacity' => 'required|integer|min:1|max:100000']);
        DB::transaction(function () use ($r) {
            DB::table('parking_settings')->where('id', 1)->lockForUpdate()->first();
            $reserved = DB::table('gate_transactions')->where('state', 'pending')->where('direction', 'entry')->where('expires_at', '>', now())->count();
            if ($r->integer('capacity') < DB::table('parking_sessions')->whereNull('exited_at')->count() + $reserved) {
                throw ValidationException::withMessages(['capacity' => 'Kapasitas lebih kecil dari kendaraan dan reservasi aktif.']);
            }
            DB::table('parking_settings')->where('id', 1)->update(['capacity' => $r->integer('capacity')]);
        });

        return back()->with('success', 'Kapasitas diperbarui.');
    }

    public function readNotice(Request $r, int $id)
    {
        DB::table('parking_notices')->where('id',$id)->where('user_id',$r->user()->id)->update(['read_at' => now()]);

        return back();
    }
}
