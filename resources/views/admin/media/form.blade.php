@extends('admin.layout')
@section('title', $media->exists ? 'تعديل المحتوى' : 'إضافة محتوى')
@section('heading', $media->exists ? 'تعديل المحتوى' : 'إضافة محتوى جديد')
@section('subheading', 'بيانات العرض وأقسام الصفحة الرئيسية وروابط الصور')
@section('content')
<div class="hero-panel"><div><span class="eyebrow">{{ $media->exists ? 'EDIT MEDIA' : 'ADD MEDIA' }}</span><h2>{{ $media->exists ? ($media->title ?: $media->name) : 'أضف فيلمًا أو مسلسلًا أو أنمي' }}</h2><p>كل علامة هنا تتحكم مباشرة في مكان ظهور المحتوى داخل تطبيق الموبايل.</p></div><a class="btn light" href="{{ route('admin.media.index') }}">← رجوع</a></div>
<form class="panel form-grid" method="post" action="{{ $media->exists ? route('admin.media.update',$media) : route('admin.media.store') }}">@csrf @if($media->exists)@method('PUT')@endif
    <div><label>النوع</label><select name="type">@foreach(['movie'=>'فيلم','series'=>'مسلسل','anime'=>'أنمي','live'=>'بث مباشر'] as $value=>$label)<option value="{{ $value }}" @selected(old('type',$media->type)===$value)>{{ $label }}</option>@endforeach</select></div>
    <div><label>العنوان</label><input name="title" value="{{ old('title',$media->title) }}" required>@error('title')<div class="error">{{ $message }}</div>@enderror</div>
    <div><label>الاسم للمسلسلات والأنمي</label><input name="name" value="{{ old('name',$media->name) }}"></div>
    <div><label>Slug</label><input name="slug" value="{{ old('slug',$media->slug) }}" required>@error('slug')<div class="error">{{ $message }}</div>@enderror</div>
    <div><label>TMDB ID</label><input name="tmdb_id" value="{{ old('tmdb_id',$media->tmdb_id) }}"></div>
    <div><label>IMDB ID</label><input name="imdb_id" value="{{ old('imdb_id',$media->imdb_id) }}"></div>
    <div><label>التقييم</label><input name="vote_average" type="number" step=".1" min="0" max="10" value="{{ old('vote_average',$media->vote_average) }}"></div>
    <div><label>ترتيب العرض / Top10</label><input name="sort_order" type="number" min="0" value="{{ old('sort_order',$media->sort_order ?? 0) }}"></div>
    <div class="full"><label>الوصف</label><textarea name="overview">{{ old('overview',$media->overview) }}</textarea></div>
    <div><label>رابط البوستر</label><input name="poster_path" value="{{ old('poster_path',$media->poster_path) }}"></div><div><label>رابط الخلفية</label><input name="backdrop_path" value="{{ old('backdrop_path',$media->backdrop_path) }}"></div>
    <div><label>تاريخ الإصدار</label><input name="release_date" type="date" value="{{ old('release_date',$media->release_date?->format('Y-m-d')) }}"></div>
    <div><label>التصنيفات</label><select name="genre_ids[]" multiple>@foreach($genres as $genre)<option value="{{ $genre->id }}" @selected(in_array($genre->id,old('genre_ids',$media->genres->pluck('id')->all() ?? [])))>{{ $genre->name }}</option>@endforeach</select></div>
    <div class="full"><label>الظهور داخل التطبيق</label><div class="checks">
        <label class="check"><input type="checkbox" name="is_published" value="1" @checked(old('is_published',$media->is_published))> منشور</label>
        <label class="check"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured',$media->is_featured))> Featured</label>
        <label class="check"><input type="checkbox" name="is_recommended" value="1" @checked(old('is_recommended',$media->is_recommended))> Recommended</label>
        <label class="check"><input type="checkbox" name="is_pinned" value="1" @checked(old('is_pinned',$media->is_pinned))> Pinned / Top10</label>
        <label class="check"><input type="checkbox" name="is_premium" value="1" @checked(old('is_premium',$media->is_premium))> Premium</label>
    </div></div>
    <div class="full form-actions"><button class="btn">حفظ التغييرات</button>@if($media->exists)<a class="btn secondary" href="{{ route('admin.media.catalog',$media) }}">المواسم والسيرفرات</a>@endif</div>
</form>
@endsection
