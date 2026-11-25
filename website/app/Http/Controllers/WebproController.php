<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WebproController extends Controller
{
    public function overview(ParkirController $parking)
    {
        $areas = DB::table('parking_areas')->orderBy('name')->get();
        $slots = DB::table('parking_slots')->orderBy('label')->get()->groupBy('parking_area_id');
        $tult = $parking->stats();

        return view('areas', compact('areas', 'slots', 'tult'));
    }

    public function manage(Request $request)
    {
        $areas = DB::table('parking_areas')->orderBy('name')->get();
        $area = $request->filled('area') ? $areas->firstWhere('id', $request->integer('area')) : null;
        abort_if($request->filled('area') && ! $area, 404);
        $slots = $area ? DB::table('parking_slots')->where('parking_area_id', $area->id)->orderBy('label')->paginate(25)->withQueryString() : null;

        return view('manage-areas', compact('areas', 'area', 'slots'));
    }

    public function saveArea(Request $request, ?int $area = null)
    {
        $unique = Rule::unique('parking_areas', 'name');
        if ($area !== null) {
            $unique->ignore($area);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'not_regex:/^TULT$/i', $unique],
            'location' => ['required', 'string', 'max:200'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        $id = DB::transaction(function () use ($area, $data) {
            if ($area === null) {
                return DB::table('parking_areas')->insertGetId($data);
            }
            $this->lockArea($area);
            if (DB::table('parking_slots')->where('parking_area_id', $area)->count() > $data['capacity']) {
                throw ValidationException::withMessages(['capacity' => 'Kapasitas tidak boleh kurang dari jumlah slot yang sudah didaftarkan.']);
            }
            DB::table('parking_areas')->where('id', $area)->update($data);

            return $area;
        });

        return redirect()->route('areas.manage', ['area' => $id])->with('success', 'Area parkir disimpan.');
    }

    public function deleteArea(int $area)
    {
        abort_unless(DB::table('parking_areas')->where('id', $area)->delete(), 404);

        return redirect()->route('areas.manage')->with('success', 'Area dan seluruh slotnya dihapus.');
    }

    public function saveSlot(Request $request, int $area, ?int $slot = null)
    {
        DB::transaction(function () use ($request, $area, $slot) {
            $parkingArea = $this->lockArea($area);
            $query = DB::table('parking_slots')->where('parking_area_id', $area);
            $unique = Rule::unique('parking_slots', 'label')->where('parking_area_id', $area);
            if ($slot !== null) {
                abort_unless((clone $query)->where('id', $slot)->exists(), 404);
                $unique->ignore($slot);
            }
            $data = $request->validate([
                'label' => ['required', 'string', 'max:30', $unique],
                'occupied' => ['required', 'boolean'],
            ]);
            if ($slot === null) {
                if ($query->count() >= $parkingArea->capacity) {
                    throw ValidationException::withMessages(['label' => 'Semua slot dalam kapasitas area sudah didaftarkan.']);
                }
                DB::table('parking_slots')->insert($data + ['parking_area_id' => $area]);
            } else {
                $query->where('id', $slot)->update($data);
            }
        });

        return back()->with('success', 'Slot parkir disimpan.');
    }

    public function deleteSlot(int $area, int $slot)
    {
        abort_unless(DB::table('parking_slots')->where('parking_area_id', $area)->where('id', $slot)->delete(), 404);

        return back()->with('success', 'Slot parkir dihapus.');
    }

    public function fines(Request $request)
    {
        $isAdmin = $request->user()->role === 'admin';
        $query = DB::table('parking_fines')->join('users', 'users.id', '=', 'parking_fines.user_id')
            ->select('parking_fines.*', 'users.name', 'users.nim');
        if (! $isAdmin) {
            $query->where('user_id', $request->user()->id);
        }
        $fines = $query->orderByDesc('issued_on')->orderByDesc('id')->paginate(20);
        $students = $isAdmin ? User::where('role', 'student')->orderBy('name')->get(['id', 'name', 'nim']) : collect();
        $editing = $isAdmin && $request->filled('edit') ? DB::table('parking_fines')->find($request->integer('edit')) : null;

        return view('fines', compact('fines', 'students', 'editing', 'isAdmin'));
    }

    public function saveFine(Request $request, ?int $fine = null)
    {
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->where('role', 'student')],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'reason' => ['required', 'string', 'max:250'],
            'issued_on' => ['required', 'date_format:Y-m-d'],
        ]);
        if ($fine === null) {
            DB::table('parking_fines')->insert($data);
        } else {
            abort_unless(DB::table('parking_fines')->where('id', $fine)->exists(), 404);
            DB::table('parking_fines')->where('id', $fine)->update($data);
        }

        return redirect()->route('fines.index')->with('success', 'Catatan denda disimpan.');
    }

    public function deleteFine(int $fine)
    {
        abort_unless(DB::table('parking_fines')->where('id', $fine)->delete(), 404);

        return redirect()->route('fines.index')->with('success', 'Catatan denda dihapus.');
    }

    private function lockArea(int $id): object
    {
        $area = DB::table('parking_areas')->where('id', $id)->lockForUpdate()->first();
        abort_unless($area, 404);

        return $area;
    }
}
