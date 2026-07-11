<!DOCTYPE html>

@if($language)
    @if ($language->rtl)
        <html lang="en" dir="rtl">
    @else
        <html lang="en">
    @endif
@else
    <html lang="en">
@endif

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="shortcut icon" href="{{ url('assets/images/logo/' . (system_setting('favicon_icon') ?? null)) }}" type="image/x-icon">
    <title>@yield('title') || {{ config('app.name') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    @include('layouts.include')
    <link rel="stylesheet" href="{{ asset('assets/css/sidebar-responsive.css') }}">
    @yield('css')
    
    <!-- Global Language Data for TinyMCE RTL Support -->
    <script>
        window.globalLanguageData = {
            current: {
                rtl: {{ $language && $language->rtl ? 'true' : 'false' }},
                code: "{{ $language ? $language->code : 'en' }}",
                name: "{{ $language ? $language->name : 'English' }}"
            },
            all: {
                @if(isset($allLanguages) && $allLanguages->count() > 0)
                    @foreach($allLanguages as $lang)
                        "{{ $lang->id }}": {
                            "rtl": {{ $lang->rtl ? 'true' : 'false' }},
                            "code": "{{ $lang->code }}",
                            "name": "{{ $lang->name }}"
                        }{{ $loop->last ? '' : ',' }}
                    @endforeach
                @endif
            }
        };
    </script>
</head>

<body>
    <div id="app">
        @include('layouts.sidebar')
        
        <!-- Mobile sidebar overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <div id="main" class='layout-navbar'>
            @include('layouts.topbar')
            <div id="main-content">
                <div class="page-heading">

                    @yield('page-title')
                </div>
                @yield('content')

            </div>

        </div>
        <div class="wrapper mt-5">
            <div class="content">
                @include('layouts.footer')

                <!-- Your page content here -->
            </div>
        </div>
        {{-- <div>
            @include('layouts.footer')
        </div> --}}
    </div>

    @include('layouts.footer_script')
    @yield('js')
    @yield('script')
</body>

</html>
