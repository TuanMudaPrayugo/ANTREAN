<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\KStep;
use App\Models\Ticket;
use App\Models\Progress;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class TimelineController extends Controller
{
    public function index(Ticket $tiket, Request $request)
    {
        $paired = $request->cookie("paired_ticket_{$tiket->id}") == 1;
        if ($tiket->status === 'running' && session('active_ticket_id') != $tiket->id && !$paired) {
            return redirect()->route('antrean.index')->withErrors(['msg' => 'Tiket ini tidak terkait dengan sesi Anda.']);
        }

        // Magic link hanya saat running
        $joinUrl = null;
if ($tiket->status === 'running') {
    // (opsional) hanya buat token baru jika kosong atau sudah kedaluwarsa
    if (empty($tiket->join_token) || empty($tiket->join_token_expires_at) || now()->greaterThan($tiket->join_token_expires_at)) {
        $tiket->join_token = Str::random(48);
    }
    $tiket->join_token_expires_at = now()->addMinutes(45);
    $tiket->save();

    // Link biasa (stabil di domain apa pun)
    $joinUrl = route('timeline.join', ['ticket' => $tiket->id, 't' => $tiket->join_token]);
}

        $tiket->load(['layanan','progresses.issues','progresses.issue']);
        $layanan  = $tiket->layanan;
        $steps    = KStep::where('layanan_id', $layanan->id)->orderBy('step_order')->get();
        $progress = $tiket->progresses->keyBy('step_id');

        if ($tiket->status === 'running' && empty($tiket->join_pin)) {
            $tiket->join_pin = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $tiket->save();
        }

        return view('user/antrean/Timeline/index', compact(
            'tiket','layanan','steps','progress','joinUrl'
        ))->with(['title'=>'Timeline','menuAntrean'=>'active']);
    }

    public function joinForm(Request $request, Ticket $ticket)
{
    $t = (string) $request->query('t', '');
    if ($t === '' || $t !== (string) $ticket->join_token) {
        abort(403, 'Invalid token');
    }
    if (empty($ticket->join_token_expires_at) || now()->greaterThan($ticket->join_token_expires_at)) {
        abort(403, 'Token expired');
    }

    if ($ticket->status !== 'running') {
        return redirect()->route('antrean.index')->withErrors(['msg' => 'Tiket sudah tidak aktif.']);
    }

    return view('user/antrean/Timeline/join-pin', ['ticket' => $ticket]);
}

    public function joinVerify(Request $request, Ticket $ticket)
{
    if ($ticket->status !== 'running') {
        return redirect()->route('antrean.index')->withErrors(['msg' => 'Tiket sudah tidak aktif.']);
    }

    // cek PIN
    $request->validate(['pin' => 'required|string']);
    if ($request->input('pin') !== (string)$ticket->join_pin) {
        return back()->withErrors(['msg' => 'PIN salah.'])->withInput();
    }

    // cek fingerprint perangkat
    $reqFp = (string) $request->input('fp', '');
    if (!empty($ticket->origin_fp) && $reqFp !== $ticket->origin_fp) {
        return back()->withErrors(['msg' => 'Hanya bisa dibuka di perangkat yang sama.'])->withInput();
    }

    session(['active_ticket_id' => $ticket->id]);
    $cookie = cookie()->forever("paired_ticket_{$ticket->id}", 1);

    return redirect()->route('timeline.show', $ticket->id)
        ->with('toast', 'Tiket aktif telah dikaitkan ke browser ini.')
        ->withCookie($cookie);
}

public function storeFingerprint(Request $request, Ticket $ticket)
{
    $request->validate(['fp' => 'required|string|min:20']);
    $ticket->origin_fp = substr($request->fp, 0, 128);
    if (empty($ticket->origin_ip)) {
        $ticket->origin_ip = $request->ip();
    }
    $ticket->save();

    return response()->noContent();
}

    public function data(Ticket $ticket, Request $request)
{
    $steps = KStep::where('layanan_id', $ticket->layanan_id)
        ->orderBy('step_order')
        ->get(['id','step_order','service_step_name','std_step_time']);

    $progressRows = Progress::where('ticket_id', $ticket->id)
        ->with(['issues:id,issue_name', 'issue:id,issue_name', 'step:id,service_step_name'])
        ->get()
        ->keyBy('step_id');

    // total durasi (detik)
    $totalSec = 0;
    foreach ($progressRows as $pg) {
        if ($pg->started_at) {
            $start = $pg->started_at instanceof CarbonInterface ? $pg->started_at : Carbon::parse($pg->started_at);
            $end   = $pg->ended_at ? ($pg->ended_at instanceof CarbonInterface ? $pg->ended_at : Carbon::parse($pg->ended_at)) : now();
            $totalSec += max(0, $end->diffInSeconds($start));
        }
    }

    $toIso = static function($dt) {
        if (empty($dt)) return null;
        if ($dt instanceof CarbonInterface) return $dt->toIso8601String();
        try { return Carbon::parse($dt)->toIso8601String(); } catch (\Throwable $e) { return null; }
    };

    $stepsPayload = $steps->map(function ($step) use ($progressRows, $toIso) {
        $p = $progressRows->get($step->id);
        $issues = [];
        if ($p) {
            if ($p->relationLoaded('issues') && $p->issues && $p->issues->isNotEmpty()) {
                $issues = $p->issues->map(fn($i)=>['id'=>(int)$i->id,'issue_name'=>(string)$i->issue_name])->values()->all();
            } elseif ($p->issue) {
                $issues = [['id'=>(int)$p->issue->id,'issue_name'=>(string)$p->issue->issue_name]];
            }
        }
        return [
            'id'         => (int)$step->id,
            'name'       => (string)$step->service_step_name,
            'status'     => $p?->status ?? 'pending',   // ← tweak kecil
            'started_at' => $toIso($p?->started_at),
            'ended_at'   => $toIso($p?->ended_at),
            'std_min'    => (int)$step->std_step_time,
            'issues'     => $issues,
        ];
    })->values();

    // nama step terakhir yang stopped (untuk banner)
    $stoppedStepName = null;
    if ($ticket->status === Progress::STATUS_STOPPED) {
        $lastStopped = $progressRows->filter(fn($p)=>$p->status==='stopped')
            ->sortByDesc('ended_at')->first();
        if ($lastStopped && $lastStopped->step) {
            $stoppedStepName = $lastStopped->step->service_step_name;
        }
    }

    // Top issues (frequent) opsional
 // ===== TOP ISSUES (frequent) =====
$freqPayload = [];

if ($request->boolean('include_freq')) {
    try {
        $days  = max(1, (int) $request->input('days', 30));
        $limit = max(1, (int) $request->input('limit', 3));
        $force = $request->boolean('force');

        /** @var \App\Domain\Timeline\Services\TimelineAnalytics $svc */
        $svc = app(\App\Domain\Timeline\Services\TimelineAnalytics::class);

        // (A) kalau mau handle bypass cache DI CONTROLLER:
        if ($force) {
            \Illuminate\Support\Facades\Cache::forget(
                "analytics:freq_issues:layanan:{$ticket->layanan_id}:days:{$days}:limit:{$limit}"
            );
        }

        // Ambil map: step_id => [ ['issue_id'=>int,'freq'=>int], ... ]
        $freqMap = $svc->frequentIssues((int) $ticket->layanan_id, $days, $limit);

        // Ambil nama issue dari tabel yang benar: kissues
        $ids = collect($freqMap)->flatten(1)->pluck('issue_id')->unique()->values();
        $names = $ids->isNotEmpty()
            ? DB::table('kissues')->whereIn('id', $ids)->pluck('issue_name', 'id')
            : collect();

        // Bentuk payload buat frontend
        $freqPayload = collect($freqMap)->map(function ($items) use ($names) {
            return collect($items)->map(function ($it) use ($names) {
                $iid = (int) ($it['issue_id'] ?? 0);
                return [
                    'issue_id'   => $iid,
                    'issue_name' => (string) ($names[$iid] ?? ('Issue #'.$iid)),
                    'freq'       => (int) ($it['freq'] ?? 0),
                ];
            })->values();
        })->toArray();

    } catch (\Throwable $e) {
        // Jangan jatuhkan endpoint kalau analytics error
        $freqPayload = [];
    }
}

    return response()->json([
        'server_now'        => now()->toIso8601String(),
        'ticket_status'     => $ticket->status,       // running|done|stopped
        'stopped_step_name' => $stoppedStepName,
        'total_sec'         => (int)$totalSec,
        'steps'             => $stepsPayload,
         'frequent_issues' => (object) $freqPayload,
    ])->header('Cache-Control','no-store, no-cache, must-revalidate, max-age=0')
      ->header('Pragma','no-cache');
}
}
