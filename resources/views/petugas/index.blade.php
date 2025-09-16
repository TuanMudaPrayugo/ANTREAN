@extends('layout/app')

@section('content')
<div class="row">
  <div class="col-sm-12">
    <div class="page-title-box">
      <div class="float-end">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="#">Metrica</a></li>
          <li class="breadcrumb-item active">Akses Petugas</li>
        </ol>
      </div>
      <h4 class="page-title">{{ $title ?? 'Data List Antrean' }}</h4>
    </div>
  </div>
</div>
@endsection

@section('main_content')
<div class="row">
  <div class="col-lg-9">

    {{-- Pencarian --}}
    <div class="card mb-2">
      <div class="card-body py-3">
        <div class="input-group">
          <span class="input-group-text"><i class="las la-search"></i></span>
          <input type="text" id="searchQueue" class="form-control" placeholder="Cari nomor antrean… (contoh: B 37 atau B37)">
        </div>
      </div>
    </div>

    {{-- RUNNING --}}
    <div class="card">
      <div class="card-header"><h5 class="mb-0">Antrian Aktif (Running)</h5></div>
      <div class="card-body p-3">
        <div id="runningList" class="list-group">
          @forelse ($running as $p)
            @php
              $t = $p->ticket; $s = $p->step; $lay = $t->layanan;
              $start = $p->started_at?->copy()->timezone('Asia/Jakarta');
              $normCode = strtoupper(preg_replace('/\s+/', '', $t->kode));
              $issueNames = $p->issues->isNotEmpty() ? $p->issues->pluck('issue_name')->all() : ($p->issue?->issue_name ? [$p->issue->issue_name] : []);
            @endphp
            {{-- Tidak pakai data-bs-toggle—dibuka manual via JS --}}
            <a href="#" role="button"
               class="list-group-item list-group-item-action bg-soft-warning mb-2 js-open-modal"
               data-progress-id="{{ $p->id }}"
               data-ticket-id="{{ $t->id }}"
               data-step-id="{{ $s->id }}"
               data-step-name="{{ $s->service_step_name }}"
               data-ticket-code="{{ $t->kode }}"
               data-code-norm="{{ $normCode }}"
               data-service-name="{{ $lay->service_name }}">
              <div class="d-flex justify-content-between align-items-center">
                <div class="text-start">
                  <div class="fw-bold">{{ $t->kode }} — {{ $lay->service_name }}</div>
                  <small>
                    Step: <strong>{{ $s->service_step_name }}</strong>
                    • Mulai: {{ $start?->format('d M Y H:i') ?? '-' }}
                    @foreach ($issueNames as $nm)
                      • <span class="badge bg-danger-subtle text-danger">Issue: {{ $nm }}</span>
                    @endforeach
                  </small>
                </div>
                <div class="text-end">
                  <small>Durasi</small>
                  <div class="fw-bold remain" data-start="{{ $start?->toIso8601String() }}">00:00</div>
                </div>
              </div>
            </a>
          @empty
            <div class="text-muted">Tidak ada antrian aktif.</div>
          @endforelse
        </div>
      </div>
    </div>

    {{-- STOPPED --}}
    @if($stopped->isNotEmpty())
    <div class="card mt-3">
      <div class="card-header"><h5 class="mb-0">Dihentikan</h5></div>
      <div class="card-body p-3">
        @foreach ($stopped as $p)
          @php
            $t=$p->ticket; $s=$p->step; $lay=$t->layanan;
            $issueNames = $p->issues->isNotEmpty() ? $p->issues->pluck('issue_name')->all() : ($p->issue?->issue_name ? [$p->issue->issue_name] : []);
          @endphp
          <div class="list-group-item bg-soft-danger mb-2">
            <div class="d-flex justify-content-between">
              <div class="text-start">
                <div class="fw-bold">{{ $t->kode }} — {{ $lay->service_name }}</div>
                <small>Berhenti di: <strong>{{ $s->service_step_name }}</strong></small>
                @foreach ($issueNames as $nm)
                  <small> • <span class="badge bg-danger-subtle text-danger">Issue: {{ $nm }}</span></small>
                @endforeach
              </div>
              <div class="text-end">
                <small>Status</small>
                <div class="fw-bold text-danger">Stopped</div>
              </div>
            </div>
          </div>
        @endforeach
      </div>
    </div>
    @endif

  </div>

  {{-- Modal: TENGAH-ATAS --}}
  <div class="modal fade stick-top" id="actionModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog">
      <form id="actionForm" method="POST">
        @csrf
        <div class="modal-content">
          <div class="modal-header bg-info text-white">
            <h5 class="modal-title">Aksi Petugas</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-2">
              <div>Kode Tiket: <strong id="mTicketCode"></strong></div>
              <div>Layanan: <strong id="mServiceName"></strong></div>
              <div>Step aktif: <strong id="mStepName"></strong></div>
            </div>

            <input type="hidden" name="action" id="mAction">
            <input type="hidden" name="issue_id" id="mIssueId">

            <div class="mb-2 d-none" id="noteWrap">
              <label class="form-label">Catatan</label>
              <textarea class="form-control" name="note" rows="2" placeholder="Catatan tambahan (opsional)"></textarea>
            </div>

            <div id="issueWrap" class="d-none">
              <label class="form-label">Pilih Issue</label>
              <div id="issueList" class="list-group small"></div>

              <div id="chosenIssue" class="mt-2 d-none">
                <span class="text-muted me-1">Issue terpilih:</span>
                <span class="badge bg-danger-subtle text-danger" id="chosenIssueText"></span>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <div class="btn-group me-auto" role="group">
              <button type="button" class="btn btn-outline-secondary" id="btnIssue">Issue</button>
            </div>
            <button type="button" class="btn btn-danger" id="btnStop">Stop</button>
            <button type="button" class="btn btn-primary" id="btnNext">Lanjut</button>
            <button type="submit" class="btn btn-warning d-none" id="btnProcess">Proses</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Posisi modal benar2 tengah-atas & z-index di atas backdrop --}}
<style>
  .js-open-modal{ cursor:pointer; }
  .modal.stick-top .modal-dialog{
    position: fixed; top: 6vh; left: 50%;
    transform: translateX(-50%) !important;
    margin: 0; width: min(640px, calc(100% - 2rem));
    z-index: 1061;
  }
  .modal.stick-top .modal-content{ max-height: calc(100vh - 12vh); overflow: auto; }
</style>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  // ====== helpers ======
  function pad(n){return String(n).padStart(2,'0');}
  var BASE_ACTION=@json(url('/petugas/progress'));
  var BASE_ISSUES=@json(url('/petugas/step'));

  // durasi di kartu RUNNING
  function tickRunning(){
    var now=new Date();
    document.querySelectorAll('.remain').forEach(function(el){
      var iso=el.getAttribute('data-start');
      if(!iso) return;
      var sec=Math.max(0,Math.floor((now-new Date(iso))/1000));
      var h=Math.floor(sec/3600), m=Math.floor((sec%3600)/60), s=sec%60;
      el.textContent=pad(h)+':'+pad(m)+':'+pad(s);
    });
  }
  tickRunning();
  setInterval(tickRunning,1000);

  // pencarian
  function norm(s){return (s||'').toString().toUpperCase().replace(/\s+/g,'').trim();}
  var searchEl=document.getElementById('searchQueue');
  if(searchEl){
    searchEl.addEventListener('input',function(e){
      var q=norm(e.target.value);
      document.querySelectorAll('#runningList [data-code-norm]').forEach(function(it){
        var code=it.getAttribute('data-code-norm')||'';
        it.style.display=(q===''||code.indexOf(q)>-1)?'':'none';
      });
    });
  }

  // ====== modal (bootstrap + fallback) ======
  var modalEl=document.getElementById('actionModal');
  var formEl =document.getElementById('actionForm');
  var btnNext=document.getElementById('btnNext');
  var btnStop=document.getElementById('btnStop');
  var btnIssue=document.getElementById('btnIssue');
  var btnProcess=document.getElementById('btnProcess');
  var bsModal=null, progressId=null, stepId=null, ticketId=null;
  var BD_ID='__tmp_bd__';

  function showModal(){
    try{
      if(window.bootstrap && window.bootstrap.Modal){
        if(!bsModal) bsModal=new bootstrap.Modal(modalEl,{backdrop:'static',keyboard:false});
        bsModal.show(); return;
      }
    }catch(e){}
    // fallback manual
    modalEl.style.display='block';
    modalEl.classList.add('show');
    modalEl.removeAttribute('aria-hidden');
    modalEl.setAttribute('aria-modal','true');
    document.body.classList.add('modal-open');
    if(!document.getElementById(BD_ID)){
      var bd=document.createElement('div');
      bd.id=BD_ID; bd.className='modal-backdrop fade show';
      document.body.appendChild(bd);
    }
  }
  function hideModal(){
    if(bsModal){ try{bsModal.hide();return;}catch(e){} }
    modalEl.classList.remove('show');
    modalEl.style.display='';
    modalEl.setAttribute('aria-hidden','true');
    document.body.classList.remove('modal-open');
    var bd=document.getElementById(BD_ID); if(bd) bd.remove();
  }
  modalEl.addEventListener('click',function(e){
    if(e.target.closest('[data-bs-dismiss="modal"]')){ e.preventDefault(); hideModal(); }
  });

  function resetModal(){
    document.getElementById('mAction').value='';
    document.getElementById('mIssueId').value='';
    document.getElementById('noteWrap').classList.add('d-none');
    document.getElementById('issueWrap').classList.add('d-none');
    document.getElementById('chosenIssue').classList.add('d-none');
    btnProcess.classList.add('d-none');
    btnProcess.textContent='Proses';
    btnProcess.dataset.mode='';
    modalEl.querySelectorAll('.modal-footer .btn').forEach(function(b){ b.disabled=false; });
    var note=formEl.querySelector('textarea[name="note"]'); if(note) note.value='';
  }
  function disableButtons(disabled){
    modalEl.querySelectorAll('.modal-footer .btn').forEach(function(b){ b.disabled=disabled; });
  }

  // buka modal dari list
  var runningList=document.getElementById('runningList');
  if(runningList){
    runningList.addEventListener('click',function(ev){
      var a=ev.target.closest('a[data-progress-id]'); if(!a) return;
      ev.preventDefault();
      progressId=a.getAttribute('data-progress-id');
      stepId =a.getAttribute('data-step-id');
      ticketId =a.getAttribute('data-ticket-id');

      document.getElementById('mTicketCode').textContent=a.getAttribute('data-ticket-code')||'';
      document.getElementById('mServiceName').textContent=a.getAttribute('data-service-name')||'';
      document.getElementById('mStepName').textContent=a.getAttribute('data-step-name')||'';

      resetModal();
      // ⚠️ pakai attribute action, jangan formEl.action (bentrok dengan input name="action")
      formEl.setAttribute('action', BASE_ACTION+'/'+progressId+'/action');
      showModal();
    });
  }

  // tombol aksi
  btnNext.addEventListener('click',function(){
    if(!formEl.getAttribute('action')) return;
    document.getElementById('mAction').value='next';
    if(formEl.requestSubmit) formEl.requestSubmit(); else formEl.submit();
  });
  btnStop.addEventListener('click',function(){
    document.getElementById('mAction').value='stop';
    document.getElementById('noteWrap').classList.remove('d-none');
    btnProcess.textContent='Kirim Stop';
    btnProcess.dataset.mode='stop';
    btnProcess.classList.remove('d-none');
    var note=formEl.querySelector('textarea[name="note"]'); if(note) note.focus();
  });
  btnIssue.addEventListener('click',function(){
    var wrap=document.getElementById('issueWrap');
    var list=document.getElementById('issueList');
    var chosen=document.getElementById('chosenIssue');
    var chosenText=document.getElementById('chosenIssueText');

    wrap.classList.remove('d-none');
    chosen.classList.add('d-none');
    list.innerHTML='<div class="list-group-item">Memuat…</div>';

    fetch(BASE_ISSUES+'/'+stepId+'/issues',{cache:'no-store'})
      .then(r=>r.json())
      .then(function(data){
        list.innerHTML='';
        if(!Array.isArray(data) || !data.length){
          list.innerHTML='<div class="list-group-item">Tidak ada issue untuk step ini.</div>'; return;
        }
        data.forEach(function(it){
          var el=document.createElement('a');
          el.href='#'; el.className='list-group-item list-group-item-action';
          el.textContent=it.issue_name;
          el.addEventListener('click',function(e){
            e.preventDefault();
            document.getElementById('mIssueId').value=it.id;
            document.getElementById('mAction').value='process';
            btnProcess.textContent='Proses';
            btnProcess.dataset.mode='process';
            btnProcess.classList.remove('d-none');
            chosenText.textContent=it.issue_name;
            chosen.classList.remove('d-none');
            list.querySelectorAll('.list-group-item').forEach(function(x){x.classList.remove('active');});
            el.classList.add('active');
          });
          list.appendChild(el);
        });
      })
      .catch(function(){ list.innerHTML='<div class="list-group-item text-danger">Gagal memuat issue.</div>'; });
  });
  btnProcess.addEventListener('click',function(){
    if(!formEl.getAttribute('action')) return;
    if(formEl.requestSubmit) formEl.requestSubmit(); else formEl.submit();
  });

  // submit → fetch JSON → broadcast → soft reload list saja (tanpa full reload halaman)
  formEl.addEventListener('submit', function(ev){
    ev.preventDefault();
    var actionUrl = formEl.getAttribute('action');
    if(!actionUrl) return;

    disableButtons(true);
    var fd=new FormData(formEl);

    fetch(actionUrl,{
      method:'POST', body:fd, credentials:'same-origin',
      headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' }
    })
    .then(function(r){ return r.ok ? r.json() : Promise.reject(r); })
    .then(function(){
      try{
        // Broadcast ke tab lain (timeline user) agar melakukan refresh segera
        localStorage.setItem('queue:pulse', JSON.stringify({
          event:'progress-updated',
          ticket:String(ticketId||''),
          at:Date.now()
        }));
      }catch(e){}
      hideModal();
      // Segarkan daftar petugas ringan (biar state match)
      location.reload();
    })
    .catch(function(err){
      disableButtons(false);
      alert('Gagal menyimpan aksi. Coba lagi.');
      console.error('action error:', err);
    });
  });
});
</script>
@endpush
@endsection
