@extends('layoutuser.app')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}"/>

<style>
  .chat-wrap{max-width:900px;margin:24px auto;padding:0 12px}
  .bubble{background:#f4f6f8;border-radius:14px;padding:12px 16px;margin:10px 0;box-shadow:0 1px 2px rgba(0,0,0,.04)}
  .bubble.me{background:#e3f2ff;align-self:flex-end}
  .title{font-weight:700;margin-bottom:6px}
  .alternatives{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
  .alt-btn{border:1px solid #d0d7de;background:#fff;border-radius:10px;padding:6px 10px;cursor:pointer}
  .feedback{display:flex;gap:8px;margin-top:10px;align-items:center}
  .btn{border:0;border-radius:10px;padding:8px 12px;cursor:pointer}
  .btn-yes{background:#10b981;color:#fff}
  .btn-no{background:#ef4444;color:#fff}
  .input-row{display:flex;gap:8px;margin-top:12px}
  .input-row input{flex:1;border:1px solid #d0d7de;border-radius:12px;padding:10px 12px}
  .input-row button{background:#2563eb;border:0;color:#fff;border-radius:12px;padding:10px 16px}

  .muted{color:#6b7280;font-size:.9rem}

  /* === Typing Indicator === */
  .typing{display:flex;align-items:center;gap:10px}
  .dots{display:inline-flex;gap:6px}
  .dot{width:8px;height:8px;border-radius:50%;background:#9ca3af;opacity:.35;animation:blink 1.2s infinite}
  .dot:nth-child(2){animation-delay:.2s}
  .dot:nth-child(3){animation-delay:.4s}
  @keyframes blink{
    0%,80%,100%{opacity:.35;transform:translateY(0)}
    40%{opacity:1;transform:translateY(-2px)}
  }
  /* skeleton/shimmer line */
  .skeleton{position:relative;overflow:hidden;border-radius:8px;background:#e5e7eb;height:10px;margin:6px 0}
  .skeleton::after{
    content:"";position:absolute;inset:0;
    background:linear-gradient(90deg, rgba(229,231,235,0) 0%, rgba(255,255,255,.7) 50%, rgba(229,231,235,0) 100%);
    transform:translateX(-100%);animation:shimmer 1.2s infinite;
  }
  @keyframes shimmer{100%{transform:translateX(100%)}}

  /* small helper */
  .is-disabled{opacity:.6;pointer-events:none}
</style>

<div class="chat-wrap">
  <h2 style="margin:8px 0 14px;">Tanya Sahar</h2>
  <div id="chat" style="display:flex;flex-direction:column;"></div>

  <div class="input-row">
    <input id="q" type="text" placeholder="Tulis pertanyaan di sini...">
    <button id="send">Kirim</button>
  </div>
</div>
@endsection

@push('scripts')
<script>
const routes = {
  ask: "{{ route('TanyaSahar.ask') }}",
  feedback: "{{ route('TanyaSahar.feedback') }}"
};
const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

const chatEl = document.getElementById('chat');
const qEl = document.getElementById('q');
const sendBtn = document.getElementById('send');

let lastState = { issue_id:null, alternatives:[], user_query:'' };
let typingNode = null;
let typingTimer = null;
//
// tambahan fungsi baru untuk rekomendasi pertanyaan
function uniqueTitles(titles){
  const seen = new Set(), out = [];
  (titles || []).forEach(t=>{
    const k = (t||'').trim().toLowerCase();
    if (!k || seen.has(k)) return;
    seen.add(k); out.push(t);
  });
  return out;
}
function buildSuggestionButtons(titles){
  const wrap = document.createElement('div');
  wrap.className = 'alternatives';
  titles.forEach(judul=>{
    const b = document.createElement('button');
    b.className = 'alt-btn';
    b.textContent = judul;
    b.addEventListener('click', ()=>{ qEl.value = judul; sendBtn.click(); });
    wrap.appendChild(b);
  });
  return wrap;
}
function disableFeedback(fbEl){ if (fbEl) fbEl.style.display='none'; }


function addUserBubble(text){
  const div = document.createElement('div');
  div.className = 'bubble me';
  div.textContent = text;
  chatEl.appendChild(div);
  chatEl.scrollTop = chatEl.scrollHeight;
}

function addTypingBubble(){
  // jika sudah ada, jangan dobel
  removeTypingBubble();

  const box = document.createElement('div');
  box.className = 'bubble';
  box.dataset.typing = '1';

  // header kecil
  const head = document.createElement('div');
  head.className = 'typing';
  const lab = document.createElement('span');
  lab.className = 'muted';
  lab.textContent = 'Sedang mengetik';
  const dots = document.createElement('span');
  dots.className = 'dots';
  dots.innerHTML = '<span class="dot"></span><span class="dot"></span><span class="dot"></span>';
  head.appendChild(lab); head.appendChild(dots);
  box.appendChild(head);

  // shimmer placeholders (variasi panjang)
  const sk1 = document.createElement('div'); sk1.className = 'skeleton'; sk1.style.width='82%';
  const sk2 = document.createElement('div'); sk2.className = 'skeleton'; sk2.style.width='65%';
  const sk3 = document.createElement('div'); sk3.className = 'skeleton'; sk3.style.width='74%';
  box.appendChild(sk1); box.appendChild(sk2); box.appendChild(sk3);

  chatEl.appendChild(box);
  chatEl.scrollTop = chatEl.scrollHeight;
  typingNode = box;

  // kalau lama > 8 detik, ganti label
  clearTimeout(typingTimer);
  typingTimer = setTimeout(()=>{
    if (!typingNode) return;
    const lbl = typingNode.querySelector('.typing .muted');
    if (lbl) lbl.textContent = 'Masih memproses...';
  }, 8000);
}

function removeTypingBubble(){
  clearTimeout(typingTimer);
  if (typingNode && typingNode.parentNode){
    typingNode.parentNode.removeChild(typingNode);
  }
  typingNode = null;
}

function addAnswerBubble(payload){
  const { title, answer, issue_id, alternatives, ask_feedback } = payload;

  const box = document.createElement('div');
  box.className = 'bubble';

  if (title) {
    const t = document.createElement('div');
    t.className = 'title';
    t.textContent = title;
    box.appendChild(t);
  }

  const a = document.createElement('div');
  const hasAnswer = !!(answer && answer.trim() !== '');
  a.innerHTML = hasAnswer
    ? nl2br(escapeHtml(answer.trim()))
    : '<span class="muted">Belum ada hasil yang pas. Coba pilih pertanyaan terkait di bawah.</span>';
  box.appendChild(a);

  // simpan state mentah dari server (jangan difilter di sini)
  lastState.issue_id = issue_id || null;
  lastState.alternatives = Array.isArray(alternatives) ? alternatives : [];

  // tampilkan saran ringan (boleh difilter sedikit untuk tampilan awal)
  let altShow = uniqueTitles(lastState.alternatives).slice(0, 5);
  if (altShow.length > 0) {
    const head = document.createElement('div');
    head.className = 'muted';
    head.style.marginTop = '8px';
    head.textContent = hasAnswer ? 'Pertanyaan terkait:' : 'Coba pertanyaan berikut:';
    box.appendChild(head);
    box.appendChild(buildSuggestionButtons(altShow));
  }

  if (ask_feedback) {
    const fb = document.createElement('div');
    fb.className = 'feedback';

    const label = document.createElement('span');
    label.className = 'muted';
    label.textContent = 'Apakah jawaban ini membantu?';
    fb.appendChild(label);

    const y = document.createElement('button');
    y.className = 'btn btn-yes';
    y.textContent = 'Ya';
    y.addEventListener('click', ()=>{ disableFeedback(fb); sendFeedback(true); });
    fb.appendChild(y);

    const n = document.createElement('button');
    n.className = 'btn btn-no';
    n.textContent = 'Tidak';
    // PENTING: saat Tidak, kita kirim alternatif MENTAH (tanpa filter ketat)
    n.addEventListener('click', ()=>{ disableFeedback(fb); sendFeedback(false); });
    fb.appendChild(n);

    box.appendChild(fb);
  }

  chatEl.appendChild(box);
  chatEl.scrollTop = chatEl.scrollHeight;
}


function sendFeedback(isHelpful){
  fetch(routes.feedback, {
    method: 'POST',
    headers: {'Content-Type':'application/json','X-CSRF-TOKEN': CSRF},
    body: JSON.stringify({
      issue_id: lastState.issue_id,
      session_id: getSessionId(),
      user_query: lastState.user_query,
      is_helpful: !!isHelpful,
      alternatives: lastState.alternatives
    })
  }).then(()=> {
    const note = document.createElement('div');
    note.className = 'bubble';

    if (isHelpful) {
      note.innerHTML = '<span class="muted">Terima kasih atas tanggapannya.</span>';
      chatEl.appendChild(note);
    } else {
      // gunakan alternatives mentah dari server — TANPA filter ketat agar selalu ada
      const raw = Array.isArray(lastState.alternatives) ? lastState.alternatives : [];
      const picked = uniqueTitles(raw).slice(0, 5);

      const head = document.createElement('div');
      head.className = 'title';
      head.textContent = 'Mungkin yang Anda maksud:';
      note.appendChild(head);

      if (picked.length > 0) {
        note.appendChild(buildSuggestionButtons(picked));
      } else {
        // benar-benar tidak ada dari server → fallback teks
        const empty = document.createElement('div');
        empty.className = 'muted';
        empty.textContent = 'Coba ketik ulang dengan kata kunci yang lebih spesifik.';
        note.appendChild(empty);
      }
      chatEl.appendChild(note);
    }

    chatEl.scrollTop = chatEl.scrollHeight;
  }).catch(()=>{/* no-op */});
}


function ask(q){
  lastState.user_query = q;
  addUserBubble(q);

  // tampilkan typing indicator
  addTypingBubble();
  sendBtn.classList.add('is-disabled');
  qEl.setAttribute('disabled', 'disabled');

  // timeout jaringan: jika >30s, tutup typing & tampilkan pesan
  const networkTimeout = setTimeout(()=>{
    removeTypingBubble();
    addAnswerBubble({ title:null, answer:'', issue_id:null, alternatives:[], ask_feedback:false });
  }, 30000);

  fetch(routes.ask, {
    method: 'POST',
    headers: {'Content-Type':'application/json','X-CSRF-TOKEN': CSRF},
    body: JSON.stringify({ q })
  })
  .then(r => r.json())
  .then(data => {
    clearTimeout(networkTimeout);
    removeTypingBubble();
    addAnswerBubble(data);
  })
  .catch(() => {
    clearTimeout(networkTimeout);
    removeTypingBubble();
    addAnswerBubble({ title:null, answer:'', issue_id:null, alternatives:[], ask_feedback:false });
  })
  .finally(()=>{
    sendBtn.classList.remove('is-disabled');
    qEl.removeAttribute('disabled');
    qEl.focus();
  });
}

sendBtn.addEventListener('click', () => {
  const q = (qEl.value || '').trim();
  if (!q) return;
  ask(q);
  qEl.value = '';
  qEl.focus();
});
qEl.addEventListener('keydown', (e)=>{
  if (e.key === 'Enter') sendBtn.click();
});

/* ==== utils ==== */
function escapeHtml(s){
  const div = document.createElement('div');
  div.innerText = s;
  return div.innerHTML;
}
function nl2br(s){ return s.replace(/\n/g, '<br>'); }
function getSessionId(){
  const k = 'ts_session_id';
  let v = localStorage.getItem(k);
  if (!v) { v = Math.random().toString(36).slice(2)+Date.now().toString(36); localStorage.setItem(k, v); }
  return v;
}
</script>
@endpush
