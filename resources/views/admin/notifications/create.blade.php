@extends('admin.layout')
@section('title', 'مركز الإشعارات')
@section('heading', 'إرسال إشعار')
@section('content')
<div class="hero-panel">
    <div>
        <span class="eyebrow">PUSH NOTIFICATIONS</span>
        <h2>تواصل مع كل مستخدمي التطبيق</h2>
        <p>اكتب رسالة عامة، افتح رابطًا، أو اربط الإشعار بفيلم أو مسلسل داخل التطبيق.</p>
    </div>
    <span class="status-pill {{ $configured ? 'success' : 'warning' }}">{{ $configured ? 'Firebase متصل' : 'يحتاج إعداد Firebase' }}</span>
</div>

@if(!$configured)
<div class="notice warning">واجهة الإرسال جاهزة، ويتبقى إضافة Service Account الخاص بمشروع Firebase إلى متغير <code>FIREBASE_CREDENTIALS_JSON</code> في Railway.</div>
@endif

<form class="panel form-grid" method="post" action="{{ route('admin.notifications.store') }}">
    @csrf
    <div><label>عنوان الإشعار</label><input name="title" maxlength="100" value="{{ old('title') }}" placeholder="فيلم جديد متاح الآن" required></div>
    <div><label>ربط بمحتوى داخل التطبيق (اختياري)</label><select name="media_id"><option value="">إشعار عام</option>@foreach($media as $item)<option value="{{ $item->id }}" @selected(old('media_id')==$item->id)>{{ $item->title ?: $item->name }} — {{ $item->type }}</option>@endforeach</select></div>
    <div class="full"><label>الرسالة</label><textarea name="message" maxlength="500" placeholder="اكتب الرسالة التي ستظهر للمستخدم..." required>{{ old('message') }}</textarea></div>
    <div><label>رابط صورة (اختياري)</label><input type="url" name="image" value="{{ old('image') }}" placeholder="https://..."></div>
    <div><label>رابط يفتح عند الضغط (اختياري)</label><input type="url" name="link" value="{{ old('link') }}" placeholder="https://..."></div>
    <div class="full form-actions"><button class="btn" type="submit" @disabled(!$configured)>إرسال الإشعار الآن</button></div>
</form>
@endsection
