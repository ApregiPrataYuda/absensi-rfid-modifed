@extends('layouts.page')

@section('title', 'Dashboard Guru')

@section('content')
<div id="view-guru-dashboard" class="view-section active animate-fade-in">
                  
                  <div class="flex flex-col md:flex-row justify-between items-end mb-8 gap-4">
                      <div>
                           <h2 class="text-2xl font-bold text-gray-800 tracking-tight">Dashboard Guru</h2>
                           <p class="text-sm text-gray-500 mt-1">Ringkasan aktivitas siswa hari ini.</p>
                      </div>
                      <div class="flex items-center gap-3">
                          <span class="text-xs font-bold bg-indigo-50 text-indigo-700 px-3 py-1.5 rounded-lg border border-indigo-100">
                              <i class="far fa-calendar-alt mr-2"></i> <span id="guruDashboardDate">...</span>
                          </span>
                          <button onclick="refreshData('dashboard')" class="flex items-center space-x-2 text-xs font-bold text-gray-600 bg-white border border-gray-200 px-4 py-2 rounded-lg shadow-sm hover:bg-gray-50 transition">
                              <i class="fas fa-sync-alt"></i> <span>Refresh</span>
                          </button>
                      </div>
                  </div>

                  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
                      <div class="bg-white p-5 rounded-xl shadow-sm border border-indigo-100 flex flex-col justify-between relative overflow-hidden group">
                          <div class="absolute right-0 top-0 w-16 h-16 bg-indigo-50 rounded-bl-full -mr-2 -mt-2 transition-transform group-hover:scale-110"></div>
                          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest relative z-10">Total Siswa</p>
                          <div class="flex items-center justify-between mt-2 relative z-10">
                              <h3 id="statGuruTotal" class="text-2xl font-bold text-gray-800">-</h3>
                              <div class="text-indigo-500 bg-indigo-50 p-2 rounded-lg"><i class="fas fa-user-graduate"></i></div>
                          </div>
                      </div>

                      <div class="bg-white p-5 rounded-xl shadow-sm border border-emerald-100 flex flex-col justify-between relative overflow-hidden group">
                          <div class="absolute right-0 top-0 w-16 h-16 bg-emerald-50 rounded-bl-full -mr-2 -mt-2 transition-transform group-hover:scale-110"></div>
                          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest relative z-10">Hadir</p>
                          <div class="flex items-center justify-between mt-2 relative z-10">
                              <h3 id="statGuruHadir" class="text-2xl font-bold text-gray-800">-</h3>
                              <div class="text-emerald-500 bg-emerald-50 p-2 rounded-lg"><i class="fas fa-check"></i></div>
                          </div>
                      </div>

                      <div class="bg-white p-5 rounded-xl shadow-sm border border-yellow-100 flex flex-col justify-between relative overflow-hidden group">
                          <div class="absolute right-0 top-0 w-16 h-16 bg-yellow-50 rounded-bl-full -mr-2 -mt-2 transition-transform group-hover:scale-110"></div>
                          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest relative z-10">Sakit</p>
                          <div class="flex items-center justify-between mt-2 relative z-10">
                              <h3 id="statGuruSakit" class="text-2xl font-bold text-gray-800">-</h3>
                              <div class="text-yellow-500 bg-yellow-50 p-2 rounded-lg"><i class="fas fa-procedures"></i></div>
                          </div>
                      </div>

                      <div class="bg-white p-5 rounded-xl shadow-sm border border-blue-100 flex flex-col justify-between relative overflow-hidden group">
                          <div class="absolute right-0 top-0 w-16 h-16 bg-blue-50 rounded-bl-full -mr-2 -mt-2 transition-transform group-hover:scale-110"></div>
                          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest relative z-10">Izin</p>
                          <div class="flex items-center justify-between mt-2 relative z-10">
                              <h3 id="statGuruIzin" class="text-2xl font-bold text-gray-800">-</h3>
                              <div class="text-blue-500 bg-blue-50 p-2 rounded-lg"><i class="fas fa-paper-plane"></i></div>
                          </div>
                      </div>

                      <div class="bg-white p-5 rounded-xl shadow-sm border border-red-100 flex flex-col justify-between relative overflow-hidden group">
                          <div class="absolute right-0 top-0 w-16 h-16 bg-red-50 rounded-bl-full -mr-2 -mt-2 transition-transform group-hover:scale-110"></div>
                          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest relative z-10">Alpa</p>
                          <div class="flex items-center justify-between mt-2 relative z-10">
                              <h3 id="statGuruAlpa" class="text-2xl font-bold text-gray-800">-</h3>
                              <div class="text-red-500 bg-red-50 p-2 rounded-lg"><i class="fas fa-times-circle"></i></div>
                          </div>
                      </div>
                  </div>

                  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                      <div class="lg:col-span-2 bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
                          <h3 class="text-sm font-bold text-gray-700 mb-6 flex items-center justify-between">
                              <span><i class="fas fa-chart-bar text-indigo-500 mr-2"></i> Statistik Kehadiran Hari Ini</span>
                              <span class="text-[10px] bg-gray-100 text-gray-500 px-2 py-1 rounded">Realtime</span>
                          </h3>
                          <div class="relative w-full h-[300px]">
                              <canvas id="guruAttendanceChart"></canvas>
                          </div>
                      </div>

                      <div class="bg-gradient-to-br from-indigo-600 to-purple-700 rounded-2xl p-6 text-white shadow-xl shadow-indigo-200 relative overflow-hidden flex flex-col justify-center items-center text-center">
                          <div class="absolute top-0 left-0 w-full h-full bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-10"></div>
                          <div class="relative z-10">
                              <div class="w-16 h-16 bg-white/20 backdrop-blur-md rounded-full flex items-center justify-center text-2xl mb-4 mx-auto border border-white/20">
                                  <i class="fas fa-qrcode"></i>
                              </div>
                              <h3 class="text-lg font-bold mb-2">Mulai Absensi</h3>
                              <p class="text-indigo-100 text-xs mb-6 px-4">Buka pemindai kamera untuk melakukan absensi siswa secara cepat.</p>
                              <a href="{{ route('scanner') }}" class="bg-white text-indigo-700 px-6 py-3 rounded-xl font-bold text-sm shadow-lg hover:bg-gray-50 transition transform active:scale-95 w-full text-center">
                                  Buka Scanner
                              </a>
                          </div>
                      </div>
                  </div>
              </div>
@endsection

@push('scripts')
@include('pages.scripts.dashboard-guru')
@endpush
