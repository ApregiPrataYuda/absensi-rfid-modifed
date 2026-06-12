@extends('layouts.page')

@section('title', 'Pengaturan Notifikasi')

@php
    $attendanceNotifEnabled = (bool) old('attendance_notif_enabled', $settings['attendance_notif_enabled'] ?? false);
    $attendanceNotifChannel = old('attendance_notif_channel', $settings['attendance_notif_channel'] ?? 'whatsapp');
    $izinSakitNotifEnabled = (bool) old('izin_sakit_notif_enabled', $settings['izin_sakit_notif_enabled'] ?? false);
    $izinSakitNotifChannel = old('izin_sakit_notif_channel', $settings['izin_sakit_notif_channel'] ?? 'whatsapp');
    $waNotifReviewerEnabled = (bool) old('wa_notif_izin_sakit_reviewer_enabled', $settings['wa_notif_izin_sakit_reviewer_enabled'] ?? false);
    $gatewayProvider = old('wa_gateway_provider', $settings['wa_gateway_provider'] ?? 'SENDERBLAST');
    $gatewayProvider = strtoupper((string) $gatewayProvider);
    if ($gatewayProvider === 'STANDARD') {
        $gatewayProvider = 'SENDERBLAST';
    }
    $bodyType = old('wa_gateway_body_type', $settings['wa_gateway_body_type'] ?? 'application/json');
    $telegramTokenMasked = (string) ($settings['telegram_bot_token_masked'] ?? '');
    $telegramStartLink = trim((string) ($settings['telegram_start_link'] ?? ''));
    $telegramWebhookStatus = trim((string) ($settings['telegram_webhook_status'] ?? 'disabled'));
    $telegramWebhookError = trim((string) ($settings['telegram_webhook_last_error'] ?? ''));
    $studentTemplatePlaceholders = ['{nama}', '{nisn}', '{kelas}', '{no_hp}', '{jenis_kelamin}', '{tanggal_lahir}', '{agama}', '{nama_ayah}', '{nama_ibu}', '{nama_orang_tua}', '{alamat}', '{siswa_label}', '{website_name}', '{app_name}'];
    $waAttendanceTemplatePlaceholders = array_merge($studentTemplatePlaceholders, ['{tanggal}', '{jam}', '{waktu}', '{tanggal_jam}', '{status}', '{keterangan}']);
    $izinSakitTemplatePlaceholders = array_merge($studentTemplatePlaceholders, ['{jenis}', '{status}', '{tanggal_mulai}', '{tanggal_selesai}', '{rentang_tanggal}', '{alasan}', '{catatan}', '{disetujui_oleh}']);
    $reviewerTemplatePlaceholders = array_merge(['{recipient_name}', '{receiver_type}', '{siswa_nama}'], $studentTemplatePlaceholders, ['{jenis}', '{tanggal_mulai}', '{tanggal_selesai}', '{rentang_tanggal}', '{alasan}']);
    $otpTemplatePlaceholders = ['{nama}', '{username}', '{otp_code}', '{otp_expired_minutes}', '{otp_request_time}', '{website_name}'];
    $telegramAttendanceTemplatePlaceholders = $waAttendanceTemplatePlaceholders;
@endphp

@section('content')
<div class="view-section active animate-fade-in">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-100 bg-gray-50/40">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h3 class="font-bold text-sm text-gray-800">Pengaturan Notifikasi</h3>
                    <p class="text-xs text-gray-500 mt-1">Atur notifikasi absensi siswa, gateway WhatsApp, dan koneksi bot Telegram dari satu halaman.</p>
                </div>
                <div class="flex flex-wrap gap-2 lg:justify-end">
                    <button type="button" class="js-main-tab px-3 py-1.5 rounded-lg text-xs font-bold bg-indigo-600 text-white" data-panel="notif-panel-config" aria-selected="true">Konfigurasi Notifikasi</button>
                    <button type="button" class="js-main-tab px-3 py-1.5 rounded-lg text-xs font-bold bg-white text-gray-600 border border-gray-200 hover:bg-gray-100" data-panel="notif-panel-template" aria-selected="false">Template Pesan</button>
                </div>
            </div>
        </div>

        <div class="p-4">
            <form id="notif-setting-form" action="{{ route('settings.notifications.update') }}" method="POST" class="space-y-5">
                @csrf

                <div id="notif-panel-config" class="js-main-panel space-y-5">
                    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
                        <div class="border border-gray-200 rounded-xl p-4 bg-gray-50/40">
                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <h4 class="font-bold text-sm text-gray-800">Notifikasi Absensi</h4>
                                    <p class="text-xs text-gray-500 mt-1">Toggle ini mengatur pengiriman otomatis saat hadir, terlambat, pulang, dan pulang cepat.</p>
                                </div>
                                <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700">
                                    <input type="hidden" name="attendance_notif_enabled" value="0">
                                    <input
                                        id="attendance-notif-toggle"
                                        type="checkbox"
                                        name="attendance_notif_enabled"
                                        value="1"
                                        @checked($attendanceNotifEnabled)
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    >
                                    Aktifkan
                                </label>
                            </div>

                            <div class="mt-4">
                                <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Kirim via</label>
                                <select
                                    id="attendance-channel-field"
                                    name="attendance_notif_channel"
                                    class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5"
                                >
                                    <option value="whatsapp" {{ $attendanceNotifChannel === 'whatsapp' ? 'selected' : '' }}>WhatsApp</option>
                                    <option value="telegram" {{ $attendanceNotifChannel === 'telegram' ? 'selected' : '' }}>Telegram</option>
                                    <option value="both" {{ $attendanceNotifChannel === 'both' ? 'selected' : '' }}>WhatsApp + Telegram</option>
                                </select>
                            </div>
                        </div>

                        <div class="border border-gray-200 rounded-xl p-4 bg-gray-50/40">
                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <h4 class="font-bold text-sm text-gray-800">Notifikasi Izin/Sakit Siswa</h4>
                                    <p class="text-xs text-gray-500 mt-1">Toggle ini mengatur kirim status pengajuan izin/sakit siswa saat dibuat, disetujui, atau ditolak.</p>
                                </div>
                                <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700">
                                    <input type="hidden" name="izin_sakit_notif_enabled" value="0">
                                    <input
                                        id="izin-sakit-notif-toggle"
                                        type="checkbox"
                                        name="izin_sakit_notif_enabled"
                                        value="1"
                                        @checked($izinSakitNotifEnabled)
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    >
                                    Aktifkan
                                </label>
                            </div>

                            <div class="mt-4">
                                <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Kirim via</label>
                                <select
                                    id="izin-sakit-channel-field"
                                    name="izin_sakit_notif_channel"
                                    class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5"
                                >
                                    <option value="whatsapp" {{ $izinSakitNotifChannel === 'whatsapp' ? 'selected' : '' }}>WhatsApp</option>
                                    <option value="telegram" {{ $izinSakitNotifChannel === 'telegram' ? 'selected' : '' }}>Telegram</option>
                                    <option value="both" {{ $izinSakitNotifChannel === 'both' ? 'selected' : '' }}>WhatsApp + Telegram</option>
                                </select>
                            </div>
                        </div>

                        <div class="border border-gray-200 rounded-xl p-4 bg-gray-50/40">
                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <h4 class="font-bold text-sm text-gray-800">Notif WA Reviewer</h4>
                                    <p class="text-xs text-gray-500 mt-1">Kirim pengajuan izin/sakit baru ke wali kelas, guru, atau admin yang meninjau.</p>
                                </div>
                                <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700">
                                    <input type="hidden" name="wa_notif_izin_sakit_reviewer_enabled" value="0">
                                    <input
                                        type="checkbox"
                                        name="wa_notif_izin_sakit_reviewer_enabled"
                                        value="1"
                                        @checked($waNotifReviewerEnabled)
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    >
                                    Aktifkan
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="border border-gray-200 rounded-xl p-4">
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div>
                                <h4 class="font-bold text-sm text-gray-800">Konfigurasi Notifikasi</h4>
                                <p class="text-xs text-gray-500 mt-1">Pilih tab konfigurasi WA atau Telegram lalu simpan perubahan pada bagian bawah halaman.</p>
                            </div>
                            <div class="flex flex-wrap gap-2 md:justify-end">
                                <button type="button" class="js-config-tab px-3 py-1.5 rounded-lg text-xs font-bold bg-indigo-600 text-white" data-panel="config-panel-wa" aria-selected="true">Konfigurasi WA</button>
                                <button type="button" class="js-config-tab px-3 py-1.5 rounded-lg text-xs font-bold bg-white text-gray-600 border border-gray-200 hover:bg-gray-100" data-panel="config-panel-telegram" aria-selected="false">Konfigurasi Telegram</button>
                            </div>
                        </div>

                        <div id="config-panel-wa" class="js-config-panel space-y-5 mt-4">
                            <div class="grid grid-cols-1 gap-4">
                                <div class="border border-gray-200 rounded-lg p-3 bg-gray-50/50">
                                    <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Target Penerima Absensi</label>
                                    <p class="text-[11px] text-gray-500 mb-2">Saat ini notifikasi absensi WA dikirim ke nomor siswa yang tersimpan.</p>
                                    <select name="wa_notif_target" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                                        <option value="siswa" selected>Siswa</option>
                                    </select>
                                </div>
                            </div>

                            <div class="border border-gray-200 rounded-xl p-4">
                                <h5 class="font-bold text-sm text-gray-800">Hubungkan BOT WA</h5>
                                <p class="text-xs text-gray-500 mt-1">Gunakan preset agar mapping payload gateway lebih cepat. Mode CUSTOM tetap tersedia bila Anda memakai gateway sendiri.</p>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                    <div class="md:col-span-2">
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Provider</label>
                                        <select id="wa-provider-field" name="wa_gateway_provider" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                                            <option value="SENDERBLAST" {{ $gatewayProvider === 'SENDERBLAST' ? 'selected' : '' }}>SENDERBLAST (UNOFFICIAL)</option>
                                            <option value="FONNTE" {{ $gatewayProvider === 'FONNTE' ? 'selected' : '' }}>FONNTE (UNOFFICIAL)</option>
                                            <option value="CUSTOM" {{ $gatewayProvider === 'CUSTOM' ? 'selected' : '' }}>CUSTOM</option>
                                        </select>
                                    </div>

                                    <div class="js-provider-field js-senderblast-hide js-fonnte-hide">
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Base URL</label>
                                        <textarea id="wa-base-url-field" name="wa_gateway_base_url" rows="2" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">{{ old('wa_gateway_base_url', $settings['wa_gateway_base_url'] ?? '') }}</textarea>
                                    </div>
                                    <div class="js-provider-field js-fonnte-only">
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Authorization</label>
                                        <textarea name="wa_gateway_authorization" rows="2" placeholder="Token Authorization" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">{{ old('wa_gateway_authorization', $settings['wa_gateway_authorization'] ?? '') }}</textarea>
                                    </div>
                                    <div class="js-provider-field js-senderblast-hide js-fonnte-hide">
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Header Key</label>
                                        <input type="text" name="wa_gateway_header_key" value="{{ old('wa_gateway_header_key', $settings['wa_gateway_header_key'] ?? '') }}" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                                    </div>
                                    <div class="js-provider-field js-senderblast-hide js-fonnte-hide">
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Header Value</label>
                                        <input type="text" name="wa_gateway_header_value" value="{{ old('wa_gateway_header_value', $settings['wa_gateway_header_value'] ?? '') }}" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                                    </div>

                                    <div class="js-provider-field js-senderblast-hide js-fonnte-hide">
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Body Type</label>
                                        <select id="wa-body-type-field" name="wa_gateway_body_type" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                                            <option value="application/json" {{ $bodyType === 'application/json' ? 'selected' : '' }}>application/json</option>
                                            <option value="application/x-www-form-urlencoded" {{ $bodyType === 'application/x-www-form-urlencoded' ? 'selected' : '' }}>application/x-www-form-urlencoded</option>
                                            <option value="multipart/form-data" {{ $bodyType === 'multipart/form-data' ? 'selected' : '' }}>multipart/form-data</option>
                                            <option value="text/plain" {{ $bodyType === 'text/plain' ? 'selected' : '' }}>text/plain</option>
                                        </select>
                                    </div>
                                    <div class="js-provider-field js-senderblast-hide js-fonnte-hide">
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Timeout Request (detik)</label>
                                        <input type="number" min="3" max="120" name="wa_gateway_timeout" value="{{ old('wa_gateway_timeout', $settings['wa_gateway_timeout'] ?? 15) }}" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                                    </div>

                                    <div class="js-provider-field">
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Parameter 1</label>
                                        <input id="wa-parameter-1-field" type="text" name="wa_gateway_parameter_1" value="{{ old('wa_gateway_parameter_1', $settings['wa_gateway_parameter_1'] ?? 'number') }}" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                                    </div>
                                    <div class="js-provider-field">
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Value 1</label>
                                        <input type="text" name="wa_gateway_value_1" value="{{ old('wa_gateway_value_1', $settings['wa_gateway_value_1'] ?? 'TUJUAN') }}" readonly class="w-full bg-gray-100 border border-gray-200 text-gray-700 text-sm rounded-lg cursor-not-allowed block p-2.5">
                                    </div>

                                    <div class="js-provider-field">
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Parameter 2</label>
                                        <input id="wa-parameter-2-field" type="text" name="wa_gateway_parameter_2" value="{{ old('wa_gateway_parameter_2', $settings['wa_gateway_parameter_2'] ?? 'message') }}" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                                    </div>
                                    <div class="js-provider-field">
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Value 2</label>
                                        <input type="text" name="wa_gateway_value_2" value="{{ old('wa_gateway_value_2', $settings['wa_gateway_value_2'] ?? 'PESAN') }}" readonly class="w-full bg-gray-100 border border-gray-200 text-gray-700 text-sm rounded-lg cursor-not-allowed block p-2.5">
                                    </div>

                                    <div class="js-provider-field js-senderblast-only">
                                        <label id="wa-parameter-3-label" class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Parameter 3</label>
                                        <input id="wa-parameter-3-field" type="text" name="wa_gateway_parameter_3" value="{{ old('wa_gateway_parameter_3', $settings['wa_gateway_parameter_3'] ?? '') }}" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                                    </div>
                                    <div class="js-provider-field js-senderblast-only">
                                        <label id="wa-value-3-label" class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Value 3</label>
                                        <input type="text" name="wa_gateway_value_3" value="{{ old('wa_gateway_value_3', $settings['wa_gateway_value_3'] ?? '') }}" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                                    </div>

                                    <div class="js-provider-field js-senderblast-only">
                                        <label id="wa-parameter-4-label" class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Parameter 4</label>
                                        <input id="wa-parameter-4-field" type="text" name="wa_gateway_parameter_4" value="{{ old('wa_gateway_parameter_4', $settings['wa_gateway_parameter_4'] ?? '') }}" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                                    </div>
                                    <div class="js-provider-field js-senderblast-only">
                                        <label id="wa-value-4-label" class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Value 4</label>
                                        <input type="text" name="wa_gateway_value_4" value="{{ old('wa_gateway_value_4', $settings['wa_gateway_value_4'] ?? '') }}" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                                    </div>
                                </div>

                                <div class="js-senderblast-only js-provider-banner hidden mt-3 px-3 py-2 rounded-lg bg-blue-50 border border-blue-200 text-[11px] text-blue-700">
                            Pastikan api key sudah aktif dan nomor pengirim connected. Apabila belum punya akun silahkan daftar dulu di
                            <a href="https://senderblast.com" target="_blank" rel="noopener noreferrer" class="font-semibold">https://senderblast.com</a>
                        </div>
                        <div class="js-fonnte-only js-provider-banner hidden mt-3 px-3 py-2 rounded-lg bg-blue-50 border border-blue-200 text-[11px] text-blue-700">
                            Pastikan token FONNTE aktif. Apabila belum punya akun silahkan daftar dulu di
                            <a href="https://fonnte.com" target="_blank" rel="noopener noreferrer" class="font-semibold">https://fonnte.com</a>
                        </div>
                                <div class="mt-4 border border-gray-200 rounded-lg bg-gray-50/50 p-3 text-[11px] text-gray-600">
                                    <div class="font-bold text-gray-700 mb-2">Panduan Cepat</div>
                                    <div class="js-guide-senderblast hidden space-y-2">
                                <div>Mode <b>SENDERBLAST</b> memakai format bawaan sistem. Isi sender di <b>Value 1</b> dan api key di <b>Value 2</b>.</div>
                            </div>
                                    <div class="js-guide-fonnte hidden space-y-1">
                                        <div>Mode <b>Fonnte</b> hanya butuh token Authorization. Target dan message akan diisi otomatis oleh sistem.</div>
                                    </div>
                                    <div class="js-guide-custom hidden space-y-2">
                                <div>Mode <b>CUSTOM</b> bebas mapping key/value. Gunakan placeholder <code>TUJUAN</code> untuk nomor dan <code>PESAN</code> untuk isi pesan.</div>
                                <div>Jika provider mewajibkan header tertentu (contoh <code>X-Secret-Key</code>), isi pada kolom <b>Header Key</b> dan <b>Header Value</b>.</div>
                                <div>Contoh payload nested:</div>
                                <pre class="bg-white border border-gray-200 rounded p-2 text-[10px] leading-4 overflow-x-auto">Parameter 1 = receiver      | Value 1 = TUJUAN
Parameter 2 = data.message     | Value 2 = PESAN
Parameter 3 = api_key | Value 3 = token_anda</pre>
                                <div>Hasil JSON:
                                    <code>{"api_key":"token_anda","receiver":"628xxxx","data":{"message":"..."}}
                                    </code>
                                </div>
                            </div>
                                </div>
                            </div>
                        </div>

                        <div id="config-panel-telegram" class="js-config-panel hidden space-y-5 mt-4">
                            <div class="border border-gray-200 rounded-xl p-4">
                                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                    <div>
                                        <h5 class="font-bold text-sm text-gray-800">Hubungkan BOT Telegram</h5>
                                        <p class="text-xs text-gray-500 mt-1">Tempel token dari BotFather. Saat disimpan, sistem akan memvalidasi token lalu mengatur webhook otomatis.</p>
                                    </div>
                                    <div class="flex flex-wrap gap-2 md:justify-end">
                                        <button type="submit" name="telegram_action" value="reapply_webhook" class="px-3 py-2 rounded-lg text-xs font-bold bg-white text-gray-700 border border-gray-200 hover:bg-gray-100">
                                            Set Ulang Webhook
                                        </button>
                                        <button id="telegram-reset-open" type="button" class="px-3 py-2 rounded-lg text-xs font-bold bg-red-50 text-red-700 border border-red-200 hover:bg-red-100">
                                            Reset Token
                                        </button>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                    <div class="md:col-span-2">
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Bot Token</label>
                                        <input
                                            id="telegram-bot-token-field"
                                            type="text"
                                            name="telegram_bot_token"
                                            value="{{ old('telegram_bot_token', '') }}"
                                            placeholder="{{ $telegramTokenMasked !== '' ? 'Token tersimpan: ' . $telegramTokenMasked : 'Contoh: 123456789:AA...' }}"
                                            class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5"
                                        >
                                        <p class="text-[11px] text-gray-500 mt-1">Kosongkan field ini bila tidak ingin mengganti token. Jika token baru milik bot berbeda, webhook bot lama akan dilepas terlebih dulu.</p>
                                    </div>

                                    <div>
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Bot ID</label>
                                        <input id="telegram-bot-id-field" type="text" value="{{ old('telegram_bot_id', $settings['telegram_bot_id'] ?? '') }}" readonly class="w-full bg-gray-100 border border-gray-200 text-gray-700 text-sm rounded-lg cursor-not-allowed block p-2.5">
                                    </div>
                                    <div>
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Username Bot</label>
                                        <input id="telegram-bot-username-field" type="text" value="{{ old('telegram_bot_username', $settings['telegram_bot_username'] ?? '') }}" readonly class="w-full bg-gray-100 border border-gray-200 text-gray-700 text-sm rounded-lg cursor-not-allowed block p-2.5">
                                    </div>

                                    <div>
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Webhook Status</label>
                                        <input id="telegram-webhook-status-field" type="text" value="{{ strtoupper($telegramWebhookStatus !== '' ? $telegramWebhookStatus : 'disabled') }}" readonly class="w-full bg-gray-100 border border-gray-200 text-gray-700 text-sm rounded-lg cursor-not-allowed block p-2.5">
                                    </div>
                                    <div>
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Siswa Terhubung</label>
                                        <input id="telegram-linked-students-count-field" type="text" value="{{ (int) ($settings['telegram_linked_students_count'] ?? 0) }}" readonly class="w-full bg-gray-100 border border-gray-200 text-gray-700 text-sm rounded-lg cursor-not-allowed block p-2.5">
                                    </div>

                                    <div class="md:col-span-2">
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Link /start</label>
                                        <textarea id="telegram-start-link-field" rows="2" readonly class="w-full bg-gray-100 border border-gray-200 text-gray-700 text-sm rounded-lg cursor-not-allowed block p-2.5">{{ $telegramStartLink }}</textarea>
                                    </div>

                                    <div class="md:col-span-2">
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Last Error</label>
                                        <textarea id="telegram-last-error-field" rows="2" readonly class="w-full bg-gray-100 border border-gray-200 text-gray-700 text-sm rounded-lg cursor-not-allowed block p-2.5">{{ $telegramWebhookError }}</textarea>
                                    </div>
                                </div>

                                <div class="mt-4 flex flex-col gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 text-[11px] text-slate-700 md:flex-row md:items-center md:justify-between">
                                    <div>
                                        Panduan setting Telegram tersedia untuk admin sekolah, mulai dari membuat bot di BotFather, menyimpan token, sampai instruksi siswa menghubungkan akun lewat <b>/start NISN</b>.
                                    </div>
                                    <a
                                        href="{{ asset('tutorial-setting-bot-telegram.html') }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100"
                                    >
                                        Buka Tutorial Telegram
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="notif-panel-template" class="js-main-panel hidden">
                    <div class="border border-gray-200 rounded-xl p-4">
                        <div class="flex flex-wrap gap-2 md:justify-end">
                            <button type="button" class="js-template-tab px-3 py-1.5 rounded-lg text-xs font-bold bg-indigo-600 text-white" data-panel="template-panel-wa" aria-selected="true">Template WA</button>
                            <button type="button" class="js-template-tab px-3 py-1.5 rounded-lg text-xs font-bold bg-white text-gray-600 border border-gray-200 hover:bg-gray-100" data-panel="template-panel-telegram" aria-selected="false">Template Telegram</button>
                        </div>

                        <div id="template-panel-wa" class="js-template-panel mt-4">
                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between mb-3">
                                <h5 class="font-bold text-sm text-gray-800">Template WA</h5>
                                <div class="flex flex-wrap gap-2 md:justify-end">
                                    <button type="button" class="js-wa-template-tab px-3 py-1.5 rounded-lg text-xs font-bold bg-indigo-600 text-white" data-panel="wa-template-panel-absensi" aria-selected="true">Absensi</button>
                                    <button type="button" class="js-wa-template-tab px-3 py-1.5 rounded-lg text-xs font-bold bg-white text-gray-600 border border-gray-200 hover:bg-gray-100" data-panel="wa-template-panel-izin-siswa" aria-selected="false">Izin/Sakit Siswa</button>
                                    <button type="button" class="js-wa-template-tab px-3 py-1.5 rounded-lg text-xs font-bold bg-white text-gray-600 border border-gray-200 hover:bg-gray-100" data-panel="wa-template-panel-reviewer" aria-selected="false">Notif Reviewer</button>
                                    <button type="button" class="js-wa-template-tab px-3 py-1.5 rounded-lg text-xs font-bold bg-white text-gray-600 border border-gray-200 hover:bg-gray-100" data-panel="wa-template-panel-otp" aria-selected="false">OTP Reset Password</button>
                                </div>
                            </div>

                            <div id="wa-template-panel-absensi" class="js-wa-template-panel">
                                <div class="mb-3 p-2 rounded-lg border border-gray-200 bg-gray-50 text-[11px] text-gray-600 space-y-2">
                                    <div class="font-semibold text-gray-700">Placeholder absensi (klik untuk menyisipkan ke kolom aktif):</div>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($waAttendanceTemplatePlaceholders as $placeholder)
                                            <button type="button" data-template-variable="{{ $placeholder }}" data-template-scope="wa-template-panel-absensi" class="px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-100 text-gray-700">{{ $placeholder }}</button>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Template Hadir</label>
                                        <textarea name="wa_template_hadir" rows="12" class="js-template-editor w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">{{ old('wa_template_hadir', $settings['wa_template_hadir'] ?? '') }}</textarea>
                                    </div>
                                    <div>
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Template Terlambat</label>
                                        <textarea name="wa_template_terlambat" rows="12" class="js-template-editor w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">{{ old('wa_template_terlambat', $settings['wa_template_terlambat'] ?? '') }}</textarea>
                                    </div>
                                    <div>
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Template Pulang</label>
                                        <textarea name="wa_template_pulang" rows="12" class="js-template-editor w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">{{ old('wa_template_pulang', $settings['wa_template_pulang'] ?? '') }}</textarea>
                                    </div>
                                    <div>
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Template Pulang Cepat</label>
                                        <textarea name="wa_template_pulang_cepat" rows="12" class="js-template-editor w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">{{ old('wa_template_pulang_cepat', $settings['wa_template_pulang_cepat'] ?? '') }}</textarea>
                                    </div>
                                </div>
                            </div>

                            <div id="wa-template-panel-izin-siswa" class="js-wa-template-panel hidden">
                                <div class="mb-3 p-2 rounded-lg border border-gray-200 bg-gray-50 text-[11px] text-gray-600 space-y-2">
                                    <div class="font-semibold text-gray-700">Placeholder izin/sakit (klik untuk menyisipkan ke kolom aktif):</div>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($izinSakitTemplatePlaceholders as $placeholder)
                                            <button type="button" data-template-variable="{{ $placeholder }}" data-template-scope="wa-template-panel-izin-siswa" class="px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-100 text-gray-700">{{ $placeholder }}</button>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Template Izin/Sakit Diajukan</label>
                                        <textarea name="wa_template_izin_sakit_diajukan" rows="8" class="js-template-editor w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">{{ old('wa_template_izin_sakit_diajukan', $settings['wa_template_izin_sakit_diajukan'] ?? '') }}</textarea>
                                    </div>
                                    <div>
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Template Izin/Sakit Disetujui</label>
                                        <textarea name="wa_template_izin_sakit_disetujui" rows="8" class="js-template-editor w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">{{ old('wa_template_izin_sakit_disetujui', $settings['wa_template_izin_sakit_disetujui'] ?? '') }}</textarea>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Template Izin/Sakit Ditolak</label>
                                        <textarea name="wa_template_izin_sakit_ditolak" rows="8" class="js-template-editor w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">{{ old('wa_template_izin_sakit_ditolak', $settings['wa_template_izin_sakit_ditolak'] ?? '') }}</textarea>
                                    </div>
                                </div>
                            </div>

                            <div id="wa-template-panel-reviewer" class="js-wa-template-panel hidden">
                                <div class="mb-3 p-2 rounded-lg border border-gray-200 bg-gray-50 text-[11px] text-gray-600 space-y-2">
                                    <div class="font-semibold text-gray-700">Placeholder reviewer izin/sakit (klik untuk menyisipkan ke kolom aktif):</div>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($reviewerTemplatePlaceholders as $placeholder)
                                            <button type="button" data-template-variable="{{ $placeholder }}" data-template-scope="wa-template-panel-reviewer" class="px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-100 text-gray-700">{{ $placeholder }}</button>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Template Notif Reviewer (Wali Kelas / Guru)</label>
                                        <textarea name="wa_template_izin_sakit_reviewer_wakel" rows="8" class="js-template-editor w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">{{ old('wa_template_izin_sakit_reviewer_wakel', $settings['wa_template_izin_sakit_reviewer_wakel'] ?? '') }}</textarea>
                                    </div>
                                    <div>
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Template Notif Reviewer (Admin)</label>
                                        <textarea name="wa_template_izin_sakit_reviewer_admin" rows="8" class="js-template-editor w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">{{ old('wa_template_izin_sakit_reviewer_admin', $settings['wa_template_izin_sakit_reviewer_admin'] ?? '') }}</textarea>
                                    </div>
                                </div>
                            </div>

                            <div id="wa-template-panel-otp" class="js-wa-template-panel hidden">
                                <div class="mb-3 p-2 rounded-lg border border-gray-200 bg-gray-50 text-[11px] text-gray-600 space-y-2">
                                    <div class="font-semibold text-gray-700">Placeholder OTP reset password (klik untuk menyisipkan ke kolom aktif):</div>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($otpTemplatePlaceholders as $placeholder)
                                            <button type="button" data-template-variable="{{ $placeholder }}" data-template-scope="wa-template-panel-otp" class="px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-100 text-gray-700">{{ $placeholder }}</button>
                                        @endforeach
                                    </div>
                                </div>
                                <div>
                                    <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Template OTP</label>
                                    <textarea name="wa_template_forgot_password_otp" rows="10" class="js-template-editor w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">{{ old('wa_template_forgot_password_otp', $settings['wa_template_forgot_password_otp'] ?? '') }}</textarea>
                                </div>
                            </div>
                        </div>

                        <div id="template-panel-telegram" class="js-template-panel hidden mt-4">
                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between mb-3">
                                <h5 class="font-bold text-sm text-gray-800">Template Telegram</h5>
                                <div class="flex flex-wrap gap-2 md:justify-end">
                                    <button type="button" class="js-telegram-template-tab px-3 py-1.5 rounded-lg text-xs font-bold bg-indigo-600 text-white" data-panel="telegram-template-panel-absensi" aria-selected="true">Absensi</button>
                                    <button type="button" class="js-telegram-template-tab px-3 py-1.5 rounded-lg text-xs font-bold bg-white text-gray-600 border border-gray-200 hover:bg-gray-100" data-panel="telegram-template-panel-izin-siswa" aria-selected="false">Izin/Sakit Siswa</button>
                                </div>
                            </div>

                            <div id="telegram-template-panel-absensi" class="js-telegram-template-panel">
                                <div class="mb-3 p-2 rounded-lg border border-gray-200 bg-gray-50 text-[11px] text-gray-600 space-y-2">
                                    <div class="font-semibold text-gray-700">Placeholder Telegram absensi (klik untuk menyisipkan ke kolom aktif):</div>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($telegramAttendanceTemplatePlaceholders as $placeholder)
                                            <button type="button" data-template-variable="{{ $placeholder }}" data-template-scope="telegram-template-panel-absensi" class="px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-100 text-gray-700">{{ $placeholder }}</button>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Template Hadir</label>
                                        <textarea name="telegram_template_hadir" rows="12" class="js-template-editor w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">{{ old('telegram_template_hadir', $settings['telegram_template_hadir'] ?? '') }}</textarea>
                                    </div>
                                    <div>
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Template Terlambat</label>
                                        <textarea name="telegram_template_terlambat" rows="12" class="js-template-editor w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">{{ old('telegram_template_terlambat', $settings['telegram_template_terlambat'] ?? '') }}</textarea>
                                    </div>
                                    <div>
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Template Pulang</label>
                                        <textarea name="telegram_template_pulang" rows="12" class="js-template-editor w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">{{ old('telegram_template_pulang', $settings['telegram_template_pulang'] ?? '') }}</textarea>
                                    </div>
                                    <div>
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Template Pulang Cepat</label>
                                        <textarea name="telegram_template_pulang_cepat" rows="12" class="js-template-editor w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">{{ old('telegram_template_pulang_cepat', $settings['telegram_template_pulang_cepat'] ?? '') }}</textarea>
                                    </div>
                                </div>
                            </div>

                            <div id="telegram-template-panel-izin-siswa" class="js-telegram-template-panel hidden">
                                <div class="mb-3 p-2 rounded-lg border border-gray-200 bg-gray-50 text-[11px] text-gray-600 space-y-2">
                                    <div class="font-semibold text-gray-700">Placeholder Telegram izin/sakit (klik untuk menyisipkan ke kolom aktif):</div>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($izinSakitTemplatePlaceholders as $placeholder)
                                            <button type="button" data-template-variable="{{ $placeholder }}" data-template-scope="telegram-template-panel-izin-siswa" class="px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-100 text-gray-700">{{ $placeholder }}</button>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Template Izin/Sakit Diajukan</label>
                                        <textarea name="telegram_template_izin_sakit_diajukan" rows="8" class="js-template-editor w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">{{ old('telegram_template_izin_sakit_diajukan', $settings['telegram_template_izin_sakit_diajukan'] ?? '') }}</textarea>
                                    </div>
                                    <div>
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Template Izin/Sakit Disetujui</label>
                                        <textarea name="telegram_template_izin_sakit_disetujui" rows="8" class="js-template-editor w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">{{ old('telegram_template_izin_sakit_disetujui', $settings['telegram_template_izin_sakit_disetujui'] ?? '') }}</textarea>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Template Izin/Sakit Ditolak</label>
                                        <textarea name="telegram_template_izin_sakit_ditolak" rows="8" class="js-template-editor w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">{{ old('telegram_template_izin_sakit_ditolak', $settings['telegram_template_izin_sakit_ditolak'] ?? '') }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <button id="wa-test-open" type="button" class="inline-flex items-center gap-2 bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-bold hover:bg-gray-50 transition">
                        <i class="fas fa-paper-plane text-xs"></i>
                        Tes Kirim Pesan
                    </button>
                    <button id="notif-setting-submit" type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-80 disabled:cursor-not-allowed">
                        <span id="notif-setting-submit-text">Simpan Pengaturan</span>
                    </button>
                </div>

                <div id="telegram-reset-modal" class="hidden fixed inset-0 z-[80] bg-black/50 overflow-y-auto">
                    <div class="min-h-full px-4 py-6 md:flex md:items-center md:justify-center">
                        <div class="max-w-lg mx-auto md:mx-0 bg-white rounded-2xl shadow-xl border border-gray-200 overflow-hidden">
                            <div class="px-5 py-4 border-b border-gray-100 flex items-start justify-between gap-3">
                                <div>
                                    <h4 class="font-bold text-sm text-gray-800">Reset Token Telegram</h4>
                                    <p class="text-xs text-gray-500 mt-1">Gunakan jika ingin mengganti bot atau menghapus seluruh konfigurasi Telegram yang tersimpan di aplikasi.</p>
                                </div>
                                <button type="button" data-telegram-reset-close class="text-gray-400 hover:text-gray-600">
                                    <span class="sr-only">Tutup</span>
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>

                            <div class="p-5 space-y-4">
                                <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-xs text-amber-800">
                                    Reset akan menghapus token bot, data webhook, status bot, dan menonaktifkan tautan siswa dari bot yang sedang tersimpan.
                                </div>

                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" data-telegram-reset-close class="px-3 py-2 rounded-lg text-sm font-semibold bg-white text-gray-600 border border-gray-200 hover:bg-gray-50">Batal</button>
                                    <button id="telegram-reset-confirm" type="submit" form="notif-setting-form" name="telegram_action" value="reset_token" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white hover:bg-red-700 disabled:opacity-80 disabled:cursor-not-allowed">
                                        Reset Token
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="wa-test-modal" class="hidden fixed inset-0 z-[80] bg-black/50 overflow-y-auto">
    <div class="min-h-full px-4 py-6 md:flex md:items-center md:justify-center">
        <div class="max-w-lg mx-auto md:mx-0 bg-white rounded-2xl shadow-xl border border-gray-200 overflow-hidden max-h-[calc(100vh-3rem)] overflow-y-auto">
            <div class="px-5 py-4 border-b border-gray-100 flex items-start justify-between gap-3">
                <div>
                    <h4 class="font-bold text-sm text-gray-800">Tes Kirim WhatsApp</h4>
                    <p class="text-xs text-gray-500 mt-1">Gunakan untuk memastikan konfigurasi gateway WA sudah benar.</p>
                </div>
                <button type="button" data-wa-test-close class="text-gray-400 hover:text-gray-600">
                    <span class="sr-only">Tutup</span>
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="p-5">
                <div id="wa-test-error" class="hidden mb-4 px-3 py-2 rounded-lg bg-red-50 border border-red-200 text-red-700 text-xs"></div>

                <form id="wa-test-form" action="{{ route('settings.notifications.test-send') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Nomor Tujuan</label>
                        <input id="wa-test-recipient" type="text" name="recipient" placeholder="62812xxxxxxx" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                    </div>
                    <div>
                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Pesan</label>
                        <textarea id="wa-test-message" name="message" rows="4" placeholder="Tes notifikasi dari Absensindo." class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5"></textarea>
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" data-wa-test-close class="px-3 py-2 rounded-lg text-sm font-semibold bg-white text-gray-600 border border-gray-200 hover:bg-gray-50">Batal</button>
                        <button id="wa-test-submit" type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-80 disabled:cursor-not-allowed">
                            <span id="wa-test-submit-text">Kirim Tes</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const form = document.getElementById('notif-setting-form');
        if (!form) return;

        const submitButton = document.getElementById('notif-setting-submit');
        const submitText = document.getElementById('notif-setting-submit-text');
        const errorBox = document.getElementById('notif-setting-error');
        const attendanceToggle = document.getElementById('attendance-notif-toggle');
        const attendanceChannelField = document.getElementById('attendance-channel-field');
        const izinSakitToggle = document.getElementById('izin-sakit-notif-toggle');
        const izinSakitChannelField = document.getElementById('izin-sakit-channel-field');
        const waTestOpenButton = document.getElementById('wa-test-open');
        const testModal = document.getElementById('wa-test-modal');
        const testForm = document.getElementById('wa-test-form');
        const testRecipientInput = document.getElementById('wa-test-recipient');
        const testErrorBox = document.getElementById('wa-test-error');
        const testSubmitButton = document.getElementById('wa-test-submit');
        const testSubmitText = document.getElementById('wa-test-submit-text');
        const testCloseButtons = Array.from(document.querySelectorAll('[data-wa-test-close]'));
        const telegramResetOpenButton = document.getElementById('telegram-reset-open');
        const telegramResetModal = document.getElementById('telegram-reset-modal');
        const telegramResetCloseButtons = Array.from(document.querySelectorAll('[data-telegram-reset-close]'));
        const modalRoot = document.getElementById('modalContainer') || document.body;
        const mainTabButtons = Array.from(document.querySelectorAll('.js-main-tab'));
        const mainPanels = Array.from(document.querySelectorAll('.js-main-panel'));
        const configTabButtons = Array.from(document.querySelectorAll('.js-config-tab'));
        const configPanels = Array.from(document.querySelectorAll('.js-config-panel'));
        const templateTabButtons = Array.from(document.querySelectorAll('.js-template-tab'));
        const templatePanels = Array.from(document.querySelectorAll('.js-template-panel'));
        const waTemplateTabButtons = Array.from(document.querySelectorAll('.js-wa-template-tab'));
        const waTemplatePanels = Array.from(document.querySelectorAll('.js-wa-template-panel'));
        const telegramTemplateTabButtons = Array.from(document.querySelectorAll('.js-telegram-template-tab'));
        const telegramTemplatePanels = Array.from(document.querySelectorAll('.js-telegram-template-panel'));
        const templateEditorFields = Array.from(form.querySelectorAll('.js-template-editor'));
        const templateVariableButtons = Array.from(form.querySelectorAll('[data-template-variable]'));
        const providerField = document.getElementById('wa-provider-field');
        const baseUrlField = document.getElementById('wa-base-url-field');
        const bodyTypeField = document.getElementById('wa-body-type-field');
        const parameter1Field = document.getElementById('wa-parameter-1-field');
        const parameter2Field = document.getElementById('wa-parameter-2-field');
        const parameter3Field = document.getElementById('wa-parameter-3-field');
        const parameter4Field = document.getElementById('wa-parameter-4-field');
        const parameter3Label = document.getElementById('wa-parameter-3-label');
        const value3Label = document.getElementById('wa-value-3-label');
        const parameter4Label = document.getElementById('wa-parameter-4-label');
        const value4Label = document.getElementById('wa-value-4-label');
        const value3Field = form.querySelector('input[name="wa_gateway_value_3"]');
        const value4Field = form.querySelector('input[name="wa_gateway_value_4"]');
        const providerFields = Array.from(form.querySelectorAll('.js-provider-field'));
        const senderblastOnlyFields = Array.from(form.querySelectorAll('.js-senderblast-only'));
        const fonnteOnlyFields = Array.from(form.querySelectorAll('.js-fonnte-only'));
        const providerBanners = Array.from(form.querySelectorAll('.js-provider-banner'));
        const guideSenderblast = Array.from(form.querySelectorAll('.js-guide-senderblast'));
        const guideFonnte = Array.from(form.querySelectorAll('.js-guide-fonnte'));
        const guideCustom = Array.from(form.querySelectorAll('.js-guide-custom'));
        const telegramBotTokenField = document.getElementById('telegram-bot-token-field');
        const telegramBotIdField = document.getElementById('telegram-bot-id-field');
        const telegramBotUsernameField = document.getElementById('telegram-bot-username-field');
        const telegramWebhookStatusField = document.getElementById('telegram-webhook-status-field');
        const telegramLinkedStudentsCountField = document.getElementById('telegram-linked-students-count-field');
        const telegramStartLinkField = document.getElementById('telegram-start-link-field');
        const telegramLastErrorField = document.getElementById('telegram-last-error-field');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const lastFocusedTemplateEditorByScope = new Map();
        let activeMainPanel = 'notif-panel-config';
        let activeConfigPanel = 'config-panel-wa';
        let lastProviderMode = null;

        function setButtonState(button, textNode, isLoading, loadingText, defaultText) {
            if (!button || !textNode) return;
            button.disabled = isLoading;
            button.classList.toggle('opacity-80', isLoading);
            button.classList.toggle('cursor-not-allowed', isLoading);
            textNode.textContent = isLoading ? loadingText : defaultText;
        }

        if (testModal && modalRoot && testModal.parentElement !== modalRoot) {
            modalRoot.appendChild(testModal);
        }
        if (telegramResetModal && modalRoot && telegramResetModal.parentElement !== modalRoot) {
            modalRoot.appendChild(telegramResetModal);
        }

        function hideMessages() {
            if (!errorBox) return;
            errorBox.classList.add('hidden');
            errorBox.innerHTML = '';
        }

        function showErrorMessages(messages) {
            if (!errorBox) return;
            const list = Array.isArray(messages) ? messages : [String(messages || 'Terjadi kesalahan.')];
            errorBox.innerHTML = list.map((text) => `<div>${String(text)}</div>`).join('');
            errorBox.classList.remove('hidden');
        }

        function rememberTemplateEditor(field) {
            const scopePanel = field?.closest('.js-wa-template-panel, .js-telegram-template-panel');
            const scopeId = String(scopePanel?.id || '').trim();
            if (!scopeId) return;
            lastFocusedTemplateEditorByScope.set(scopeId, field);
        }

        function resolveTemplateEditor(scopeId) {
            const normalizedScopeId = String(scopeId || '').trim();
            if (normalizedScopeId === '') return null;

            const lastFocusedField = lastFocusedTemplateEditorByScope.get(normalizedScopeId);
            if (lastFocusedField && lastFocusedField.isConnected && !lastFocusedField.disabled) {
                return lastFocusedField;
            }

            const scopePanel = document.getElementById(normalizedScopeId);
            if (!scopePanel) return null;

            return scopePanel.querySelector('.js-template-editor');
        }

        function insertTemplateVariable(scopeId, variableToken) {
            const field = resolveTemplateEditor(scopeId);
            const token = String(variableToken || '').trim();
            if (!field || token === '') return;

            const start = Number(field.selectionStart ?? field.value.length);
            const end = Number(field.selectionEnd ?? field.value.length);
            const original = String(field.value || '');
            const nextValue = original.slice(0, start) + token + original.slice(end);
            field.value = nextValue;

            const cursorPos = start + token.length;
            field.focus();
            if (typeof field.setSelectionRange === 'function') {
                field.setSelectionRange(cursorPos, cursorPos);
            }

            rememberTemplateEditor(field);
        }

        function updateAttendanceChannelState() {
            if (!attendanceToggle || !attendanceChannelField) return;
            const disabled = !attendanceToggle.checked;
            attendanceChannelField.classList.toggle('bg-gray-100', disabled);
            attendanceChannelField.classList.toggle('text-gray-500', disabled);
            attendanceChannelField.classList.toggle('cursor-not-allowed', disabled);
            attendanceChannelField.setAttribute('aria-disabled', disabled ? 'true' : 'false');
        }

        function updateIzinSakitChannelState() {
            if (!izinSakitToggle || !izinSakitChannelField) return;
            const disabled = !izinSakitToggle.checked;
            izinSakitChannelField.classList.toggle('bg-gray-100', disabled);
            izinSakitChannelField.classList.toggle('text-gray-500', disabled);
            izinSakitChannelField.classList.toggle('cursor-not-allowed', disabled);
            izinSakitChannelField.setAttribute('aria-disabled', disabled ? 'true' : 'false');
        }

        function updateWaTestButtonVisibility() {
            if (!waTestOpenButton) return;
            const shouldShow = activeMainPanel === 'notif-panel-config' && activeConfigPanel === 'config-panel-wa';
            waTestOpenButton.classList.toggle('hidden', !shouldShow);
        }

        function activateButtonGroup(buttons, activeButton) {
            buttons.forEach((button) => {
                const active = button === activeButton;
                button.setAttribute('aria-selected', active ? 'true' : 'false');
                button.classList.toggle('bg-indigo-600', active);
                button.classList.toggle('text-white', active);
                button.classList.toggle('bg-white', !active);
                button.classList.toggle('text-gray-600', !active);
                button.classList.toggle('border', !active);
                button.classList.toggle('border-gray-200', !active);
                button.classList.toggle('hover:bg-gray-100', !active);
            });
        }

        function activateMainTab(button) {
            const panelId = String(button?.dataset?.panel || '').trim();
            if (!panelId) return;
            activeMainPanel = panelId;
            activateButtonGroup(mainTabButtons, button);
            mainPanels.forEach((panel) => {
                panel.classList.toggle('hidden', panel.id !== panelId);
            });
            updateWaTestButtonVisibility();
        }

        function activateConfigTab(button) {
            const panelId = String(button?.dataset?.panel || '').trim();
            if (!panelId) return;
            activeConfigPanel = panelId;
            activateButtonGroup(configTabButtons, button);
            configPanels.forEach((panel) => {
                panel.classList.toggle('hidden', panel.id !== panelId);
            });
            updateWaTestButtonVisibility();
        }

        function activateTemplateTab(button) {
            const panelId = String(button?.dataset?.panel || '').trim();
            if (!panelId) return;
            activateButtonGroup(templateTabButtons, button);
            templatePanels.forEach((panel) => {
                panel.classList.toggle('hidden', panel.id !== panelId);
            });
        }

        function activateWaTemplateTab(button) {
            const panelId = String(button?.dataset?.panel || '').trim();
            if (!panelId) return;
            activateButtonGroup(waTemplateTabButtons, button);
            waTemplatePanels.forEach((panel) => {
                panel.classList.toggle('hidden', panel.id !== panelId);
            });
        }

        function activateTelegramTemplateTab(button) {
            const panelId = String(button?.dataset?.panel || '').trim();
            if (!panelId) return;
            activateButtonGroup(telegramTemplateTabButtons, button);
            telegramTemplatePanels.forEach((panel) => {
                panel.classList.toggle('hidden', panel.id !== panelId);
            });
        }

        function hideTestError() {
            if (!testErrorBox) return;
            testErrorBox.classList.add('hidden');
            testErrorBox.textContent = '';
        }

        function showTestError(message) {
            if (!testErrorBox) return;
            testErrorBox.textContent = String(message || 'Gagal mengirim pesan tes.');
            testErrorBox.classList.remove('hidden');
        }

        function openTestModal() {
            if (!testModal) return;
            hideTestError();
            if (testForm) testForm.reset();
            testModal.classList.remove('hidden');
            setTimeout(() => testRecipientInput?.focus(), 50);
        }

        function closeTestModal() {
            if (!testModal) return;
            testModal.classList.add('hidden');
            hideTestError();
            setButtonState(testSubmitButton, testSubmitText, false, 'Mengirim...', 'Kirim Tes');
        }

        function openTelegramResetModal() {
            if (!telegramResetModal) return;
            telegramResetModal.classList.remove('hidden');
        }

        function closeTelegramResetModal() {
            if (!telegramResetModal) return;
            telegramResetModal.classList.add('hidden');
        }

        function setInputReadonlyState(field, locked) {
            if (!field) return;
            field.readOnly = locked;
            field.classList.toggle('bg-gray-100', locked);
            field.classList.toggle('text-gray-700', locked);
            field.classList.toggle('cursor-not-allowed', locked);
            field.classList.toggle('bg-white', !locked);
        }

        function setSelectReadonlyState(field, locked) {
            if (!field) return;
            field.classList.toggle('pointer-events-none', locked);
            field.classList.toggle('bg-gray-100', locked);
            field.classList.toggle('text-gray-700', locked);
            field.classList.toggle('cursor-not-allowed', locked);
            field.classList.toggle('bg-white', !locked);
            field.setAttribute('aria-disabled', locked ? 'true' : 'false');
        }

        function applyProviderPreset() {
            if (!providerField) return;

            const provider = String(providerField.value || '').trim().toUpperCase();
            const isSenderblast = provider === 'SENDERBLAST' || provider === 'STANDARD';
            const isFonnte = provider === 'FONNTE';
            const isPresetProvider = isSenderblast || isFonnte;
            const switchedFromPresetToCustom = (lastProviderMode === 'SENDERBLAST' || lastProviderMode === 'FONNTE') && !isPresetProvider;

            if (isSenderblast) {
                providerFields.forEach((field) => field.classList.add('hidden'));
                senderblastOnlyFields.forEach((field) => field.classList.remove('hidden'));
            } else if (isFonnte) {
                providerFields.forEach((field) => field.classList.add('hidden'));
                fonnteOnlyFields.forEach((field) => field.classList.remove('hidden'));
            } else {
                providerFields.forEach((field) => field.classList.remove('hidden'));
            }
            if (!isSenderblast && !isFonnte) {
                senderblastOnlyFields.forEach((field) => field.classList.remove('hidden'));
                fonnteOnlyFields.forEach((field) => field.classList.remove('hidden'));
            } else {
                senderblastOnlyFields.forEach((field) => {
                    field.classList.toggle('hidden', !isSenderblast);
                });
                fonnteOnlyFields.forEach((field) => {
                    field.classList.toggle('hidden', !isFonnte);
                });
            }

            guideSenderblast.forEach((field) => field.classList.toggle('hidden', !isSenderblast));
            guideFonnte.forEach((field) => field.classList.toggle('hidden', !isFonnte));
            guideCustom.forEach((field) => field.classList.toggle('hidden', isPresetProvider));

            providerBanners.forEach((field) => {
                if (field.classList.contains('js-senderblast-only')) {
                    field.classList.toggle('hidden', !isSenderblast);
                    return;
                }
                if (field.classList.contains('js-fonnte-only')) {
                    field.classList.toggle('hidden', !isFonnte);
                }
            });

            if (parameter3Label) parameter3Label.textContent = isSenderblast ? 'Parameter 1' : 'Parameter 3';
            if (value3Label) value3Label.textContent = isSenderblast ? 'Value 1' : 'Value 3';
            if (parameter4Label) parameter4Label.textContent = isSenderblast ? 'Parameter 2' : 'Parameter 4';
            if (value4Label) value4Label.textContent = isSenderblast ? 'Value 2' : 'Value 4';

            if (isSenderblast) {
                if (baseUrlField) baseUrlField.value = 'https://app.senderblast.com/api/v1/send-message';
                if (bodyTypeField) bodyTypeField.value = 'application/json';
                if (parameter1Field) parameter1Field.value = 'number';
                if (parameter2Field) parameter2Field.value = 'message';
                if (parameter3Field) parameter3Field.value = 'sender';
                if (parameter4Field) parameter4Field.value = 'api_key';
            } else if (isFonnte) {
                if (baseUrlField) baseUrlField.value = 'https://api.fonnte.com/send';
                if (bodyTypeField) bodyTypeField.value = 'application/json';
                if (parameter1Field) parameter1Field.value = 'target';
                if (parameter2Field) parameter2Field.value = 'message';
                if (parameter3Field) parameter3Field.value = '';
                if (parameter4Field) parameter4Field.value = '';
                if (value3Field) value3Field.value = '';
                if (value4Field) value4Field.value = '';
            } else if (switchedFromPresetToCustom) {
                if (baseUrlField) baseUrlField.value = '';
                if (bodyTypeField) bodyTypeField.value = 'application/json';
                if (parameter1Field) parameter1Field.value = '';
                if (parameter2Field) parameter2Field.value = '';
                if (parameter3Field) parameter3Field.value = '';
                if (parameter4Field) parameter4Field.value = '';
                if (value3Field) value3Field.value = '';
                if (value4Field) value4Field.value = '';
            }

            setInputReadonlyState(baseUrlField, isPresetProvider);
            setSelectReadonlyState(bodyTypeField, isPresetProvider);
            setInputReadonlyState(parameter1Field, isPresetProvider);
            setInputReadonlyState(parameter2Field, isPresetProvider);
            setInputReadonlyState(parameter3Field, isSenderblast);
            setInputReadonlyState(parameter4Field, isSenderblast);
            lastProviderMode = isSenderblast ? 'SENDERBLAST' : (isFonnte ? 'FONNTE' : 'CUSTOM');
        }

        async function handleTestSubmit(event) {
            event.preventDefault();
            if (!testForm) return;

            hideTestError();
            setButtonState(testSubmitButton, testSubmitText, true, 'Mengirim...', 'Kirim Tes');

            try {
                const response = await fetch(testForm.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: new FormData(testForm),
                    credentials: 'same-origin',
                });

                const payload = await response.json().catch(() => ({}));
                if (!response.ok || payload.success === false) {
                    if (response.status === 422 && payload.errors && typeof payload.errors === 'object') {
                        const firstError = Object.values(payload.errors).flat()[0] || 'Data tes tidak valid.';
                        showTestError(firstError);
                    } else {
                        showTestError(payload.message || 'Gagal mengirim pesan tes.');
                    }
                    if (window.showAlert) {
                        window.showAlert('error', payload.message || 'Gagal mengirim pesan tes.');
                    }
                    return;
                }

                if (window.showAlert) {
                    window.showAlert('success', payload.message || 'Pesan tes berhasil dikirim.');
                }
                closeTestModal();
            } catch (error) {
                showTestError(error.message || 'Terjadi kesalahan saat mengirim pesan tes.');
                if (window.showAlert) {
                    window.showAlert('error', error.message || 'Terjadi kesalahan saat mengirim pesan tes.');
                }
            } finally {
                setButtonState(testSubmitButton, testSubmitText, false, 'Mengirim...', 'Kirim Tes');
            }
        }

        function updateTelegramInfoFields(settings) {
            if (!settings || typeof settings !== 'object') return;

            if (telegramBotTokenField) {
                telegramBotTokenField.value = '';
                const maskedToken = String(settings.telegram_bot_token_masked || '').trim();
                telegramBotTokenField.placeholder = maskedToken !== ''
                    ? `Token tersimpan: ${maskedToken}`
                    : 'Contoh: 123456789:AA...';
            }

            if (telegramBotIdField) {
                telegramBotIdField.value = String(settings.telegram_bot_id || '');
            }
            if (telegramBotUsernameField) {
                telegramBotUsernameField.value = String(settings.telegram_bot_username || '');
            }
            if (telegramWebhookStatusField) {
                telegramWebhookStatusField.value = String(settings.telegram_webhook_status || 'disabled').toUpperCase();
            }
            if (telegramLinkedStudentsCountField) {
                telegramLinkedStudentsCountField.value = String(settings.telegram_linked_students_count ?? 0);
            }
            if (telegramStartLinkField) {
                telegramStartLinkField.value = String(settings.telegram_start_link || '');
            }
            if (telegramLastErrorField) {
                telegramLastErrorField.value = String(settings.telegram_webhook_last_error || '');
            }
        }

        function updateNotificationStateFields(settings) {
            if (!settings || typeof settings !== 'object') return;

            if (attendanceToggle) {
                attendanceToggle.checked = Boolean(settings.attendance_notif_enabled);
            }
            if (attendanceChannelField) {
                attendanceChannelField.value = String(settings.attendance_notif_channel || 'whatsapp');
            }
            if (izinSakitToggle) {
                izinSakitToggle.checked = Boolean(settings.izin_sakit_notif_enabled);
            }
            if (izinSakitChannelField) {
                izinSakitChannelField.value = String(settings.izin_sakit_notif_channel || 'whatsapp');
            }

            const telegramTextFields = {
                telegram_template_hadir: settings.telegram_template_hadir || '',
                telegram_template_terlambat: settings.telegram_template_terlambat || '',
                telegram_template_pulang: settings.telegram_template_pulang || '',
                telegram_template_pulang_cepat: settings.telegram_template_pulang_cepat || '',
                telegram_template_izin_sakit_diajukan: settings.telegram_template_izin_sakit_diajukan || '',
                telegram_template_izin_sakit_disetujui: settings.telegram_template_izin_sakit_disetujui || '',
                telegram_template_izin_sakit_ditolak: settings.telegram_template_izin_sakit_ditolak || '',
            };

            Object.entries(telegramTextFields).forEach(([name, value]) => {
                const field = form.querySelector(`[name="${name}"]`);
                if (field) {
                    field.value = String(value);
                }
            });

            updateAttendanceChannelState();
            updateIzinSakitChannelState();
        }

        async function handleSubmit(event) {
            event.preventDefault();
            hideMessages();

            const submitter = event.submitter || null;
            const submitterWasPrimary = submitter === submitButton || !submitter;
            let restoreSubmitter = null;

            setButtonState(submitButton, submitText, true, 'Menyimpan...', 'Simpan Pengaturan');

            if (submitter && !submitterWasPrimary) {
                restoreSubmitter = {
                    button: submitter,
                    html: submitter.innerHTML,
                };
                submitter.disabled = true;
                submitter.classList.add('opacity-80', 'cursor-not-allowed');
                submitter.innerHTML = 'Memproses...';
            }

            try {
                const formData = new FormData(form);
                if (submitter && submitter.name) {
                    formData.set(submitter.name, submitter.value || '1');
                }

                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: formData,
                    credentials: 'same-origin',
                });

                const payload = await response.json().catch(() => ({}));
                if (!response.ok || payload.success === false) {
                    if (response.status === 422 && payload.errors && typeof payload.errors === 'object') {
                        const errors = Object.values(payload.errors).flat();
                        showErrorMessages(errors);
                    } else {
                        showErrorMessages(payload.message || 'Gagal menyimpan pengaturan notifikasi.');
                    }

                    if (window.showAlert) {
                        window.showAlert('error', payload.message || 'Gagal menyimpan pengaturan notifikasi.');
                    }
                    return;
                }

                updateTelegramInfoFields(payload.data || {});
                if (submitter?.value === 'reset_token') {
                    updateNotificationStateFields(payload.data || {});
                    closeTelegramResetModal();
                }

                if (window.showAlert) {
                    window.showAlert('success', payload.message || 'Pengaturan notifikasi berhasil disimpan.');
                }
            } catch (error) {
                showErrorMessages(error.message || 'Terjadi kesalahan saat menyimpan.');
                if (window.showAlert) {
                    window.showAlert('error', error.message || 'Terjadi kesalahan saat menyimpan.');
                }
            } finally {
                setButtonState(submitButton, submitText, false, 'Menyimpan...', 'Simpan Pengaturan');
                if (restoreSubmitter) {
                    restoreSubmitter.button.disabled = false;
                    restoreSubmitter.button.classList.remove('opacity-80', 'cursor-not-allowed');
                    restoreSubmitter.button.innerHTML = restoreSubmitter.html;
                }
            }
        }

        form.addEventListener('submit', handleSubmit);

        attendanceToggle?.addEventListener('change', updateAttendanceChannelState);
        izinSakitToggle?.addEventListener('change', updateIzinSakitChannelState);
        waTestOpenButton?.addEventListener('click', openTestModal);
        telegramResetOpenButton?.addEventListener('click', openTelegramResetModal);
        testForm?.addEventListener('submit', handleTestSubmit);
        testRecipientInput?.addEventListener('input', () => {
            const raw = String(testRecipientInput.value || '');
            const startsWithPlus = raw.startsWith('+');
            const digitsOnly = raw.replace(/[^0-9]/g, '');
            testRecipientInput.value = startsWithPlus ? `+${digitsOnly}` : digitsOnly;
        });

        testCloseButtons.forEach((button) => {
            button.addEventListener('click', closeTestModal);
        });
        telegramResetCloseButtons.forEach((button) => {
            button.addEventListener('click', closeTelegramResetModal);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeTestModal();
                closeTelegramResetModal();
            }
        });

        providerField?.addEventListener('change', applyProviderPreset);
        mainTabButtons.forEach((button) => {
            button.addEventListener('click', () => activateMainTab(button));
        });
        configTabButtons.forEach((button) => {
            button.addEventListener('click', () => activateConfigTab(button));
        });
        templateTabButtons.forEach((button) => {
            button.addEventListener('click', () => activateTemplateTab(button));
        });
        waTemplateTabButtons.forEach((button) => {
            button.addEventListener('click', () => activateWaTemplateTab(button));
        });
        telegramTemplateTabButtons.forEach((button) => {
            button.addEventListener('click', () => activateTelegramTemplateTab(button));
        });
        templateEditorFields.forEach((field) => {
            ['focus', 'click', 'keyup'].forEach((eventName) => {
                field.addEventListener(eventName, () => rememberTemplateEditor(field));
            });
        });
        templateVariableButtons.forEach((button) => {
            button.addEventListener('click', () => {
                insertTemplateVariable(button.dataset.templateScope, button.dataset.templateVariable);
            });
        });

        const defaultMainTab = mainTabButtons.find((button) => button.getAttribute('aria-selected') === 'true') || mainTabButtons[0];
        const defaultConfigTab = configTabButtons.find((button) => button.getAttribute('aria-selected') === 'true') || configTabButtons[0];
        const defaultTemplateTab = templateTabButtons.find((button) => button.getAttribute('aria-selected') === 'true') || templateTabButtons[0];
        const defaultWaTemplateTab = waTemplateTabButtons.find((button) => button.getAttribute('aria-selected') === 'true') || waTemplateTabButtons[0];
        const defaultTelegramTemplateTab = telegramTemplateTabButtons.find((button) => button.getAttribute('aria-selected') === 'true') || telegramTemplateTabButtons[0];

        if (defaultMainTab) activateMainTab(defaultMainTab);
        if (defaultConfigTab) activateConfigTab(defaultConfigTab);
        if (defaultTemplateTab) activateTemplateTab(defaultTemplateTab);
        if (defaultWaTemplateTab) activateWaTemplateTab(defaultWaTemplateTab);
        if (defaultTelegramTemplateTab) activateTelegramTemplateTab(defaultTelegramTemplateTab);

        updateAttendanceChannelState();
        updateIzinSakitChannelState();
        applyProviderPreset();
        updateWaTestButtonVisibility();
    })();
</script>
@endpush
