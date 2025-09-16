@extends('layout/app')

@section('main_content')
<div class="container min-vh-100 d-flex justify-content-center align-items-start pt-5">
  <div class="col-12 col-md-6 col-xl-4 p-0">
    <div class="card">
      <div class="card-header bg-info">
        <h5 class="card-title mb-0">Verifikasi PIN</h5>
      </div>
      <div class="card-body">
        @if ($errors->any())
          <div class="alert alert-danger mb-3">{{ $errors->first() }}</div>
        @endif

        <p class="text-muted">
          Masukkan PIN yang tampil pada perangkat pemilik antrean untuk mengaitkan tiket ini ke browser Anda.
        </p>

        <form method="POST" action="{{ route('timeline.join.verify', $ticket->id) }}">
          @csrf
          <input type="hidden" name="fp" id="fp">
          <div class="mb-3">
            <label class="form-label">PIN</label>
            <input type="text" name="pin" class="form-control" inputmode="numeric" maxlength="6"
                  placeholder="4 digit" required value="{{ old('pin') }}">
          </div>
          <div class="d-grid">
            <button type="submit" class="btn btn-primary">Verifikasi & Lanjut</button>
          </div>
        </form>

        <div class="mt-3">
          <a href="{{ route('antrean.index') }}" class="btn btn-link p-0">Kembali ke Antrean</a>
        </div>
      </div>
    </div>
  </div>
</div>

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

  document.getElementById('fp').value = await sha256(raw);
})();
</script>


@endsection
