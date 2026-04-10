<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Dynamic Quiz System' }}</title>
    <style>
        :root {
            --bg: #f2f6ff;
            --card: #ffffff;
            --text: #13213b;
            --muted: #5a6580;
            --primary: #0f62fe;
            --primary-2: #133e7c;
            --ok: #0b875b;
            --danger: #b42318;
            --border: #dbe4ff;
            --shadow: 0 16px 40px rgba(8, 32, 89, 0.12);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background:
                radial-gradient(circle at 10% 20%, #c9deff 0%, transparent 40%),
                radial-gradient(circle at 90% 10%, #ffe8d1 0%, transparent 35%),
                linear-gradient(120deg, #f0f5ff 0%, #f9fbff 50%, #eef7ff 100%);
            color: var(--text);
            min-height: 100vh;
        }

        .wrap {
            max-width: 1080px;
            margin: 0 auto;
            padding: 24px 20px 60px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            gap: 16px;
        }

        .brand {
            text-decoration: none;
            color: var(--primary-2);
            font-weight: 800;
            letter-spacing: 0.4px;
            font-size: 1.25rem;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: var(--shadow);
            padding: 22px;
            animation: fadeUp .35s ease both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        h1, h2, h3 {
            margin-top: 0;
            line-height: 1.2;
        }

        .muted { color: var(--muted); }
        .grid { display: grid; gap: 16px; }
        .grid-2 { grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }

        .row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 0.95rem;
        }

        input[type="text"], input[type="number"], input[type="url"], textarea, select {
            width: 100%;
            border: 1px solid #c9d7ff;
            border-radius: 10px;
            padding: 10px 12px;
            font: inherit;
            color: var(--text);
            background: #fcfdff;
        }

        textarea { min-height: 110px; resize: vertical; }

        .btn {
            border: none;
            border-radius: 10px;
            padding: 10px 14px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary { background: var(--primary); color: #fff; }
        .btn-secondary { background: #eaf0ff; color: var(--primary-2); }
        .btn-danger { background: #ffe5e2; color: var(--danger); }
        .btn-success { background: #daf6eb; color: #0f6848; }

        .chip {
            border-radius: 999px;
            padding: 5px 10px;
            background: #e8efff;
            color: var(--primary-2);
            font-size: .82rem;
            font-weight: 700;
        }

        .alert {
            border-radius: 10px;
            padding: 11px 12px;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .alert-success { background: #dcfce7; color: var(--ok); border: 1px solid #b7f2ca; }
        .alert-error { background: #ffe2e0; color: var(--danger); border: 1px solid #ffbeb8; }

        .question-preview iframe { width: 100%; max-width: 480px; height: 260px; border: 0; border-radius: 12px; }
        .question-preview img, .option-media { max-width: 220px; border-radius: 10px; border: 1px solid #d5e1ff; }

        .hr { height: 1px; background: #e4ebff; margin: 15px 0; }

        @media (max-width: 640px) {
            .wrap { padding: 18px 14px 40px; }
            .card { padding: 16px; border-radius: 14px; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="topbar">
        <a href="{{ route('quizzes.index') }}" class="brand">Dynamic Quiz System</a>
        <a class="btn btn-primary" href="{{ route('quizzes.create') }}">Create Quiz</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-error">
            <div>Please fix the following issues:</div>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</div>
</body>
</html>

