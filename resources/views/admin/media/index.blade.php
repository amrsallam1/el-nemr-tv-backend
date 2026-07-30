@extends('admin.layout')
@section('title', 'إدارة المحتوى')
@section('heading', 'المحتوى')
@section('subheading', 'تحكم في الأفلام والمسلسلات والأنمي وأقسام الصفحة الرئيسية')
@section('page-actions')<div class="actions"><a class="btn light" href="{{ route('admin.media.import') }}">⇩ Bulk Import</a><a class="btn" href="{{ route('admin.media.create') }}">＋ إضافة محتوى</a></div>@endsection
@section('content')
<div class="hero-panel"><div><span class="eyebrow">MEDIA LIBRARY</span><h2>إدارة كل ما يظهر في التطبيق</h2><p>ابحث بالعنوان أو TMDB، وحدد Featured أو Pinned، ورتب النتائج حسب المشاهدات والتقييم وترتيب العرض.</p></div><span class="status-pill success">{{ $items->total() }} عنصر</span></div>

<div class="panel">
    <form method="get" class="filters">
        <div><label>البحث</label><input name="search" value="{{ request('search') }}" placeholder="العنوان، TMDB ID أو اسم المسلسل"></div>
        <div><label>النوع</label><select name="type"><option value="">كل الأنواع</option>@foreach(['movie'=>'أفلام','series'=>'مسلسلات','anime'=>'أنمي','live'=>'بث مباشر'] as $value=>$label)<option value="{{ $value }}" @selected(request('type')===$value)>{{ $label }}</option>@endforeach</select></div>
        <div><label>الحالة</label><select name="status"><option value="">الكل</option><option value="published" @selected(request('status')==='published')>منشور</option><option value="draft" @selected(request('status')==='draft')>مسودة</option></select></div>
        <div><label>Featured</label><select name="featured"><option value="">الكل</option><option value="yes" @selected(request('featured')==='yes')>Featured فقط</option><option value="no" @selected(request('featured')==='no')>غير مميز</option></select></div>
        <div><label>Pinned</label><select name="pinned"><option value="">الكل</option><option value="yes" @selected(request('pinned')==='yes')>Pinned فقط</option><option value="no" @selected(request('pinned')==='no')>غير مثبت</option></select></div>
        <div><label>أقل تقييم</label><input name="min_vote" type="number" min="0" max="10" step=".1" value="{{ request('min_vote') }}" placeholder="0.0"></div>
        <div><label>الترتيب</label><select name="sort"><option value="">الأحدث</option><option value="views" @selected(request('sort')==='views')>الأكثر مشاهدة</option><option value="rating" @selected(request('sort')==='rating')>الأعلى تقييمًا</option><option value="order" @selected(request('sort')==='order')>ترتيب العرض</option></select></div>
        <div class="actions"><button class="btn">بحث وفلترة</button><a class="btn light" href="{{ route('admin.media.index') }}">إلغاء الفلاتر</a></div>
    </form>
</div>

<div class="panel table-wrap">
<table><thead><tr><th>المحتوى</th><th>IDs</th><th>المشاهدات</th><th>التقييم</th><th>الأقسام</th><th>الحالة</th><th>الإجراءات</th></tr></thead><tbody>
@forelse($items as $item)
<tr>
    <td><div class="media-cell">@if($item->poster_path)<img class="poster" src="{{ $item->poster_path }}" alt="">@else<div class="poster"></div>@endif<div class="media-title"><strong>{{ $item->title ?: $item->name }}</strong><small>{{ $item->release_date?->format('Y-m-d') ?: $item->type }}</small></div></div></td>
    <td><span class="badge">#{{ $item->id }}</span><br><small class="muted">TMDB {{ $item->tmdb_id ?: '—' }}</small></td>
    <td>◉ {{ number_format($item->views) }}</td><td>★ {{ number_format((float)$item->vote_average,1) }}</td>
    <td>@if($item->is_featured)<span class="badge brand">Featured</span>@endif @if($item->is_pinned)<span class="badge">Pinned</span>@endif @if($item->is_recommended)<span class="badge success">Recommended</span>@endif</td>
    <td><span class="badge {{ $item->is_published ? 'success' : 'warning' }}">{{ $item->is_published ? 'Visible' : 'Draft' }}</span></td>
    <td><div class="actions"><a class="btn small light" href="{{ route('admin.media.catalog',$item) }}">السيرفرات</a><a class="btn small secondary" href="{{ route('admin.media.edit',$item) }}">تعديل</a><form method="post" action="{{ route('admin.media.destroy',$item) }}">@csrf @method('DELETE')<button class="btn small danger" onclick="return confirm('نقل هذا المحتوى إلى المحذوفات؟')">حذف</button></form></div></td>
</tr>
@empty<tr><td colspan="7">لا توجد نتائج مطابقة.</td></tr>@endforelse
</tbody></table>
<div style="margin-top:18px">{{ $items->links() }}</div>
</div>
@endsection
