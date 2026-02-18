<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Instrument Sans", sans-serif;
            background: #f4f6f8;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1f2937;
        }

        .card {
            background: #ffffff;
            padding: 48px 55px;
            border-radius: 16px;
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.06);
            text-align: center;
            min-width: 340px;
            border: 1px solid #eef0f3;
        }

        .app-logo {
            width: 72px;
            height: 72px;
            object-fit: contain;
            margin-bottom: 18px;
            border-radius: 12px;
        }

        .app-name {
            font-size: 26px;
            font-weight: 600;
            margin-bottom: 14px;
            letter-spacing: 0.2px;
        }

        .status {
            display: inline-flex;
            align-items: center;
            font-size: 14px;
            font-weight: 500;
            color: #16a34a;
            background: #ecfdf5;
            padding: 6px 12px;
            border-radius: 999px;
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #16a34a;
            margin-right: 8px;
        }

        @media (max-width:480px) {
            .card {
                padding: 35px 30px;
                min-width: auto;
                width: 90%;
            }
        }
    </style>
</head>

<body>

    <div class="card">
        <img src="{{ asset('images/logo.jpeg') }}" alt="App Logo" class="app-logo">

        <div class="app-name">
            {{ config('app.name') }}
        </div>

        <div class="status">
            <span class="dot"></span>
            Service is Online
        </div>
    </div>

</body>

</html>
