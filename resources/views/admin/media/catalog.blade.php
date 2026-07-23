@extends('admin.layout')
@section('title','الحلقات والسيرفرات')
@section('heading',$media->title)
@section('content')
@if($errors->any())<div class="flash" style="background:#4a1f25">{{ $errors->first() }}</div>@endif

<div class="panel" style="margin-bottom:20px">
<h2>سيرفرات تشغيل المحتوى</h2>
<form class="grid" method="post" action="{{ route('admin.media.streams.store',$media) }}">@csrf
<div><label>اسم السيرفر</label><input name="name" placeholder="Server 1"></div>
<div><label>الجودة</label><input name="quality" placeholder="1080p"></div>
<div class="full"><label>رابط التشغيل</label><input name="url" type="url" required placeholder="https://..."></div>
<div><label>اللغة</label><input name="language"></div><div><button class="btn">إضافة السيرفر</button></div>
</form>
@foreach($media->streams as $stream)<p><strong>{{ $stream->name }}</strong> — {{ $stream->quality }} <span class="muted">{{ Str::limit($stream->url,70) }}</span>
<form style="display:inline" method="post" action="{{ route('admin.streams.destroy',$stream) }}">@csrf @method('DELETE')<button class="btn danger">حذف</button></form></p>@endforeach
</div>

@if(in_array($media->type,['series','anime']))
<div class="panel" style="margin-bottom:20px"><h2>إضافة موسم</h2><form style="display:flex;gap:10px" method="post" action="{{ route('admin.seasons.store',$media) }}">@csrf<input name="season_number" type="number" min="0" required placeholder="رقم الموسم"><input name="name" placeholder="اسم الموسم"><button class="btn">إضافة</button></form></div>
@foreach($media->seasons as $season)
<div class="panel" style="margin-bottom:20px">
<div style="display:flex;justify-content:space-between"><h2>الموسم {{ $season->season_number }} {{ $season->name }}</h2><form method="post" action="{{ route('admin.seasons.destroy',[$media,$season]) }}">@csrf @method('DELETE')<button class="btn danger" onclick="return confirm('حذف الموسم وكل حلقاته؟')">حذف الموسم</button></form></div>
<form class="grid" method="post" action="{{ route('admin.episodes.store',$season) }}">@csrf
<div><label>رقم الحلقة</label><input name="episode_number" type="number" min="0" required></div><div><label>اسم الحلقة</label><input name="name" required></div><div class="full"><label>صورة الحلقة</label><input name="still_path"></div><div><button class="btn">إضافة حلقة</button></div>
</form>
@foreach($season->episodes as $episode)
<div class="card" style="margin-top:15px"><div style="display:flex;justify-content:space-between"><h3>حلقة {{ $episode->episode_number }} — {{ $episode->name }}</h3><form method="post" action="{{ route('admin.episodes.destroy',$episode) }}">@csrf @method('DELETE')<button class="btn danger">حذف</button></form></div>
<form class="grid" method="post" action="{{ route('admin.episodes.streams.store',$episode) }}">@csrf<div><input name="name" placeholder="اسم السيرفر"></div><div><input name="quality" placeholder="الجودة"></div><div class="full"><input name="url" type="url" required placeholder="رابط تشغيل الحلقة"></div><div><button class="btn">إضافة سيرفر</button></div></form>
@foreach($episode->streams as $stream)<p>{{ $stream->name }} — {{ $stream->quality }} <span class="muted">{{ Str::limit($stream->url,60) }}</span> <form style="display:inline" method="post" action="{{ route('admin.streams.destroy',$stream) }}">@csrf @method('DELETE')<button class="btn danger">حذف</button></form></p>@endforeach
</div>
@endforeach
</div>
@endforeach
@endif
@endsection
