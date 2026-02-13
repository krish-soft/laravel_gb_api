<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>@yield('title', config('app.name'))</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Bootstrap 5 --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f4f6f9;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* ================= HEADER ================= */
        .app-header {
            background: #1f6f3d;
            /* dark farmer green */
            color: #fff;
            border-bottom: 3px solid #14532d;
        }

        .app-header .app-name {
            font-weight: 600;
            font-size: 18px;
            letter-spacing: 0.4px;
        }

        .app-logo {
            height: 38px;
            width: auto;
            margin-right: 10px;
        }

        /* ================= MAIN ================= */
        .main-wrapper {
            flex: 1;
        }

        .card-form {
            max-width: 640px;
            margin: auto;
            border-radius: 12px;
        }

        /* ================= FOOTER ================= */
        .app-footer {
            background: #ffffff;
            border-top: 1px solid #e5e7eb;
            font-size: 13px;
            color: #6b7280;
        }
    </style>

    @stack('head')
</head>

<body>

    {{-- ================= TOP BAR ================= --}}
    <div class="app-header py-2">
        <div class="container d-flex align-items-center">

            {{-- LOGO --}}
            <img src="{{ asset('images/logo.jpeg') }}" class="app-logo" alt="Logo">

            {{-- APP NAME --}}
            <div class="app-name">
                {{ config('app.name') }}
            </div>

        </div>
    </div>

    {{-- ================= CONTENT ================= --}}
    <div class="container py-4 main-wrapper">

        {{-- Flash messages --}}
        @if (session('success'))
            <div class="alert alert-success shadow-sm">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger shadow-sm">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')

    </div>

    {{-- ================= FOOTER ================= --}}
    <div class="app-footer py-3">
        <div class="container text-end">

            © {{ date('Y') }} {{ config('app.name') }}

          

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    @stack('scripts')

</body>

</html>
