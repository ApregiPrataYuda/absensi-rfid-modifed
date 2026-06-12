@extends('layouts.page')

@section('title', 'Pengaturan Devices')

@section('content')
@php
    $totalDevices = $devices->count();
    $activeDevices = $devices->where('status', \App\Models\Device::STATUS_ACTIVE)->count();
    $inactiveDevices = $devices->where('status', \App\Models\Device::STATUS_INACTIVE)->count();
    $pendingDevices = $devices->where('status', \App\Models\Device::STATUS_PENDING)->count();
    $revokedDevices = $devices->where('status', \App\Models\Device::STATUS_REVOKED)->count();
@endphp
<div class="view-section active animate-fade-in space-y-4">
    <div class="grid grid-cols-2 xl:grid-cols-5 gap-3">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Total Device</div>
            <div id="deviceStatTotal" class="mt-2 text-2xl font-bold text-slate-800">{{ $totalDevices }}</div>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-4 shadow-sm">
            <div class="text-[11px] font-bold uppercase tracking-wide text-emerald-700">Active</div>
            <div id="deviceStatActive" class="mt-2 text-2xl font-bold text-emerald-800">{{ $activeDevices }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4 shadow-sm">
            <div class="text-[11px] font-bold uppercase tracking-wide text-slate-700">Nonaktif</div>
            <div id="deviceStatInactive" class="mt-2 text-2xl font-bold text-slate-800">{{ $inactiveDevices }}</div>
        </div>
        <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-4 shadow-sm">
            <div class="text-[11px] font-bold uppercase tracking-wide text-amber-700">Pending</div>
            <div id="deviceStatPending" class="mt-2 text-2xl font-bold text-amber-800">{{ $pendingDevices }}</div>
        </div>
        <div class="rounded-xl border border-rose-200 bg-rose-50/70 p-4 shadow-sm">
            <div class="text-[11px] font-bold uppercase tracking-wide text-rose-700">Revoked</div>
            <div id="deviceStatRevoked" class="mt-2 text-2xl font-bold text-rose-800">{{ $revokedDevices }}</div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 bg-gray-50/30 flex flex-col md:flex-row md:items-center md:justify-between gap-2">
            <div>
                <h3 class="font-bold text-sm text-gray-800">Manajemen Devices</h3>
                <p class="text-xs text-gray-500 mt-1">Daftarkan serial number yang terdapat di mesin absensi agar bisa digunakan.</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap justify-end">
                <button type="button" onclick="refreshDevicePage(this)" class="bg-white text-gray-600 border border-gray-200 px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm hover:bg-gray-50 hover:text-indigo-600 transition" title="Perbarui Data">
                    <i class="fas fa-sync-alt"></i>
                </button>
                <button type="button" onclick="openAddDeviceModal()" class="inline-flex items-center gap-2 bg-indigo-600 text-white px-3 py-2 rounded-lg text-xs font-bold hover:bg-indigo-700 transition">
                    <i class="fas fa-microchip text-[11px]"></i>
                    Tambah Device
                </button>
            </div>
        </div>

        <div class="p-4 space-y-4">
            @if (session('success'))
                <div class="px-3 py-2 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-semibold">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="px-3 py-2 rounded-lg bg-red-50 border border-red-200 text-red-700 text-xs">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div class="overflow-x-auto border border-gray-200 rounded-xl">
                <table class="w-full text-left border-collapse min-w-[1080px]">
                    <thead class="bg-gray-50 text-gray-500 text-[10px] uppercase font-semibold border-b border-gray-200">
                        <tr>
                            <th class="p-3 text-center w-12">No</th>
                            <th class="p-3">Nama Device</th>
                            <th class="p-3">Serial Number</th>
                            <th class="p-3">Status</th>
                            <th class="p-3">MAC Address</th>
                            <th class="p-3">Firmware</th>
                            <th class="p-3">Log</th>
                            <th class="p-3">Last Seen</th>
                            <th class="p-3">Activated</th>
                            <th class="p-3 text-center w-64">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="deviceTableBody" class="divide-y divide-gray-50 bg-white text-xs text-gray-700">
                        @forelse ($devices as $index => $device)
                            @php
                                $status = strtolower((string) $device->status);
                                $statusBadgeClass = match ($status) {
                                    'active' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    'inactive' => 'bg-slate-100 text-slate-700 border-slate-200',
                                    'revoked' => 'bg-rose-50 text-rose-700 border-rose-200',
                                    default => 'bg-amber-50 text-amber-700 border-amber-200',
                                };
                                $isToggleDisabled = !in_array($status, ['active', 'inactive'], true);
                                $toggleActionRoute = $status === 'inactive'
                                    ? route('settings.devices.activate', $device)
                                    : route('settings.devices.deactivate', $device);
                                $toggleActionType = $status === 'inactive' ? 'activate' : 'deactivate';
                                $toggleConfirmMessage = $status === 'inactive'
                                    ? 'Aktifkan kembali device ' . $device->serial_number . '?'
                                    : 'Nonaktifkan device ' . $device->serial_number . '? Device tidak bisa mengirim absensi selama dinonaktifkan.';
                                $toggleButtonClass = $status === 'inactive'
                                    ? 'inline-flex items-center justify-center w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-100 transition disabled:opacity-50 disabled:cursor-not-allowed'
                                    : 'inline-flex items-center justify-center w-9 h-9 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 transition disabled:opacity-50 disabled:cursor-not-allowed';
                                $toggleTitle = $status === 'inactive' ? 'Aktifkan' : 'Nonaktifkan';
                                $toggleIcon = $status === 'inactive' ? 'fa-circle-check' : 'fa-power-off';
                            @endphp
                            <tr class="hover:bg-gray-50" data-device-id="{{ $device->id }}">
                                <td class="p-3 text-center text-gray-500" data-cell="number">{{ $index + 1 }}</td>
                                <td class="p-3">
                                    <div class="font-semibold text-gray-800" data-cell="name">{{ $device->name ?: '-' }}</div>
                                </td>
                                <td class="p-3">
                                    <div class="font-semibold text-gray-800" data-cell="serial-number">{{ $device->serial_number }}</div>
                                    <div class="text-[11px] text-gray-500" data-cell="created-at">Terdaftar {{ $device->created_at?->format('d M Y H:i') ?? '-' }}</div>
                                </td>
                                <td class="p-3">
                                    <span data-cell="status-badge" class="inline-flex items-center px-2.5 py-1 rounded-lg border text-[11px] font-bold uppercase tracking-wide {{ $statusBadgeClass }}">
                                        {{ $status }}
                                    </span>
                                </td>
                                <td class="p-3 font-mono text-[11px]" data-cell="mac-address">{{ $device->mac_address ?: '-' }}</td>
                                <td class="p-3" data-cell="firmware">{{ $device->firmware_version ?: '-' }}</td>
                                <td class="p-3" data-cell="logs">{{ number_format((int) $device->attendance_logs_count) }}</td>
                                <td class="p-3" data-cell="last-seen">
                                    @if ($device->last_seen)
                                        <div>{{ $device->last_seen->format('d M Y') }}</div>
                                        <div class="text-[11px] text-gray-500">{{ $device->last_seen->format('H:i:s') }}</div>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="p-3" data-cell="activated">
                                    @if ($device->activated_at)
                                        <div>{{ $device->activated_at->format('d M Y') }}</div>
                                        <div class="text-[11px] text-gray-500">{{ $device->activated_at->format('H:i:s') }}</div>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="p-3">
                                    <div class="flex items-center justify-center gap-2">
                                        <form method="POST" action="{{ $toggleActionRoute }}" class="js-device-action-form" data-action-type="{{ $toggleActionType }}" data-confirm="{{ $toggleConfirmMessage }}">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" class="{{ $toggleButtonClass }}" title="{{ $toggleTitle }}" {{ $isToggleDisabled ? 'disabled' : '' }}>
                                                <i class="fas {{ $toggleIcon }} text-xs"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('settings.devices.reset', $device) }}" class="js-device-action-form" data-action-type="reset" data-confirm="Reset device {{ $device->serial_number }} ke status pending?">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 transition" title="Reset">
                                                <i class="fas fa-rotate-left text-xs"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('settings.devices.revoke', $device) }}" class="js-device-action-form" data-action-type="revoke" data-confirm="Revoke device {{ $device->serial_number }}?">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 transition disabled:opacity-50 disabled:cursor-not-allowed" title="Revoke" {{ $status === 'revoked' ? 'disabled' : '' }}>
                                                <i class="fas fa-ban text-xs"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('settings.devices.destroy', $device) }}" class="js-device-action-form" data-action-type="delete" data-confirm="Hapus permanen device {{ $device->serial_number }}? Semua log attendance device ini juga akan ikut terhapus.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-stone-100 text-stone-700 hover:bg-stone-200 transition" title="Hapus">
                                                <i class="fas fa-trash text-xs"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr id="deviceEmptyState">
                                <td colspan="10" class="p-10 text-center text-gray-400">
                                    Belum ada device yang terdaftar.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="deviceModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-gray-900/55" onclick="closeDeviceModal()"></div>
    <div class="relative w-full max-w-lg">
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden w-full">
            <div class="px-5 py-3 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <h3 class="text-lg font-bold text-gray-800">Tambah Device</h3>
                <button type="button" onclick="closeDeviceModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            <div class="px-5 pt-3 pb-5">
                <form id="deviceForm" method="POST" action="{{ route('settings.devices.store') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Nama Device</label>
                        <input id="deviceName" name="name" value="{{ old('name') }}" placeholder="Contoh: Gerbang Utama" required class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                    </div>

                    <div>
                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Serial Number</label>
                        <input id="deviceSerialNumber" name="serial_number" value="{{ old('serial_number') }}" placeholder="Contoh: ESP32-001" required class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                        <p class="text-[11px] text-gray-500 mt-1">Masukkan serial number yang terdapat di mesin absensi.</p>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" onclick="closeDeviceModal()" class="inline-flex items-center justify-center gap-2 h-9 px-4 rounded-lg border border-gray-200 bg-white text-gray-700 font-semibold text-xs hover:bg-gray-50 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-200 transition">
                            <i class="fas fa-times text-[10px]"></i>
                            Batal
                        </button>
                        <button type="submit" class="inline-flex items-center justify-center gap-2 h-9 px-4 rounded-lg bg-gradient-to-r from-indigo-600 to-blue-600 text-white font-bold text-xs shadow-sm hover:from-indigo-700 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-indigo-300 transition">
                            <i class="fas fa-save text-[10px]"></i>
                            <span id="deviceSubmitText">Simpan Device</span>
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
    let deviceModalRefs = null;
    const deviceIndexUrl = @json(route('settings.devices.index'));
    const deviceActivateUrlTemplate = @json(route('settings.devices.activate', ['device' => '__DEVICE_ID__']));
    const deviceDeactivateUrlTemplate = @json(route('settings.devices.deactivate', ['device' => '__DEVICE_ID__']));
    const deviceResetUrlTemplate = @json(route('settings.devices.reset', ['device' => '__DEVICE_ID__']));
    const deviceRevokeUrlTemplate = @json(route('settings.devices.revoke', ['device' => '__DEVICE_ID__']));
    const deviceDestroyUrlTemplate = @json(route('settings.devices.destroy', ['device' => '__DEVICE_ID__']));

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? String(meta.getAttribute('content') || '') : '';
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function extractAjaxErrorMessage(payload, fallback = 'Terjadi kesalahan.') {
        if (!payload || typeof payload !== 'object') {
            return fallback;
        }

        if (typeof payload.message === 'string' && payload.message.trim() !== '') {
            return payload.message;
        }

        const errors = payload.errors;
        if (errors && typeof errors === 'object') {
            const firstKey = Object.keys(errors)[0];
            const firstValue = firstKey ? errors[firstKey] : null;
            if (Array.isArray(firstValue) && firstValue.length > 0) {
                return String(firstValue[0]);
            }
            if (typeof firstValue === 'string' && firstValue.trim() !== '') {
                return firstValue;
            }
        }

        return fallback;
    }

    async function refreshDevicePage(triggerButton = null) {
        const icon = triggerButton instanceof HTMLButtonElement
            ? triggerButton.querySelector('i')
            : null;

        if (triggerButton instanceof HTMLButtonElement) {
            triggerButton.disabled = true;
        }
        if (icon) {
            icon.classList.add('fa-spin');
        }

        try {
            const response = await fetch(deviceIndexUrl, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            const payload = await response.json().catch(() => ({}));
            if (!response.ok || payload.success === false) {
                throw new Error(extractAjaxErrorMessage(payload, 'Gagal memuat data device.'));
            }

            const data = payload && typeof payload === 'object' ? payload.data : null;
            renderDeviceRows(data && Array.isArray(data.devices) ? data.devices : []);
            updateStats(data && typeof data === 'object' ? data.stats : null);

            if (window.showAlert) {
                window.showAlert('success', payload.message || 'Data device berhasil diperbarui.');
            }
        } catch (error) {
            if (window.showAlert) {
                window.showAlert('error', error.message || 'Gagal memuat data device.');
            }
        } finally {
            if (icon) {
                icon.classList.remove('fa-spin');
            }
            if (triggerButton instanceof HTMLButtonElement) {
                triggerButton.disabled = false;
            }
        }
    }

    async function sendAjaxForm(form) {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: new FormData(form),
            credentials: 'same-origin',
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok || payload.success === false) {
            throw new Error(extractAjaxErrorMessage(payload, 'Gagal memproses data device.'));
        }

        return payload;
    }

    function getDeviceModalRefs() {
        if (deviceModalRefs) return deviceModalRefs;

        deviceModalRefs = {
            modal: document.getElementById('deviceModal'),
            form: document.getElementById('deviceForm'),
            name: document.getElementById('deviceName'),
            serialNumber: document.getElementById('deviceSerialNumber'),
            submitButton: document.querySelector('#deviceForm button[type="submit"]'),
            submitText: document.getElementById('deviceSubmitText'),
        };

        return deviceModalRefs;
    }

    function getStatusBadgeClass(status) {
        const normalized = String(status || '').toLowerCase().trim();
        if (normalized === 'active') {
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        }
        if (normalized === 'inactive') {
            return 'bg-slate-100 text-slate-700 border-slate-200';
        }
        if (normalized === 'revoked') {
            return 'bg-rose-50 text-rose-700 border-rose-200';
        }

        return 'bg-amber-50 text-amber-700 border-amber-200';
    }

    function renderDateTimeCell(dateValue, timeValue) {
        const dateText = String(dateValue || '').trim();
        const timeText = String(timeValue || '').trim();
        if (dateText === '') {
            return '-';
        }

        return `<div>${escapeHtml(dateText)}</div>${timeText !== '' ? `<div class="text-[11px] text-gray-500">${escapeHtml(timeText)}</div>` : ''}`;
    }

    function buildActionUrl(template, deviceId) {
        return String(template || '').replace('__DEVICE_ID__', String(deviceId));
    }

    function createActionForm(device, type) {
        const status = String(device.status || '').toLowerCase().trim();
        const config = {
            action: buildActionUrl(deviceResetUrlTemplate, device.id),
            confirmMessage: `Reset device ${device.serial_number} ke status pending?`,
            iconClass: 'fa-rotate-left',
            buttonClass: 'inline-flex items-center justify-center w-9 h-9 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 transition',
            buttonTitle: 'Reset',
            methodOverride: 'PUT',
            isDisabled: false,
        };

        if (type === 'activate') {
            config.action = buildActionUrl(deviceActivateUrlTemplate, device.id);
            config.confirmMessage = `Aktifkan kembali device ${device.serial_number}?`;
            config.iconClass = 'fa-circle-check';
            config.buttonClass = 'inline-flex items-center justify-center w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-100 transition disabled:opacity-50 disabled:cursor-not-allowed';
            config.buttonTitle = 'Aktifkan';
            config.isDisabled = status !== 'inactive';
        } else if (type === 'deactivate') {
            config.action = buildActionUrl(deviceDeactivateUrlTemplate, device.id);
            config.confirmMessage = `Nonaktifkan device ${device.serial_number}? Device tidak bisa mengirim absensi selama dinonaktifkan.`;
            config.iconClass = 'fa-power-off';
            config.buttonClass = 'inline-flex items-center justify-center w-9 h-9 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 transition disabled:opacity-50 disabled:cursor-not-allowed';
            config.buttonTitle = 'Nonaktifkan';
            config.isDisabled = status !== 'active';
        } else if (type === 'revoke') {
            config.action = buildActionUrl(deviceRevokeUrlTemplate, device.id);
            config.confirmMessage = `Revoke device ${device.serial_number}?`;
            config.iconClass = 'fa-ban';
            config.buttonClass = 'inline-flex items-center justify-center w-9 h-9 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 transition disabled:opacity-50 disabled:cursor-not-allowed';
            config.buttonTitle = 'Revoke';
            config.isDisabled = status === 'revoked';
        } else if (type === 'delete') {
            config.action = buildActionUrl(deviceDestroyUrlTemplate, device.id);
            config.confirmMessage = `Hapus permanen device ${device.serial_number}? Semua log attendance device ini juga akan ikut terhapus.`;
            config.iconClass = 'fa-trash';
            config.buttonClass = 'inline-flex items-center justify-center w-9 h-9 rounded-lg bg-stone-100 text-stone-700 hover:bg-stone-200 transition';
            config.buttonTitle = 'Hapus';
            config.methodOverride = 'DELETE';
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = config.action;
        form.className = 'js-device-action-form';
        form.dataset.actionType = type;
        form.dataset.confirm = config.confirmMessage;
        form.innerHTML = `
            <input type="hidden" name="_token" value="${escapeHtml(getCsrfToken())}">
            <input type="hidden" name="_method" value="${config.methodOverride}">
            <button type="submit" class="${config.buttonClass}" title="${config.buttonTitle}" ${config.isDisabled ? 'disabled' : ''}>
                <i class="fas ${config.iconClass} text-xs"></i>
            </button>
        `;

        form.addEventListener('submit', handleDeviceActionSubmit);

        return form;
    }

    function createToggleActionForm(device) {
        const status = String(device.status || '').toLowerCase().trim();

        if (status === 'inactive') {
            return createActionForm(device, 'activate');
        }

        return createActionForm(device, 'deactivate');
    }

    function buildDeviceRow(device) {
        const row = document.createElement('tr');
        row.className = 'hover:bg-gray-50';
        row.dataset.deviceId = String(device.id);
        row.innerHTML = `
            <td class="p-3 text-center text-gray-500" data-cell="number"></td>
            <td class="p-3">
                <div class="font-semibold text-gray-800" data-cell="name">${escapeHtml(device.name || '-')}</div>
            </td>
            <td class="p-3">
                <div class="font-semibold text-gray-800" data-cell="serial-number">${escapeHtml(device.serial_number || '-')}</div>
                <div class="text-[11px] text-gray-500" data-cell="created-at">Terdaftar ${escapeHtml(device.created_at_label || '-')}</div>
            </td>
            <td class="p-3">
                <span data-cell="status-badge" class="inline-flex items-center px-2.5 py-1 rounded-lg border text-[11px] font-bold uppercase tracking-wide ${getStatusBadgeClass(device.status)}">${escapeHtml(device.status || '-')}</span>
            </td>
            <td class="p-3 font-mono text-[11px]" data-cell="mac-address">${escapeHtml(device.mac_address || '-')}</td>
            <td class="p-3" data-cell="firmware">${escapeHtml(device.firmware_version || '-')}</td>
            <td class="p-3" data-cell="logs">${escapeHtml(device.attendance_logs_count ?? 0)}</td>
            <td class="p-3" data-cell="last-seen">${renderDateTimeCell(device.last_seen_date, device.last_seen_time)}</td>
            <td class="p-3" data-cell="activated">${renderDateTimeCell(device.activated_at_date, device.activated_at_time)}</td>
            <td class="p-3"><div class="flex items-center justify-center gap-2" data-cell="actions"></div></td>
        `;

        const actionsCell = row.querySelector('[data-cell="actions"]');
        if (actionsCell) {
            actionsCell.appendChild(createToggleActionForm(device));
            actionsCell.appendChild(createActionForm(device, 'reset'));
            actionsCell.appendChild(createActionForm(device, 'revoke'));
            actionsCell.appendChild(createActionForm(device, 'delete'));
        }

        return row;
    }

    function refreshRowNumbers() {
        document.querySelectorAll('#deviceTableBody tr[data-device-id]').forEach((row, index) => {
            const numberCell = row.querySelector('[data-cell="number"]');
            if (numberCell) {
                numberCell.textContent = String(index + 1);
            }
        });
    }

    function updateStats(stats) {
        if (!stats || typeof stats !== 'object') return;

        const mappings = {
            deviceStatTotal: stats.total,
            deviceStatActive: stats.active,
            deviceStatInactive: stats.inactive,
            deviceStatPending: stats.pending,
            deviceStatRevoked: stats.revoked,
        };

        Object.entries(mappings).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = String(value ?? 0);
            }
        });
    }

    function renderEmptyState() {
        const tableBody = document.getElementById('deviceTableBody');
        if (!tableBody) return;

        const emptyState = document.getElementById('deviceEmptyState');
        if (emptyState) {
            return;
        }

        const row = document.createElement('tr');
        row.id = 'deviceEmptyState';
        row.innerHTML = `
            <td colspan="10" class="p-10 text-center text-gray-400">
                Belum ada device yang terdaftar.
            </td>
        `;
        tableBody.appendChild(row);
    }

    function renderDeviceRows(devices) {
        const tableBody = document.getElementById('deviceTableBody');
        if (!tableBody) return;

        tableBody.innerHTML = '';

        if (!Array.isArray(devices) || devices.length === 0) {
            renderEmptyState();
            return;
        }

        devices.forEach((device) => {
            tableBody.appendChild(buildDeviceRow(device));
        });

        refreshRowNumbers();
    }

    function upsertDeviceRow(device, options = {}) {
        const tableBody = document.getElementById('deviceTableBody');
        if (!tableBody || !device || typeof device !== 'object') return;

        const newRow = buildDeviceRow(device);
        const existingRow = tableBody.querySelector(`tr[data-device-id="${String(device.id)}"]`);
        const emptyState = document.getElementById('deviceEmptyState');

        if (emptyState) {
            emptyState.remove();
        }

        if (existingRow) {
            existingRow.replaceWith(newRow);
        } else if (options.prepend) {
            tableBody.prepend(newRow);
        } else {
            tableBody.appendChild(newRow);
        }

        refreshRowNumbers();
        newRow.classList.add('bg-indigo-50');
        setTimeout(() => newRow.classList.remove('bg-indigo-50'), 900);
    }

    function removeDeviceRow(deviceId) {
        const tableBody = document.getElementById('deviceTableBody');
        if (!tableBody) return;

        const row = tableBody.querySelector(`tr[data-device-id="${String(deviceId)}"]`);
        if (row) {
            row.remove();
        }

        refreshRowNumbers();

        if (!tableBody.querySelector('tr[data-device-id]')) {
            renderEmptyState();
        }
    }

    async function confirmDeviceAction(actionType, message) {
        const type = String(actionType || '').toLowerCase().trim();
        const text = String(message || 'Lanjutkan proses ini?');

        if (typeof Swal === 'undefined' || typeof Swal.fire !== 'function') {
            return window.confirm(text);
        }

        let title = 'Konfirmasi Aksi';
        let icon = 'question';
        let confirmButtonColor = '#4F46E5';
        let confirmButtonText = 'Ya, Lanjutkan';

        if (type === 'reset') {
            title = 'Reset device?';
            icon = 'warning';
            confirmButtonColor = '#D97706';
            confirmButtonText = 'Ya, Reset';
        } else if (type === 'activate') {
            title = 'Aktifkan device?';
            icon = 'question';
            confirmButtonColor = '#059669';
            confirmButtonText = 'Ya, Aktifkan';
        } else if (type === 'deactivate') {
            title = 'Nonaktifkan device?';
            icon = 'warning';
            confirmButtonColor = '#475569';
            confirmButtonText = 'Ya, Nonaktifkan';
        } else if (type === 'revoke') {
            title = 'Revoke device?';
            icon = 'warning';
            confirmButtonColor = '#DC2626';
            confirmButtonText = 'Ya, Revoke';
        } else if (type === 'delete') {
            title = 'Hapus device?';
            icon = 'warning';
            confirmButtonColor = '#111827';
            confirmButtonText = 'Ya, Hapus';
        }

        const result = await Swal.fire({
            title,
            text,
            icon,
            showCancelButton: true,
            confirmButtonColor,
            cancelButtonColor: '#6B7280',
            confirmButtonText,
            cancelButtonText: 'Batal',
            reverseButtons: true,
        });

        return !!result.isConfirmed;
    }

    function openAddDeviceModal() {
        const refs = getDeviceModalRefs();
        if (!refs.modal || !refs.form) return;

        refs.form.reset();
        refs.modal.classList.remove('hidden');
        refs.modal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
        setTimeout(() => {
            if (refs.name) {
                refs.name.focus();
                return;
            }

            refs.serialNumber?.focus();
        }, 30);
    }

    function closeDeviceModal() {
        const refs = getDeviceModalRefs();
        if (!refs.modal) return;

        refs.modal.classList.add('hidden');
        refs.modal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
    }

    async function handleDeviceFormSubmit(event) {
        event.preventDefault();
        const refs = getDeviceModalRefs();
        if (!refs.form) return;

        const originalText = refs.submitText ? refs.submitText.textContent : 'Simpan Device';
        if (refs.submitButton) refs.submitButton.disabled = true;
        if (refs.submitText) refs.submitText.textContent = 'Menyimpan...';

        try {
            const payload = await sendAjaxForm(refs.form);
            const data = payload && typeof payload === 'object' ? payload.data : null;
            if (data && data.device) {
                upsertDeviceRow(data.device, { prepend: true });
            }
            if (data && data.stats) {
                updateStats(data.stats);
            }
            if (window.showAlert) {
                window.showAlert('success', payload.message || 'Device berhasil disimpan.');
            }
            refs.form.reset();
            closeDeviceModal();
        } catch (error) {
            if (window.showAlert) {
                window.showAlert('error', error.message || 'Gagal menyimpan device.');
            }
        } finally {
            if (refs.submitButton) refs.submitButton.disabled = false;
            if (refs.submitText) refs.submitText.textContent = originalText;
        }
    }

    async function handleDeviceActionSubmit(event) {
        event.preventDefault();
        const form = event.currentTarget;
        if (!(form instanceof HTMLFormElement)) return;

        const actionType = String(form.dataset.actionType || '').toLowerCase().trim();
        const message = String(form.dataset.confirm || 'Lanjutkan proses ini?');
        const isConfirmed = await confirmDeviceAction(actionType, message);
        if (!isConfirmed) {
            return;
        }

        const button = form.querySelector('button[type="submit"]');
        const icon = button ? button.querySelector('i') : null;
        if (button) button.disabled = true;
        if (icon) icon.classList.add('fa-spin');

        try {
            const payload = await sendAjaxForm(form);
            const data = payload && typeof payload === 'object' ? payload.data : null;
            if (data && typeof data.deleted_id !== 'undefined' && data.deleted_id !== null) {
                removeDeviceRow(data.deleted_id);
            } else if (data && data.device) {
                upsertDeviceRow(data.device);
            }
            if (data && data.stats) {
                updateStats(data.stats);
            }
            if (window.showAlert) {
                window.showAlert('success', payload.message || 'Aksi berhasil diproses.');
            }
        } catch (error) {
            if (window.showAlert) {
                window.showAlert('error', error.message || 'Gagal memproses aksi device.');
            }
            if (button) button.disabled = false;
            if (icon) icon.classList.remove('fa-spin');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const refs = getDeviceModalRefs();
        if (refs.form) {
            refs.form.addEventListener('submit', handleDeviceFormSubmit);
        }

        document.querySelectorAll('.js-device-action-form').forEach((form) => {
            form.addEventListener('submit', handleDeviceActionSubmit);
        });

        const hasErrors = @json($errors->any());
        if (hasErrors) {
            openAddDeviceModal();
        }
    });

    window.openAddDeviceModal = openAddDeviceModal;
    window.closeDeviceModal = closeDeviceModal;
    window.refreshDevicePage = refreshDevicePage;
</script>
@endpush
