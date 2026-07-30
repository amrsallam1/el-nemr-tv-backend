<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.users.index', [
            'users' => User::query()
                ->when($request->filled('search'), function ($query) use ($request) {
                    $search = '%'.$request->string('search').'%';
                    $query->where(fn ($nested) => $nested->where('name', 'like', $search)->orWhere('email', 'like', $search));
                })
                ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->string('status') === 'active'))
                ->latest()->paginate(30)->withQueryString(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, 'لا يمكن تعديل حسابك من هذه الصفحة.');
        $data = $request->validate([
            'role' => ['required', Rule::in(['user', 'admin'])],
            'is_active' => ['required', 'boolean'],
            'is_premium' => ['required', 'boolean'],
        ]);
        $user->update($data);

        return back()->with('success', 'تم تحديث المستخدم.');
    }
}
