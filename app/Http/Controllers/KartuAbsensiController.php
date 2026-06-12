<?php

namespace App\Http\Controllers;

use App\Models\KartuAbsensi;
use App\Models\Siswa;
use App\Services\AttendanceCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KartuAbsensiController extends Controller
{
    public function index(): View
    {
        [$cards, $students] = $this->getPageData();

        return view('pages.kartu-absensi', [
            'cards' => $cards,
            'students' => $students,
            'cardRecords' => $cards->map(fn (KartuAbsensi $card) => $this->serializeCard($card))->values()->all(),
            'studentRecords' => $students->map(fn (Siswa $student) => $this->serializeStudent($student))->values()->all(),
            'dataUrl' => route('kartu-absensi.data'),
            'streamUrl' => route('kartu-absensi.stream'),
            'storeUrl' => route('kartu-absensi.store'),
            'updateUrlTemplate' => route('kartu-absensi.update', ['kartuAbsensi' => '__ID__']),
        ]);
    }

    public function data(): JsonResponse
    {
        [$cards, $students] = $this->getPageData();

        return response()->json([
            'success' => true,
            'data' => $this->serializePayload($cards, $students),
        ]);
    }

    public function stream(): StreamedResponse
    {
        return response()->stream(function (): void {
            $this->prepareSseRuntime();

            $lastFingerprint = null;
            $heartbeatTicks = 0;

            echo "retry: 3000\n\n";
            $this->sendSsePadding();
            $this->flushSseBuffer();

            while (!connection_aborted()) {
                [$cards, $students] = $this->getPageData();
                $payload = $this->serializePayload($cards, $students);
                $fingerprint = sha1($this->encodeSsePayload($payload));

                if ($lastFingerprint !== $fingerprint) {
                    $lastFingerprint = $fingerprint;
                    $heartbeatTicks = 0;
                    $this->sendSseEvent('sync', $payload);
                } else {
                    $heartbeatTicks++;

                    if ($heartbeatTicks >= 5) {
                        $this->sendSseComment('heartbeat');
                        $heartbeatTicks = 0;
                    }
                }

                sleep(3);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate, no-transform',
            'Pragma' => 'no-cache',
            'Connection' => 'keep-alive',
            'Content-Encoding' => 'none',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function captureStream(): StreamedResponse
    {
        return response()->stream(function (): void {
            $this->prepareSseRuntime();

            $baseline = $this->getLatestCaptureSnapshot();
            $baselineFingerprint = $baseline === null
                ? null
                : sha1($this->encodeSsePayload($baseline));

            $attempts = 0;
            $maxAttempts = 15;

            echo "retry: 3000\n\n";
            $this->sendSsePadding();
            $this->flushSseBuffer();

            while (!connection_aborted() && $attempts < $maxAttempts) {
                $snapshot = $this->getLatestCaptureSnapshot();
                $snapshotFingerprint = $snapshot === null
                    ? null
                    : sha1($this->encodeSsePayload($snapshot));

                if ($snapshot !== null && $snapshotFingerprint !== null && $snapshotFingerprint !== $baselineFingerprint) {
                    $this->sendSseEvent('captured', $snapshot);

                    return;
                }

                $attempts++;

                if ($attempts % 5 === 0) {
                    $this->sendSseComment('waiting');
                }

                sleep(3);
            }

            $this->sendSseEvent('timeout', [
                'message' => 'Waktu tunggu scan kartu habis.',
            ]);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate, no-transform',
            'Pragma' => 'no-cache',
            'Connection' => 'keep-alive',
            'Content-Encoding' => 'none',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function store(Request $request, AttendanceCardService $cardService): JsonResponse|RedirectResponse
    {
        $validated = $this->validateCard($request);

        $card = KartuAbsensi::query()->create([
            'type' => KartuAbsensi::TYPE_RFID,
            'code' => $cardService->normalizeCode($validated['code']),
            'siswa_id' => $validated['siswa_id'] ?? null,
        ]);

        $card->load('siswa');

        if ($this->wantsJson($request)) {
            return response()->json([
                'success' => true,
                'message' => 'Kartu absensi berhasil ditambahkan.',
                'data' => $this->serializeCard($card),
            ], 201);
        }

        return redirect()
            ->route('kartu-absensi.index')
            ->with('success', 'Kartu absensi berhasil ditambahkan.');
    }

    public function update(Request $request, KartuAbsensi $kartuAbsensi): JsonResponse|RedirectResponse
    {
        $this->ensureManagedCard($kartuAbsensi);

        $validated = $this->validateCardAssignment($request, $kartuAbsensi);

        $kartuAbsensi->forceFill([
            'siswa_id' => $validated['siswa_id'] ?? null,
        ])->save();

        $kartuAbsensi->load('siswa');

        if ($this->wantsJson($request)) {
            return response()->json([
                'success' => true,
                'message' => 'Kartu absensi berhasil diperbarui.',
                'data' => $this->serializeCard($kartuAbsensi),
            ]);
        }

        return redirect()
            ->route('kartu-absensi.index')
            ->with('success', 'Kartu absensi berhasil diperbarui.');
    }

    public function destroy(Request $request, KartuAbsensi $kartuAbsensi): JsonResponse|RedirectResponse
    {
        $this->ensureManagedCard($kartuAbsensi);

        $deletedId = $kartuAbsensi->id;
        $kartuAbsensi->delete();

        if ($this->wantsJson($request)) {
            return response()->json([
                'success' => true,
                'message' => 'Kartu absensi berhasil dihapus.',
                'data' => [
                    'id' => $deletedId,
                ],
            ]);
        }

        return redirect()
            ->route('kartu-absensi.index')
            ->with('success', 'Kartu absensi berhasil dihapus.');
    }

    protected function validateCard(Request $request, ?KartuAbsensi $card = null): array
    {
        $type = $card?->type ?? KartuAbsensi::TYPE_RFID;

        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('kartu_absensi', 'code')
                    ->where(fn ($query) => $query->where('type', $type))
                    ->ignore($card?->id),
            ],
            'siswa_id' => [
                'nullable',
                'integer',
                'exists:siswa,id',
                $this->studentCardUniqueRule($card),
            ],
        ], [
            'code.unique' => 'Kode kartu sudah terdaftar.',
            'siswa_id.unique' => 'Siswa ini sudah ditautkan ke kartu lain.',
        ]);
    }

    protected function validateCardAssignment(Request $request, ?KartuAbsensi $card = null): array
    {
        return $request->validate([
            'siswa_id' => [
                'nullable',
                'integer',
                'exists:siswa,id',
                $this->studentCardUniqueRule($card),
            ],
        ], [
            'siswa_id.unique' => 'Siswa ini sudah ditautkan ke kartu lain.',
        ]);
    }

    protected function ensureManagedCard(KartuAbsensi $card): void
    {
        abort_unless($card->type === KartuAbsensi::TYPE_RFID, 404);
    }

    protected function wantsJson(Request $request): bool
    {
        return $request->expectsJson() || $request->ajax();
    }

    protected function studentCardUniqueRule(?KartuAbsensi $card = null): Unique
    {
        return Rule::unique('kartu_absensi', 'siswa_id')
            ->where(fn ($query) => $query->where('type', KartuAbsensi::TYPE_RFID))
            ->ignore($card?->id);
    }

    protected function serializeCard(KartuAbsensi $card): array
    {
        return [
            'id' => $card->id,
            'code' => $card->code,
            'siswa_id' => $card->siswa_id,
            'student_name' => $card->siswa?->nama,
            'student_nisn' => $card->siswa?->nisn,
            'student_class' => $card->siswa?->kelas,
            'last_scanned_at' => $card->last_scanned_at?->toIso8601String(),
            'last_scanned_date' => $card->last_scanned_at?->format('d M Y'),
            'last_scanned_time' => $card->last_scanned_at?->format('H:i'),
            'last_scanned_source' => $card->last_scanned_source,
        ];
    }

    protected function serializeStudent(Siswa $student): array
    {
        return [
            'id' => $student->id,
            'nama' => $student->nama,
            'nisn' => $student->nisn,
            'kelas' => $student->kelas,
        ];
    }

    protected function serializePayload($cards, $students): array
    {
        return [
            'cards' => $cards->map(fn (KartuAbsensi $card) => $this->serializeCard($card))->values()->all(),
            'students' => $students->map(fn (Siswa $student) => $this->serializeStudent($student))->values()->all(),
        ];
    }

    protected function sendSseEvent(string $event, array $payload): void
    {
        echo 'event: ' . $event . "\n";
        echo 'data: ' . $this->encodeSsePayload($payload) . "\n\n";

        $this->flushSseBuffer();
    }

    protected function sendSseComment(string $comment): void
    {
        echo ': ' . $comment . "\n\n";

        $this->flushSseBuffer();
    }

    protected function sendSsePadding(): void
    {
        echo ': ' . str_repeat(' ', 2048) . "\n\n";
    }

    protected function prepareSseRuntime(): void
    {
        ignore_user_abort(true);

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', '0');
        @ini_set('implicit_flush', '1');

        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }

        @ob_implicit_flush(true);
    }

    protected function encodeSsePayload(array $payload): string
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? '{}' : $encoded;
    }

    protected function flushSseBuffer(): void
    {
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }

        flush();
    }

    protected function getLatestCaptureSnapshot(): ?array
    {
        $card = KartuAbsensi::query()
            ->where('type', KartuAbsensi::TYPE_RFID)
            ->whereNotNull('last_scanned_at')
            ->where('last_scanned_source', 'device')
            ->orderByDesc('last_scanned_at')
            ->orderByDesc('id')
            ->first();

        if (!$card) {
            return null;
        }

        return [
            'id' => $card->id,
            'code' => $card->code,
            'last_scanned_at' => $card->last_scanned_at?->toIso8601String(),
            'last_scanned_source' => $card->last_scanned_source,
        ];
    }

    protected function getPageData(): array
    {
        $cards = KartuAbsensi::query()
            ->where('type', KartuAbsensi::TYPE_RFID)
            ->with('siswa')
            ->orderByRaw('CASE WHEN siswa_id IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('last_scanned_at')
            ->orderByDesc('id')
            ->get();

        $students = Siswa::query()
            ->orderBy('nama')
            ->get(['id', 'nama', 'nisn', 'kelas']);

        return [$cards, $students];
    }
}
