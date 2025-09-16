@extends('layout/app')

@section('content')
<div class="row">
  <div class="col-sm-12">
    <div class="page-title-box">
      <div class="float-end">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="#">Metrica</a></li>
          <li class="breadcrumb-item"><a href="#">Pages</a></li>
          <li class="breadcrumb-item active">Timeline</li>
        </ol>
      </div>
      <h4 class="page-title">Layanan: {{ $layanan->service_name }}</h4>
    </div>
  </div>
</div>
@endsection

@section('main_content')
@php
use Carbon\Carbon;
use Carbon\CarbonInterface;

$asCarbon = function ($dt) { return $dt instanceof CarbonInterface ? $dt : ($dt ? Carbon::parse($dt) : null); };
$mmToHHMM = fn (int $minutes) => sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
$fmtHMS = function (int $sec) {
  $sec=max(0,$sec); $h=intdiv($sec,3600); $m=intdiv($sec%3600,60); $s=$sec%60;
  return sprintf('%02d:%02d:%02d',$h,$m,$s);
};
$createdAt = $tiket->created_at ? $tiket->created_at->copy()->timezone('Asia/Jakarta')->locale('id') : null;
@endphp

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header bg-info">
        <h4 class="card-title mb-0">Nomor Tiket: {{ $tiket->kode }}</h4>
        <p class="text-muted mb-0">Timeline layanan untuk tiket ini</p>
      </div>

      <div class="card-body">
        <div class="row">
          <div class="col-lg-8 mx-auto">

            @if ($errors->any())
              <div class="alert alert-danger mb-3">{{ $errors->first() }}</div>
            @endif
            @if (session('toast'))
              <div class="alert alert-info mb-3">{{ session('toast') }}</div>
            @endif

                    @if (!empty($joinUrl) && $tiket->status === 'running') 
                    <div class="alert alert-secondary mt-1 text-start"> 
                      <div><strong>Buka di browser lain di perangkat ini?</strong></div> 
                    <div class="mt-1">Masukkan PIN: 
                      <span class="fs-5 fw-bold">{{ $tiket->join_pin }}</span></div> 
                    <div class="input-group mt-2"> 
                      <input type="text" class="form-control" id="joinLinkInput" value="{{ $joinUrl }}" readonly> 
                      <button class="btn btn-outline-primary" type="button" id="btnCopyJoin">Copy Link</button> 
                    </div> 
                  </div> 
                  @endif

            {{-- Banner DONE --}}
            <div id="doneBanner" class="alert alert-primary mt-3 d-none text-start">
              <strong>🎉 Selamat!</strong> Semua tahapan layanan selesai.
              <span class="ms-2">Total durasi: <strong id="jsTotalDurationDone">--:--:--</strong></span>
            </div>

            {{-- Header info --}}
            <div class="alert alert-success text-start mt-3">
              <h5 class="mb-1">Nomor Antrian: <span class="fw-bold">{{ $tiket->kode }}</span></h5>
              <p class="mb-0">
                Layanan: <strong>{{ $layanan->service_name }}</strong><br>
                Waktu: <strong>{{ $createdAt ? $createdAt->translatedFormat('l, j F Y H:i:s') : '-' }}</strong><br>
                Durasi total: <strong id="jsTotalDuration">--:--:--</strong>
              </p>
            </div>

            {{-- Banner STOP (diisi via JS) --}}
            <div id="stoppedBanner" class="d-none"></div>

            {{-- ======= TIMELINE ======= --}}
            <div class="mt-3">
              @foreach ($steps as $step)
                @php
                  $p = $progress[$step->id] ?? null;
                  $isActive = $p && $p->status === 'running';
                  $isDone   = $p && $p->status === 'done';
                  $isStop   = $p && $p->status === 'stopped';
                  $statusText = $isActive ? 'Running' : ($isDone ? 'Done' : ($isStop ? 'Stopped' : 'Menunggu'));

                  $stdMin = (int) $step->std_step_time;
                  $stdSec = $stdMin * 60;

                  $startC = $asCarbon($p->started_at ?? null);
                  $endC   = $asCarbon($p->ended_at ?? null);
                  $actualSec = $startC ? ($endC ?: Carbon::now())->diffInSeconds($startC) : 0;
                @endphp

                <div class="tl-item step-item {{ $isActive ? 'running' : ($isDone ? 'done' : ($isStop ? 'stopped' : 'pending')) }}"
                     data-step-id="{{ $step->id }}" data-std-sec="{{ $stdSec }}">
                  <div class="tl-left">
                    <div class="tl-dot">
                      <i class="las {{ $isDone ? 'la-check' : ($isActive ? 'la-hourglass-start' : ($isStop ? 'la-times' : 'la-circle')) }}"></i>
                    </div>
                    @if (!$loop->last)
                      <div class="tl-conn {{ $isDone ? 'solid' : '' }}"></div>
                    @endif
                  </div>

                  <div class="tl-body">
                    <div class="d-flex justify-content-between align-items-center">
                      <h6 class="mb-1 step-title">{{ $step->service_step_name }}</h6>
                      <span class="text-muted">estimasi {{ $mmToHHMM($stdMin) }}</span>
                    </div>

                    <div class="progress mt-1" style="height:6px;">
                      <div class="progress-bar step-bar"
                           role="progressbar"
                           style="width: {{ $isDone ? '100' : ($isActive ? '1' : '0') }}%;"
                           aria-valuemin="0" aria-valuemax="100"></div>
                    </div>

                    <p class="text-muted mt-2 mb-1 text-start small step-meta">
                      Status: <strong class="js-status">{{ $statusText }}</strong>
                      • Mulai: <span class="js-start">{{ $startC ? $startC->copy()->timezone('Asia/Jakarta')->format('d M Y H:i') : '-' }}</span>
                      • Selesai: <span class="js-end">{{ $endC ? $endC->copy()->timezone('Asia/Jakarta')->format('d M Y H:i') : '-' }}</span>

                      {{-- REMAIN saat running --}}
                      <span class="js-remain-wrap {{ $isActive ? '' : 'd-none' }}">
                        • Sisa:
                        <strong class="remain"
                                data-start="{{ $startC?->copy()->timezone('Asia/Jakarta')->toIso8601String() }}"
                                data-std-sec="{{ $stdSec }}"
                                data-warn-sec="{{ max(60, (int)($stdSec * 0.2)) }}">00:00</strong>
                        <span class="badge bg-warning text-dark d-none ms-1" data-role="warn">Hampir habis</span>
                        <span class="badge bg-danger d-none ms-1" data-role="overtime">Lewat waktu</span>
                      </span>

                      {{-- ACTUAL saat non-running --}}
                      <span class="js-actual-wrap {{ $isActive ? 'd-none' : '' }}">
                        • Durasi aktual: <strong class="js-actual">{{ $startC ? $fmtHMS($actualSec) : '00:00:00' }}</strong>
                      </span>
                    </p>

                    {{-- 2 ALERT BOXES: TOP ISSUES & KENDALA ANDA --}}
                    <div class="issue-boxes">
                      <div class="js-top-issues" data-role="top-issues"></div>
                      <div class="js-user-issues" data-role="user-issues"></div>
                    </div>
                  </div>
                </div>
              @endforeach
            </div>
            {{-- ======= /TIMELINE ======= --}}

          </div>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Modal Solusi Issue --}}
<div class="modal fade" id="issueSolutionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title" id="issueSolutionTitle">Detail Issue</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div id="issueSolutionBody">Memuat…</div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<style>
  .card-body{overflow-x:hidden;}
  .tl-item{ display:flex; gap:12px; position:relative; padding:12px 8px; }
  .tl-left{ width:38px; position:relative; }
  .tl-body{ flex:1 1 auto; word-wrap:break-word; overflow-wrap:anywhere; }
  .tl-body *{ word-wrap:break-word; overflow-wrap:anywhere; }
  .tl-dot{ width:26px; height:26px; border-radius:50%; border:3px solid #cbd5e1; background:#fff; display:grid; place-items:center; color:#94a3b8; font-size:14px; }
  .step-item.running .tl-dot{ border-color:#0d6efd; color:#0d6efd; background:#eff6ff; }
  .step-item.done .tl-dot{ border-color:#198754; color:#198754; background:#ecfdf5; }
  .step-item.stopped .tl-dot{ border-color:#dc3545; color:#dc3545; background:#fef2f2; }
  .tl-conn{ position:absolute; left:16px; top:30px; bottom:-6px; width:3px; background: repeating-linear-gradient(to bottom, #e5e7eb 0 8px, transparent 8px 16px); transition: background-color .4s ease, opacity .3s ease; }
  .tl-conn.solid{ background:#93c5fd; }
  .step-item.running .step-bar{ position: relative; transition: width .7s linear; }
  .step-item.running .step-bar::after{ content:""; position:absolute; inset:0; background: linear-gradient(90deg, transparent, rgba(255,255,255,.8), transparent); animation: shimmer 1.2s linear infinite; }
  .step-item.done .step-bar{ width:100% !important; }
  .step-item.done .step-title{ color:#198754; }
  .step-item.stopped .step-title{ color:#dc3545; }
  .step-item.running .step-title{ color:#0d6efd; }
  .progress{min-width:120px;}

  .step-meta{display:flex; flex-wrap:wrap; gap:.5rem 1rem;}

  .issue-boxes{display:grid; gap:.5rem; margin-top:.5rem;}
  .alert-top-issues{background:#ecfdf5; border:1px solid #bbf7d0; color:#065f46; padding:.5rem .75rem; border-radius:.5rem;}
  .alert-user-issues{background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:.5rem .75rem; border-radius:.5rem;}

  .issue-list{display:flex; flex-wrap:wrap; gap:.5rem; margin:.25rem 0 0; padding:0; list-style:none;}
  .issue-chip{display:inline-block; padding:.25rem .5rem; border-radius:.4rem; border:1px solid currentColor; background:#fff; text-decoration:none; cursor:pointer; font-weight:500; max-width:100%;}
  .alert-top-issues .issue-chip{color:#065f46; border-color:#a7f3d0;}
  .alert-user-issues .issue-chip{color:#991b1b; border-color:#fecaca;}
</style>

{{-- ======== untuk proses copy link ============ --}}
@if (!empty($joinUrl) && $tiket->status === 'running')
<script>
(async function(){
  const plat  = navigator.userAgentData?.platform || navigator.platform || '';
  const cores = navigator.hardwareConcurrency || 0;
  const mem   = navigator.deviceMemory || 0;
  const w     = screen.width, h = screen.height, dpr = window.devicePixelRatio || 1;

  const raw = [plat, cores, mem, w, h, dpr].join('|');

  async function sha256(s){
    const enc = new TextEncoder().encode(s);
    const buf = await crypto.subtle.digest('SHA-256', enc);
    return Array.from(new Uint8Array(buf)).map(b=>b.toString(16).padStart(2,'0')).join('');
  }

  const fp = await sha256(raw);

  fetch("{{ route('timeline.store-fp', ['ticket'=>$tiket->id]) }}", {
    method:'POST',
    headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
    body: JSON.stringify({ fp })
  });
})();
</script>
@endif
{{-- ============ end ====================== --}}



<script>
(function(){
  /* ========= helpers ========= */
  const pad = n => String(n).padStart(2,'0');
  const toHMS = sec => { sec=Math.max(0,Math.floor(sec||0)); const h=Math.floor(sec/3600), m=Math.floor((sec%3600)/60), s=sec%60; return `${pad(h)}:${pad(m)}:${pad(s)}`; };
  const g = (obj, keys, def=null)=>{ for(const k of keys){ if(obj && obj[k]!=null) return obj[k]; } return def; };

  /* ========= ENDPOINTS ========= */
  const DATA_URL = "{{ url('/tiket/'.$tiket->id.'/timeline/data') }}" + "?include_freq=1&days=30&limit=3&force=1";
  const FALLBACK_TOP_ISSUES_URL = "{{ url('/petugas/step') }}"; // /{stepId}/issues/frequent

  /* ========= STATE ========= */
  const fetchedTopOnce = new Set();   // pastikan fallback per step cuma sekali
  let lastTicketStatus = '';

  function updateTotal(totalSec){
    const t = toHMS(totalSec);
    const a = document.getElementById('jsTotalDuration');     if (a) a.textContent = t;
    const b = document.getElementById('jsTotalDurationDone'); if (b) b.textContent = t;
    const c = document.getElementById('jsTotalDurationStop'); if (c) c.textContent = t;
  }

  function computeTotalActualSeconds(steps){
    let total = 0;
    for(const s of (steps||[])){
      const st = s.started_at ? new Date(s.started_at).getTime() : null;
      const en = s.ended_at   ? new Date(s.ended_at).getTime()   : null;
      if(st){ total += Math.max(0, Math.floor(((en ?? Date.now()) - st)/1000)); }
    }
    return total;
  }

  /* ========= live remain ========= */
  function tickRemain(){
    const now = Date.now();
    document.querySelectorAll('.step-item .js-remain-wrap:not(.d-none) .remain').forEach(el=>{
      const startIso = el.getAttribute('data-start');
      const stdSec = parseInt(el.getAttribute('data-std-sec')||'0',10);
      const warnSec = parseInt(el.getAttribute('data-warn-sec')||'60',10);
      if(!startIso || !stdSec) return;

      const passed = Math.max(0, Math.floor((now - new Date(startIso).getTime())/1000));
      const remain = Math.max(0, stdSec - passed);
      const wrap = el.closest('.js-remain-wrap');
      const warn = wrap?.querySelector('[data-role="warn"]');
      const over = wrap?.querySelector('[data-role="overtime"]');

      if (passed > stdSec){
        const ot = passed - stdSec;
        el.textContent = '00:00';
        el.classList.add('text-danger');
        if (over){ over.textContent = `Lewat waktu ${pad(Math.floor(ot/60))}:${pad(ot%60)}`; over.classList.remove('d-none'); }
        warn?.classList.add('d-none');
      } else {
        el.textContent = `${pad(Math.floor(remain/60))}:${pad(remain%60)}`;
        el.classList.remove('text-danger');
        over?.classList.add('d-none');
        if (warn){ (remain <= warnSec) ? warn.classList.remove('d-none') : warn.classList.add('d-none'); }
      }
    });
  }
  setInterval(tickRemain, 1000);
  tickRemain();

  function percent(startISO, endISO, stdMin, serverNowISO){
    const stdSec = Math.max(1, (parseInt(stdMin)||0)*60);
    const now = new Date(serverNowISO || Date.now()).getTime();
    const s = startISO ? new Date(startISO).getTime() : null;
    const e = endISO ? new Date(endISO).getTime() : null;
    if (!s) return 0;
    const elapsed = (e ? e : now) - s;
    return Math.max(0, Math.min(100, (elapsed/1000)/stdSec*100));
  }

  /* ========= Render TOP issues (hijau) TANPA frekuensi ========= */
  function renderTopIssues(stepId, items){
    const slot = document.querySelector(`.step-item[data-step-id="${stepId}"] [data-role="top-issues"]`);
    if (!slot) return;
    if (!Array.isArray(items) || !items.length){ slot.innerHTML=''; return; }

    const list = items.map(it => ({
      id: it.issue_id ?? it.id,
      name: it.issue_name ?? it.name ?? 'Issue'
    }));

    slot.innerHTML = `
      <div class="alert-top-issues">
        <div><strong>Kendala yang sering di hadapai Wajib Pajak Lain :</strong></div>
        <ul class="issue-list">
          ${list.map(o => `<li><a href="#" class="issue-chip js-issue-chip" data-issue-id="${o.id}">${o.name}</a></li>`).join('')}
        </ul>
      </div>
    `;
  }

  async function fetchTopIssuesFallback(stepId){
    if (fetchedTopOnce.has(stepId)) return;
    fetchedTopOnce.add(stepId);
    try{
      const url = `${FALLBACK_TOP_ISSUES_URL}/${stepId}/issues/frequent?days=30&limit=3`;
      const r = await fetch(url, {headers:{'Accept':'application/json'}, cache:'no-store'});
      if(!r.ok) return;
      const arr = await r.json();
      if(Array.isArray(arr)){
        const items = arr.map(x => ({
          issue_id: x.issue_id ?? x.id,
          issue_name: x.issue_name ?? x.name ?? `Issue #${x.issue_id ?? x.id}`,
        }));
        renderTopIssues(stepId, items);
      }
    }catch(_){}
  }

  /* ========= polling ========= */
  (function(){
    let timer=null, INTERVAL=2000, FAST=1000, SLOW=5000;

    async function refresh(force=false){
      try{
        const url = DATA_URL + `&ts=${force?Date.now():Math.floor(Date.now()/4000)}`;
        const r = await fetch(url, { headers: {'Accept':'application/json'}, cache:'no-store', credentials:'same-origin' });
        if(!r.ok) return;
        const data = await r.json();

        const steps = Array.isArray(data.steps) ? data.steps : [];
        const serverNow = g(data, ['server_now','now'],'');

        // total durasi aktual
        const totalActual = computeTotalActualSeconds(steps);
        updateTotal(totalActual);

        // status tiket
        const ticketStatus = (g(data, ['ticket_status','status'],'')||'').toString().toLowerCase();
        const doneBanner = document.getElementById('doneBanner');
        if (doneBanner) { (ticketStatus==='done') ? doneBanner.classList.remove('d-none') : doneBanner.classList.add('d-none'); }

        const stoppedName = g(data, ['stopped_step_name'], null);
        const stoppedBanner = document.getElementById('stoppedBanner');
        if (stoppedBanner){
          if (ticketStatus==='stopped' && stoppedName){
            stoppedBanner.className = 'alert alert-warning mt-3';
            stoppedBanner.innerHTML = `
              <strong>Mohon maaf,</strong> tahapan layanan Anda berhenti di <strong>${stoppedName}</strong>.
              <span class="ms-2">Total durasi: <strong id="jsTotalDurationStop">${toHMS(totalActual)}</strong></span>
              <div class="small text-muted mt-1">Silakan lengkapi berkas lalu ambil nomor antrean baru.</div>
            `;
          } else {
            stoppedBanner.className = 'd-none';
            stoppedBanner.innerHTML = '';
          }
        }

        // auto scroll ke header saat done/stop
        if (ticketStatus !== lastTicketStatus && (ticketStatus === 'done' || ticketStatus === 'stopped')) {
          window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        lastTicketStatus = ticketStatus;

        /* ===== reset visual ringan ===== */
        const itemById = {};
        document.querySelectorAll('.step-item[data-step-id]').forEach(w=>{
          const sid = String(w.getAttribute('data-step-id'));
          itemById[sid] = w;

          w.classList.remove('running','done','stopped','pending');
          w.classList.add('pending');

          const icon = w.querySelector('.tl-dot i');
          if (icon) { icon.classList.remove('la-check','la-hourglass-start','la-times','la-circle'); icon.classList.add('la-circle'); }
          const bar = w.querySelector('.step-bar'); if (bar) bar.style.width = '0%';

          const remainWrap = w.querySelector('.js-remain-wrap');
          const actualWrap = w.querySelector('.js-actual-wrap');
          remainWrap && remainWrap.classList.add('d-none');
          actualWrap && actualWrap.classList.remove('d-none');
          const act = actualWrap?.querySelector('.js-actual'); act && (act.textContent = '00:00:00');

          const st = w.querySelector('.js-start'); st && (st.textContent = '-');
          const en = w.querySelector('.js-end');   en && (en.textContent = '-');

          const sEl = w.querySelector('.js-status'); sEl && (sEl.textContent = 'Menunggu');

          const conn = w.querySelector('.tl-conn'); conn && conn.classList.remove('solid');

          // kosongkan isi alert (akan diisi ulang)
          const topSlot  = w.querySelector('[data-role="top-issues"]');  if(topSlot) topSlot.innerHTML='';
          const userSlot = w.querySelector('[data-role="user-issues"]'); if(userSlot) userSlot.innerHTML='';
        });

        /* ===== apply payload ===== */
        let running = 0;
        let foundStopped = false;
        for (const step of steps){
          const stepId = String(g(step,['id','step_id']));
          const wrap = itemById[stepId];
          if(!wrap) continue;

          let status  = (g(step,['status'],'pending')||'pending').toString().toLowerCase();
          // cascade stop: jika sudah ada stop sebelumnya, semua berikutnya stop
          if (foundStopped) status = 'stopped';
          if (status === 'stopped') foundStopped = true;

          const stdMin  = g(step,['std_min','std_step_time'],0);
          const started = g(step,['started_at'], null);
          const ended   = g(step,['ended_at'], null);

          wrap.classList.remove('running','done','stopped','pending');
          wrap.classList.add(status);
          if(status==='running') running++;

          const sEl = wrap.querySelector('.js-status');
          sEl && (sEl.textContent = (status==='pending' ? 'Menunggu' : status[0].toUpperCase()+status.slice(1)));

          const fmt = iso => iso ? new Date(iso).toLocaleString('id-ID',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '-';
          const st = wrap.querySelector('.js-start'); st && (st.textContent = fmt(started));
          const en = wrap.querySelector('.js-end');   en && (en.textContent = fmt(ended));

          const conn = wrap.querySelector('.tl-conn'); if (conn) { (status==='done'||ended) ? conn.classList.add('solid') : conn.classList.remove('solid'); }

          const bar = wrap.querySelector('.step-bar');
          if (bar){
            if (status==='done' || ended){ bar.style.width = '100%'; }
            else if (status==='running'){ bar.style.width = `${percent(started,null,stdMin,serverNow)}%`; }
            else { bar.style.width = '0%'; }
          }

          const icon = wrap.querySelector('.tl-dot i');
          if (icon){
            icon.classList.remove('la-check','la-hourglass-start','la-times','la-circle');
            if (status==='done')        icon.classList.add('la-check');
            else if (status==='running')icon.classList.add('la-hourglass-start');
            else if (status==='stopped')icon.classList.add('la-times');
            else                        icon.classList.add('la-circle');
          }

          const remainWrap = wrap.querySelector('.js-remain-wrap');
          const actualWrap = wrap.querySelector('.js-actual-wrap');
          const remainEl   = wrap.querySelector('.remain');

          if(status==='running'){
            remainWrap && remainWrap.classList.remove('d-none');
            actualWrap && actualWrap.classList.add('d-none');
            if(remainEl && started){
              remainEl.setAttribute('data-start', new Date(started).toISOString());
              const stdSec = Math.max(0,(parseInt(stdMin)||0)*60);
              if(stdSec>0) remainEl.setAttribute('data-std-sec', String(stdSec));
            }
          }else{
            remainWrap && remainWrap.classList.add('d-none');
            if(actualWrap){
              actualWrap.classList.remove('d-none');
              const sMs = started ? new Date(started).getTime() : null;
              const eMs = ended ? new Date(ended).getTime() : Date.now();
              const sec = sMs ? Math.max(0, Math.floor((eMs - sMs)/1000)) : 0;
              const act = actualWrap.querySelector('.js-actual'); act && (act.textContent = toHMS(sec));
            }
          }

          // === Alert merah: Kendala Anda ===
          const userSlot = wrap.querySelector('[data-role="user-issues"]');
          const userList = Array.isArray(step.issues) ? step.issues : [];
          if (userSlot) {
            if (userList.length) {
              const mapped = userList.map(o => ({
                id: o.id ?? o.issue_id,
                name: o.issue_name ?? o.name ?? 'Issue'
              }));
              userSlot.innerHTML = `
                <div class="alert-user-issues">
                  <div><strong>Kendala Anda :</strong></div>
                  <ul class="issue-list">
                    ${mapped.map(o => `<li><a href="#" class="issue-chip js-issue-chip" data-issue-id="${o.id}">${o.name}</a></li>`).join('')}
                  </ul>
                </div>
              `;
            } else {
              userSlot.innerHTML = '';
            }
          }
        }

        // 1) Render TOP issues dari analytics jika ada
        const freq = g(data,['frequent_issues'], null);
        if (freq && typeof freq === 'object' && Object.keys(freq).length){
          Object.keys(freq).forEach(stepId => renderTopIssues(stepId, freq[stepId] || []));
        }

        // 2) Fallback per step (sekali) bila analytics kosong
        document.querySelectorAll('.step-item[data-step-id]').forEach(w=>{
          const sid = String(w.getAttribute('data-step-id'));
          const slot = w.querySelector('[data-role="top-issues"]');
          if (slot && slot.innerHTML.trim()==='') fetchTopIssuesFallback(sid);
        });

        // interval adaptif
        const target = (steps||[]).some(s=>s.status==='running') ? FAST : SLOW;
        if (INTERVAL !== target){ INTERVAL = target; restart(); }

      }catch(e){ /* silent */ }
    }

    function loop(){ timer = setTimeout(()=>{ refresh(false).finally(loop); }, INTERVAL); }
    function restart(){ clearTimeout(timer); loop(); }

    refresh(true).finally(loop);

    ['visibilitychange','focus','online'].forEach(ev => window.addEventListener(ev, ()=>{ if(!document.hidden) refresh(true); }));

    window.addEventListener('storage', (e)=>{
      if(e.key==='queue:pulse'){
        try{
          const payload = JSON.parse(e.newValue||'{}');
          if(payload && payload.event==='progress-updated'){ refresh(true); }
        }catch(_){}
      }
    });
  })();

  /* ========= Copy link ========= */
  (function(){
    const btn = document.getElementById('btnCopyJoin');
    const input = document.getElementById('joinLinkInput');
    if(btn && input){
      btn.addEventListener('click', ()=>{
        input.select(); input.setSelectionRange(0, 99999);
        try{ document.execCommand('copy'); btn.textContent='Tersalin'; setTimeout(()=>btn.textContent='Copy Link',1200); }catch(_){}
      });
    }
  })();

  /* ========= Modal solusi (klik chip) ========= */
  document.addEventListener('click', async (ev)=>{
    const chip = ev.target.closest('.js-issue-chip');
    if(!chip) return;

    const id = chip.getAttribute('data-issue-id');
    if(!id) return;

    const mEl = document.getElementById('issueSolutionModal');
    const titleEl = document.getElementById('issueSolutionTitle');
    const bodyEl  = document.getElementById('issueSolutionBody');
    if (!mEl || !titleEl || !bodyEl) return;

    titleEl.textContent = 'Detail Issue';
    bodyEl.textContent  = 'Memuat…';

    try{
      const r = await fetch(`{{ url('/petugas/issues') }}/${id}/detail`, {headers:{'Accept':'application/json'}, cache:'no-store'});
      if(!r.ok) throw new Error('HTTP '+r.status);
      const d = await r.json();

      titleEl.textContent = d.issue_name || 'Detail Issue';
      bodyEl.innerHTML = (d.solution && String(d.solution).trim().length)
        ? `<div class="text-start">${String(d.solution).replace(/\n/g,'<br>')}</div>`
        : `<div class="text-muted">Belum ada solusi yang tercatat.</div>`;
    }catch(e){
      bodyEl.innerHTML = `<div class="text-danger">Gagal memuat detail issue.</div>`;
    }

    try{
      if(window.bootstrap && window.bootstrap.Modal){
        const bs = new bootstrap.Modal(mEl, {backdrop:'static'});
        bs.show();
      }else{
        mEl.style.display='block'; mEl.classList.add('show');
      }
    }catch(_){}
  });

})();
</script>
@endsection
