<?php

namespace App\Http\Controllers;

use App\Models\KStep;
use App\Models\Ticket;
use App\Models\KLayanan;
use App\Models\Progress;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\SyaratDokumen;
use Illuminate\Support\Facades\DB;

class AntreanController extends Controller
{
    // === GUARD RINGAN DI INDEX: jika ada tiket aktif → langsung ke timeline ===
    public function index()
    {
        if ($id = session('active_ticket_id')) {
            $t = Ticket::find($id);
            if ($t && $t->status === 'running') {
                return redirect()->route('timeline.show', $t->id);
            }
            session()->forget('active_ticket_id');
        }

        $docs = SyaratDokumen::with([
                'layanan' => fn($q) => $q->select('id','service_name','status_layanan')
            ])
            ->whereHas('layanan', fn($q) => $q->where('status_layanan', 1))
            ->get();

        return view('user/antrean/index', [
            'title'       => 'Layanan Antrean',
            'menuAntrean' => 'active',
            'grouped'     => $docs->groupBy(fn($d)=>$d->layanan->service_name),
        ]);
    }

    // === GUARD DI SCAN: kalau ada tiket aktif → arahkan ===
    public function scan(KLayanan $layanan)
    {
        if ($id = session('active_ticket_id')) {
            $t = Ticket::find($id);
            if ($t && $t->status === 'running') {
                return redirect()->route('timeline.show', $t->id);
            }
            session()->forget('active_ticket_id');
        }
        return view('user/antrean/scan/index', compact('layanan'));
    }

    // === START: pembuatan tiket dengan semua rule ===
    public function start(Request $request, KLayanan $layanan)
    {
        return DB::transaction(function () use ($request, $layanan) {

            // 1) Ambil / normalisasi kode dari payload "SERVICE:<KODE>"
            $payload = (string)$request->input('payload','');
            $kode = null;
            if (preg_match('/^SERVICE:\s*([A-Z0-9][A-Z0-9\s-]*)$/i', $payload, $m)) {
                $kode = strtoupper(trim(preg_replace('/\s+/', ' ', $m[1])));
            } else {
                // fallback generator: prefix dari nama layanan + urut harian
                $prefix = strtoupper(Str::substr($layanan->service_name, 0, 1));
                $urut   = Ticket::where('layanan_id',$layanan->id)->today()->count() + 1;
                $kode   = sprintf('%s %03d', $prefix, $urut);
            }

            $today = now('Asia/Jakarta')->toDateString();

           // 2) Cek reuse nomor hari-ini di layanan manapun
            $usedToday = Ticket::where('kode',$kode)->where('ticket_date',$today)->latest('id')->first();

            if ($usedToday) {
                if ($usedToday->layanan_id !== $layanan->id) {
                    return back()->withErrors([
                        'payload' => "nomor antrean ini sudah terpakai di layanan {$usedToday->layanan->service_name}"
                    ]);
                }

                if ($usedToday->status === 'running') {
                    // jika sesi ini pemiliknya -> arahkan
                    if (session('active_ticket_id') == $usedToday->id) {
                        return redirect()->route('timeline.show', $usedToday->id)
                            ->with('toast','Antrean Anda masih aktif.');
                    }
                    // bukan sesi ini -> tolak (ini mencegah HP lain re-scan)
                    return back()->withErrors([
                        'payload' => 'Nomor antrean ini sudah digunakan di perangkat lain.'
                    ]);
                }

                return back()->withErrors([
                    'payload' => 'Nomor antrean ini sudah dipakai hari ini. Silakan ambil nomor antrean baru.'
                ]);
            }

        // 3) Apakah sesi ini sudah punya tiket aktif di layanan ini?
        if ($id = session('active_ticket_id')) {
            $active = Ticket::find($id);
            if ($active && $active->status === 'running' && $active->layanan_id === $layanan->id) {
                return redirect()->route('timeline.show', $active->id)
                    ->with('toast', 'Anda masih memiliki antrean aktif untuk layanan ini.');
            }
        }

            // 4) Buat tiket baru
            $ticket = Ticket::create([
                'layanan_id'  => $layanan->id,
                'kode'        => $kode,
                'ticket_date' => $today,
                'status'      => 'running',
            ]);

            // 5) Jalankan step pertama
            $first = KStep::where('layanan_id',$layanan->id)->orderBy('step_order')->firstOrFail();
            Progress::firstOrCreate(
                ['ticket_id' => $ticket->id, 'step_id' => $first->id],
                ['started_at' => now(), 'status' => 'running']
            );

            // 6) simpan sesi aktif
            session(['active_ticket_id' => $ticket->id]);

            return redirect()->route('timeline.show', $ticket->id)->with('ticket', [
                'number'   => $ticket->kode,
                'service'  => $layanan->service_name,
                'datetime' => now()->setTimezone('Asia/Jakarta')->locale('id')
                                  ->translatedFormat('l, j F Y H:i:s'),
            ]);
        });
    }

    // === PETUGAS: next/stop dengan pembersihan session jika tiket selesai ===
    public function decision(Request $request, Progress $progress)
    {
        $request->validate(['action' => 'required|in:next,stop']);

        return DB::transaction(function () use ($request, $progress) {
            $ticket = $progress->ticket;
            $currentStep = $progress->step;

            if (in_array($ticket->status, ['done','stopped'], true)) {
                return back()->with('toast','Tiket sudah tidak aktif.');
            }

           if ($request->action === 'stop') {
            if ($progress->status === 'running') {
                $progress->update(['ended_at'=>now(),'status'=>'stopped']);
            }
            $ticket->status = 'stopped';
            $ticket->join_pin = null; // opsional: tidak tampil lagi
            $ticket->save();
            if (session('active_ticket_id') == $ticket->id) session()->forget('active_ticket_id');
            return back()->with('toast','Layanan dihentikan.');
        }

            if ($progress->status === 'running') {
                $progress->update(['ended_at'=>now(),'status'=>'done']);
            }

            $next = KStep::where('layanan_id',$ticket->layanan_id)
                    ->where('step_order','>',$currentStep->step_order)
                    ->orderBy('step_order')->first();

            if (!$next) {
                $ticket->status = 'done';
                $ticket->join_pin = null; // opsional
                $ticket->save();
                if (session('active_ticket_id') == $ticket->id) session()->forget('active_ticket_id');
                return back()->with('toast','Layanan selesai.');
            }

            Progress::firstOrCreate(
                ['ticket_id'=>$ticket->id,'step_id'=>$next->id],
                ['started_at'=>now(),'status'=>'running']
            );

            return back()->with('toast','Lanjut ke tahapan berikutnya.');
        });
    }
}
