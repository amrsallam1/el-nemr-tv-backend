@extends('admin.layout')
@section('title', 'المستخدمون')
@section('heading', 'المستخدمون')
@section('subheading', 'إدارة حالة الحسابات والصلاحيات والاشتراك')
@section('content')
<div class="panel"><form class="filters" method="get"><div><label>البحث</label><input name="search" value="{{ request('search') }}" placeholder="الاسم أو البريد"></div><div><label>الحالة</label><select name="status"><option value="">الكل</option><option value="active" @selected(request('status')==='active')>نشط</option><option value="blocked" @selected(request('status')==='blocked')>موقوف</option></select></div><div class="actions"><button class="btn">بحث</button></div></form></div>
<div class="panel table-wrap"><table><thead><tr><th>المستخدم</th><th>التسجيل</th><th>الصلاحية</th><th>الحالة</th><th>Premium</th><th>حفظ</th></tr></thead><tbody>
@foreach($users as $user)<tr><form method="post" action="{{ route('admin.users.update',$user) }}">@csrf @method('PUT')<td><strong>{{ $user->name }}</strong><br><small class="muted">{{ $user->email }}</small></td><td>{{ $user->created_at?->format('Y-m-d') }}</td><td><select name="role"><option value="user" @selected($user->role==='user')>User</option><option value="admin" @selected($user->role==='admin')>Admin</option></select></td><td><select name="is_active"><option value="1" @selected($user->is_active)>نشط</option><option value="0" @selected(!$user->is_active)>موقوف</option></select></td><td><select name="is_premium"><option value="0" @selected(!$user->is_premium)>عادي</option><option value="1" @selected($user->is_premium)>Premium</option></select></td><td><button class="btn small" @disabled($user->is(auth()->user()))>حفظ</button></td></form></tr>@endforeach
</tbody></table><div style="margin-top:18px">{{ $users->links() }}</div></div>
@endsection
