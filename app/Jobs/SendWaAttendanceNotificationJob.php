<?php

namespace App\Jobs;

use App\Models\Siswa;
use App\Services\WaGatewayService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendWaAttendanceNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 20;

    public array $backoff = [5, 15, 30];

    public function __construct(
        public int $siswaId,
        public array $context = [],
        public ?string $nisn = null,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(WaGatewayService $waGatewayService): void
    {
        if ($this->siswaId <= 0) {
            return;
        }

        $siswa = Siswa::query()->find($this->siswaId);
        if (!$siswa) {
            return;
        }

        $waGatewayService->notifyAttendance($siswa, $this->context);
    }

    public function failed(\Throwable $exception): void
    {
        Log::warning('WA attendance notification job failed', [
            'siswa_id' => $this->siswaId,
            'nisn' => $this->nisn,
            'message' => $exception->getMessage(),
        ]);
    }
}

