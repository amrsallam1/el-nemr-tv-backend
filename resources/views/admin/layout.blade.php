<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'لوحة El-Nemr TV')</title>
    <style>
        :root{--bg:#080b12;--panel:#111726;--line:#25304a;--text:#f5f7ff;--muted:#9da8c2;--brand:#14b8c8;--danger:#ef5350}
        *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font-family:Tahoma,Arial,sans-serif}
        a{color:inherit;text-decoration:none}.shell{display:grid;grid-template-columns:230px 1fr;min-height:100vh}
        aside{background:#0d1220;border-left:1px solid var(--line);padding:28px 18px}.brand{color:var(--brand);font-size:24px;font-weight:bold;margin-bottom:35px}
        nav a{display:block;padding:13px 15px;border-radius:9px;margin:7px 0;color:var(--muted)}nav a:hover{background:var(--panel);color:white}
        main{padding:32px;max-width:1400px;width:100%}.top{display:flex;justify-content:space-between;align-items:center;margin-bottom:26px}
        .panel,.card{background:var(--panel);border:1px solid var(--line);border-radius:14px}.panel{padding:20px}.cards{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
        .developer{margin-top:-27px;margin-bottom:32px;color:#fff;font-size:14px;font-weight:bold;letter-spacing:.4px}.developer span{color:var(--brand)}
        .footer{margin-top:28px;padding:18px;text-align:center;color:var(--muted);border-top:1px solid var(--line)}.footer strong{color:var(--brand)}
        .card{padding:20px}.card strong{display:block;font-size:30px;color:var(--brand);margin-top:8px}
        table{width:100%;border-collapse:collapse}th,td{text-align:right;padding:13px;border-bottom:1px solid var(--line)}th{color:var(--muted)}
        .btn{display:inline-block;border:0;border-radius:8px;padding:10px 16px;background:var(--brand);color:#041014;cursor:pointer;font-weight:bold}.btn.secondary{background:#26334f;color:white}.btn.danger{background:var(--danger);color:white}
        input,select,textarea{width:100%;background:#0a0f1b;color:white;border:1px solid var(--line);border-radius:8px;padding:11px}textarea{min-height:120px}
        label{display:block;color:var(--muted);margin-bottom:7px}.grid{display:grid;grid-template-columns:repeat(2,1fr);gap:18px}.full{grid-column:1/-1}
        .flash{padding:12px 16px;background:#103c35;border-radius:8px;margin-bottom:18px}.error{color:#ff8a80;font-size:13px}.muted{color:var(--muted)}
        @media(max-width:800px){.shell{display:block}aside{display:none}.cards,.grid{grid-template-columns:1fr}main{padding:18px}}
    </style>
</head>
<body>
<div class="shell">
    <aside>
        <div class="brand">El-Nemr TV</div>
        <div class="developer">Dev by <span>Dr Amr</span></div>
        <nav>
            <a href="{{ route('admin.dashboard') }}">الرئيسية</a>
            <a href="{{ route('admin.media.index') }}">المحتوى</a>
            <a href="{{ route('admin.media.create') }}">إضافة محتوى</a>
        </nav>
    </aside>
    <main>
        <div class="top"><h1>@yield('heading')</h1><form method="post" action="{{ route('admin.logout') }}">@csrf<button class="btn secondary">خروج</button></form></div>
        @if(session('success'))<div class="flash">{{ session('success') }}</div>@endif
        @yield('content')
        <div class="footer">El-Nemr TV Control Panel — <strong>Dev by Dr Amr</strong></div>
    </main>
</div>
</body>
</html>
