@extends('layouts.main')

@section('body')
<div id="loginPage" class="min-h-screen bg-[#F3F4F6] flex items-center justify-center p-4 md:p-6">
    <div class="w-full max-w-5xl rounded-2xl overflow-hidden border border-slate-200 shadow-lg bg-white animate-fade-in">
        <div class="grid grid-cols-1 lg:grid-cols-5">
            <section class="lg:col-span-2 p-7 md:p-8 text-white relative overflow-hidden" style="background: linear-gradient(180deg, #4967d9 0%, #5b63d6 38%, #6b5fce 70%, #7a58c8 100%);">
                <div class="absolute -top-12 -right-14 w-44 h-44 rounded-full bg-white/10"></div>
                <div class="absolute -bottom-10 -left-12 w-40 h-40 rounded-full bg-white/10"></div>
                <div class="relative z-10 h-full flex flex-col justify-between gap-8">
                    <div>
                        <div class="inline-flex items-center gap-3 mb-5">
                            <div class="w-10 h-10 rounded-lg bg-white/15 border border-white/25 flex items-center justify-center">
                                <i class="fas fa-qrcode text-base"></i>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-[0.2em] font-bold text-white/90">Absensi PT Duta Indo Raya</p>
                                <p class="text-[11px] text-white/80">Sistem Absensi</p>
                            </div>
                        </div>
                        <h1 class="text-2xl md:text-3xl font-bold leading-tight">
                            Masuk ke Dashboard
                        </h1>
                        <p id="loginMotivationText" class="text-sm text-white/85 mt-3 leading-relaxed transition-opacity duration-300">
                            Pengembangan kompetensi yang berkelanjutan menciptakan fondasi perusahaan yang kokoh.
                        </p>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-lg border border-white/20 bg-white/10 px-3 py-2">
                            <div class="font-semibold">Realtime</div>
                            <div class="text-white/75 mt-0.5">Monitoring aktif</div>
                        </div>
                        <div class="rounded-lg border border-white/20 bg-white/10 px-3 py-2">
                            <div class="font-semibold">Terpusat</div>
                            <div class="text-white/75 mt-0.5">Data konsisten</div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="lg:col-span-3 p-7 md:p-9 bg-white flex items-center">
                <div class="w-full max-w-md mx-auto">
                    <div class="mb-7">
                        <h2 class="text-xl md:text-2xl font-bold text-slate-800">Selamat Datang</h2>
                        <p class="text-slate-500 text-sm mt-1.5">Silakan masuk menggunakan akun Anda.</p>
                    </div>

                    <div class="bg-slate-100 p-1.5 rounded-xl flex mb-6 border border-slate-200">
                        <button id="btnSiswaTab" onclick="switchLoginTab('siswa')" class="flex-1 py-2.5 text-sm font-bold rounded-lg shadow-sm bg-white text-indigo-700 ring-1 ring-black/5 transition-all duration-300 flex items-center justify-center gap-2">
                            <i class="fas fa-user-tie"></i> Karyawan
                        </button>
                        <button id="btnAdminTab" onclick="switchLoginTab('admin')" class="flex-1 py-2.5 text-sm font-medium rounded-lg text-slate-500 hover:text-slate-700 hover:bg-white/50 transition-all duration-300 flex items-center justify-center gap-2">
                            <i class="fas fa-chalkboard-teacher"></i> Admin / Mandor
                        </button>
                    </div>

                <form id="loginForm" method="POST" action="{{ route('login') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" id="login_type" name="login_type" value="siswa">

                    @if ($errors->has('login_type'))
                        <div id="serverLoginError" class="mb-3 p-3.5 bg-red-50 border border-red-100 rounded-xl flex items-start gap-3 animate-fade-in">
                            <div class="p-2 bg-red-100 rounded-full text-red-600 shrink-0">
                                <i class="fas fa-exclamation-triangle text-xs"></i>
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-red-700">Akses Ditolak</h4>
                                <p class="text-xs text-red-600 mt-0.5">{{ $errors->first('login_type') }}</p>
                            </div>
                        </div>
                    @endif

                    <div id="loginError" class="mb-3 hidden p-3.5 bg-red-50 border border-red-100 rounded-xl flex items-start gap-3 animate-fade-in">
                        <div class="p-2 bg-red-100 rounded-full text-red-600 shrink-0">
                            <i class="fas fa-exclamation-triangle text-xs"></i>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-red-700">Akses Ditolak</h4>
                            <p id="errorText" class="text-xs text-red-600 mt-0.5">Username atau password salah.</p>
                        </div>
                    </div>

                    <div id="loginFormsWrap" class="min-h-[176px] relative">
                        <div id="formSiswaLogin" class="animate-fade-in">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 ml-1">NIK KARYAWAN</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-600 transition-colors">
                                    <i class="far fa-id-card text-base"></i>
                                </div>
                                <input type="number" id="nisn" name="nisn" class="block w-full pl-11 pr-4 py-3.5 bg-slate-50 border-slate-200 border rounded-xl text-slate-900 text-sm focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all placeholder-slate-400" placeholder="Masukkan NIK Karyawan" value="{{ old('nisn') }}">
                            </div>
                            <p id="nisnInlineError" class="text-xs mt-2 leading-5 {{ $errors->has('nisn') ? 'text-red-600' : 'hidden' }}">
                                {{ $errors->has('nisn') ? $errors->first('nisn') : '' }}
                            </p>

                            <p id="siswaFlowHint" class="text-xs text-indigo-600 mt-2 leading-5 hidden"></p>

                            <div id="siswaExistingPasswordWrap" class="hidden mt-4">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 ml-1">Password</label>
                                <div class="relative group">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-600 transition-colors">
                                        <i class="fas fa-lock text-base"></i>
                                    </div>
                                    <input type="password" id="siswaPassword" name="password" disabled class="block w-full pl-11 pr-12 py-3.5 bg-slate-50 border-slate-200 border rounded-xl text-slate-900 text-sm focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all placeholder-slate-400" placeholder="Masukkan password akun karyawan">
                                    <button type="button" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-indigo-600 transition-colors" onclick="togglePassword('siswaPassword', 'toggleSiswaPasswordIcon')">
                                        <i class="fas fa-eye" id="toggleSiswaPasswordIcon"></i>
                                    </button>
                                </div>
                                <p id="siswaPasswordInlineError" class="text-xs mt-2 leading-5 {{ $errors->has('password') ? 'text-red-600' : 'hidden' }}">
                                    {{ $errors->has('password') ? $errors->first('password') : '' }}
                                </p>
                                <div class="text-right mt-1">
                                    <button type="button" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700 hover:underline" data-forgot-password-open>
                                        Lupa password?
                                    </button>
                                </div>
                            </div>

                            <div id="siswaSetupPasswordWrap" class="hidden mt-4 space-y-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 ml-1">Password Baru</label>
                                    <div class="relative group">
                                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-600 transition-colors">
                                            <i class="fas fa-key text-base"></i>
                                        </div>
                                        <input type="password" id="siswaNewPassword" name="new_password" disabled class="block w-full pl-11 pr-12 py-3.5 bg-slate-50 border-slate-200 border rounded-xl text-slate-900 text-sm focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all placeholder-slate-400" placeholder="Minimal 8 karakter">
                                        <button type="button" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-indigo-600 transition-colors" onclick="togglePassword('siswaNewPassword', 'toggleSiswaNewPasswordIcon')">
                                            <i class="fas fa-eye" id="toggleSiswaNewPasswordIcon"></i>
                                        </button>
                                    </div>
                                    <p id="siswaNewPasswordInlineError" class="text-xs mt-2 leading-5 {{ $errors->has('new_password') ? 'text-red-600' : 'hidden' }}">
                                        {{ $errors->has('new_password') ? $errors->first('new_password') : '' }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 ml-1">Konfirmasi Password Baru</label>
                                    <div class="relative group">
                                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-600 transition-colors">
                                            <i class="fas fa-check-circle text-base"></i>
                                        </div>
                                        <input type="password" id="siswaNewPasswordConfirmation" name="new_password_confirmation" disabled class="block w-full pl-11 pr-12 py-3.5 bg-slate-50 border-slate-200 border rounded-xl text-slate-900 text-sm focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all placeholder-slate-400" placeholder="Ulangi password baru">
                                        <button type="button" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-indigo-600 transition-colors" onclick="togglePassword('siswaNewPasswordConfirmation', 'toggleSiswaNewPasswordConfirmationIcon')">
                                            <i class="fas fa-eye" id="toggleSiswaNewPasswordConfirmationIcon"></i>
                                        </button>
                                    </div>
                                    <p id="siswaNewPasswordConfirmationInlineError" class="text-xs mt-2 leading-5 {{ $errors->has('new_password_confirmation') ? 'text-red-600' : 'hidden' }}">
                                        {{ $errors->has('new_password_confirmation') ? $errors->first('new_password_confirmation') : '' }}
                                    </p>
                                </div>
                            </div>

                        </div>

                        <div id="formAdminLogin" class="hidden space-y-4 animate-fade-in">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 ml-1">Username</label>
                                <div class="relative group">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-600 transition-colors">
                                        <i class="far fa-user text-base"></i>
                                    </div>
                                    <input type="text" id="username" name="username" class="block w-full pl-11 pr-4 py-3.5 bg-slate-50 border-slate-200 border rounded-xl text-slate-900 text-sm focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all placeholder-slate-400" placeholder="Username akun" value="{{ old('username') }}">
                                </div>
                                @if ($errors->has('username'))
                                    <p class="text-xs text-red-600 mt-2">{{ $errors->first('username') }}</p>
                                @endif
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 ml-1">Password</label>
                                <div class="relative group">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-600 transition-colors">
                                        <i class="fas fa-lock text-base"></i>
                                    </div>
                                    <input type="password" id="password" name="password" class="block w-full pl-11 pr-12 py-3.5 bg-slate-50 border-slate-200 border rounded-xl text-slate-900 text-sm focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all placeholder-slate-400" placeholder="********">
                                    <div class="absolute inset-y-0 right-0 pr-4 flex items-center cursor-pointer text-slate-400 hover:text-indigo-600 transition-colors" onclick="togglePassword('password', 'togglePasswordIcon')">
                                        <i class="fas fa-eye" id="togglePasswordIcon"></i>
                                    </div>
                                </div>
                                @if ($errors->has('password'))
                                    <p class="text-xs text-red-600 mt-2">{{ $errors->first('password') }}</p>
                                @endif
                            </div>

                            <div class="text-right">
                                <button type="button" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700 hover:underline" data-forgot-password-open>
                                    Lupa password?
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <button id="loginSubmitButton" type="submit" class="w-full py-3.5 px-6 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-sm transition-all transform active:scale-[0.98] text-sm flex justify-center items-center group">
                        <span class="inline-flex items-center justify-center gap-2 leading-none">
                            <span data-login-submit-spinner class="hidden inline-flex items-center justify-center" aria-hidden="true">
                                <span class="inline-block w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin"></span>
                            </span>
                            <span data-login-submit-label class="leading-none">Masuk Sekarang</span>
                            <i data-login-submit-icon class="fas fa-arrow-right group-hover:translate-x-1 transition-transform leading-none"></i>
                        </span>
                    </button>
                </form>

                <div id="forgotPasswordModal" class="fixed inset-0 z-[90] hidden">
                    <div class="absolute inset-0 bg-black/40" data-forgot-password-close></div>
                    <div class="relative min-h-full flex items-center justify-center p-4">
                        <div class="w-full max-w-md bg-white rounded-xl shadow-xl border border-gray-200 overflow-hidden">
                            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                                <h4 class="font-bold text-sm text-gray-800">Reset Password via OTP WhatsApp</h4>
                                <button type="button" class="text-gray-400 hover:text-gray-600" data-forgot-password-close aria-label="Tutup">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <form id="forgotPasswordForm" class="p-4 space-y-3" novalidate>
                                <div id="forgotPasswordError" class="hidden px-3 py-2 rounded-lg bg-red-50 border border-red-200 text-red-700 text-xs"></div>
                                <div id="forgotPasswordSuccess" class="hidden px-3 py-2 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs"></div>

                                <div>
                                    <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Username / Email / NIK</label>
                                    <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-2">
                                        <input id="forgotPasswordUsername" type="text" autocomplete="username" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5" placeholder="Username, email, atau NIK">
                                        <button id="forgotPasswordRequestOtp" type="button" class="inline-flex items-center justify-center gap-2 bg-indigo-600 text-white px-3.5 py-2 rounded-lg text-xs font-bold hover:bg-indigo-700 transition whitespace-nowrap">
                                            <span data-forgot-request-spinner class="hidden inline-flex items-center justify-center" aria-hidden="true">
                                                <span class="inline-block w-3.5 h-3.5 border-2 border-white/40 border-t-white rounded-full animate-spin"></span>
                                            </span>
                                            <span data-forgot-request-label>Kirim OTP</span>
                                        </button>
                                    </div>
                                    <p class="text-[11px] text-gray-500 mt-1">Masukkan username, email, atau NIK. Kode OTP akan dikirim ke nomor WhatsApp yang terdaftar pada akun.</p>
                                </div>

                                <div class="border-t border-gray-100 pt-3 space-y-3">
                                    <div>
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Kode OTP</label>
                                        <input id="forgotPasswordOtpCode" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5 tracking-[0.2em]" placeholder="000000">
                                    </div>

                                    <div>
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Password Baru</label>
                                        <input id="forgotPasswordNewPassword" type="password" autocomplete="new-password" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5" placeholder="Minimal 8 karakter">
                                    </div>

                                    <div>
                                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Konfirmasi Password Baru</label>
                                        <input id="forgotPasswordNewPasswordConfirmation" type="password" autocomplete="new-password" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5" placeholder="Ulangi password baru">
                                    </div>

                                    <div class="flex justify-end gap-2">
                                        <button type="button" class="px-3 py-2 rounded-lg border border-gray-300 text-gray-700 text-xs font-semibold hover:bg-gray-50" data-forgot-password-close>Batal</button>
                                        <button id="forgotPasswordResetButton" type="submit" class="inline-flex items-center gap-2 bg-emerald-600 text-white px-3.5 py-2 rounded-lg text-xs font-bold hover:bg-emerald-700 transition">
                                            <span data-forgot-reset-spinner class="hidden inline-flex items-center justify-center" aria-hidden="true">
                                                <span class="inline-block w-3.5 h-3.5 border-2 border-white/40 border-t-white rounded-full animate-spin"></span>
                                            </span>
                                            <span data-forgot-reset-label>Simpan Password Baru</span>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const fallbackLoginRedirect = @json(route('dashboard', absolute: false));
    const siswaLookupUrl = @json(route('login.siswa.lookup'));
    const forgotPasswordOtpRequestUrl = @json(route('password.otp.request'));
    const forgotPasswordOtpResetUrl = @json(route('password.otp.reset'));

    const siswaFlowState = {
        mode: 'initial', // initial | existing | setup
        checkedNisn: '',
    };

    function setInlineError(elementId, message = '') {
        const element = document.getElementById(elementId);
        if (!element) return false;

        const text = String(message || '').trim();
        if (text === '') {
            element.textContent = '';
            element.classList.add('hidden');
            element.classList.remove('text-red-600');
            return false;
        }

        element.textContent = text;
        element.classList.remove('hidden');
        element.classList.add('text-red-600');
        return true;
    }

    function setSiswaHint(message = '') {
        const hint = document.getElementById('siswaFlowHint');
        if (!hint) return;

        const text = String(message || '').trim();
        if (text === '') {
            hint.textContent = '';
            hint.classList.add('hidden');
            return;
        }

        hint.textContent = text;
        hint.classList.remove('hidden');
    }

    function showNisnInlineError(message) {
        return setInlineError('nisnInlineError', message || 'NIK tidak valid.');
    }

    function showSiswaPasswordInlineError(message) {
        return setInlineError('siswaPasswordInlineError', message || 'Password wajib diisi.');
    }

    function showSiswaNewPasswordInlineError(message) {
        return setInlineError('siswaNewPasswordInlineError', message || 'Password baru wajib diisi.');
    }

    function showSiswaNewPasswordConfirmationInlineError(message) {
        return setInlineError('siswaNewPasswordConfirmationInlineError', message || 'Konfirmasi password baru wajib diisi.');
    }

    function clearLoginError() {
        const serverBox = document.getElementById('serverLoginError');
        if (serverBox) {
            serverBox.classList.add('hidden');
        }

        const box = document.getElementById('loginError');
        if (box) {
            box.classList.add('hidden');
        }

        setInlineError('nisnInlineError');
        setInlineError('siswaPasswordInlineError');
        setInlineError('siswaNewPasswordInlineError');
        setInlineError('siswaNewPasswordConfirmationInlineError');
    }

    function showLoginError(message, fieldName = '') {
        const loginType = String(document.getElementById('login_type')?.value || 'siswa');
        const normalizedField = String(fieldName || '').trim().toLowerCase();

        if (loginType === 'siswa') {
            if (normalizedField === 'nisn' || normalizedField === '') {
                if (showNisnInlineError(message)) return;
            }
            if (normalizedField === 'password') {
                setSiswaMode('existing', true);
                if (showSiswaPasswordInlineError(message)) return;
            }
            if (normalizedField === 'new_password') {
                setSiswaMode('setup', true);
                if (showSiswaNewPasswordInlineError(message)) return;
            }
            if (normalizedField === 'new_password_confirmation') {
                setSiswaMode('setup', true);
                if (showSiswaNewPasswordConfirmationInlineError(message)) return;
            }
        }

        const box = document.getElementById('loginError');
        const text = document.getElementById('errorText');
        if (!box || !text) return;
        text.textContent = String(message || 'Login gagal. Silakan coba lagi.');
        box.classList.remove('hidden');
    }

    function extractLoginError(payload) {
        if (payload && typeof payload === 'object' && payload.errors && typeof payload.errors === 'object') {
            const errorEntries = Object.entries(payload.errors);
            for (let i = 0; i < errorEntries.length; i++) {
                const key = String(errorEntries[i][0] || '').trim();
                const value = errorEntries[i][1];
                if (Array.isArray(value) && value.length > 0) {
                    return {
                        field: key,
                        message: String(value[0]),
                    };
                }
                if (typeof value === 'string' && value.trim() !== '') {
                    return {
                        field: key,
                        message: value,
                    };
                }
            }
        }

        if (payload && typeof payload.message === 'string' && payload.message.trim() !== '') {
            return {
                field: '',
                message: payload.message,
            };
        }

        return {
            field: '',
            message: 'Login gagal. Silakan periksa kembali data akun Anda.',
        };
    }

    function isSiswaTabActive() {
        return String(document.getElementById('login_type')?.value || 'siswa') === 'siswa';
    }

    function getLoginSubmitElements() {
        const submitButton = document.getElementById('loginSubmitButton');
        return {
            submitButton,
            submitLabel: submitButton ? submitButton.querySelector('[data-login-submit-label]') : null,
            submitSpinner: submitButton ? submitButton.querySelector('[data-login-submit-spinner]') : null,
            submitIcon: submitButton ? submitButton.querySelector('[data-login-submit-icon]') : null,
        };
    }

    function setLoginSubmitIdleLabel() {
        const { submitLabel } = getLoginSubmitElements();
        if (!submitLabel) return;

        const loginType = String(document.getElementById('login_type')?.value || 'siswa');
        if (loginType === 'admin') {
            submitLabel.textContent = 'Masuk Sekarang';
            return;
        }

        if (siswaFlowState.mode === 'setup') {
            submitLabel.textContent = 'Buat Password & Masuk';
            return;
        }

        if (siswaFlowState.mode === 'existing') {
            submitLabel.textContent = 'Masuk Sekarang';
            return;
        }

        submitLabel.textContent = 'Lanjut';
    }

    function setLoginSubmitLoading(isLoading, loadingText = 'Memproses...') {
        const { submitButton, submitLabel, submitSpinner, submitIcon } = getLoginSubmitElements();
        if (submitButton) {
            submitButton.disabled = isLoading;
            submitButton.classList.toggle('opacity-70', isLoading);
            submitButton.classList.toggle('cursor-not-allowed', isLoading);
        }
        if (submitLabel) {
            submitLabel.textContent = isLoading ? loadingText : submitLabel.textContent;
        }
        if (submitSpinner) {
            submitSpinner.classList.toggle('hidden', !isLoading);
        }
        if (submitIcon) {
            submitIcon.classList.toggle('hidden', isLoading);
        }
    }

    function setSiswaMode(mode, preserveHint = false) {
        const existingWrap = document.getElementById('siswaExistingPasswordWrap');
        const setupWrap = document.getElementById('siswaSetupPasswordWrap');
        const siswaPassword = document.getElementById('siswaPassword');
        const siswaNewPassword = document.getElementById('siswaNewPassword');
        const siswaNewPasswordConfirmation = document.getElementById('siswaNewPasswordConfirmation');
        const adminUsername = document.getElementById('username');
        const adminPassword = document.getElementById('password');
        const siswaActive = isSiswaTabActive();

        siswaFlowState.mode = ['initial', 'existing', 'setup'].includes(mode) ? mode : 'initial';

        if (existingWrap) {
            existingWrap.classList.toggle('hidden', siswaFlowState.mode !== 'existing');
        }
        if (setupWrap) {
            setupWrap.classList.toggle('hidden', siswaFlowState.mode !== 'setup');
        }

        if (siswaPassword) {
            siswaPassword.disabled = !(siswaActive && siswaFlowState.mode === 'existing');
        }
        if (siswaNewPassword) {
            siswaNewPassword.disabled = !(siswaActive && siswaFlowState.mode === 'setup');
        }
        if (siswaNewPasswordConfirmation) {
            siswaNewPasswordConfirmation.disabled = !(siswaActive && siswaFlowState.mode === 'setup');
        }
        if (adminUsername) {
            adminUsername.disabled = siswaActive;
        }
        if (adminPassword) {
            adminPassword.disabled = siswaActive;
        }

        if (!preserveHint && siswaFlowState.mode === 'initial') {
            setSiswaHint('');
        }

        setLoginSubmitIdleLabel();
        window.setTimeout(lockLoginFormsHeight, 0);
    }

    function resetSiswaFlow(clearNisnValue = false) {
        if (clearNisnValue) {
            const nisnInput = document.getElementById('nisn');
            if (nisnInput) {
                nisnInput.value = '';
            }
        }

        siswaFlowState.mode = 'initial';
        siswaFlowState.checkedNisn = '';
        setSiswaHint('');
        setInlineError('siswaPasswordInlineError');
        setInlineError('siswaNewPasswordInlineError');
        setInlineError('siswaNewPasswordConfirmationInlineError');

        const siswaPassword = document.getElementById('siswaPassword');
        const siswaNewPassword = document.getElementById('siswaNewPassword');
        const siswaNewPasswordConfirmation = document.getElementById('siswaNewPasswordConfirmation');
        if (siswaPassword) siswaPassword.value = '';
        if (siswaNewPassword) siswaNewPassword.value = '';
        if (siswaNewPasswordConfirmation) siswaNewPasswordConfirmation.value = '';

        setSiswaMode('initial', false);
    }

    async function lookupSiswaByNisn(nisn) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };
        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }

        const formData = new FormData();
        formData.append('nisn', String(nisn || '').trim());

        const response = await fetch(siswaLookupUrl, {
            method: 'POST',
            headers,
            body: formData,
            credentials: 'same-origin',
        });

        const type = response.headers.get('content-type') || '';
        const payload = type.includes('application/json') ? await response.json() : {};

        return { response, payload };
    }

    function lockLoginFormsHeight() {
        const wrapper = document.getElementById('loginFormsWrap');
        const siswaForm = document.getElementById('formSiswaLogin');
        const adminForm = document.getElementById('formAdminLogin');
        if (!wrapper || !siswaForm || !adminForm) return;

        const forms = [siswaForm, adminForm];
        const snapshots = forms.map((form) => ({
            form,
            wasHidden: form.classList.contains('hidden'),
            position: form.style.position,
            visibility: form.style.visibility,
            pointerEvents: form.style.pointerEvents,
            width: form.style.width,
            left: form.style.left,
            top: form.style.top,
        }));

        snapshots.forEach((snapshot) => {
            if (!snapshot.wasHidden) return;
            snapshot.form.classList.remove('hidden');
            snapshot.form.style.position = 'absolute';
            snapshot.form.style.visibility = 'hidden';
            snapshot.form.style.pointerEvents = 'none';
            snapshot.form.style.width = '100%';
            snapshot.form.style.left = '0';
            snapshot.form.style.top = '0';
        });

        const maxHeight = Math.max(...forms.map((form) => form.offsetHeight), 0);

        snapshots.forEach((snapshot) => {
            const { form } = snapshot;
            if (snapshot.wasHidden) {
                form.classList.add('hidden');
            }
            form.style.position = snapshot.position;
            form.style.visibility = snapshot.visibility;
            form.style.pointerEvents = snapshot.pointerEvents;
            form.style.width = snapshot.width;
            form.style.left = snapshot.left;
            form.style.top = snapshot.top;
        });

        if (maxHeight > 0) {
            wrapper.style.minHeight = `${maxHeight}px`;
        }
    }

    function switchLoginTab(type) {
        const siswaTab = document.getElementById('btnSiswaTab');
        const adminTab = document.getElementById('btnAdminTab');
        const siswaForm = document.getElementById('formSiswaLogin');
        const adminForm = document.getElementById('formAdminLogin');
        const inputType = document.getElementById('login_type');

        if (type === 'siswa') {
            siswaTab.classList.add('bg-white', 'text-indigo-700', 'font-bold', 'ring-1', 'ring-black/5');
            siswaTab.classList.remove('text-slate-500');
            adminTab.classList.remove('bg-white', 'text-indigo-700', 'font-bold', 'ring-1', 'ring-black/5');
            adminTab.classList.add('text-slate-500');
            siswaForm.classList.remove('hidden');
            adminForm.classList.add('hidden');
            if (inputType) inputType.value = 'siswa';
        } else {
            adminTab.classList.add('bg-white', 'text-indigo-700', 'font-bold', 'ring-1', 'ring-black/5');
            adminTab.classList.remove('text-slate-500');
            siswaTab.classList.remove('bg-white', 'text-indigo-700', 'font-bold', 'ring-1', 'ring-black/5');
            siswaTab.classList.add('text-slate-500');
            adminForm.classList.remove('hidden');
            siswaForm.classList.add('hidden');
            if (inputType) inputType.value = 'admin';
        }

        setSiswaMode(siswaFlowState.mode, true);
        setLoginSubmitIdleLabel();
        clearLoginError();
        window.setTimeout(lockLoginFormsHeight, 0);
    }

    function togglePassword(inputId = 'password', iconId = 'togglePasswordIcon') {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        if (!input) return;
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        if (icon) {
            icon.classList.toggle('fa-eye', !isPassword);
            icon.classList.toggle('fa-eye-slash', isPassword);
        }
    }

    function initLoginMotivation() {
        const target = document.getElementById('loginMotivationText');
        if (!target) return;

        const motivations = [
                'Setiap kehadiran hari ini adalah kontribusi nyata bagi kemajuan perusahaan.',
                'Disiplin kerja dan hadir tepat waktu adalah fondasi profesionalisme sejati.',
                'Kantor bukan hanya tempat bekerja, tetapi tempat membangun karier dan dampak.',
                'Keterampilan yang diasah hari ini akan membuka peluang karier di masa depan.',
                'Konsistensi hadir dan bekerja keras akan memperkuat reputasi serta kompetensi.',
                'Semangat kerja yang tinggi dimulai dari kedisiplinan setiap harinya.',
                'Datang ke kantor tepat waktu adalah bentuk tanggung jawab kepada tim dan perusahaan.',
                'Prestasi karier lahir dari kebiasaan profesional yang dilakukan terus-menerus.',
                'Bekerja dengan tekun hari ini adalah investasi terbaik untuk masa depan kariermu.',
                'Langkah sederhana di tempat kerja hari ini dapat menjadi pencapaian besar di masa depan.',
            ];

        let currentIndex = Math.floor(Math.random() * motivations.length);
        target.textContent = motivations[currentIndex];

        if (motivations.length < 2) {
            return;
        }

        window.setInterval(() => {
            let nextIndex = currentIndex;
            while (nextIndex === currentIndex) {
                nextIndex = Math.floor(Math.random() * motivations.length);
            }
            currentIndex = nextIndex;

            target.classList.add('opacity-0');
            window.setTimeout(() => {
                target.textContent = motivations[currentIndex];
                target.classList.remove('opacity-0');
            }, 180);
        }, 7000);
    }

    function extractApiErrorMessage(payload, fallbackMessage) {
        if (payload && typeof payload === 'object' && payload.errors && typeof payload.errors === 'object') {
            const values = Object.values(payload.errors);
            for (let i = 0; i < values.length; i++) {
                const row = values[i];
                if (Array.isArray(row) && row.length > 0) {
                    return String(row[0]);
                }
                if (typeof row === 'string' && row.trim() !== '') {
                    return row;
                }
            }
        }

        if (payload && typeof payload.message === 'string' && payload.message.trim() !== '') {
            return payload.message;
        }

        return fallbackMessage;
    }

    function initForgotPasswordModal() {
        const modal = document.getElementById('forgotPasswordModal');
        const openButtons = Array.from(document.querySelectorAll('[data-forgot-password-open]'));
        const closeButtons = Array.from(document.querySelectorAll('[data-forgot-password-close]'));
        const form = document.getElementById('forgotPasswordForm');
        const usernameField = document.getElementById('forgotPasswordUsername');
        const otpField = document.getElementById('forgotPasswordOtpCode');
        const passwordField = document.getElementById('forgotPasswordNewPassword');
        const passwordConfirmField = document.getElementById('forgotPasswordNewPasswordConfirmation');
        const errorBox = document.getElementById('forgotPasswordError');
        const successBox = document.getElementById('forgotPasswordSuccess');
        const requestButton = document.getElementById('forgotPasswordRequestOtp');
        const resetButton = document.getElementById('forgotPasswordResetButton');
        const requestLabel = requestButton ? requestButton.querySelector('[data-forgot-request-label]') : null;
        const requestSpinner = requestButton ? requestButton.querySelector('[data-forgot-request-spinner]') : null;
        const resetLabel = resetButton ? resetButton.querySelector('[data-forgot-reset-label]') : null;
        const resetSpinner = resetButton ? resetButton.querySelector('[data-forgot-reset-spinner]') : null;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        if (!modal || !form || !usernameField || !otpField || !passwordField || !passwordConfirmField) {
            return;
        }

        const hideFeedback = () => {
            if (errorBox) {
                errorBox.classList.add('hidden');
                errorBox.textContent = '';
            }
            if (successBox) {
                successBox.classList.add('hidden');
                successBox.textContent = '';
            }
        };

        const showError = (message) => {
            if (!errorBox) return;
            errorBox.textContent = String(message || 'Terjadi kesalahan.');
            errorBox.classList.remove('hidden');
            if (successBox) {
                successBox.classList.add('hidden');
                successBox.textContent = '';
            }
        };

        const showSuccess = (message) => {
            if (!successBox) return;
            successBox.textContent = String(message || 'Berhasil.');
            successBox.classList.remove('hidden');
            if (errorBox) {
                errorBox.classList.add('hidden');
                errorBox.textContent = '';
            }
        };

        const setLoadingState = (mode, isLoading) => {
            if (mode === 'request' && requestButton) {
                requestButton.disabled = isLoading;
                requestButton.classList.toggle('opacity-75', isLoading);
                requestButton.classList.toggle('cursor-not-allowed', isLoading);
                if (requestLabel) {
                    requestLabel.textContent = isLoading ? 'Mengirim OTP...' : 'Kirim OTP';
                }
                if (requestSpinner) {
                    requestSpinner.classList.toggle('hidden', !isLoading);
                }
            }

            if (mode === 'reset' && resetButton) {
                resetButton.disabled = isLoading;
                resetButton.classList.toggle('opacity-75', isLoading);
                resetButton.classList.toggle('cursor-not-allowed', isLoading);
                if (resetLabel) {
                    resetLabel.textContent = isLoading ? 'Menyimpan...' : 'Simpan Password Baru';
                }
                if (resetSpinner) {
                    resetSpinner.classList.toggle('hidden', !isLoading);
                }
            }
        };

        const resetModalForm = () => {
            form.reset();
            hideFeedback();
            setLoadingState('request', false);
            setLoadingState('reset', false);
        };

        const resolveDefaultIdentifier = () => {
            const loginType = String(document.getElementById('login_type')?.value || 'siswa');
            if (loginType === 'siswa') {
                return String(document.getElementById('nisn')?.value || '').trim();
            }

            return String(document.getElementById('username')?.value || '').trim();
        };

        const openModal = (prefillIdentifier = '') => {
            resetModalForm();
            const finalIdentifier = String(prefillIdentifier || '').trim();
            if (finalIdentifier !== '') {
                usernameField.value = finalIdentifier;
            }
            modal.classList.remove('hidden');
            window.setTimeout(() => usernameField.focus(), 50);
        };

        const closeModal = () => {
            modal.classList.add('hidden');
            resetModalForm();
        };

        const postForm = async (url, dataObject) => {
            const headers = {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            };
            if (csrfToken) {
                headers['X-CSRF-TOKEN'] = csrfToken;
            }

            const formData = new FormData();
            Object.keys(dataObject).forEach((key) => {
                formData.append(key, String(dataObject[key] ?? ''));
            });

            const response = await fetch(url, {
                method: 'POST',
                headers,
                body: formData,
                credentials: 'same-origin',
            });

            const type = response.headers.get('content-type') || '';
            const payload = type.includes('application/json') ? await response.json() : {};

            return { response, payload };
        };

        openButtons.forEach((button) => {
            button.addEventListener('click', () => {
                openModal(resolveDefaultIdentifier());
            });
        });

        closeButtons.forEach((button) => {
            button.addEventListener('click', closeModal);
        });

        if (requestButton) {
            requestButton.addEventListener('click', async () => {
                hideFeedback();

                const username = String(usernameField.value || '').trim();
                if (username === '') {
                    showError('Username, email, atau NIK wajib diisi.');
                    usernameField.focus();
                    return;
                }

                setLoadingState('request', true);
                try {
                    const { response, payload } = await postForm(forgotPasswordOtpRequestUrl, {
                        username,
                    });

                    if (!response.ok) {
                        showError(extractApiErrorMessage(payload, 'Gagal mengirim OTP.'));
                        return;
                    }

                    showSuccess(String(payload.message || 'Kode OTP berhasil dikirim ke WhatsApp Anda.'));
                    otpField.focus();
                } catch (error) {
                    showError('Gagal terhubung ke server. Silakan coba lagi.');
                } finally {
                    setLoadingState('request', false);
                }
            });
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            hideFeedback();

            const username = String(usernameField.value || '').trim();
            const otpCode = String(otpField.value || '').trim();
            const password = String(passwordField.value || '');
            const passwordConfirmation = String(passwordConfirmField.value || '');

            if (username === '') {
                showError('Username, email, atau NISN wajib diisi.');
                usernameField.focus();
                return;
            }

            if (otpCode.length !== 6) {
                showError('Kode OTP harus 6 digit.');
                otpField.focus();
                return;
            }

            if (password.length < 8) {
                showError('Password baru minimal 8 karakter.');
                passwordField.focus();
                return;
            }

            if (password !== passwordConfirmation) {
                showError('Konfirmasi password tidak sama.');
                passwordConfirmField.focus();
                return;
            }

            setLoadingState('reset', true);
            try {
                const { response, payload } = await postForm(forgotPasswordOtpResetUrl, {
                    username,
                    otp_code: otpCode,
                    password,
                    password_confirmation: passwordConfirmation,
                });

                if (!response.ok) {
                    showError(extractApiErrorMessage(payload, 'Gagal memproses reset password.'));
                    return;
                }

                showSuccess(String(payload.message || 'Password berhasil diperbarui.'));

                switchLoginTab('admin');
                const loginUsername = document.getElementById('username');
                if (loginUsername && !username.includes('@')) {
                    loginUsername.value = username;
                }
                const loginPassword = document.getElementById('password');
                if (loginPassword) {
                    loginPassword.value = '';
                }

                window.setTimeout(() => {
                    closeModal();
                }, 1200);
            } catch (error) {
                showError('Gagal terhubung ke server. Silakan coba lagi.');
            } finally {
                setLoadingState('reset', false);
            }
        });
    }

    async function handleAjaxLoginSubmit(event) {
        event.preventDefault();

        const form = event.currentTarget;
        if (!form) return;

        clearLoginError();

        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
        const headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };
        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }

        const loginType = String(document.getElementById('login_type')?.value || 'siswa');

        if (loginType === 'siswa') {
            const nisnInput = document.getElementById('nisn');
            const nisn = String(nisnInput?.value || '').trim();
            if (nisn === '') {
                showNisnInlineError('NIK wajib diisi.');
                if (nisnInput) nisnInput.focus();
                return;
            }

            if (siswaFlowState.mode === 'initial' || siswaFlowState.checkedNisn !== nisn) {
                setLoginSubmitLoading(true, 'Memeriksa NIK...');
                try {
                    const { response, payload } = await lookupSiswaByNisn(nisn);
                    if (!response.ok) {
                        const errorInfo = extractLoginError(payload);
                        showLoginError(errorInfo.message, errorInfo.field || 'nisn');
                        return;
                    }

                    const mode = String(payload.mode || '').trim().toLowerCase();
                    if (mode === 'existing') {
                        siswaFlowState.checkedNisn = nisn;
                        setSiswaMode('existing', false);
                        setSiswaHint(String(payload.message || ''));
                        const siswaPassword = document.getElementById('siswaPassword');
                        if (siswaPassword) siswaPassword.focus();
                        return;
                    }

                    siswaFlowState.checkedNisn = nisn;
                    setSiswaMode('setup', false);
                    setSiswaHint(String(payload.message || 'Password belum dibuat, Silahkan buat baru.'));
                    const siswaNewPassword = document.getElementById('siswaNewPassword');
                    if (siswaNewPassword) siswaNewPassword.focus();
                    return;
                } catch (error) {
                    showLoginError('Gagal terhubung ke server. Silakan coba beberapa saat lagi.');
                    return;
                } finally {
                    setLoginSubmitLoading(false);
                    setLoginSubmitIdleLabel();
                }
            }

            if (siswaFlowState.mode === 'existing') {
                const siswaPassword = document.getElementById('siswaPassword');
                const value = String(siswaPassword?.value || '');
                if (value.trim() === '') {
                    showSiswaPasswordInlineError('Password wajib diisi.');
                    if (siswaPassword) siswaPassword.focus();
                    return;
                }
            }

            if (siswaFlowState.mode === 'setup') {
                const siswaNewPassword = document.getElementById('siswaNewPassword');
                const siswaNewPasswordConfirmation = document.getElementById('siswaNewPasswordConfirmation');
                const password = String(siswaNewPassword?.value || '');
                const confirmation = String(siswaNewPasswordConfirmation?.value || '');

                if (password.length < 8) {
                    showSiswaNewPasswordInlineError('Password baru minimal 8 karakter.');
                    if (siswaNewPassword) siswaNewPassword.focus();
                    return;
                }

                if (password !== confirmation) {
                    showSiswaNewPasswordConfirmationInlineError('Konfirmasi password baru tidak sama.');
                    if (siswaNewPasswordConfirmation) siswaNewPasswordConfirmation.focus();
                    return;
                }
            }
        }

        setLoginSubmitLoading(true, 'Memproses...');
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers,
                body: new FormData(form),
                credentials: 'same-origin',
            });

            const responseType = response.headers.get('content-type') || '';
            let payload = {};
            if (responseType.includes('application/json')) {
                payload = await response.json();
            }

            if (response.ok) {
                const redirectUrl = payload.redirect ? String(payload.redirect) : fallbackLoginRedirect;
                window.location.assign(redirectUrl);
                return;
            }

            const errorInfo = extractLoginError(payload);
            showLoginError(errorInfo.message, errorInfo.field);
        } catch (error) {
            showLoginError('Gagal terhubung ke server. Silakan coba beberapa saat lagi.');
        } finally {
            setLoginSubmitLoading(false);
            setLoginSubmitIdleLabel();
        }
    }

    function initAjaxLogin() {
        const form = document.getElementById('loginForm');
        if (!form) return;
        form.addEventListener('submit', handleAjaxLoginSubmit);
    }

    function initLoginFieldErrorClear() {
        const form = document.getElementById('loginForm');
        if (!form) return;

        const fields = form.querySelectorAll('input[type="text"], input[type="password"], input[type="number"]');
        fields.forEach((field) => {
            field.addEventListener('input', clearLoginError);
            field.addEventListener('change', clearLoginError);
        });

        const nisnField = document.getElementById('nisn');
        if (nisnField) {
            nisnField.addEventListener('input', () => {
                const nisnValue = String(nisnField.value || '').trim();
                if (siswaFlowState.checkedNisn !== '' && nisnValue !== siswaFlowState.checkedNisn) {
                    resetSiswaFlow(false);
                }
            });
        }
    }

    function initLoginPage() {
        initLoginMotivation();
        initAjaxLogin();
        initLoginFieldErrorClear();
        initForgotPasswordModal();
        resetSiswaFlow(false);
        setLoginSubmitIdleLabel();
        lockLoginFormsHeight();
        window.addEventListener('resize', lockLoginFormsHeight);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLoginPage);
    } else {
        initLoginPage();
    }
</script>
@endpush
