@extends('layouts.main')

@section('body')
  @php
      $isStudentAppUser = auth()->user()?->hasRole('siswa') ?? false;
  @endphp
  <div id="dashboardContainer" class="fixed inset-0 flex h-[100dvh] bg-[#F3F4F6] overflow-hidden">
      
      @include('partials.sidebar')

      <div id="mobileOverlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-30 hidden transition-opacity duration-300 opacity-0"></div>

      <main id="mainContent" class="flex-1 flex flex-col md:ml-64 h-full min-h-0 relative transition-all duration-300 bg-[#F3F4F6] overflow-hidden">
          
          @include('partials.header')

          <div id="mainContentArea" class="flex-1 min-h-0 overflow-x-hidden overflow-y-auto overscroll-contain p-3 sm:p-4 md:p-6 {{ $isStudentAppUser ? 'pb-28 md:pb-8' : 'pb-6 md:pb-8' }} scroll-smooth">
              @yield('content')
          </div>
      </main>
  </div>

  @if ($isStudentAppUser)
    @include('partials.student-mobile-nav')
  @endif

  <div id="loadingOverlay" class="fixed inset-0 bg-slate-900/20 backdrop-blur-sm z-50 hidden flex items-center justify-center flex-col transition-opacity">
    <div class="p-5 bg-white rounded-2xl shadow-2xl flex flex-col items-center">
        <div class="w-10 h-10 border-4 border-indigo-100 border-t-indigo-600 rounded-full animate-spin mb-3"></div>
        <p class="text-slate-800 text-xs font-bold tracking-wide">Memproses...</p>
    </div>
  </div>
  
  <div id="modalContainer"></div>
@endsection
