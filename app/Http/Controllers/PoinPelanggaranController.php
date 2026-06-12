<?php

namespace App\Http\Controllers;

use App\Models\JenisPelanggaran;
use App\Models\Kelas;
use App\Models\PoinPelanggaranSiswa;
use App\Models\Siswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PoinPelanggaranController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('poin-pelanggaran.riwayat.index');
    }

    public function masterPage(): View
    {
        return view('pages.poin-pelanggaran-master');
    }

    public function riwayatPage(): View
    {
        return view('pages.poin-pelanggaran-riwayat');
    }

    public function data(Request $request): JsonResponse
    {
        $filters = [
            'tanggal_dari' => trim((string) $request->query('tanggal_dari', '')),
            'tanggal_sampai' => trim((string) $request->query('tanggal_sampai', '')),
            'kelas' => trim((string) $request->query('kelas', '')),
            'siswa_id' => (int) $request->query('siswa_id', 0),
            'q' => trim((string) $request->query('q', '')),
        ];

        $isWakel = (bool) $request->user()?->hasRole('wakel');
        $wakelKelas = trim((string) ($request->user()?->kelas ?? ''));

        $rowsQuery = PoinPelanggaranSiswa::query()
            ->with([
                'siswa:id,nama,nisn,kelas',
                'jenisPelanggaran:id,nama,kategori',
                'inputBy:id,name,username',
            ])
            ->orderByDesc('tanggal')
            ->orderByDesc('id');

        if ($filters['tanggal_dari'] !== '') {
            $rowsQuery->whereDate('tanggal', '>=', $filters['tanggal_dari']);
        }
        if ($filters['tanggal_sampai'] !== '') {
            $rowsQuery->whereDate('tanggal', '<=', $filters['tanggal_sampai']);
        }
        if ($filters['kelas'] !== '') {
            $rowsQuery->whereHas('siswa', fn ($query) => $query->where('kelas', $filters['kelas']));
        }
        if ($filters['siswa_id'] > 0) {
            $rowsQuery->where('siswa_id', $filters['siswa_id']);
        }
        if ($filters['q'] !== '') {
            $keyword = $filters['q'];
            $rowsQuery->whereHas('siswa', function ($query) use ($keyword): void {
                $query->where(function ($subQuery) use ($keyword): void {
                    $subQuery
                        ->where('nama', 'like', '%' . $keyword . '%')
                        ->orWhere('nisn', 'like', '%' . $keyword . '%');
                });
            });
        }
        if ($isWakel && $wakelKelas !== '') {
            $rowsQuery->whereHas('siswa', fn ($query) => $query->where('kelas', $wakelKelas));
        }

        $rows = $rowsQuery
            ->get()
            ->map(fn (PoinPelanggaranSiswa $row) => $this->formatRow($row))
            ->values();

        $jenis = JenisPelanggaran::query()
            ->orderByDesc('is_active')
            ->orderBy('nama')
            ->get(['id', 'nama', 'kategori', 'poin', 'is_active'])
            ->map(fn (JenisPelanggaran $row) => [
                'id' => (int) $row->id,
                'nama' => (string) $row->nama,
                'kategori' => (string) ($row->kategori ?? ''),
                'poin' => (int) $row->poin,
                'is_active' => (bool) $row->is_active,
            ])
            ->values();

        $siswaQuery = Siswa::query()
            ->orderBy('kelas')
            ->orderBy('nama');
        if ($isWakel && $wakelKelas !== '') {
            $siswaQuery->where('kelas', $wakelKelas);
        }
        $siswa = $siswaQuery
            ->get(['id', 'nama', 'nisn', 'kelas'])
            ->map(fn (Siswa $row) => [
                'id' => (int) $row->id,
                'nama' => (string) $row->nama,
                'nisn' => (string) $row->nisn,
                'kelas' => (string) ($row->kelas ?? ''),
            ])
            ->values();

        $kelasQuery = Kelas::query()->orderBy('nama');
        if ($isWakel && $wakelKelas !== '') {
            $kelasQuery->where('nama', $wakelKelas);
        }

        $ringkasanQuery = PoinPelanggaranSiswa::query()
            ->selectRaw('siswa_id, SUM(poin) as total_poin, COUNT(*) as total_pelanggaran')
            ->groupBy('siswa_id')
            ->with(['siswa:id,nama,nisn,kelas'])
            ->orderByDesc('total_poin')
            ->orderByDesc('total_pelanggaran')
            ->limit(10);

        if ($isWakel && $wakelKelas !== '') {
            $ringkasanQuery->whereHas('siswa', fn ($query) => $query->where('kelas', $wakelKelas));
        }

        $ringkasan = $ringkasanQuery
            ->get()
            ->map(fn (PoinPelanggaranSiswa $row) => [
                'siswa_id' => (int) $row->siswa_id,
                'nama' => (string) ($row->siswa?->nama ?? '-'),
                'nisn' => (string) ($row->siswa?->nisn ?? '-'),
                'kelas' => (string) ($row->siswa?->kelas ?? '-'),
                'total_poin' => (int) ($row->total_poin ?? 0),
                'total_pelanggaran' => (int) ($row->total_pelanggaran ?? 0),
            ])
            ->values();

        return response()->json([
            'data' => $rows,
            'jenis' => $jenis,
            'siswa' => $siswa,
            'kelas' => $kelasQuery->get(['id', 'nama']),
            'ringkasan' => $ringkasan,
            'can_manage' => $request->user()?->can('poin-pelanggaran.manage') ?? false,
        ]);
    }

    public function storeJenis(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama' => [
                'required',
                'string',
                'max:150',
                Rule::unique('jenis_pelanggaran', 'nama'),
            ],
            'kategori' => ['nullable', 'string', 'max:80'],
            'poin' => ['required', 'integer', 'min:1', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $row = JenisPelanggaran::query()->create([
            'nama' => trim((string) $validated['nama']),
            'kategori' => trim((string) ($validated['kategori'] ?? '')) ?: null,
            'poin' => (int) $validated['poin'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return response()->json([
            'message' => 'Jenis pelanggaran berhasil ditambahkan.',
            'data' => [
                'id' => (int) $row->id,
                'nama' => (string) $row->nama,
                'kategori' => (string) ($row->kategori ?? ''),
                'poin' => (int) $row->poin,
                'is_active' => (bool) $row->is_active,
            ],
        ]);
    }

    public function updateJenis(Request $request, JenisPelanggaran $jenisPelanggaran): JsonResponse
    {
        $validated = $request->validate([
            'nama' => [
                'required',
                'string',
                'max:150',
                Rule::unique('jenis_pelanggaran', 'nama')->ignore($jenisPelanggaran->id),
            ],
            'kategori' => ['nullable', 'string', 'max:80'],
            'poin' => ['required', 'integer', 'min:1', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $jenisPelanggaran->update([
            'nama' => trim((string) $validated['nama']),
            'kategori' => trim((string) ($validated['kategori'] ?? '')) ?: null,
            'poin' => (int) $validated['poin'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return response()->json([
            'message' => 'Jenis pelanggaran berhasil diperbarui.',
            'data' => [
                'id' => (int) $jenisPelanggaran->id,
                'nama' => (string) $jenisPelanggaran->nama,
                'kategori' => (string) ($jenisPelanggaran->kategori ?? ''),
                'poin' => (int) $jenisPelanggaran->poin,
                'is_active' => (bool) $jenisPelanggaran->is_active,
            ],
        ]);
    }

    public function destroyJenis(JenisPelanggaran $jenisPelanggaran): JsonResponse
    {
        $jenisPelanggaran->delete();

        return response()->json([
            'message' => 'Jenis pelanggaran berhasil dihapus.',
        ]);
    }

    public function storePelanggaran(Request $request): JsonResponse
    {
        $validated = $this->validatePelanggaranPayload($request);
        $this->assertWakelKelasAccess($request, (int) $validated['siswa_id']);

        $jenis = JenisPelanggaran::query()->findOrFail((int) $validated['jenis_pelanggaran_id']);

        $row = PoinPelanggaranSiswa::query()->create([
            'siswa_id' => (int) $validated['siswa_id'],
            'jenis_pelanggaran_id' => (int) $jenis->id,
            'nama_pelanggaran' => (string) $jenis->nama,
            'poin' => (int) $jenis->poin,
            'tanggal' => (string) $validated['tanggal'],
            'catatan' => trim((string) ($validated['catatan'] ?? '')) ?: null,
            'input_by_user_id' => (int) ($request->user()?->id ?? 0) ?: null,
        ]);

        return response()->json([
            'message' => 'Pelanggaran siswa berhasil dicatat.',
            'data' => $this->formatRow($row->load(['siswa:id,nama,nisn,kelas', 'jenisPelanggaran:id,nama,kategori', 'inputBy:id,name,username'])),
        ]);
    }

    public function updatePelanggaran(Request $request, PoinPelanggaranSiswa $poinPelanggaran): JsonResponse
    {
        $validated = $this->validatePelanggaranPayload($request);
        $this->assertWakelKelasAccess($request, (int) $validated['siswa_id']);

        $jenis = JenisPelanggaran::query()->findOrFail((int) $validated['jenis_pelanggaran_id']);

        $poinPelanggaran->update([
            'siswa_id' => (int) $validated['siswa_id'],
            'jenis_pelanggaran_id' => (int) $jenis->id,
            'nama_pelanggaran' => (string) $jenis->nama,
            'poin' => (int) $jenis->poin,
            'tanggal' => (string) $validated['tanggal'],
            'catatan' => trim((string) ($validated['catatan'] ?? '')) ?: null,
        ]);

        return response()->json([
            'message' => 'Catatan pelanggaran berhasil diperbarui.',
            'data' => $this->formatRow($poinPelanggaran->load(['siswa:id,nama,nisn,kelas', 'jenisPelanggaran:id,nama,kategori', 'inputBy:id,name,username'])),
        ]);
    }

    public function destroyPelanggaran(Request $request, PoinPelanggaranSiswa $poinPelanggaran): JsonResponse
    {
        $this->assertWakelKelasAccess($request, (int) $poinPelanggaran->siswa_id);
        $poinPelanggaran->delete();

        return response()->json([
            'message' => 'Catatan pelanggaran berhasil dihapus.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatePelanggaranPayload(Request $request): array
    {
        return $request->validate([
            'siswa_id' => [
                'required',
                'integer',
                Rule::exists('siswa', 'id'),
            ],
            'jenis_pelanggaran_id' => [
                'required',
                'integer',
                Rule::exists('jenis_pelanggaran', 'id'),
            ],
            'tanggal' => ['required', 'date'],
            'catatan' => ['nullable', 'string'],
        ]);
    }

    protected function assertWakelKelasAccess(Request $request, int $siswaId): void
    {
        if (!$request->user()?->hasRole('wakel')) {
            return;
        }

        $wakelKelas = trim((string) ($request->user()?->kelas ?? ''));
        if ($wakelKelas === '') {
            throw ValidationException::withMessages([
                'siswa_id' => 'Akun wali kelas belum ditautkan ke kelas.',
            ]);
        }

        $siswaKelas = trim((string) (Siswa::query()->whereKey($siswaId)->value('kelas') ?? ''));
        if ($siswaKelas !== $wakelKelas) {
            throw ValidationException::withMessages([
                'siswa_id' => 'Wali kelas hanya bisa mencatat pelanggaran siswa di kelasnya sendiri.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatRow(PoinPelanggaranSiswa $row): array
    {
        return [
            'id' => (int) $row->id,
            'tanggal' => (string) ($row->tanggal?->format('Y-m-d') ?? ''),
            'siswa_id' => (int) $row->siswa_id,
            'siswa_nama' => (string) ($row->siswa?->nama ?? '-'),
            'siswa_nisn' => (string) ($row->siswa?->nisn ?? '-'),
            'kelas' => (string) ($row->siswa?->kelas ?? '-'),
            'jenis_pelanggaran_id' => $row->jenis_pelanggaran_id !== null ? (int) $row->jenis_pelanggaran_id : null,
            'nama_pelanggaran' => (string) $row->nama_pelanggaran,
            'kategori' => (string) ($row->jenisPelanggaran?->kategori ?? ''),
            'poin' => (int) $row->poin,
            'catatan' => (string) ($row->catatan ?? ''),
            'input_by' => (string) ($row->inputBy?->name ?: ($row->inputBy?->username ?? '-')),
            'created_at' => (string) ($row->created_at?->format('Y-m-d H:i:s') ?? ''),
        ];
    }
}
