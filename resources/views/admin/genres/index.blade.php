@extends('admin.layout')
@section('title', 'التصنيفات')
@section('heading', 'التصنيفات')
@section('subheading', 'إدارة الأقسام التي تظهر في تصفح تطبيق الموبايل')
@section('content')
<div class="hero-panel"><div><span class="eyebrow">GENRES</span><h2>تنظيم مكتبة المحتوى</h2><p>التصنيفات هنا تغذي قوائم التصفح والبحث داخل التطبيق مباشرة.</p></div><span class="status-pill success">{{ $genres->count() }} تصنيف</span></div>
<form class="panel form-grid" method="post" action="{{ route('admin.genres.store') }}">@csrf<div><label>اسم التصنيف الجديد</label><input name="name" required placeholder="أكشن"></div><div class="form-actions" style="align-items:end"><button class="btn">إضافة التصنيف</button></div></form>
<div class="panel table-wrap"><table><thead><tr><th>الاسم</th><th>Slug</th><th>عدد الأعمال</th><th>الإجراءات</th></tr></thead><tbody>
@forelse($genres as $genre)<tr><form method="post" action="{{ route('admin.genres.update',$genre) }}">@csrf @method('PUT')<td><input name="name" value="{{ $genre->name }}" required></td><td><input name="slug" value="{{ $genre->slug }}" required></td><td><span class="badge brand">{{ $genre->media_count }}</span></td><td><div class="actions"><button class="btn small">حفظ</button></form><form method="post" action="{{ route('admin.genres.destroy',$genre) }}">@csrf @method('DELETE')<button class="btn small danger" onclick="return confirm('حذف التصنيف؟')">حذف</button></form></div></td></tr>@empty<tr><td colspan="4">لا توجد تصنيفات.</td></tr>@endforelse
</tbody></table></div>
@endsection
