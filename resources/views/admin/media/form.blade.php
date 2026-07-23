@extends('admin.layout')
@section('title',$media->exists?'تعديل المحتوى':'إضافة محتوى')
@section('heading',$media->exists?'تعديل المحتوى':'إضافة محتوى')
@section('content')
<form class="panel grid" method="post" action="{{ $media->exists ? route('admin.media.update',$media) : route('admin.media.store') }}">@csrf @if($media->exists)@method('PUT')@endif
<div><label>النوع</label><select name="type">@foreach(['movie'=>'فيلم','series'=>'مسلسل','anime'=>'أنمي','live'=>'بث مباشر'] as $value=>$label)<option value="{{ $value }}" @selected(old('type',$media->type)===$value)>{{ $label }}</option>@endforeach</select></div>
<div><label>العنوان</label><input name="title" value="{{ old('title',$media->title) }}" required>@error('title')<div class="error">{{ $message }}</div>@enderror</div>
<div><label>الرابط المختصر Slug</label><input name="slug" value="{{ old('slug',$media->slug) }}" required>@error('slug')<div class="error">{{ $message }}</div>@enderror</div>
<div><label>التقييم</label><input name="vote_average" type="number" step=".1" min="0" max="10" value="{{ old('vote_average',$media->vote_average) }}"></div>
<div class="full"><label>الوصف</label><textarea name="overview">{{ old('overview',$media->overview) }}</textarea></div>
<div><label>رابط البوستر</label><input name="poster_path" value="{{ old('poster_path',$media->poster_path) }}"></div><div><label>رابط الخلفية</label><input name="backdrop_path" value="{{ old('backdrop_path',$media->backdrop_path) }}"></div>
<div><label>تاريخ الإصدار</label><input name="release_date" type="date" value="{{ old('release_date',$media->release_date?->format('Y-m-d')) }}"></div>
<div><label>التصنيفات</label><select name="genre_ids[]" multiple size="5">@foreach($genres as $genre)<option value="{{ $genre->id }}" @selected(in_array($genre->id,old('genre_ids',$media->genres->pluck('id')->all() ?? [])))>{{ $genre->name }}</option>@endforeach</select></div>
<div class="full"><label><input style="width:auto" type="checkbox" name="is_published" value="1" @checked(old('is_published',$media->is_published))> منشور</label><label><input style="width:auto" type="checkbox" name="is_featured" value="1" @checked(old('is_featured',$media->is_featured))> مميز</label><label><input style="width:auto" type="checkbox" name="is_premium" value="1" @checked(old('is_premium',$media->is_premium))> بريميوم</label></div>
<div class="full"><button class="btn">حفظ</button></div></form>
@endsection
