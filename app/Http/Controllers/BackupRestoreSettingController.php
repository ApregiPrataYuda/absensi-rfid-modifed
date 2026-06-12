<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupRestoreSettingController extends Controller
{
    public function index(): View
    {
        $connection = (string) config('database.default', 'mysql');
        $driver = (string) config("database.connections.$connection.driver", '');
        $database = (string) config("database.connections.$connection.database", '');

        return view('pages.settings-backup', [
            'connection' => $connection,
            'driver' => $driver,
            'database' => $database,
        ]);
    }

    public function download(Request $request): StreamedResponse
    {
        $connection = (string) config('database.default', 'mysql');
        $driver = (string) config("database.connections.$connection.driver", '');
        $database = (string) config("database.connections.$connection.database", '');

        if ($driver !== 'mysql') {
            abort(400, 'Fitur backup hanya mendukung database MySQL.');
        }

        if ($database === '') {
            abort(400, 'Database belum dikonfigurasi.');
        }

        $safeDatabase = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $database) ?: 'database';
        $fileName = 'backup_' . $safeDatabase . '_' . now()->format('Ymd_His') . '.sql';

        return response()->streamDownload(function () use ($connection, $database): void {
            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }
            if (function_exists('ignore_user_abort')) {
                @ignore_user_abort(true);
            }

            $conn = DB::connection($connection);
            $pdo = $conn->getPdo();
            $charset = (string) config("database.connections.$connection.charset", 'utf8mb4');

            echo 'SET NAMES ' . $charset . ";\n";
            echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

            $tables = $this->listMysqlTables($connection, $database);

            foreach ($tables as $table) {
                $escapedTable = $this->escapeIdentifier($table);

                echo 'DROP TABLE IF EXISTS `' . $escapedTable . "`;\n";
                echo $this->getMysqlCreateTableSql($connection, $table) . ";\n\n";

                $columns = $this->listMysqlColumns($connection, $table);
                if ($columns === []) {
                    continue;
                }

                $escapedColumns = array_map(fn (string $col): string => '`' . $this->escapeIdentifier($col) . '`', $columns);
                $insertPrefix = 'INSERT INTO `' . $escapedTable . '` (' . implode(', ', $escapedColumns) . ') VALUES';

                $batch = [];
                $batchSize = 200;

                foreach ($conn->table($table)->cursor() as $row) {
                    $values = [];
                    foreach ($columns as $column) {
                        $values[] = $this->quoteValue($pdo, $row->{$column} ?? null);
                    }

                    $batch[] = '(' . implode(', ', $values) . ')';

                    if (count($batch) >= $batchSize) {
                        echo $insertPrefix . "\n" . implode(",\n", $batch) . ";\n";
                        $batch = [];
                    }
                }

                if ($batch !== []) {
                    echo $insertPrefix . "\n" . implode(",\n", $batch) . ";\n";
                }

                echo "\n";

                if (function_exists('ob_flush')) {
                    @ob_flush();
                }
                if (function_exists('flush')) {
                    @flush();
                }
            }

            echo "SET FOREIGN_KEY_CHECKS=1;\n";
        }, $fileName, [
            'Content-Type' => 'application/sql; charset=UTF-8',
        ]);
    }

    public function restore(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'backup_file' => ['required', 'file', 'mimes:sql,txt', 'max:51200'],
            'confirm_restore' => ['required', 'accepted'],
        ], [
            'confirm_restore.accepted' => 'Silakan centang konfirmasi sebelum melakukan restore.',
        ]);

        $connection = (string) config('database.default', 'mysql');
        $driver = (string) config("database.connections.$connection.driver", '');
        $database = (string) config("database.connections.$connection.database", '');

        if ($driver !== 'mysql') {
            return $this->restoreErrorResponse($request, 'Fitur restore hanya mendukung database MySQL.');
        }

        if ($database === '') {
            return $this->restoreErrorResponse($request, 'Database belum dikonfigurasi.');
        }

        $file = $request->file('backup_file');
        $path = $file?->getRealPath();

        if (!$path || !is_file($path)) {
            return $this->restoreErrorResponse($request, 'File backup tidak valid atau tidak dapat dibaca.');
        }

        try {
            try {
                if (($request->expectsJson() || $request->ajax()) && $request->hasSession()) {
                    $request->session()->save();
                }
                if (($request->expectsJson() || $request->ajax()) && function_exists('session_write_close')) {
                    @session_write_close();
                }
            } catch (\Throwable $e) {
                // ignore
            }

            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }
            if (function_exists('ignore_user_abort')) {
                @ignore_user_abort(true);
            }

            $executed = $this->importMysqlSqlFile($connection, $database, $path);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Restore database berhasil.',
                    'data' => [
                        'statements_executed' => $executed,
                    ],
                ]);
            }

            return back()->with('success', 'Restore database berhasil.');
        } catch (\Throwable $e) {
            report($e);

            $message = 'Restore gagal: ' . $this->safeErrorMessage($e);
            return $this->restoreErrorResponse($request, $message);
        }
    }

    /**
     * @return array<int, string>
     */
    protected function listMysqlTables(string $connection, string $database): array
    {
        $rows = DB::connection($connection)->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");

        $tables = [];
        foreach ($rows as $row) {
            $values = array_values((array) $row);
            $table = trim((string) ($values[0] ?? ''));

            if ($table !== '') {
                $tables[] = $table;
            }
        }

        sort($tables);

        return $tables;
    }

    protected function getMysqlCreateTableSql(string $connection, string $table): string
    {
        $escapedTable = $this->escapeIdentifier($table);
        $row = DB::connection($connection)->selectOne('SHOW CREATE TABLE `' . $escapedTable . '`');

        if (!$row) {
            return '';
        }

        $sql = (string) ($row->{'Create Table'} ?? '');

        return trim($sql);
    }

    /**
     * @return array<int, string>
     */
    protected function listMysqlColumns(string $connection, string $table): array
    {
        $escapedTable = $this->escapeIdentifier($table);
        $rows = DB::connection($connection)->select('SHOW COLUMNS FROM `' . $escapedTable . '`');

        $columns = [];
        foreach ($rows as $row) {
            $field = trim((string) ($row->Field ?? ''));
            if ($field !== '') {
                $columns[] = $field;
            }
        }

        return $columns;
    }

    protected function quoteValue(\PDO $pdo, $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return (string) $pdo->quote((string) $value);
    }

    protected function escapeIdentifier(string $identifier): string
    {
        return str_replace('`', '``', $identifier);
    }

    protected function restoreErrorResponse(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 422);
        }

        return back()->withErrors([$message]);
    }

    protected function safeErrorMessage(\Throwable $e): string
    {
        $message = trim((string) $e->getMessage());

        return $message !== '' ? $message : 'Terjadi kesalahan.';
    }

    protected function importMysqlSqlFile(string $connection, string $database, string $path): int
    {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            throw new \RuntimeException('Tidak dapat membuka file backup.');
        }

        $conn = DB::connection($connection);
        $conn->unprepared('SET FOREIGN_KEY_CHECKS=0');

        $executed = 0;
        $statement = '';

        $inSingleQuote = false;
        $inDoubleQuote = false;
        $inBacktick = false;
        $inLineComment = false;
        $inBlockComment = false;
        $escaped = false;

        try {
            while (!feof($handle)) {
                $chunk = fread($handle, 1024 * 1024);
                if ($chunk === false || $chunk === '') {
                    continue;
                }

                $length = strlen($chunk);
                for ($i = 0; $i < $length; $i++) {
                    $char = $chunk[$i];
                    $next = $i + 1 < $length ? $chunk[$i + 1] : null;

                    if ($inLineComment) {
                        if ($char === "\n") {
                            $inLineComment = false;
                            $statement .= "\n";
                        }
                        continue;
                    }

                    if ($inBlockComment) {
                        if ($char === '*' && $next === '/') {
                            $inBlockComment = false;
                            $i++;
                        }
                        continue;
                    }

                    if ($inSingleQuote) {
                        $statement .= $char;

                        if ($escaped) {
                            $escaped = false;
                            continue;
                        }

                        if ($char === '\\') {
                            $escaped = true;
                            continue;
                        }

                        if ($char === "'" && $next === "'") {
                            $statement .= $next;
                            $i++;
                            continue;
                        }

                        if ($char === "'") {
                            $inSingleQuote = false;
                        }

                        continue;
                    }

                    if ($inDoubleQuote) {
                        $statement .= $char;

                        if ($escaped) {
                            $escaped = false;
                            continue;
                        }

                        if ($char === '\\') {
                            $escaped = true;
                            continue;
                        }

                        if ($char === '"' && $next === '"') {
                            $statement .= $next;
                            $i++;
                            continue;
                        }

                        if ($char === '"') {
                            $inDoubleQuote = false;
                        }

                        continue;
                    }

                    if ($inBacktick) {
                        $statement .= $char;

                        if ($char === '`') {
                            $inBacktick = false;
                        }

                        continue;
                    }

                    if ($char === '-' && $next === '-') {
                        $third = $i + 2 < $length ? $chunk[$i + 2] : '';
                        if (in_array($third, [' ', "\t", "\r", "\n"], true)) {
                            $inLineComment = true;
                            $i++;
                            continue;
                        }
                    }

                    if ($char === '#') {
                        $inLineComment = true;
                        continue;
                    }

                    if ($char === '/' && $next === '*') {
                        $inBlockComment = true;
                        $i++;
                        continue;
                    }

                    if ($char === "'") {
                        $inSingleQuote = true;
                        $statement .= $char;
                        continue;
                    }

                    if ($char === '"') {
                        $inDoubleQuote = true;
                        $statement .= $char;
                        continue;
                    }

                    if ($char === '`') {
                        $inBacktick = true;
                        $statement .= $char;
                        continue;
                    }

                    if ($char === ';') {
                        $sql = trim($statement);
                        $statement = '';

                        if ($sql === '') {
                            continue;
                        }

                        $handled = $this->handleRestoreStatement($conn, $database, $sql);
                        if ($handled) {
                            $executed++;
                        }

                        continue;
                    }

                    $statement .= $char;
                }
            }

            $sql = trim($statement);
            if ($sql !== '') {
                $handled = $this->handleRestoreStatement($conn, $database, $sql);
                if ($handled) {
                    $executed++;
                }
            }
        } finally {
            fclose($handle);
            $conn->unprepared('SET FOREIGN_KEY_CHECKS=1');
        }

        return $executed;
    }

    protected function handleRestoreStatement($connection, string $database, string $sql): bool
    {
        if (preg_match('/^DELIMITER\\b/i', $sql)) {
            throw new \RuntimeException('File SQL menggunakan DELIMITER, belum didukung.');
        }

        if (preg_match('/^(CREATE|DROP)\\s+DATABASE\\b/i', $sql)) {
            throw new \RuntimeException('Demi keamanan, restore tidak mengizinkan CREATE/DROP DATABASE.');
        }

        if (preg_match('/^(CREATE|ALTER)\\s+USER\\b/i', $sql) || preg_match('/^(GRANT|REVOKE)\\b/i', $sql)) {
            throw new \RuntimeException('Demi keamanan, restore tidak mengizinkan perintah user/privilege (CREATE/ALTER USER, GRANT, REVOKE).');
        }

        if (preg_match('/^USE\\s+(.+)$/i', $sql, $matches)) {
            $target = trim((string) ($matches[1] ?? ''));
            $target = rtrim($target, ';');
            $target = trim($target, " \t\n\r\0\x0B`'\"");

            if ($target !== '' && $target !== $database) {
                throw new \RuntimeException('Perintah USE mengarah ke database "' . $target . '", tidak sesuai dengan konfigurasi aplikasi.');
            }

            return false;
        }

        $connection->unprepared($sql);

        return true;
    }
}
