@extends('layouts.template')
    
@section('navbar-mobile-toggle', '<!-- No nav toggle -->')
@section('navbar-sections', '<!-- No navbar sections -->')

@section('main')
    <div id="main" class="flares-main">
        <div class="page-header">
            <div class="container">
            @yield('heading')				
            </div>
        </div>
        <div class="container">
            {{-- Banners are hero units below the nav but above other content --}}
            @yield('banner')
            
            {{-- Render notifications or alerts --}}
            @yield('alerts')
            
            {{-- The main screen functionality --}}
            @yield('content')
        </div>
    </div>
@endsection

@section('angular-scripts', '<!-- No angular scripts -->')
