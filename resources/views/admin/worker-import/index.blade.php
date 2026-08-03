@extends('admin.layout')
@section('title', 'استيراد Worker')
@section('heading', 'الاستيراد التلقائي المتحكم به')
@section('subheading', 'اختر المحتوى واللغة والسنوات والعدد، وسيعمل الاستيراد في الخلفية مع تقرير تفصيلي.')
@section('content')
<div class="hero-panel"><div><span class="eyebrow">EL-NEMR WORKER</span><h2>تحكم يدوي، تنفيذ تلقائي</h2><p>تُفحص السنة من رابط الفيديو الحقيقي، ويُمنع التكرار، وتُحفظ الصور وروابط المشاهدة والتحميل داخل التطبيق.</p></div></div>

<div class="panel">
<form method="post" action="{{ route('admin.worker-import.store') }}" id="import-form">@csrf
<div class="form-grid">
    <div><label>نوع المحتوى</label><select name="type" required>
        <option value="movies" @selected(old('type')==='movies')>أفلام</option>
        <option value="series" @selected(old('type')==='series')>مسلسلات — جميع التصنيفات</option>
        <option value="all" @selected(old('type')==='all')>أفلام ومسلسلات</option>
    </select></div>
    <div><label>لغة العنوان</label><select name="language" required>
        <option value="all" @selected(old('language','all')==='all')>الكل</option>
        <option value="arabic" @selected(old('language')==='arabic')>عربي</option>
        <option value="english" @selected(old('language')==='english')>إنجليزي</option>
    </select></div>
    <div><label>عدد العناصر الجديدة المطلوبة</label><input name="limit" type="number" min="1" max="200" value="{{ old('limit',20) }}" required><small class="muted">يستمر الفحص حتى الوصول للعدد أو انتهاء الصفحات.</small></div>
    <div><label>عدد صفحات المصدر</label><input name="pages" type="number" min="1" max="10" value="{{ old('pages',3) }}" required><small class="muted">زود الصفحات عند اختيار سنة أو لغة محددة.</small></div>
    <div class="full"><label>السنوات المطلوبة (2015–2026)</label><div class="checks">
        @foreach(range(2026,2015) as $year)<label class="check"><input type="checkbox" name="years[]" value="{{ $year }}" @checked(in_array($year,old('years',[2025,2026])))>{{ $year }}</label>@endforeach
    </div>@error('years')<div class="error">{{ $message }}</div>@enderror</div>
    <div class="full notice warning">العربي والإنجليزي يُحددان حسب حروف عنوان المصدر. العناصر ذات السنة غير المعروفة تُرفض ولا تُضاف.</div>
    <div class="full form-actions"><button class="btn" type="submit" @disabled($activeRun)>بدء الاستيراد التلقائي</button></div>
</div></form>
</div>

@if($activeRun)
<div class="panel" id="active-run" data-status-url="{{ route('admin.worker-import.status',$activeRun) }}">
    <h2>العملية الحالية #{{ $activeRun->id }}</h2>
    <p><span id="run-status" class="status-pill warning">{{ $activeRun->status }}</span> <span class="muted">يمكنك مغادرة الصفحة والعودة لاحقًا.</span></p>
    <div id="live-summary"></div>
</div>
@endif

<div class="panel">
<div class="page-head"><div><h2>تقارير الاستيراد</h2><p>آخر 15 عملية مع النتائج والأسباب.</p></div></div>
@forelse($runs as $run)
@php($reports=$run->report['reports']??[])
<details style="border:1px solid var(--line);border-radius:12px;padding:14px;margin-bottom:10px" @if($loop->first) open @endif>
<summary style="cursor:pointer;font-weight:800">#{{ $run->id }} — {{ $run->status }} — {{ implode('، ',$run->options['years']??[]) }} — {{ $run->created_at->format('Y-m-d H:i') }}</summary>
<div class="cards" style="margin-top:14px">
@foreach(['created'=>'مضاف','updated'=>'محدّث','skipped'=>'مرفوض/متكرر','failed'=>'فشل'] as $key=>$label)<div class="card"><span class="label">{{ $label }}</span><strong>{{ collect($reports)->sum($key) }}</strong></div>@endforeach
</div>
@if($run->error)<div class="notice warning">{{ $run->error }}</div>@endif
<div class="table-wrap"><table><thead><tr><th>العنوان</th><th>النوع</th><th>السنة</th><th>النتيجة</th><th>السبب</th></tr></thead><tbody>
@forelse(collect($reports)->flatMap(fn($report)=>$report['items']??[]) as $item)
<tr><td>{{ $item['title']??'—' }}</td><td>{{ $item['type']??'—' }}</td><td>{{ $item['year']??'—' }}</td><td><span class="badge {{ ($item['status']??'')==='created'?'success':'warning' }}">{{ $item['status']??'—' }}</span></td><td>{{ $item['reason']??'—' }}</td></tr>
@empty<tr><td colspan="5" class="muted">لا توجد تفاصيل بعد.</td></tr>@endforelse
</tbody></table></div>
</details>
@empty<div class="muted">لم تُشغّل أي عملية من هذه الصفحة بعد.</div>@endforelse
</div>

@if($activeRun)
<script>
const box=document.getElementById('active-run');
const poll=async()=>{try{const r=await fetch(box.dataset.statusUrl,{headers:{Accept:'application/json'}});const d=await r.json();document.getElementById('run-status').textContent=d.status;const reports=d.report?.reports||[];const sum=k=>reports.reduce((n,x)=>n+(x[k]||0),0);document.getElementById('live-summary').textContent=`مضاف: ${sum('created')} — محدّث: ${sum('updated')} — مرفوض: ${sum('skipped')} — فشل: ${sum('failed')}`;if(['completed','failed'].includes(d.status)){location.reload();return}}catch(e){}setTimeout(poll,5000)};poll();
</script>
@endif
@endsection
