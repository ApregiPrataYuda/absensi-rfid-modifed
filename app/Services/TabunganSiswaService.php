<?php

namespace App\Services;

use App\Models\TabunganSiswaAccount;
use App\Models\TabunganSiswaTransaction;
use App\Models\TabunganSiswaTransactionAudit;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TabunganSiswaService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function createAccount(array $payload, User $actor): TabunganSiswaAccount
    {
        return DB::transaction(function () use ($payload, $actor): TabunganSiswaAccount {
            $account = TabunganSiswaAccount::query()->create([
                'siswa_id' => (int) $payload['siswa_id'],
                'jenis_tabungan_id' => (int) $payload['jenis_tabungan_id'],
                'nomor_rekening' => $this->buildAccountNumberPlaceholder(),
                'saldo_cached' => 0,
                'is_active' => (bool) ($payload['is_active'] ?? true),
                'opened_at' => !empty($payload['opened_at']) ? (string) $payload['opened_at'] : now(),
            ]);

            $account->forceFill([
                'nomor_rekening' => $this->generateAccountNumber((int) $account->id),
            ])->save();

            $initialDeposit = (int) ($payload['setoran_awal'] ?? 0);
            if ($initialDeposit > 0) {
                $this->createOpeningDepositTransaction(
                    $account,
                    $initialDeposit,
                    $payload['opened_at'] ?? null,
                    $actor
                );
            }

            return $account->fresh([
                'siswa:id,nama,nisn,kelas',
                'jenisTabungan:id,kode,nama',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateAccount(TabunganSiswaAccount $account, array $payload): TabunganSiswaAccount
    {
        return DB::transaction(function () use ($account, $payload): TabunganSiswaAccount {
            $lockedAccount = TabunganSiswaAccount::query()
                ->lockForUpdate()
                ->findOrFail($account->id);

            $lockedAccount->forceFill([
                'is_active' => (bool) ($payload['is_active'] ?? false),
                'opened_at' => !empty($payload['opened_at'])
                    ? (string) $payload['opened_at']
                    : ($lockedAccount->opened_at ?? now()),
            ])->save();

            return $lockedAccount->fresh([
                'siswa:id,nama,nisn,kelas',
                'jenisTabungan:id,kode,nama',
            ]);
        });
    }

    public function deleteAccount(TabunganSiswaAccount $account): void
    {
        DB::transaction(function () use ($account): void {
            $lockedAccount = TabunganSiswaAccount::query()
                ->lockForUpdate()
                ->findOrFail($account->id);

            $hasTransactions = TabunganSiswaTransaction::withTrashed()
                ->where('account_id', (int) $lockedAccount->id)
                ->exists();

            if ($hasTransactions || (int) $lockedAccount->saldo_cached !== 0) {
                throw ValidationException::withMessages([
                    'rekening' => 'Rekening yang sudah memiliki histori transaksi atau saldo tidak bisa dihapus.',
                ]);
            }

            $lockedAccount->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createTransaction(array $payload, User $actor): TabunganSiswaTransaction
    {
        return DB::transaction(function () use ($payload, $actor): TabunganSiswaTransaction {
            $account = array_key_exists('account_id', $payload)
                ? $this->resolveLockedExistingAccount((int) $payload['account_id'], true)
                : $this->resolveLockedAccount(
                    (int) $payload['siswa_id'],
                    (int) $payload['jenis_tabungan_id']
                );

            $transaction = TabunganSiswaTransaction::query()->create([
                'account_id' => (int) $account->id,
                'nomor_bukti' => $this->buildReceiptNumberPlaceholder(),
                'transacted_at' => (string) ($payload['transacted_at'] ?? now()->toDateTimeString()),
                'jenis_transaksi' => (string) $payload['jenis_transaksi'],
                'nominal' => (int) $payload['nominal'],
                'saldo_sebelum' => 0,
                'saldo_sesudah' => 0,
                'keterangan' => $this->nullableText($payload['keterangan'] ?? null),
                'performed_by_user_id' => (int) $actor->id,
            ]);

            $transaction->forceFill([
                'nomor_bukti' => $this->generateReceiptNumber((int) $transaction->id),
            ])->save();

            $this->rebuildAccountBalances((int) $account->id);

            $transaction->refresh();
            $this->logAudit($transaction, 'created', null, $this->snapshot($transaction), $actor, null);

            return $transaction->load($this->transactionRelations());
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateTransaction(TabunganSiswaTransaction $transaction, array $payload, User $actor): TabunganSiswaTransaction
    {
        return DB::transaction(function () use ($transaction, $payload, $actor): TabunganSiswaTransaction {
            $lockedTransaction = TabunganSiswaTransaction::query()
                ->with($this->transactionRelations())
                ->lockForUpdate()
                ->findOrFail($transaction->id);

            $oldSnapshot = $this->snapshot($lockedTransaction);
            $oldAccountId = (int) $lockedTransaction->account_id;

            $newAccount = array_key_exists('account_id', $payload)
                ? $this->resolveLockedExistingAccount(
                    (int) $payload['account_id'],
                    (int) $payload['account_id'] !== $oldAccountId
                )
                : $this->resolveLockedAccount(
                    (int) $payload['siswa_id'],
                    (int) $payload['jenis_tabungan_id']
                );

            $lockedTransaction->forceFill([
                'account_id' => (int) $newAccount->id,
                'transacted_at' => (string) (
                    $payload['transacted_at']
                    ?? $lockedTransaction->transacted_at?->toDateTimeString()
                    ?? now()->toDateTimeString()
                ),
                'jenis_transaksi' => (string) $payload['jenis_transaksi'],
                'nominal' => (int) $payload['nominal'],
                'keterangan' => $this->nullableText($payload['keterangan'] ?? null),
                'updated_by_user_id' => (int) $actor->id,
            ])->save();

            $this->rebuildAccountBalances($oldAccountId);
            if ((int) $newAccount->id !== $oldAccountId) {
                $this->rebuildAccountBalances((int) $newAccount->id);
            }

            $lockedTransaction->refresh();
            $lockedTransaction->load($this->transactionRelations());
            $this->logAudit($lockedTransaction, 'updated', $oldSnapshot, $this->snapshot($lockedTransaction), $actor, null);

            return $lockedTransaction;
        });
    }

    public function deleteTransaction(TabunganSiswaTransaction $transaction, User $actor, string $reason): void
    {
        DB::transaction(function () use ($transaction, $actor, $reason): void {
            $lockedTransaction = TabunganSiswaTransaction::query()
                ->with($this->transactionRelations())
                ->lockForUpdate()
                ->findOrFail($transaction->id);

            $oldSnapshot = $this->snapshot($lockedTransaction);
            $accountId = (int) $lockedTransaction->account_id;

            $lockedTransaction->forceFill([
                'deleted_by_user_id' => (int) $actor->id,
                'delete_reason' => $reason,
            ])->save();
            $lockedTransaction->delete();

            $this->rebuildAccountBalances($accountId);
            $this->logAudit($lockedTransaction, 'deleted', $oldSnapshot, null, $actor, $reason);
        });
    }

    protected function resolveLockedAccount(int $siswaId, int $jenisTabunganId): TabunganSiswaAccount
    {
        $account = TabunganSiswaAccount::query()->firstOrCreate(
            [
                'siswa_id' => $siswaId,
                'jenis_tabungan_id' => $jenisTabunganId,
            ],
            [
                'nomor_rekening' => $this->buildAccountNumberPlaceholder(),
                'saldo_cached' => 0,
                'is_active' => true,
                'opened_at' => now(),
            ]
        );

        $lockedAccount = TabunganSiswaAccount::query()
            ->lockForUpdate()
            ->findOrFail($account->id);

        if (trim((string) ($lockedAccount->nomor_rekening ?? '')) === '') {
            $lockedAccount->forceFill([
                'nomor_rekening' => $this->generateAccountNumber((int) $lockedAccount->id),
            ])->save();
        }

        return $lockedAccount;
    }

    protected function resolveLockedExistingAccount(int $accountId, bool $requireActive = false): TabunganSiswaAccount
    {
        $account = TabunganSiswaAccount::query()
            ->lockForUpdate()
            ->findOrFail($accountId);

        if ($requireActive && !$account->is_active) {
            throw ValidationException::withMessages([
                'account_id' => 'Rekening tabungan yang dipilih sedang nonaktif.',
            ]);
        }

        return $account;
    }

    protected function rebuildAccountBalances(int $accountId): void
    {
        $account = TabunganSiswaAccount::query()
            ->lockForUpdate()
            ->findOrFail($accountId);

        $rows = TabunganSiswaTransaction::query()
            ->where('account_id', $accountId)
            ->orderBy('transacted_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $runningBalance = 0;

        foreach ($rows as $row) {
            $amount = $row->signedAmount();
            $before = $runningBalance;
            $after = $before + $amount;

            if ($after < 0) {
                throw ValidationException::withMessages([
                    'nominal' => 'Saldo tabungan tidak mencukupi untuk perubahan transaksi tersebut.',
                ]);
            }

            if ((int) $row->saldo_sebelum !== $before || (int) $row->saldo_sesudah !== $after) {
                DB::table('tabungan_siswa_transactions')
                    ->where('id', $row->id)
                    ->update([
                        'saldo_sebelum' => $before,
                        'saldo_sesudah' => $after,
                    ]);
            }

            $runningBalance = $after;
        }

        $account->forceFill([
            'saldo_cached' => $runningBalance,
            'opened_at' => $account->opened_at ?? now(),
        ])->save();
    }

    protected function createOpeningDepositTransaction(
        TabunganSiswaAccount $account,
        int $amount,
        mixed $openedAt,
        User $actor
    ): void {
        $transaction = TabunganSiswaTransaction::query()->create([
            'account_id' => (int) $account->id,
            'nomor_bukti' => $this->buildReceiptNumberPlaceholder(),
            'transacted_at' => $this->openingDepositTimestamp($openedAt),
            'jenis_transaksi' => TabunganSiswaTransaction::TYPE_SETORAN,
            'nominal' => $amount,
            'saldo_sebelum' => 0,
            'saldo_sesudah' => 0,
            'keterangan' => 'Setoran awal pembukaan rekening',
            'performed_by_user_id' => (int) $actor->id,
        ]);

        $transaction->forceFill([
            'nomor_bukti' => $this->generateReceiptNumber((int) $transaction->id),
        ])->save();

        $this->rebuildAccountBalances((int) $account->id);

        $transaction->refresh();
        $this->logAudit(
            $transaction,
            'created',
            null,
            $this->snapshot($transaction),
            $actor,
            'Setoran awal saat pembukaan rekening.'
        );
    }

    protected function openingDepositTimestamp(mixed $openedAt): string
    {
        $now = now();

        if (empty($openedAt)) {
            return $now->toDateTimeString();
        }

        return Carbon::parse((string) $openedAt)
            ->setTime($now->hour, $now->minute, $now->second)
            ->toDateTimeString();
    }

    protected function buildReceiptNumberPlaceholder(): string
    {
        return 'TMP-' . now()->format('YmdHisv') . '-' . mt_rand(1000, 9999);
    }

    protected function buildAccountNumberPlaceholder(): string
    {
        return now()->format('YmdHisv') . str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    protected function generateAccountNumber(int $accountId): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = $this->randomNumericAccountNumber();
            $exists = TabunganSiswaAccount::query()
                ->where('nomor_rekening', $candidate)
                ->where('id', '!=', $accountId)
                ->exists();

            if (!$exists) {
                return $candidate;
            }
        }

        return now()->format('ymdHis') . str_pad((string) ($accountId % 10000), 4, '0', STR_PAD_LEFT);
    }

    protected function randomNumericAccountNumber(): string
    {
        return (string) random_int(100000, 999999) . (string) random_int(100000, 999999);
    }

    protected function generateReceiptNumber(int $transactionId): string
    {
        return 'TBG-' . now()->format('Ymd') . '-' . str_pad((string) $transactionId, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<int, string>
     */
    protected function transactionRelations(): array
    {
        return [
            'account.siswa:id,nama,nisn,kelas',
            'account.jenisTabungan:id,kode,nama',
            'performedBy:id,name,username',
            'updatedBy:id,name,username',
            'deletedBy:id,name,username',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshot(TabunganSiswaTransaction $transaction): array
    {
        $transaction->loadMissing($this->transactionRelations());

        return [
            'id' => (int) $transaction->id,
            'account_id' => (int) $transaction->account_id,
            'nomor_rekening' => (string) ($transaction->account?->nomor_rekening ?? ''),
            'siswa_id' => (int) ($transaction->account?->siswa_id ?? 0),
            'siswa_nama' => (string) ($transaction->account?->siswa?->nama ?? ''),
            'siswa_nisn' => (string) ($transaction->account?->siswa?->nisn ?? ''),
            'jenis_tabungan_id' => (int) ($transaction->account?->jenis_tabungan_id ?? 0),
            'jenis_tabungan_nama' => (string) ($transaction->account?->jenisTabungan?->nama ?? ''),
            'nomor_bukti' => (string) ($transaction->nomor_bukti ?? ''),
            'transacted_at' => $transaction->transacted_at?->toDateTimeString(),
            'jenis_transaksi' => (string) ($transaction->jenis_transaksi ?? ''),
            'nominal' => (int) ($transaction->nominal ?? 0),
            'saldo_sebelum' => (int) ($transaction->saldo_sebelum ?? 0),
            'saldo_sesudah' => (int) ($transaction->saldo_sesudah ?? 0),
            'keterangan' => (string) ($transaction->keterangan ?? ''),
            'performed_by_user_id' => (int) ($transaction->performed_by_user_id ?? 0),
            'updated_by_user_id' => (int) ($transaction->updated_by_user_id ?? 0),
            'deleted_by_user_id' => (int) ($transaction->deleted_by_user_id ?? 0),
            'deleted_at' => $transaction->deleted_at?->toDateTimeString(),
            'delete_reason' => (string) ($transaction->delete_reason ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $oldData
     * @param  array<string, mixed>|null  $newData
     */
    protected function logAudit(
        TabunganSiswaTransaction $transaction,
        string $action,
        ?array $oldData,
        ?array $newData,
        User $actor,
        ?string $note
    ): void {
        TabunganSiswaTransactionAudit::query()->create([
            'transaction_id' => (int) $transaction->id,
            'actor_user_id' => (int) $actor->id,
            'action' => $action,
            'old_data' => $oldData,
            'new_data' => $newData,
            'note' => $this->nullableText($note),
        ]);
    }

    protected function nullableText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }

}
