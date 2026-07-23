@extends('admin.layout')
@section('title','الرئيسية')
@section('heading','لوحة التحكم')
@section('content')
<div class="cards">@foreach($stats as $label=>$value)<div class="card"><span class="muted">{{ $label }}</span><strong>{{ $value }}</strong></div>@endforeach</div>
<div class="panel"><h2>أحدث المحتوى</h2><table><thead><tr><th>العنوان</th><th>النوع</th><th>الحالة</th></tr></thead><tbody>
@forelse($latest as $item)<tr><td>{{ $item->title }}</td><td>{{ $item->type }}</td><td>{{ $item->is_published ? 'منشور' : 'مسودة' }}</td></tr>@empty<tr><td colspan="3">لا يوجد محتوى بعد.</td></tr>@endforelse
</tbody></table></div>
@endsection
