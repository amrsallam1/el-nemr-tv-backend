<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RunWorkerContentImport;
use App\Models\ContentImportRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkerImportController extends Controller
{
    public function index(): View
    {
        return view('admin.worker-import.index', [
            'runs' => ContentImportRun::latest()->limit(15)->get(),
            'activeRun' => ContentImportRun::whereIn('status', ['pending', 'running'])->latest()->first(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:movies,series,all'],
            'language' => ['required', 'in:all,arabic,english'],
            'years' => ['required', 'array', 'min:1'],
            'years.*' => ['integer', 'min:2015', 'max:2026'],
            'limit' => ['required', 'integer', 'min:1', 'max:200'],
            'pages' => ['required', 'integer', 'min:1', 'max:10'],
        ]);
        if (ContentImportRun::whereIn('status', ['pending', 'running'])->exists()) {
            return back()->withInput()->with('error', 'هناك عملية استيراد تعمل بالفعل. انتظر انتهاءها أولًا.');
        }
        $data['years'] = array_values(array_unique(array_map('intval', $data['years'])));
        sort($data['years']);
        $run = ContentImportRun::create([
            'user_id' => $request->user()->id,
            'status' => 'pending',
            'options' => $data,
        ]);
        RunWorkerContentImport::dispatch($run->id);

        return redirect()->route('admin.worker-import.index')->with('success', 'بدأ الاستيراد في الخلفية. التقرير سيتحدث تلقائيًا.');
    }

    public function status(ContentImportRun $run): JsonResponse
    {
        return response()->json([
            'id' => $run->id,
            'status' => $run->status,
            'options' => $run->options,
            'report' => $run->report,
            'error' => $run->error,
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
        ]);
    }
}
