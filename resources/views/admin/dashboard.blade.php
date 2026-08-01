@extends('admin.layout')
@section('title', 'Dashboard')
@section('heading', 'لوحة التحكم')
@section('subheading', 'نظرة سريعة على المحتوى وحالة أقسام التطبيق')
@section('page-actions')<a class="btn" href="{{ route('admin.media.create') }}">＋ إضافة محتوى</a>@endsection
@section('content')
<div class="cards">
    @foreach($stats as $label => $value)
        <div class="card"><span class="label">{{ $label }}</span><strong class="{{ $loop->first ? 'accent' : '' }}">{{ number_format($value) }}</strong></div>
    @endforeach
</div>

<div class="hero-panel" id="egyptian-series-sync">
    <div><span class="eyebrow">EGYPTIAN SERIES</span><h2>استيراد مسلسلات مصرية</h2><p>يستورد المسلسلات مع المواسم والحلقات والصور من TMDB.</p></div>
    <form method="post" action="{{ route('admin.scraper.egyptian-series') }}" onsubmit="const button=this.querySelector('button');button.disabled=true;button.textContent='جاري الاستيراد…';">@csrf<button class="btn" type="submit">استيراد مسلسلات مصرية</button></form>
</div>

<div class="panel">
    <div class="page-head"><div><h2>أقسام التطبيق</h2><p>الأرقام تتحدث مباشرة من قاعدة البيانات المستخدمة في تطبيق الموبايل.</p></div><a class="btn light" href="{{ route('admin.media.index') }}">إدارة الكل</a></div>
    <div class="section-grid">
        @foreach($sectionStats as $label => $value)<div class="section-chip"><span>{{ $label }}</span><strong>{{ number_format($value) }}</strong></div>@endforeach
    </div>
</div>

<div class="hero-panel" id="movie-sync">
    <div><span class="eyebrow">AUTOMATION</span><h2>تحديث الأفلام تلقائيًا من TMDB</h2><p>يتجاوز الأفلام الموجودة، يفحص رابط المشاهدة، ثم يضيف المحتوى الصالح مباشرة إلى التطبيق. التشغيل اليومي مضبوط على 50 فيلمًا.</p></div>
    <form method="post" action="{{ route('admin.scraper.run') }}" onsubmit="const button=this.querySelector('button');button.disabled=true;button.textContent='جاري التشغيل…';">@csrf<button class="btn" type="submit">↻ تشغيل الآن</button></form>
</div>

<div class="hero-panel" id="egyptian-sync">
    <div><span class="eyebrow">EGYPTIAN MOVIES</span><h2>إضافة أفلام مصرية</h2><p>يجلب أفلامًا مصرية جديدة من TMDB ويرسل إشعارًا للمستخدمين عند إضافة كل فيلم.</p></div>
    <form style="position:relative;z-index:5" method="post" action="{{ route('admin.scraper.egyptian') }}" onsubmit="const button=this.querySelector('button');button.disabled=true;button.textContent='جاري الإضافة…';">@csrf<button style="position:relative;z-index:6" class="btn" type="submit">إضافة أفلام مصرية</button></form>
</div>

@if(session('scraper_output'))
<div class="panel"><h2>نتيجة آخر تشغيل يدوي</h2><pre style="white-space:pre-wrap;word-break:break-word;margin:0;color:#344054;line-height:1.7">{{ session('scraper_output') }}</pre></div>
@endif

<div class="panel">
    <div class="page-head"><div><h2>أحدث المحتوى</h2><p>آخر العناصر المضافة أو المعدلة.</p></div></div>
    <div class="table-wrap"><table><thead><tr><th>المحتوى</th><th>النوع</th><th>القسم</th><th>الحالة</th></tr></thead><tbody>
    @forelse($latest as $item)
        <tr><td><div class="media-cell">@if($item->poster_path)<img class="poster" src="{{ $item->poster_path }}" alt="">@endif<div class="media-title"><strong>{{ $item->title ?: $item->name }}</strong><small>TMDB {{ $item->tmdb_id ?: '—' }}</small></div></div></td><td>{{ $item->type }}</td><td>@if($item->is_featured)<span class="badge brand">Featured</span>@endif @if($item->is_pinned)<span class="badge">Pinned</span>@endif</td><td><span class="badge {{ $item->is_published ? 'success' : 'warning' }}">{{ $item->is_published ? 'منشور' : 'مسودة' }}</span></td></tr>
    @empty<tr><td colspan="4">لا يوجد محتوى بعد.</td></tr>@endforelse
    </tbody></table></div>
</div>
@endsection
