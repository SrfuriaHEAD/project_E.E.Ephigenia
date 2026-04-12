<section class="search-section">

  <div class="search-header">
    <span class="section-label">ACERVO</span>
    <div class="search-wrap">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
      </svg>
      <input id="search" type="text" placeholder="Buscar por título ou registro…" autocomplete="off">
    </div>
    <span id="status-bar">—</span>
  </div>

  <div id="grid" class="book-grid">
    <p class="empty-state">Carregando acervo…</p>
  </div>

</section>

<script>
  let timer = null;
  let modalRegistro = null;

  // ── Helpers ──────────────────────────────────────────────────────────────

  function esc(s) {
    return String(s)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;')
      .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function fmtDate(iso) {
    if (!iso) return '—';
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y}`;
  }

  function diasRestantes(iso) {
    const hoje   = new Date(); hoje.setHours(0,0,0,0);
    const devol  = new Date(iso + 'T00:00:00');
    return Math.round((devol - hoje) / 86400000);
  }

  // ── Grid ─────────────────────────────────────────────────────────────────

  function render(livros) {
    const grid   = document.getElementById('grid');
    const status = document.getElementById('status-bar');

    if (!livros.length) {
      grid.innerHTML = '<p class="empty-state">Nenhum livro encontrado.</p>';
      status.textContent = '0 resultados';
      return;
    }

    status.textContent = `${livros.length} livro${livros.length !== 1 ? 's' : ''}`;

    grid.innerHTML = livros.map(l => {
      const pct   = l.quantidade > 0 ? Math.round((l.disponiveis / l.quantidade) * 100) : 0;
      const cls   = l.disponiveis === 0 ? 'esgotado' : (l.disponiveis <= 1 ? 'alerta' : '');
      const label = l.disponiveis === 0 ? '0 disponíveis' :
                    `<span>${l.disponiveis}</span> disponíve${l.disponiveis !== 1 ? 'is' : 'l'}`;
      return `
        <article class="book-card" data-reg="${esc(l.registro)}" onclick="abrirModal('${esc(l.registro)}')">
          <div class="book-card-accent"></div>
          <div class="book-card-body">
            <p class="book-title">${esc(l.nome)}</p>
            <p class="book-reg">REG #${esc(l.registro)}</p>
            <p class="book-qty ${cls}">${label}</p>
            <div class="book-avail-bar">
              <div class="book-avail-fill" style="width:${pct}%"></div>
            </div>
          </div>
        </article>`;
    }).join('');
  }

  async function buscar(q) {
    const fd = new FormData();
    fd.append('acao', 'procurar_livros');
    fd.append('busca', q);
    try {
      const res  = await fetch('', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) render(data.livros);
    } catch {
      document.getElementById('grid').innerHTML =
        '<p class="empty-state">⚠ Erro ao carregar acervo.</p>';
    }
  }

  document.getElementById('search').addEventListener('input', function () {
    clearTimeout(timer);
    timer = setTimeout(() => buscar(this.value.trim()), 300);
  });

  buscar('');

  // ── Modal ─────────────────────────────────────────────────────────────────

  function renderLoans(livro) {
    document.getElementById('modal-reg').textContent   = `REG #${livro.registro}`;
    document.getElementById('modal-title').textContent = livro.nome;
    document.getElementById('stat-total').textContent  = livro.quantidade;

    const el = document.getElementById('stat-emprestados');
    el.textContent = livro.emprestados;
    el.className   = 'stat-value' + (livro.emprestados > 0 ? ' warn' : '');

    const ed = document.getElementById('stat-disponíveis');
    ed.textContent = livro.disponiveis;
    ed.className   = 'stat-value ' +
      (livro.disponiveis === 0 ? 'bad' : livro.disponiveis <= 1 ? 'warn' : 'ok');

    const lista = document.getElementById('loans-list');
    if (!livro.emprestimos || livro.emprestimos.length === 0) {
      lista.innerHTML = '<p class="no-loans">Nenhum empréstimo ativo.</p>';
      return;
    }

    lista.innerHTML = livro.emprestimos.map(e => {
      const dias = diasRestantes(e.devolucao);
      let cls = '';
      let info = `Devolver até ${fmtDate(e.devolucao)}`;
      if (dias < 0)      { cls = 'atrasado'; info = `⚠ Atrasado (${Math.abs(dias)} dia${Math.abs(dias)!==1?'s':''})`; }
      else if (dias === 0){ cls = 'hoje';     info = `⚠ Devolver HOJE`; }
      else if (dias <= 2)  info = `Devolver em ${dias} dia${dias!==1?'s':''} (${fmtDate(e.devolucao)})`;

      return `
        <div class="loan-row" id="row-${e.id}">
          <div>
            <p class="loan-aluno">${esc(e.aluno)}</p>
            <p class="loan-date">Retirada: ${fmtDate(e.retirada)}</p>
          </div>
          <p class="loan-devol ${cls}">${info}</p>
          <button class="btn-devolver" onclick="devolver('${e.id}')">Devolvido ✓</button>
        </div>`;
    }).join('');
  }

  async function abrirModal(registro) {
    modalRegistro = registro;
    document.getElementById('loan-aluno').value = '';
    document.getElementById('loan-devol').value = '';
    document.getElementById('loan-msg').textContent = '';

    // Set min date for devolution
    const tomorrow = new Date(); tomorrow.setDate(tomorrow.getDate() + 1);
    document.getElementById('loan-devol').min = tomorrow.toISOString().split('T')[0];

    const fd = new FormData();
    fd.append('acao', 'detalhes_livro');
    fd.append('registro', registro);

    try {
      const res  = await fetch('', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        renderLoans(data.livro);
        document.getElementById('modal-overlay').classList.add('open');
      }
    } catch { console.error('Erro ao carregar detalhes.'); }
  }

  function fecharModal() {
    document.getElementById('modal-overlay').classList.remove('open');
    modalRegistro = null;
    buscar(document.getElementById('search').value.trim()); // refresh grid
  }

  document.getElementById('modal-close').addEventListener('click', fecharModal);
  document.getElementById('modal-overlay').addEventListener('click', function(e) {
    if (e.target === this) fecharModal();
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') fecharModal(); });

  // ── Emprestar ─────────────────────────────────────────────────────────────

  document.getElementById('btn-emprestar').addEventListener('click', async () => {
    const aluno    = document.getElementById('loan-aluno').value.trim();
    const devolucao= document.getElementById('loan-devol').value;
    const msg      = document.getElementById('loan-msg');

    if (!aluno || !devolucao) { msg.textContent = 'Preencha nome e data.'; msg.style.color='#cc6600'; return; }

    const fd = new FormData();
    fd.append('acao',      'emprestar_livro');
    fd.append('registro',  modalRegistro);
    fd.append('aluno',     aluno);
    fd.append('devolucao', devolucao);

    const res  = await fetch('', { method: 'POST', body: fd });
    const data = await res.json();

    msg.textContent  = data.msg;
    msg.style.color  = data.success ? '#4caf7d' : '#cc4400';

    if (data.success) {
      document.getElementById('loan-aluno').value = '';
      document.getElementById('loan-devol').value = '';
      await abrirModal(modalRegistro); // refresh modal
    }
  });

  // ── Devolver ──────────────────────────────────────────────────────────────

  async function devolver(id) {
    const fd = new FormData();
    fd.append('acao', 'devolver_livro');
    fd.append('id',   id);

    const res  = await fetch('', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success) {
      await abrirModal(modalRegistro); // refresh
    } else {
      alert(data.msg);
    }
  }
</script>