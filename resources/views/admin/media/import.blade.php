@extends('admin.layout')
@section('title','استيراد CSV')
@section('heading','استيراد محتوى جماعي')
@section('content')
<div class="panel">
    <p class="muted">الأعمدة الأساسية: <code>title,type</code>. ويمكن إضافة <code>stream_url,quality,language,embed,poster_url,backdrop_url,tmdb_id,year,overview</code>.</p>
    <p class="muted">الأنواع المقبولة: movie أو series أو anime أو live. المحتوى الموجود بنفس TMDB ID سيتم تحديثه بدل تكراره.</p>
    <form method="post" action="{{ route('admin.media.import.store') }}" enctype="multipart/form-data">
        @csrf
        <label for="csv">ملف CSV</label>
        <input id="csv" type="file" name="csv" accept=".csv,text/csv" required>
        @error('csv')<div class="error">{{ $message }}</div>@enderror
        <div style="margin-top:18px"><button class="btn" type="submit">بدء الاستيراد</button> <a class="btn secondary" href="{{ route('admin.media.index') }}">إلغاء</a></div>
    </form>
</div>
@endsection
