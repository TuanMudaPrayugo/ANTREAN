<?php

namespace App\Http\Controllers;

use App\Models\KStep;
use App\Models\KIssue;
use App\Models\Progress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class KonfirmasiPetugasController extends Controller
{
    public function index()
    {
        $running = Progress::with(['ticket.layanan','step','issues','issue'])
            ->where('status', Progress::STATUS_RUNNING)
            ->orderBy('started_at')
            ->get();

        $stopped = Progress::with(['ticket.layanan','step','issues','issue'])
            ->where('status', Progress::STATUS_STOPPED)
            ->orderByDesc('updated_at')
            ->get();

        return view('petugas.index', [
            'title' => 'Data List Antrean',
            'menuKonfirmasiPetugas' => 'active',
            'activeTab' => 'petugas',
            'running' => $running,
            'stopped' => $stopped,
        ]);
    }

    public function issues(KStep $step)
    {
        return response()->json(
            KIssue::where('steplayanan_id', $step->id)->orderBy('issue_name')->get(['id','issue_name'])
        );
    }

    // === optional: frequent issues per step (fallback sederhana) ===
    public function frequentIssues(KStep $step, Request $request)
{
    $days  = (int) $request->query('days', 30);
    $limit = (int) $request->query('limit', 3);

    try {
        $qb = DB::table('issue_progress as ip')
            ->join('progresses as p', 'p.id', '=', 'ip.progress_id') // <-- tabel yg benar
            ->join('k_issues as i', 'i.id', '=', 'ip.issue_id')
            ->where('p.step_id', $step->id)
            ->whereNotNull('p.started_at')
            ->whereNotNull('p.ended_at');

        // days=0 => seluruh histori (tanpa filter tanggal)
        if ($days > 0) {
            $qb->where('p.ended_at', '>=', now()->subDays($days));
        }

        // Supaya sederhana & cepat: pakai AVG durasi (detik).
        // (Kalau mau median beneran, nanti bisa diganti teknik SUBSTRING_INDEX/GROUP_CONCAT)
        $rows = $qb->selectRaw('
                    ip.issue_id,
                    i.issue_name,
                    COUNT(*) as freq,
                    ROUND(AVG(TIMESTAMPDIFF(SECOND, p.started_at, p.ended_at))) as median_sec
                ')
                ->groupBy('ip.issue_id', 'i.issue_name')
                ->orderByDesc('freq')
                ->orderBy('median_sec')   // yg rata-rata lebih cepat didahulukan
                ->limit(max(1, $limit))
                ->get();

        return response()->json($rows);
    } catch (\Throwable $e) {
        // log agar ketahuan kalau ada salah skema
        Log::error('frequentIssues error', [
            'step_id' => $step->id,
            'msg'     => $e->getMessage(),
        ]);
        // jangan bikin 500 ke browser — kembalikan array kosong
        return response()->json([], 200);
    }
}

    // === detail issue untuk modal di timeline ===
    public function issueDetail(\App\Models\KIssue $issue)
{
    // Ambil dari kolom DB 'solusion' (nullable), rapikan spasi
    $solution = $issue->solusion;
    $solution = is_string($solution) ? trim($solution) : '';

    // Fallback jika kosong
    if ($solution === '') {
        $solution = 'Belum ada solusi yang tercatat.';
    }

    return response()->json([
        'id'         => (int) $issue->id,
        'issue_name' => (string) $issue->issue_name,
        'solution'   => $solution, // ✅ konsisten ke frontend
    ]);
}

    public function action(Request $request, Progress $progress)
    {
        $request->validate([
            'action'   => 'required|in:next,stop,process',
            'issue_id' => 'nullable', // int atau array
            'note'     => 'nullable|string|max:2000',
        ]);

        return DB::transaction(function () use ($request, $progress) {
            $ticket = $progress->ticket;
            $step   = $progress->step;

            // simpan issue (legacy + pivot)
            if ($request->filled('issue_id')) {
                $ids = is_array($request->issue_id)
                    ? array_values(array_filter(array_map('intval', $request->issue_id)))
                    : [ (int) $request->issue_id ];

                $progress->issue_id = $ids[0] ?? null;
                if ($request->filled('note')) {
                    $progress->issue_note = $request->note;
                }
                $progress->save();
                $progress->issues()->syncWithoutDetaching($ids);
            }

            switch ($request->action) {
                case 'process':
                    $this->bustAnalyticsCache($ticket->layanan_id);
                    return $this->afterAction($request, 'Tetap diproses di tahapan saat ini.');

                case 'stop':
                    if ($progress->status === Progress::STATUS_RUNNING) {
                        $progress->update(['ended_at' => now(), 'status' => Progress::STATUS_STOPPED]);
                    }
                    // hentikan tiket
                    $ticket->update(['status' => Progress::STATUS_STOPPED]);
                    $this->bustAnalyticsCache($ticket->layanan_id);
                    return $this->afterAction($request, 'Tiket dihentikan.');

                case 'next':
                default:
                    if ($progress->status === Progress::STATUS_RUNNING) {
                        $progress->update(['ended_at' => now(), 'status' => Progress::STATUS_DONE]);
                    }
                    $next = KStep::where('layanan_id', $ticket->layanan_id)
                        ->where('step_order', '>', $step->step_order)
                        ->orderBy('step_order')
                        ->first();

                    if (!$next) {
                        $ticket->update(['status' => Progress::STATUS_DONE]);
                        $this->bustAnalyticsCache($ticket->layanan_id);
                        return $this->afterAction($request, 'Tiket selesai semua tahapan.');
                    }

                    Progress::firstOrCreate(
                        ['ticket_id' => $ticket->id, 'step_id' => $next->id],
                        ['started_at' => now(), 'status' => Progress::STATUS_RUNNING]
                    );

                    $this->bustAnalyticsCache($ticket->layanan_id);
                    return $this->afterAction($request, 'Lanjut ke tahapan berikutnya: '.$next->service_step_name);
            }
        });
    }

    private function afterAction(Request $req, string $msg)
    {
        if ($req->wantsJson()) {
            return response()->json(['ok' => true, 'message' => $msg]);
        }
        return back()->with('toast', $msg);
    }

    private function bustAnalyticsCache(int $layananId): void
    {
        foreach ([7,14,30] as $d) {
            foreach ([3,5,10] as $l) {
                Cache::forget("analytics:freq_issues:layanan:$layananId:days:$d:limit:$l");
            }
        }
    }
}
