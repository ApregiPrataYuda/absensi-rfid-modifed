<?php

namespace App\Http\Controllers;

use Dompdf\Dompdf;
use Dompdf\Options;
use App\Models\JenisTabungan;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TabunganSiswaAccount;
use App\Models\TabunganSiswaTransaction;
use App\Models\User;
use App\Services\TabunganSiswaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TabunganSiswaController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $this->canAccessManagementPage($user), 403);

        return redirect()->route('tabungan-siswa.rekening.index');
    }

    public function jenisPage(Request $request): View
    {
        $user = $request->user();
        abort_unless($user && $this->canAccessManagementPage($user), 403);

        return view('pages.tabungan-siswa-jenis');
    }

    public function rekeningPage(Request $request): View
    {
        $user = $request->user();
        abort_unless($user && $this->canAccessManagementPage($user), 403);

        return view('pages.tabungan-siswa-rekening');
    }

    public function jenisData(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $this->canAccessManagementPage($user), 403);

        $types = JenisTabungan::query()
            ->withCount('accounts')
            ->orderByDesc('is_active')
            ->orderBy('nama')
            ->get();

        return response()->json([
            'data' => $types->map(fn (JenisTabungan $type) => $this->formatTypeRow($type))->values(),
            'summary' => [
                'type_count' => (int) $types->count(),
                'active_count' => (int) $types->where('is_active', true)->count(),
                'account_count' => (int) $types->sum('accounts_count'),
            ],
            'can_manage_types' => $this->canManageTypes($user),
        ]);
    }

    public function rekeningData(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $this->canAccessManagementPage($user), 403);

        $filters = $this->accountFilters($request);
        $query = TabunganSiswaAccount::query()
            ->with([
                'siswa:id,nama,nisn,kelas',
                'jenisTabungan:id,kode,nama,is_active',
            ])
            ->withCount('transactions')
            ->withMax('transactions as latest_transaction_at', 'transacted_at');

        $this->applyAccountFilters($query, $filters, $user);

        $accounts = $query
            ->orderByDesc('is_active')
            ->orderBy('siswa_id')
            ->orderBy('jenis_tabungan_id')
            ->get();

        return response()->json([
            'data' => $accounts->map(fn (TabunganSiswaAccount $account) => $this->formatAccountRow($account))->values(),
            'summary' => [
                'account_count' => (int) $accounts->count(),
                'active_count' => (int) $accounts->where('is_active', true)->count(),
                'student_count' => (int) $accounts->pluck('siswa_id')->filter()->unique()->count(),
                'saldo_total' => (int) $accounts->sum('saldo_cached'),
            ],
            'students' => $this->studentOptions($user),
            'classes' => $this->classOptions($user),
            'types' => $this->typeOptions(),
            'accounts' => $this->accountOptions($user),
            'can_manage_accounts' => $this->canManageAccounts($user),
            'can_manage_transactions' => $this->canManageTransactions($user),
        ]);
    }

    public function rekeningHistory(Request $request, TabunganSiswaAccount $account): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $this->canAccessManagementPage($user), 403);

        $account->load([
            'siswa:id,nama,nisn,kelas',
            'jenisTabungan:id,kode,nama,is_active',
        ])->loadCount('transactions');
        abort_unless($this->canViewAccount($account, $user), 404);

        $transactions = TabunganSiswaTransaction::query()
            ->with([
                'account.siswa:id,nama,nisn,kelas',
                'account.jenisTabungan:id,kode,nama',
                'performedBy:id,name,username',
                'updatedBy:id,name,username',
            ])
            ->where('account_id', (int) $account->id)
            ->orderByDesc('transacted_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'account' => $this->formatAccountRow($account),
            'transactions' => $transactions->map(fn (TabunganSiswaTransaction $transaction) => $this->formatTransactionRow($transaction))->values(),
            'summary' => [
                'transaction_count' => (int) $transactions->count(),
                'saldo_akhir' => (int) $account->saldo_cached,
            ],
            'statement_period' => [
                'month' => now()->month,
                'year' => now()->year,
            ],
            'can_manage_transactions' => $this->canManageTransactions($user),
        ]);
    }

    public function transaksiData(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $this->canAccessManagementPage($user), 403);

        $filters = $this->transactionFilters($request);
        $query = TabunganSiswaTransaction::query()
            ->with([
                'account.siswa:id,nama,nisn,kelas',
                'account.jenisTabungan:id,kode,nama,is_active',
                'performedBy:id,name,username',
                'updatedBy:id,name,username',
            ]);

        $this->applyTransactionFilters($query, $filters, $user);

        $transactions = $query
            ->orderByDesc('transacted_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $transactions->map(fn (TabunganSiswaTransaction $transaction) => $this->formatTransactionRow($transaction))->values(),
            'summary' => [
                'transaction_count' => (int) $transactions->count(),
                'setoran_total' => (int) $transactions
                    ->whereIn('jenis_transaksi', [
                        TabunganSiswaTransaction::TYPE_SETORAN,
                        TabunganSiswaTransaction::TYPE_PENYESUAIAN_MASUK,
                    ])
                    ->sum('nominal'),
                'penarikan_total' => (int) $transactions
                    ->whereIn('jenis_transaksi', [
                        TabunganSiswaTransaction::TYPE_PENARIKAN,
                        TabunganSiswaTransaction::TYPE_PENYESUAIAN_KELUAR,
                    ])
                    ->sum('nominal'),
                'mutasi_bersih' => (int) $transactions->sum(fn (TabunganSiswaTransaction $transaction) => $transaction->signedAmount()),
            ],
            'students' => $this->studentOptions($user),
            'classes' => $this->classOptions($user),
            'types' => $this->typeOptions(),
            'accounts' => $this->accountOptions($user),
            'transaction_type_options' => $this->transactionTypeOptions(),
            'can_manage_transactions' => $this->canManageTransactions($user),
        ]);
    }

    public function selfIndex(Request $request): View
    {
        $user = $request->user();
        abort_unless($user && $user->hasRole('siswa') && $user->can('tabungan-siswa.self.view'), 403);

        $siswa = $this->resolveStudentFromUser($user);
        $accounts = collect();
        $recentTransactions = collect();
        $stats = [
            'account_count' => 0,
            'saldo_total' => 0,
            'setoran_bulan_ini' => 0,
            'penarikan_bulan_ini' => 0,
        ];

        if ($siswa) {
            $accounts = TabunganSiswaAccount::query()
                ->with([
                    'jenisTabungan:id,kode,nama',
                    'transactions' => fn ($query) => $query->latest('transacted_at')->latest('id')->limit(1),
                ])
                ->where('siswa_id', (int) $siswa->id)
                ->orderByDesc('saldo_cached')
                ->orderBy('jenis_tabungan_id')
                ->get();

            $recentTransactions = TabunganSiswaTransaction::query()
                ->with([
                    'account.jenisTabungan:id,kode,nama',
                    'performedBy:id,name,username',
                ])
                ->whereHas('account', fn (Builder $query) => $query->where('siswa_id', (int) $siswa->id))
                ->orderByDesc('transacted_at')
                ->orderByDesc('id')
                ->limit(30)
                ->get();

            $monthStart = now()->startOfMonth();
            $monthEnd = now()->endOfMonth();
            $monthlyTransactions = TabunganSiswaTransaction::query()
                ->whereHas('account', fn (Builder $query) => $query->where('siswa_id', (int) $siswa->id))
                ->whereBetween('transacted_at', [$monthStart, $monthEnd])
                ->get();

            $stats = [
                'account_count' => (int) $accounts->count(),
                'saldo_total' => (int) $accounts->sum('saldo_cached'),
                'setoran_bulan_ini' => (int) $monthlyTransactions
                    ->whereIn('jenis_transaksi', [
                        TabunganSiswaTransaction::TYPE_SETORAN,
                        TabunganSiswaTransaction::TYPE_PENYESUAIAN_MASUK,
                    ])
                    ->sum('nominal'),
                'penarikan_bulan_ini' => (int) $monthlyTransactions
                    ->whereIn('jenis_transaksi', [
                        TabunganSiswaTransaction::TYPE_PENARIKAN,
                        TabunganSiswaTransaction::TYPE_PENYESUAIAN_KELUAR,
                    ])
                    ->sum('nominal'),
            ];
        }

        return view('pages.tabungan-saya', [
            'siswa' => $siswa,
            'accounts' => $accounts,
            'recentTransactions' => $recentTransactions,
            'stats' => $stats,
            'transactionTypeOptions' => $this->transactionTypeOptions(),
        ]);
    }

    public function storeType(Request $request): JsonResponse
    {
        abort_unless($this->canManageTypes($request->user()), 403);

        $validated = $this->validateTypePayload($request);
        $type = JenisTabungan::query()->create($validated);

        return response()->json([
            'message' => 'Jenis tabungan berhasil ditambahkan.',
            'data' => $this->formatTypeRow($type->loadCount('accounts')),
        ]);
    }

    public function updateType(Request $request, JenisTabungan $jenisTabungan): JsonResponse
    {
        abort_unless($this->canManageTypes($request->user()), 403);

        $validated = $this->validateTypePayload($request, $jenisTabungan);
        $jenisTabungan->update($validated);

        return response()->json([
            'message' => 'Jenis tabungan berhasil diperbarui.',
            'data' => $this->formatTypeRow($jenisTabungan->fresh()->loadCount('accounts')),
        ]);
    }

    public function destroyType(Request $request, JenisTabungan $jenisTabungan): JsonResponse
    {
        abort_unless($this->canManageTypes($request->user()), 403);

        if ($jenisTabungan->accounts()->exists()) {
            throw ValidationException::withMessages([
                'jenis_tabungan' => 'Jenis tabungan yang sudah dipakai rekening tidak bisa dihapus. Nonaktifkan saja.',
            ]);
        }

        $jenisTabungan->delete();

        return response()->json([
            'message' => 'Jenis tabungan berhasil dihapus.',
        ]);
    }

    public function storeAccount(Request $request, TabunganSiswaService $service): JsonResponse
    {
        abort_unless($this->canManageAccounts($request->user()), 403);

        $validated = $this->validateAccountCreatePayload($request);
        $account = $service->createAccount($validated, $request->user());

        return response()->json([
            'message' => 'Rekening tabungan berhasil ditambahkan.',
            'data' => $this->formatAccountRow($account->loadCount('transactions')),
        ]);
    }

    public function updateAccount(
        Request $request,
        TabunganSiswaAccount $account,
        TabunganSiswaService $service
    ): JsonResponse {
        abort_unless($this->canManageAccounts($request->user()), 403);
        abort_unless($this->canViewAccount($account, $request->user()), 404);

        $validated = $this->validateAccountUpdatePayload($request, $account);
        $updated = $service->updateAccount($account, $validated);

        return response()->json([
            'message' => 'Rekening tabungan berhasil diperbarui.',
            'data' => $this->formatAccountRow($updated->loadCount('transactions')),
        ]);
    }

    public function destroyAccount(
        Request $request,
        TabunganSiswaAccount $account,
        TabunganSiswaService $service
    ): JsonResponse {
        abort_unless($this->canManageAccounts($request->user()), 403);
        abort_unless($this->canViewAccount($account, $request->user()), 404);

        $service->deleteAccount($account);

        return response()->json([
            'message' => 'Rekening tabungan berhasil dihapus.',
        ]);
    }

    public function storeTransaction(Request $request, TabunganSiswaService $service): JsonResponse
    {
        abort_unless($this->canManageTransactions($request->user()), 403);

        $validated = $this->validateTransactionPayload($request);
        $account = TabunganSiswaAccount::query()->findOrFail((int) $validated['account_id']);
        abort_unless($this->canViewAccount($account, $request->user()), 404);

        $transaction = $service->createTransaction($validated, $request->user());

        return response()->json([
            'message' => 'Transaksi tabungan berhasil disimpan.',
            'data' => $this->formatTransactionRow($transaction),
        ]);
    }

    public function updateTransaction(
        Request $request,
        TabunganSiswaTransaction $transaction,
        TabunganSiswaService $service
    ): JsonResponse {
        abort_unless($this->canManageTransactions($request->user()), 403);
        abort_unless($this->canViewTransaction($transaction, $request->user()), 404);

        $validated = $this->validateTransactionPayload($request, $transaction);
        $account = TabunganSiswaAccount::query()->findOrFail((int) $validated['account_id']);
        abort_unless($this->canViewAccount($account, $request->user()), 404);

        $updated = $service->updateTransaction($transaction, $validated, $request->user());

        return response()->json([
            'message' => 'Transaksi tabungan berhasil diperbarui.',
            'data' => $this->formatTransactionRow($updated),
        ]);
    }

    public function destroyTransaction(
        Request $request,
        TabunganSiswaTransaction $transaction,
        TabunganSiswaService $service
    ): JsonResponse {
        abort_unless($this->canManageTransactions($request->user()), 403);
        abort_unless($this->canViewTransaction($transaction, $request->user()), 404);

        $validated = $request->validate([
            'delete_reason' => ['required', 'string', 'max:1000'],
        ]);

        $service->deleteTransaction(
            $transaction,
            $request->user(),
            trim((string) $validated['delete_reason'])
        );

        return response()->json([
            'message' => 'Transaksi tabungan berhasil dihapus.',
        ]);
    }

    public function printTransaction(Request $request, TabunganSiswaTransaction $transaction): Response
    {
        $user = $request->user();
        $transaction->load([
            'account.siswa:id,nama,nisn,kelas',
            'account.jenisTabungan:id,kode,nama',
            'performedBy:id,name,username',
            'updatedBy:id,name,username',
        ]);

        abort_unless($this->canPrintTransaction($transaction, $user), 403);

        $html = view('pages.tabungan-siswa-print', [
            'transaction' => $transaction,
            'printedAt' => now(),
            'printedBy' => trim((string) ($user?->name ?: ($user?->username ?? ''))),
        ])->render();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A5', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        $filename = sprintf(
            'bukti-transaksi-%s.pdf',
            preg_replace('/[^0-9A-Za-z_-]+/', '-', (string) ($transaction->nomor_bukti ?? 'transaksi'))
        );

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    public function printAccountStatement(Request $request, TabunganSiswaAccount $account): Response
    {
        $user = $request->user();
        abort_unless($this->canPrintAccountStatement($account, $user), 403);

        $account->load([
            'siswa:id,nama,nisn,kelas',
            'jenisTabungan:id,kode,nama,is_active',
        ])->loadCount('transactions');

        $now = now();
        $selectedMonth = (int) $request->query('month', $now->month);
        $selectedYear = (int) $request->query('year', $now->year);

        if ($selectedMonth < 1 || $selectedMonth > 12) {
            $selectedMonth = (int) $now->month;
        }

        if ($selectedYear < 2000 || $selectedYear > 2100) {
            $selectedYear = (int) $now->year;
        }

        if ($selectedYear > (int) $now->year || (
            $selectedYear === (int) $now->year
            && $selectedMonth > (int) $now->month
        )) {
            $selectedMonth = (int) $now->month;
            $selectedYear = (int) $now->year;
        }

        $periodStart = Carbon::create(
            $selectedYear,
            $selectedMonth,
            1,
            0,
            0,
            0,
            config('app.timezone', 'Asia/Jakarta')
        )->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();

        $transactions = TabunganSiswaTransaction::query()
            ->with([
                'performedBy:id,name,username',
                'updatedBy:id,name,username',
            ])
            ->where('account_id', (int) $account->id)
            ->whereBetween('transacted_at', [$periodStart, $periodEnd])
            ->orderBy('transacted_at')
            ->orderBy('id')
            ->get();

        $lastTransactionBeforePeriod = TabunganSiswaTransaction::query()
            ->where('account_id', (int) $account->id)
            ->where('transacted_at', '<', $periodStart)
            ->orderByDesc('transacted_at')
            ->orderByDesc('id')
            ->first();

        $statementRows = $transactions->map(function (TabunganSiswaTransaction $transaction): array {
            $typeLabel = $this->transactionTypeOptions()[$transaction->jenis_transaksi]
                ?? ucfirst(str_replace('_', ' ', (string) $transaction->jenis_transaksi));
            $operatorName = trim((string) ($transaction->performedBy?->name ?: ($transaction->performedBy?->username ?? '-')));
            $editorName = trim((string) ($transaction->updatedBy?->name ?: ($transaction->updatedBy?->username ?? '')));

            return [
                'tanggal' => $transaction->transacted_at?->format('d M Y H:i') ?: '-',
                'nomor_bukti' => (string) ($transaction->nomor_bukti ?? '-'),
                'mutasi_label' => $typeLabel,
                'keterangan' => (string) ($transaction->keterangan ?? ''),
                'debit' => $transaction->signedAmount() < 0 ? (int) $transaction->nominal : 0,
                'kredit' => $transaction->signedAmount() >= 0 ? (int) $transaction->nominal : 0,
                'saldo' => (int) $transaction->saldo_sesudah,
                'operator' => $operatorName,
                'editor' => $editorName,
            ];
        })->values();

        $saldoAwal = (int) ($lastTransactionBeforePeriod?->saldo_sesudah ?? 0);
        $saldoAkhir = (int) ($transactions->last()?->saldo_sesudah ?? $saldoAwal);
        $totalDebit = (int) $transactions
            ->whereIn('jenis_transaksi', [
                TabunganSiswaTransaction::TYPE_PENARIKAN,
                TabunganSiswaTransaction::TYPE_PENYESUAIAN_KELUAR,
            ])
            ->sum('nominal');
        $totalKredit = (int) $transactions
            ->whereIn('jenis_transaksi', [
                TabunganSiswaTransaction::TYPE_SETORAN,
                TabunganSiswaTransaction::TYPE_PENYESUAIAN_MASUK,
                    ])
                    ->sum('nominal');

        $html = view('pages.tabungan-siswa-rekening-koran-pdf', [
            'account' => $account,
            'statementRows' => $statementRows,
            'saldoAwal' => $saldoAwal,
            'saldoAkhir' => $saldoAkhir,
            'totalDebit' => $totalDebit,
            'totalKredit' => $totalKredit,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'selectedMonth' => $selectedMonth,
            'selectedYear' => $selectedYear,
            'printedAt' => now(),
            'printedBy' => trim((string) ($user->name ?: $user->username)),
        ])->render();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        $filename = sprintf(
            'rekening-koran-%s-%s.pdf',
            preg_replace('/[^0-9A-Za-z_-]+/', '-', (string) ($account->nomor_rekening ?? 'rekening')),
            now()->format('YmdHis')
        );

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateTypePayload(Request $request, ?JenisTabungan $jenisTabungan = null): array
    {
        $validated = $request->validate([
            'kode' => [
                'required',
                'string',
                'max:50',
                Rule::unique('jenis_tabungan', 'kode')->ignore($jenisTabungan?->id),
            ],
            'nama' => ['required', 'string', 'max:150'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'kode' => strtoupper(trim((string) $validated['kode'])),
            'nama' => trim((string) $validated['nama']),
            'deskripsi' => $this->nullableText($validated['deskripsi'] ?? null),
            'is_active' => $request->boolean('is_active', true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateAccountCreatePayload(Request $request): array
    {
        $validated = $request->validate([
            'siswa_id' => ['required', 'integer', Rule::exists('siswa', 'id')],
            'jenis_tabungan_id' => ['required', 'integer', Rule::exists('jenis_tabungan', 'id')],
            'opened_at' => ['nullable', 'date'],
            'setoran_awal' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $exists = TabunganSiswaAccount::query()
            ->where('siswa_id', (int) $validated['siswa_id'])
            ->where('jenis_tabungan_id', (int) $validated['jenis_tabungan_id'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'jenis_tabungan_id' => 'Rekening untuk siswa dan jenis tabungan tersebut sudah ada.',
            ]);
        }

        return [
            'siswa_id' => (int) $validated['siswa_id'],
            'jenis_tabungan_id' => (int) $validated['jenis_tabungan_id'],
            'opened_at' => $validated['opened_at'] ?? now()->toDateString(),
            'setoran_awal' => (int) ($validated['setoran_awal'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateAccountUpdatePayload(Request $request, TabunganSiswaAccount $account): array
    {
        $validated = $request->validate([
            'opened_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'opened_at' => $validated['opened_at'] ?? optional($account->opened_at)->toDateString() ?? now()->toDateString(),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateTransactionPayload(
        Request $request,
        ?TabunganSiswaTransaction $transaction = null
    ): array
    {
        $validated = $request->validate([
            'account_id' => ['required', 'integer', Rule::exists('tabungan_siswa_accounts', 'id')],
            'jenis_transaksi' => ['required', Rule::in(array_keys($this->transactionTypeOptions()))],
            'nominal' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'transacted_at' => ['nullable', 'date'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
        ]);

        return [
            'account_id' => (int) $validated['account_id'],
            'jenis_transaksi' => (string) $validated['jenis_transaksi'],
            'nominal' => (int) $validated['nominal'],
            'transacted_at' => (string) (
                $validated['transacted_at']
                ?? $transaction?->transacted_at?->toDateTimeString()
                ?? now()->toDateTimeString()
            ),
            'keterangan' => $this->nullableText($validated['keterangan'] ?? null),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function accountFilters(Request $request): array
    {
        return [
            'kelas' => trim((string) $request->query('kelas', '')),
            'jenis_tabungan_id' => trim((string) $request->query('jenis_tabungan_id', '')),
            'status' => trim((string) $request->query('status', '')),
            'q' => trim((string) $request->query('q', '')),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function transactionFilters(Request $request): array
    {
        return [
            'kelas' => trim((string) $request->query('kelas', '')),
            'siswa_id' => trim((string) $request->query('siswa_id', '')),
            'jenis_tabungan_id' => trim((string) $request->query('jenis_tabungan_id', '')),
            'account_id' => trim((string) $request->query('account_id', '')),
            'jenis_transaksi' => trim((string) $request->query('jenis_transaksi', '')),
            'tanggal_dari' => trim((string) $request->query('tanggal_dari', '')),
            'tanggal_sampai' => trim((string) $request->query('tanggal_sampai', '')),
            'q' => trim((string) $request->query('q', '')),
        ];
    }

    protected function applyAccountFilters(Builder $query, array $filters, User $user): void
    {
        if ($this->isScopedWakel($user)) {
            $wakelClass = trim((string) ($user->kelas ?? ''));
            if ($wakelClass !== '') {
                $query->whereHas('siswa', fn (Builder $siswaQuery) => $siswaQuery->where('kelas', $wakelClass));
            }
        }

        if ((int) ($filters['jenis_tabungan_id'] ?? 0) > 0) {
            $query->where('jenis_tabungan_id', (int) $filters['jenis_tabungan_id']);
        }

        if (trim((string) ($filters['kelas'] ?? '')) !== '') {
            $kelas = trim((string) $filters['kelas']);
            $query->whereHas('siswa', fn (Builder $siswaQuery) => $siswaQuery->where('kelas', $kelas));
        }

        if (in_array(($filters['status'] ?? ''), ['active', 'inactive'], true)) {
            $query->where('is_active', ($filters['status'] ?? '') === 'active');
        }

        if (trim((string) ($filters['q'] ?? '')) !== '') {
            $keyword = trim((string) $filters['q']);
            $query->where(function (Builder $inner) use ($keyword): void {
                $inner->where('nomor_rekening', 'like', '%' . $keyword . '%')
                    ->orWhereHas('siswa', function (Builder $siswaQuery) use ($keyword): void {
                        $siswaQuery->where('nama', 'like', '%' . $keyword . '%')
                            ->orWhere('nisn', 'like', '%' . $keyword . '%')
                            ->orWhere('kelas', 'like', '%' . $keyword . '%');
                    })
                    ->orWhereHas('jenisTabungan', function (Builder $typeQuery) use ($keyword): void {
                        $typeQuery->where('nama', 'like', '%' . $keyword . '%')
                            ->orWhere('kode', 'like', '%' . $keyword . '%');
                    });
            });
        }
    }

    protected function applyTransactionFilters(Builder $query, array $filters, User $user): void
    {
        if ($this->isScopedWakel($user)) {
            $wakelClass = trim((string) ($user->kelas ?? ''));
            if ($wakelClass !== '') {
                $query->whereHas('account.siswa', fn (Builder $siswaQuery) => $siswaQuery->where('kelas', $wakelClass));
            }
        }

        if ((int) ($filters['account_id'] ?? 0) > 0) {
            $query->where('account_id', (int) $filters['account_id']);
        }

        if ((int) ($filters['siswa_id'] ?? 0) > 0) {
            $siswaId = (int) $filters['siswa_id'];
            $query->whereHas('account', fn (Builder $accountQuery) => $accountQuery->where('siswa_id', $siswaId));
        }

        if ((int) ($filters['jenis_tabungan_id'] ?? 0) > 0) {
            $jenisId = (int) $filters['jenis_tabungan_id'];
            $query->whereHas('account', fn (Builder $accountQuery) => $accountQuery->where('jenis_tabungan_id', $jenisId));
        }

        if (trim((string) ($filters['kelas'] ?? '')) !== '') {
            $kelas = trim((string) $filters['kelas']);
            $query->whereHas('account.siswa', fn (Builder $siswaQuery) => $siswaQuery->where('kelas', $kelas));
        }

        if (trim((string) ($filters['jenis_transaksi'] ?? '')) !== '') {
            $query->where('jenis_transaksi', trim((string) $filters['jenis_transaksi']));
        }

        if (trim((string) ($filters['tanggal_dari'] ?? '')) !== '') {
            $query->whereDate('transacted_at', '>=', trim((string) $filters['tanggal_dari']));
        }

        if (trim((string) ($filters['tanggal_sampai'] ?? '')) !== '') {
            $query->whereDate('transacted_at', '<=', trim((string) $filters['tanggal_sampai']));
        }

        if (trim((string) ($filters['q'] ?? '')) !== '') {
            $keyword = trim((string) $filters['q']);
            $query->where(function (Builder $inner) use ($keyword): void {
                $inner->where('nomor_bukti', 'like', '%' . $keyword . '%')
                    ->orWhere('keterangan', 'like', '%' . $keyword . '%')
                    ->orWhereHas('account', function (Builder $accountQuery) use ($keyword): void {
                        $accountQuery->where('nomor_rekening', 'like', '%' . $keyword . '%')
                            ->orWhereHas('siswa', function (Builder $siswaQuery) use ($keyword): void {
                                $siswaQuery->where('nama', 'like', '%' . $keyword . '%')
                                    ->orWhere('nisn', 'like', '%' . $keyword . '%')
                                    ->orWhere('kelas', 'like', '%' . $keyword . '%');
                            })
                            ->orWhereHas('jenisTabungan', function (Builder $typeQuery) use ($keyword): void {
                                $typeQuery->where('nama', 'like', '%' . $keyword . '%')
                                    ->orWhere('kode', 'like', '%' . $keyword . '%');
                            });
                    });
            });
        }
    }

    protected function studentFilterQuery(User $user): Builder
    {
        $query = Siswa::query()
            ->orderBy('kelas')
            ->orderBy('nama');

        if ($this->isScopedWakel($user)) {
            $wakelClass = trim((string) ($user->kelas ?? ''));
            if ($wakelClass !== '') {
                $query->where('kelas', $wakelClass);
            }
        }

        return $query;
    }

    protected function classOptions(User $user): array
    {
        $query = Kelas::query()->orderBy('nama');

        if ($this->isScopedWakel($user)) {
            $wakelClass = trim((string) ($user->kelas ?? ''));
            if ($wakelClass !== '') {
                $query->where('nama', $wakelClass);
            }
        }

        return $query->get(['id', 'nama'])
            ->map(fn (Kelas $kelas) => [
                'id' => (int) $kelas->id,
                'nama' => (string) $kelas->nama,
            ])
            ->values()
            ->all();
    }

    protected function studentOptions(User $user): array
    {
        return $this->studentFilterQuery($user)
            ->get(['id', 'nama', 'nisn', 'kelas'])
            ->map(fn (Siswa $siswa) => [
                'id' => (int) $siswa->id,
                'nama' => (string) $siswa->nama,
                'nisn' => (string) $siswa->nisn,
                'kelas' => (string) ($siswa->kelas ?? ''),
                'label' => trim((string) $siswa->nama) . ' - ' . trim((string) ($siswa->kelas ?? '-')) . ' (' . trim((string) $siswa->nisn) . ')',
            ])
            ->values()
            ->all();
    }

    protected function typeOptions(): array
    {
        return JenisTabungan::query()
            ->orderByDesc('is_active')
            ->orderBy('nama')
            ->get(['id', 'kode', 'nama', 'is_active'])
            ->map(fn (JenisTabungan $type) => [
                'id' => (int) $type->id,
                'kode' => (string) $type->kode,
                'nama' => (string) $type->nama,
                'is_active' => (bool) $type->is_active,
                'label' => trim((string) $type->nama) . ' (' . trim((string) $type->kode) . ')',
            ])
            ->values()
            ->all();
    }

    protected function accountOptions(User $user): array
    {
        $query = TabunganSiswaAccount::query()
            ->with([
                'siswa:id,nama,nisn,kelas',
                'jenisTabungan:id,kode,nama,is_active',
            ])
            ->orderByDesc('is_active')
            ->orderBy('nomor_rekening');

        if ($this->isScopedWakel($user)) {
            $wakelClass = trim((string) ($user->kelas ?? ''));
            if ($wakelClass !== '') {
                $query->whereHas('siswa', fn (Builder $siswaQuery) => $siswaQuery->where('kelas', $wakelClass));
            }
        }

        return $query->get()
            ->map(fn (TabunganSiswaAccount $account) => [
                'id' => (int) $account->id,
                'nomor_rekening' => (string) ($account->nomor_rekening ?? ''),
                'siswa_id' => (int) $account->siswa_id,
                'siswa_nama' => (string) ($account->siswa?->nama ?? '-'),
                'siswa_nisn' => (string) ($account->siswa?->nisn ?? '-'),
                'kelas' => (string) ($account->siswa?->kelas ?? '-'),
                'jenis_tabungan_id' => (int) ($account->jenis_tabungan_id ?? 0),
                'jenis_tabungan' => (string) ($account->jenisTabungan?->nama ?? '-'),
                'is_active' => (bool) $account->is_active,
                'label' => trim((string) ($account->nomor_rekening ?? '-'))
                    . ' - ' . trim((string) ($account->siswa?->nama ?? '-'))
                    . ' - ' . trim((string) ($account->jenisTabungan?->nama ?? '-')),
            ])
            ->values()
            ->all();
    }

    protected function canAccessManagementPage(?User $user): bool
    {
        return (bool) ($user && $user->hasAnyPermission([
            'tabungan-siswa.view',
            'tabungan-siswa.manage',
            'tabungan-siswa.report',
            'tabungan-siswa.jenis.manage',
        ]));
    }

    protected function canManageTransactions(?User $user): bool
    {
        return (bool) ($user && $user->can('tabungan-siswa.manage'));
    }

    protected function canManageAccounts(?User $user): bool
    {
        return $this->canManageTransactions($user);
    }

    protected function canManageTypes(?User $user): bool
    {
        return (bool) ($user && $user->can('tabungan-siswa.jenis.manage'));
    }

    protected function isScopedWakel(?User $user): bool
    {
        return (bool) ($user
            && $user->hasRole('wakel')
            && !$user->hasAnyRole(['super-admin', 'admin', 'bendahara', 'kepsek', 'wakasek']));
    }

    protected function canViewAccount(TabunganSiswaAccount $account, ?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($this->isScopedWakel($user)) {
            return trim((string) ($account->siswa?->kelas ?? '')) === trim((string) ($user->kelas ?? ''));
        }

        return $this->canAccessManagementPage($user);
    }

    protected function canViewTransaction(TabunganSiswaTransaction $transaction, ?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($this->isScopedWakel($user)) {
            return trim((string) ($transaction->account?->siswa?->kelas ?? '')) === trim((string) ($user->kelas ?? ''));
        }

        return $this->canAccessManagementPage($user);
    }

    protected function canPrintTransaction(TabunganSiswaTransaction $transaction, ?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->hasRole('siswa')) {
            $siswa = $this->resolveStudentFromUser($user);

            return $siswa
                && $user->can('tabungan-siswa.self.view')
                && (int) ($transaction->account?->siswa_id ?? 0) === (int) $siswa->id;
        }

        return $this->canViewTransaction($transaction, $user);
    }

    protected function canPrintAccountStatement(TabunganSiswaAccount $account, ?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->hasRole('siswa')) {
            $siswa = $this->resolveStudentFromUser($user);

            return $siswa
                && $user->can('tabungan-siswa.self.view')
                && (int) ($account->siswa_id ?? 0) === (int) $siswa->id;
        }

        return $this->canViewAccount($account, $user);
    }

    protected function resolveStudentFromUser(User $user): ?Siswa
    {
        return Siswa::query()
            ->where('nisn', trim((string) $user->username))
            ->first();
    }

    /**
     * @return array<string, string>
     */
    protected function transactionTypeOptions(): array
    {
        return [
            TabunganSiswaTransaction::TYPE_SETORAN => 'Setoran',
            TabunganSiswaTransaction::TYPE_PENARIKAN => 'Penarikan',
            TabunganSiswaTransaction::TYPE_PENYESUAIAN_MASUK => 'Penyesuaian Masuk',
            TabunganSiswaTransaction::TYPE_PENYESUAIAN_KELUAR => 'Penyesuaian Keluar',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatTypeRow(JenisTabungan $type): array
    {
        return [
            'id' => (int) $type->id,
            'kode' => (string) $type->kode,
            'nama' => (string) $type->nama,
            'deskripsi' => (string) ($type->deskripsi ?? ''),
            'is_active' => (bool) $type->is_active,
            'accounts_count' => (int) ($type->accounts_count ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatAccountRow(TabunganSiswaAccount $account): array
    {
        return [
            'id' => (int) $account->id,
            'nomor_rekening' => (string) ($account->nomor_rekening ?? '-'),
            'siswa_id' => (int) ($account->siswa_id ?? 0),
            'siswa_nama' => (string) ($account->siswa?->nama ?? '-'),
            'siswa_nisn' => (string) ($account->siswa?->nisn ?? '-'),
            'kelas' => (string) ($account->siswa?->kelas ?? '-'),
            'jenis_tabungan_id' => (int) ($account->jenis_tabungan_id ?? 0),
            'jenis_tabungan' => (string) ($account->jenisTabungan?->nama ?? '-'),
            'jenis_tabungan_kode' => (string) ($account->jenisTabungan?->kode ?? '-'),
            'saldo_cached' => (int) ($account->saldo_cached ?? 0),
            'is_active' => (bool) $account->is_active,
            'opened_at' => $account->opened_at?->format('Y-m-d'),
            'opened_at_label' => $account->opened_at?->format('d M Y'),
            'transactions_count' => (int) ($account->transactions_count ?? 0),
            'latest_transaction_at' => $this->formatDateTime($account->latest_transaction_at ?? null),
            'can_delete' => (int) ($account->transactions_count ?? 0) === 0 && (int) ($account->saldo_cached ?? 0) === 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatTransactionRow(TabunganSiswaTransaction $transaction): array
    {
        $transaction->loadMissing([
            'account.siswa:id,nama,nisn,kelas',
            'account.jenisTabungan:id,kode,nama',
            'performedBy:id,name,username',
            'updatedBy:id,name,username',
        ]);

        return [
            'id' => (int) $transaction->id,
            'account_id' => (int) ($transaction->account_id ?? 0),
            'nomor_bukti' => (string) ($transaction->nomor_bukti ?? ''),
            'nomor_rekening' => (string) ($transaction->account?->nomor_rekening ?? '-'),
            'siswa_id' => (int) ($transaction->account?->siswa_id ?? 0),
            'siswa_nama' => (string) ($transaction->account?->siswa?->nama ?? '-'),
            'siswa_nisn' => (string) ($transaction->account?->siswa?->nisn ?? '-'),
            'kelas' => (string) ($transaction->account?->siswa?->kelas ?? '-'),
            'jenis_tabungan_id' => (int) ($transaction->account?->jenis_tabungan_id ?? 0),
            'jenis_tabungan' => (string) ($transaction->account?->jenisTabungan?->nama ?? '-'),
            'jenis_tabungan_kode' => (string) ($transaction->account?->jenisTabungan?->kode ?? '-'),
            'jenis_transaksi' => (string) $transaction->jenis_transaksi,
            'jenis_transaksi_label' => $this->transactionTypeOptions()[$transaction->jenis_transaksi] ?? ucfirst(str_replace('_', ' ', (string) $transaction->jenis_transaksi)),
            'nominal' => (int) $transaction->nominal,
            'signed_nominal' => (int) $transaction->signedAmount(),
            'saldo_sebelum' => (int) $transaction->saldo_sebelum,
            'saldo_sesudah' => (int) $transaction->saldo_sesudah,
            'keterangan' => (string) ($transaction->keterangan ?? ''),
            'transacted_at' => $transaction->transacted_at?->format('Y-m-d H:i:s'),
            'transacted_at_label' => $transaction->transacted_at?->format('d M Y H:i'),
            'performed_by' => (string) ($transaction->performedBy?->name ?: ($transaction->performedBy?->username ?? '-')),
            'updated_by' => (string) ($transaction->updatedBy?->name ?: ($transaction->updatedBy?->username ?? '')),
            'print_url' => route('tabungan-siswa.transaksi.print', $transaction),
        ];
    }

    protected function formatDateTime(mixed $value): ?string
    {
        if ($value instanceof Carbon) {
            return $value->format('d M Y H:i');
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value)->format('d M Y H:i');
            } catch (\Throwable $e) {
                return trim($value);
            }
        }

        return null;
    }

    protected function nullableText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
