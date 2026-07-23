@extends('admin.layout')
@section('title','المحتوى')
@section('heading','إدارة المحتوى')
@section('content')
<div class="panel"><form method="get" style="display:flex;gap:10px;margin-bottom:18px"><input name="search" value="{{ request('search') }}" placeholder="ابحث بالعنوان"><button class="btn">بحث</button><a class="btn secondary" href="{{ route('admin.media.create') }}">إضافة</a></form>
<table><thead><tr><th>العنوان</th><th>النوع</th><th>الحالة</th><th>إجراءات</th></tr></thead><tbody>
@forelse($items as $item)<tr><td>{{ $item->title }}</td><td>{{ $item->type }}</td><td>{{ $item->is_published ? 'منشور' : 'مسودة' }}</td><td><a class="btn" href="{{ route('admin.media.catalog',$item) }}">الحلقات والسيرفرات</a> <a class="btn secondary" href="{{ route('admin.media.edit',$item) }}">تعديل</a> <form style="display:inline" method="post" action="{{ route('admin.media.destroy',$item) }}">@csrf @method('DELETE')<button class="btn danger" onclick="return confirm('حذف المحتوى؟')">حذف</button></form></td></tr>
@empty<tr><td colspan="4">لا يوجد محتوى.</td></tr>@endforelse
</tbody></table><div style="margin-top:18px">{{ $items->links() }}</div></div>
@endsection
