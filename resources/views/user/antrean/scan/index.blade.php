@extends('layout/app')

@section('main_content')
<div class="container min-vh-100 d-flex justify-content-center align-items-start pt-5">
  <div class="col-12 col-md-6 col-xl-3 p-0">
    <div class="card">
      <div class="card-header bg-info">
        <h4 class="card-title text-center">
          Scan Barcode untuk layanan {{ $layanan->service_name }}
        </h4>
      </div>
      <div class="card-body">

        {{-- ALERTS menandakan nomor antrean sudah di pakai --}}
        @if ($errors->has('payload'))
          <div class="alert alert-danger mb-3">{{ $errors->first('payload') }}</div>
        @elseif ($errors->any())
          <div class="alert alert-danger mb-3">{{ $errors->first() }}</div>
        @endif

        @if (session('toast'))
          <div class="alert alert-info mb-3">{{ session('toast') }}</div>
        @endif

        <div class="card-body border">
          <p class="card-subtitle font-14 mb-2">Scan Barcode anda disini</p>
          <p class="card-text text-muted">Arahkan kamera ke barcode layanan.</p>

          {{-- Area scanner --}}
          <div id="reader" style="max-width:480px"></div>

          {{-- Hasil sementara --}}
          <div class="alert alert-light border mt-3 mb-0 d-none" id="scanResult"></div>

          {{-- Form auto submit --}}
          <form id="startForm" action="{{ route('scan.start', $layanan->id) }}" method="POST" class="d-none">
            @csrf
            <input type="hidden" name="payload" id="payload">
          </form>

          {{-- Modal konfirmasi --}}
          <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
              <div class="modal-content">
                <div class="modal-header bg-info text-white">
                  <h5 class="modal-title" id="confirmLabel">Konfirmasi Antrean</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                  <p>Nomor antrean terbaca:</p>
                  <h4 id="confirmQueue" class="fw-bold text-center"></h4>
                  <p class="mt-3">Layanan: <strong>{{ $layanan->service_name }}</strong></p>
                  <p class="mt-3 text-muted">
                    Waktu: <span id="confirmDateTime"></span>
                  </p>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" id="btnCancel">Batal</button>
                  <button type="button" class="btn btn-primary" id="btnConfirm">Ya, Mulai</button>
                </div>
              </div>
            </div>
          </div>
          {{-- End Modal --}}

          {{-- Container tombol setelah batal --}}
          <div id="afterCancel" class="d-none mt-3 text-center">
            <button id="btnRescan" class="btn btn-warning me-2">Scan Ulang</button>
            <a href="{{ url()->previous() }}" class="btn btn-secondary">Kembali</a>
          </div>

        </div>

        @if ($errors->any())
          <div class="alert alert-danger">
            {{ $errors->first() }}
          </div>
        @endif
        @if (session('toast'))
          <div class="alert alert-info">{{ session('toast') }}</div>
        @endif


      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
  {{-- ZXing fallback (multi-format: QR, Code128, EAN, Code39, ITF, Codabar, dsb) --}}
  <script src="https://unpkg.com/@zxing/library@0.20.0"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const scanResult     = document.getElementById('scanResult');
      const form           = document.getElementById('startForm');
      const payloadEl      = document.getElementById('payload');
      const afterCancelBox = document.getElementById('afterCancel');

      // pakai container yang sama (#reader), tapi kita render <video> sendiri
      const reader = document.getElementById('reader');
      reader.innerHTML = '<video id="cam" playsinline muted autoplay style="width:100%;max-width:480px;border-radius:8px;"></video>';
      const video = document.getElementById('cam');

      let mediaStream = null, rafId = null, zxingReader = null;

      // ==== helper: izin cepat agar label kamera terisi (iOS/Safari) ====
      async function primePermission() {
        try {
          if (!navigator.mediaDevices?.getUserMedia) return;
          const s = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' }}, audio: false });
          s.getTracks().forEach(t => t.stop());
        } catch (_) {}
      }

      // ==== helper: deviceId kamera belakang ====
      async function getBackDeviceId() {
        try {
          const devs = await navigator.mediaDevices.enumerateDevices();
          const vids = devs.filter(d => d.kind === 'videoinput');
          if (!vids.length) return null;
          const back = vids.find(d => /(back|rear|environment|trás|arrière|задн|후면|背面|后置)/i.test(d.label));
          return (back || vids[vids.length - 1]).deviceId; // fallback: terakhir (biasanya rear di Android)
        } catch { return null; }
      }

      async function openBackCamera() {
        const backId = await getBackDeviceId();
        const constraints = backId
          ? { video: { deviceId: { exact: backId }}, audio: false }
          : { video: { facingMode: { ideal: 'environment' }}, audio: false };

        const stream = await navigator.mediaDevices.getUserMedia(constraints);
        video.srcObject = stream;
        await video.play();
        mediaStream = stream;
        return backId;
      }

      function stopAll() {
        if (rafId) { cancelAnimationFrame(rafId); rafId = null; }
        if (zxingReader) { try { zxingReader.reset(); } catch(_){} zxingReader = null; }
        if (mediaStream) { mediaStream.getTracks().forEach(t => t.stop()); mediaStream = null; }
      }

      // ==== handleSuccess: tetap punyamu (modal konfirmasi tetap muncul) ====
      function handleSuccess(raw) {
        const rawText = String(raw || '').trim();

        const withPrefix = /^SERVICE:\s*[A-Z0-9][A-Z0-9\s-]*$/i.test(rawText);
        const queueCode  = /^[A-Z0-9][A-Z0-9\s-]*$/i.test(rawText);

        if (!withPrefix && !queueCode) {
          scanResult.classList.remove('d-none', 'alert-success');
          scanResult.classList.add('alert-danger');
          scanResult.innerText = 'Format barcode/QR tidak dikenali.';
          return;
        }

        const norm = rawText
          .toUpperCase()
          .replace(/[^A-Z0-9\s-]/g, '')
          .replace(/\s+/g, ' ')
          .trim();

        const payload = withPrefix
          ? norm.replace(/^SERVICE:\s*/i, 'SERVICE:')
          : `SERVICE:${norm}`;

        payloadEl.value = payload;
        document.getElementById('confirmQueue').innerText = norm;

        const now     = new Date();
        const dateStr = now.toLocaleDateString('id-ID', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
        const hh = String(now.getHours()).padStart(2,'0');
        const mm = String(now.getMinutes()).padStart(2,'0');
        const ss = String(now.getSeconds()).padStart(2,'0');
        document.getElementById('confirmDateTime').innerText = `${dateStr} ${hh}:${mm}:${ss}`;

        stopAll();

        const modalEl = document.getElementById('confirmModal');
        const modal   = new bootstrap.Modal(modalEl);
        modal.show();

        document.getElementById('btnConfirm').onclick = () => form.submit();
        document.getElementById('btnCancel').onclick  = () => {
          modal.hide();
          afterCancelBox.classList.remove('d-none');
          document.getElementById('btnRescan').onclick = () => {
            afterCancelBox.classList.add('d-none');
            startScanner();
          };
        };
      }

      // ==== scanner A: BarcodeDetector (cepat, native) ====
      async function startWithBarcodeDetector() {
        if (!('BarcodeDetector' in window)) return false;

        let formats = [
          'qr_code','code_128','code_39','ean_13','ean_8','upc_a','upc_e','itf','codabar',
          'data_matrix','pdf417','aztec'
        ];
        try {
          const sup = await window.BarcodeDetector.getSupportedFormats?.();
          if (Array.isArray(sup) && sup.length) {
            formats = formats.filter(f => sup.includes(f));
          }
        } catch(_) {}

        const detector = new window.BarcodeDetector({ formats });
        await openBackCamera();

        const loop = async () => {
          try {
            const codes = await detector.detect(video);
            if (codes && codes.length) {
              const value = codes[0].rawValue || codes[0].rawText || '';
              if (value) { handleSuccess(value); return; }
            }
          } catch(_) {}
          rafId = requestAnimationFrame(loop);
        };
        rafId = requestAnimationFrame(loop);
        return true;
      }

      // ==== scanner B: ZXing (multi-format) ====
      async function startWithZXing() {
        await primePermission();
        const deviceId = await openBackCamera();

        zxingReader = new ZXing.BrowserMultiFormatReader();
        return new Promise((resolve) => {
          zxingReader.decodeFromVideoDevice(deviceId || undefined, video, (result, err) => {
            if (result) {
              handleSuccess(result.getText());
              resolve(true);
            }
            // err muncul saat scanning belum ketemu; abaikan
          });
        });
      }

      async function startScanner() {
        afterCancelBox.classList.add('d-none');
        scanResult?.classList.add('d-none');
        if (scanResult) scanResult.innerText = '';

        stopAll();

        try {
          const okBD = await startWithBarcodeDetector();
          if (okBD) return;

          const okZX = await startWithZXing();
          if (okZX) return;

          afterCancelBox.classList.remove('d-none');
        } catch (e) {
          afterCancelBox.classList.remove('d-none');
        }
      }

      document.getElementById('btnRescan')?.addEventListener('click', () => {
        afterCancelBox.classList.add('d-none');
        startScanner();
      });

      // auto-start
      startScanner();
    });
  </script>
@endpush