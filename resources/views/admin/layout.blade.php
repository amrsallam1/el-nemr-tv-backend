<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'لوحة El-Nemr TV')</title>
    <style>
        :root{--bg:#f4f7fb;--surface:#fff;--surface-2:#f7f9fc;--ink:#111827;--muted:#667085;--line:#e5eaf1;--nav:#0c0e12;--brand:#06b6c9;--brand-dark:#087f8c;--navy:#182536;--danger:#dc3545;--success:#15966a;--warning:#c47a05;--shadow:0 12px 36px rgba(16,24,40,.08)}
        *{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:var(--bg);color:var(--ink);font-family:Tahoma,"Segoe UI",Arial,sans-serif;font-size:14px}a{color:inherit;text-decoration:none}button,input,select,textarea{font:inherit}
        .shell{display:grid;grid-template-columns:1fr 258px;min-height:100vh}.sidebar{grid-column:2;background:var(--nav);color:#aab2c0;padding:0 14px 28px;position:sticky;top:0;height:100vh;overflow:auto}.brand{height:76px;display:flex;align-items:center;padding:0 10px;color:#fff;font-size:25px;font-weight:900;letter-spacing:.5px;border-bottom:1px solid #24272d}.brand span{color:var(--brand);font-weight:400}.developer{padding:13px 12px 4px;font-size:11px;color:#687180}.nav-section{padding:18px 12px 6px;font-size:10px;font-weight:800;letter-spacing:1.3px;color:#586171}.nav a,.nav .nav-button{width:100%;display:flex;align-items:center;gap:11px;padding:11px 12px;border:0;border-radius:8px;margin:2px 0;transition:.18s;background:transparent;color:inherit;cursor:pointer;text-align:right}.nav a:hover,.nav a.active,.nav .nav-button:hover{background:#171b20;color:#fff}.nav a.active{box-shadow:inset -3px 0 0 var(--brand)}.nav .icon{width:20px;text-align:center;color:#8b95a5}.nav a.active .icon{color:var(--brand)}
        .workspace{grid-column:1;min-width:0}.topbar{height:76px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;padding:0 30px;position:sticky;top:0;z-index:20}.topbar-title{display:flex;gap:12px;align-items:center}.topbar-title small{color:var(--muted)}.user-actions{display:flex;align-items:center;gap:12px}.avatar{width:38px;height:38px;border-radius:50%;display:grid;place-items:center;background:var(--navy);color:#fff;font-weight:800}.content{padding:24px 28px 38px;max-width:1600px;margin:auto}.breadcrumb{background:#eaf0f6;color:#596579;padding:15px 18px;border-radius:5px;margin-bottom:16px}.page-head{display:flex;align-items:center;justify-content:space-between;gap:18px;margin:0 0 18px}.page-head h1{font-size:25px;margin:0}.page-head p{margin:6px 0 0;color:var(--muted)}
        .hero-panel{background:linear-gradient(120deg,#1b2739,#173042 58%,#075e68);color:#fff;border-radius:22px;padding:26px 30px;display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:20px;box-shadow:var(--shadow);overflow:hidden;position:relative}.hero-panel:after{content:"";position:absolute;width:220px;height:220px;border:1px solid rgba(255,255,255,.08);border-radius:50%;left:-60px;top:-80px}.hero-panel h2{font-size:27px;margin:5px 0 7px}.hero-panel p{margin:0;color:#c6d4df;max-width:780px;line-height:1.8}.eyebrow{font-size:10px;color:#6ce5ef;font-weight:800;letter-spacing:1.5px}.panel,.card{background:var(--surface);border:1px solid var(--line);border-radius:18px;box-shadow:0 4px 14px rgba(16,24,40,.035)}.panel{padding:22px;margin-bottom:20px}.cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:15px;margin-bottom:20px}.card{padding:19px}.card .label{color:var(--muted);font-size:12px}.card strong{display:block;font-size:27px;margin-top:9px;color:var(--navy)}.card .accent{color:var(--brand-dark)}
        .section-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.section-chip{padding:14px;border:1px solid var(--line);background:var(--surface-2);border-radius:12px;display:flex;justify-content:space-between}.section-chip strong{color:var(--brand-dark)}
        .filters,.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.filters{grid-template-columns:2fr repeat(4,minmax(130px,1fr));align-items:end}.full{grid-column:1/-1}.field,label{display:block}label{font-size:12px;font-weight:700;color:#344054;margin-bottom:7px}input,select,textarea{width:100%;border:1px solid #dbe2ea;background:#fff;color:var(--ink);border-radius:10px;padding:11px 12px;outline:0;transition:.15s}input:focus,select:focus,textarea:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(6,182,201,.12)}textarea{min-height:130px;resize:vertical}select[multiple]{min-height:130px}.checks{display:flex;flex-wrap:wrap;gap:12px}.check{background:var(--surface-2);border:1px solid var(--line);padding:11px 13px;border-radius:10px}.check input{width:auto;margin-left:6px}.form-actions{display:flex;gap:10px;padding-top:4px}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;border:0;border-radius:9px;padding:10px 15px;background:var(--brand);color:#042f35;cursor:pointer;font-weight:800;white-space:nowrap}.btn:hover{filter:brightness(.96)}.btn.secondary{background:#344054;color:#fff}.btn.light{background:#eef2f6;color:#344054}.btn.danger{background:var(--danger);color:#fff}.btn:disabled{opacity:.45;cursor:not-allowed}.btn.small{padding:7px 10px;font-size:12px}
        .table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;min-width:760px}th,td{text-align:right;padding:13px 12px;border-bottom:1px solid var(--line);vertical-align:middle}th{font-size:11px;color:#667085;background:#f8fafc;text-transform:uppercase;letter-spacing:.2px}.media-cell{display:flex;align-items:center;gap:12px}.poster{width:44px;height:62px;object-fit:cover;border-radius:7px;background:#e7ecf2}.media-title strong{display:block}.media-title small{color:var(--muted)}.badge,.status-pill{display:inline-flex;align-items:center;padding:6px 9px;border-radius:999px;font-size:11px;font-weight:800;background:#eef2f6;color:#475467}.badge.success,.status-pill.success{background:#e8f7f1;color:var(--success)}.badge.warning,.status-pill.warning{background:#fff3dd;color:var(--warning)}.badge.brand{background:#e5f9fb;color:#087f8c}.actions{display:flex;gap:6px;flex-wrap:wrap}.muted{color:var(--muted)}
        .flash,.notice{padding:13px 16px;border-radius:10px;margin-bottom:16px}.flash{background:#e8f7f1;color:#126b50;border:1px solid #bde7d8}.notice.warning{background:#fff7e7;color:#8a5700;border:1px solid #f2d59d}.error{color:var(--danger);font-size:12px;margin-top:5px}.footer{text-align:center;color:#98a2b3;padding:15px}.footer strong{color:var(--brand-dark)}.mobile-menu{display:none}
        @media(max-width:1100px){.filters{grid-template-columns:repeat(2,1fr)}.cards{grid-template-columns:repeat(2,1fr)}.section-grid{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:760px){.shell{display:block}.sidebar{position:fixed;right:0;z-index:50;width:258px;transform:translateX(100%);transition:.2s}.sidebar.open{transform:none}.workspace{display:block}.mobile-menu{display:inline-flex}.topbar{padding:0 15px}.content{padding:16px}.cards,.filters,.form-grid,.section-grid{grid-template-columns:1fr}.hero-panel{padding:22px;align-items:flex-start;flex-direction:column}.page-head{align-items:flex-start;flex-direction:column}.topbar-title small{display:none}}
    </style>
</head>
<body>
<div class="shell">
    <aside class="sidebar" id="sidebar">
        <div class="brand">EL-NEMR <span>TV</span></div>
        <div class="developer">CONTROL CENTER · Laravel 13</div>
        <nav class="nav">
            <div class="nav-section">الرئيسية</div>
            <a class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><span class="icon">⌂</span>Dashboard</a>
            <a href="{{ route('admin.media.index',['featured'=>'yes']) }}"><span class="icon">★</span>Featured</a>
            <a href="{{ route('admin.media.index',['sort'=>'views']) }}"><span class="icon">↗</span>Trending Now</a>
            <a href="{{ route('admin.media.index',['pinned'=>'yes','sort'=>'order']) }}"><span class="icon">▣</span>Top10 & Pinned</a>
            <div class="nav-section">المحتوى</div>
            <a class="{{ request('type')==='movie' ? 'active' : '' }}" href="{{ route('admin.media.index',['type'=>'movie']) }}"><span class="icon">▤</span>الأفلام</a>
            <a class="{{ request('type')==='series' ? 'active' : '' }}" href="{{ route('admin.media.index',['type'=>'series']) }}"><span class="icon">▻</span>المسلسلات</a>
            <a class="{{ request('type')==='anime' ? 'active' : '' }}" href="{{ route('admin.media.index',['type'=>'anime']) }}"><span class="icon">◈</span>الأنمي</a>
            <a class="{{ request('type')==='live' ? 'active' : '' }}" href="{{ route('admin.media.index',['type'=>'live']) }}"><span class="icon">◉</span>البث المباشر</a>
            <a href="{{ route('admin.media.create') }}"><span class="icon">＋</span>إضافة محتوى</a>
            <a href="{{ route('admin.media.import') }}"><span class="icon">⇩</span>Bulk Import</a>
            <a class="{{ request()->routeIs('admin.genres.*') ? 'active' : '' }}" href="{{ route('admin.genres.index') }}"><span class="icon">▦</span>التصنيفات</a>
            <a class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}"><span class="icon">♙</span>المستخدمون</a>
            <div class="nav-section">التواصل والأتمتة</div>
            <a class="{{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}" href="{{ route('admin.notifications.create') }}"><span class="icon">♢</span>الإشعارات</a>
            <form method="post" action="{{ route('admin.scraper.run') }}" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='جاري التشغيل…';">@csrf<button class="nav-button" type="submit"><span class="icon">↻</span>تشغيل مزامنة TMDB</button></form>
        </nav>
    </aside>
    <section class="workspace">
        <header class="topbar">
            <div class="topbar-title"><button class="btn light mobile-menu" type="button" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button><div><strong>El-Nemr TV</strong><br><small>إدارة المحتوى والتطبيق</small></div></div>
            <div class="user-actions"><div class="avatar">EN</div><form method="post" action="{{ route('admin.logout') }}">@csrf<button class="btn light">خروج</button></form></div>
        </header>
        <main class="content">
            <div class="breadcrumb">الرئيسية &nbsp;/&nbsp; @yield('title', 'لوحة التحكم')</div>
            <div class="page-head"><div><h1>@yield('heading')</h1><p>@yield('subheading')</p></div>@yield('page-actions')</div>
            @if(session('success'))<div class="flash">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="notice warning">{{ session('error') }}</div>@endif
            @yield('content')
            <div class="footer">El-Nemr TV Control Center · <strong>Modern Laravel Backend</strong></div>
        </main>
    </section>
</div>
</body>
</html>
