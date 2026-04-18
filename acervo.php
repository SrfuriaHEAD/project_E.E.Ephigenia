<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ob_start();

// ══ CONFIG ══════
$diretorio      = __DIR__ . '/db';
global $arquivo, $arqEmprestimos;
$arquivo        = $diretorio . '/banco.txt';
$arqEmprestimos = $diretorio . '/emprestimos.txt';

// ══ ROTAS ═══════
require __DIR__ . '/src/functions/procurar_livro.php';
require __DIR__ . '/src/functions/emprestar_livro.php';
require __DIR__ . '/src/functions/devolver_livro.php';
require __DIR__ . '/src/functions/deletar_livro.php';
require __DIR__ . '/src/functions/historico.php';




// ── Handler: deletar_livro (único que não é tratado pelos requires acima) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'deletar_livro') {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    $result = deletarLivro($_POST['registro'] ?? '');
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Acervo — Biblioteca E.E. Ephigenia</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="src/static/style.css">
  <style>
    /* ── Search section ── */
    .search-section { margin-bottom: 4rem; }
    .search-header {
      display: flex; align-items: center; gap: 1.25rem;
      margin-bottom: 2rem; flex-wrap: wrap;
    }
    .section-label {
      font-family: var(--font-mono); font-size: 0.6rem;
      letter-spacing: 0.25em; color: var(--rust); white-space: nowrap;
    }
    .search-wrap { position: relative; flex: 1; min-width: 220px; }
    .search-wrap svg {
      position: absolute; left: 10px; top: 50%;
      transform: translateY(-50%); color: #444; pointer-events: none;
    }
    .search-wrap input {
      width: 100%; background: #111; border: 1px solid #1e1e1e;
      border-radius: var(--radius); color: #e0e0e0;
      font-family: var(--font-mono); font-size: 0.85rem;
      padding: 0.6rem 0.9rem 0.6rem 2.2rem; outline: none;
      transition: border-color var(--transition);
    }
    .search-wrap input:focus { border-color: var(--rust); }
    .search-wrap input::placeholder { color: #c0c0c0; font-style: italic; }
    #status-bar {
      font-family: var(--font-mono); font-size: 0.6rem;
      letter-spacing: 0.15em; color: #dadada; margin-left: auto;
    }

    /* ── Grid ── */
    .book-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 1px; background: #1a1a1a; border: 1px solid #1a1a1a;
    }
    .book-card {
      background: #0f0f0f; display: flex;
      transition: background var(--transition); cursor: pointer;
    }
    .book-card:hover { background: #161616; }
    .book-card-accent {
      width: 3px; background: #1e1e1e; flex-shrink: 0;
      transition: background var(--transition);
    }
    .book-card:hover .book-card-accent { background: var(--rust); }
    .book-card-body { padding: 1.25rem 1rem; flex: 1; }
    .book-title {
      font-family: var(--font-display); font-size: 0.95rem;
      font-weight: 700; color: #e0e0e0; margin-bottom: 0.35rem; line-height: 1.3;
    }
    .book-reg {
      font-family: var(--font-mono); font-size: 0.6rem;
      letter-spacing: 0.15em; color: #d1d1d1; margin-bottom: 0.75rem;
    }
    .book-qty { font-family: var(--font-mono); font-size: 0.9rem; color: #c5c5c5; }
    .book-qty span { color: var(--rust); font-weight: 500; }
    .book-qty.alerta span { color: #ff6600; }
    .book-qty.esgotado span { color: #cc0000; }
    .book-avail-bar {
      margin-top: 0.6rem; height: 2px; background: #1e1e1e; border-radius: 1px; overflow: hidden;
    }
    .book-avail-fill { height: 100%; background: var(--rust); transition: width 0.4s ease; }
    .empty-state {
      grid-column: 1 / -1; font-family: var(--font-mono); font-size: 0.75rem;
      color: #333; font-style: italic; padding: 2rem; background: #0f0f0f;
    }

    /* ── MODAL ── */
    .modal-overlay {
      position: fixed; inset: 0; z-index: 500;
      background: rgba(0,0,0,0.88); backdrop-filter: blur(4px);
      display: flex; align-items: center; justify-content: center;
      padding: 1rem; opacity: 0; pointer-events: none;
      transition: opacity 0.25s ease;
    }
    .modal-overlay.open { opacity: 1; pointer-events: all; }
    .modal {
      background: #0f0f0f; border: 1px solid #1e1e1e; border-top: 3px solid var(--rust);
      width: 100%; max-width: 700px; max-height: 90vh;
      overflow-y: auto; position: relative;
      transform: translateY(20px); transition: transform 0.25s ease;
    }
    .modal-overlay.open .modal { transform: translateY(0); }
    .modal-header {
      display: flex; align-items: flex-start; justify-content: space-between;
      padding: 1.75rem 2rem 1.25rem; border-bottom: 1px solid #1a1a1a; gap: 1rem;
      position: sticky; top: 0; background: #0f0f0f; z-index: 10;
    }
    .modal-reg {
      font-family: var(--font-mono); font-size: 0.55rem;
      letter-spacing: 0.25em; color: var(--rust); margin-bottom: 0.35rem;
    }
    .modal-title {
      font-family: var(--font-display); font-size: 1.5rem;
      font-weight: 900; color: #f0f0f0; line-height: 1.1;
    }
    .modal-close {
      background: #1a1a1a; border: 1px solid #2a2a2a; color: #aaa;
      font-family: var(--font-mono); font-size: 0.75rem; cursor: pointer;
      padding: 0.5rem 0.9rem; transition: all var(--transition); flex-shrink: 0;
      letter-spacing: 0.05em; border-radius: var(--radius);
    }
    .modal-close:hover { background: var(--rust); border-color: var(--rust); color: #fff; }
    .modal-stats {
      display: grid; grid-template-columns: repeat(3, 1fr);
      gap: 1px; background: #1a1a1a; border-bottom: 1px solid #1a1a1a;
    }
    .stat-cell { background: #0f0f0f; padding: 1.1rem 1.5rem; }
    .stat-label {
      font-family: var(--font-mono); font-size: 0.5rem;
      letter-spacing: 0.2em; color: #555; margin-bottom: 0.35rem; text-transform: uppercase;
    }
    .stat-value {
      font-family: var(--font-display); font-size: 1.7rem;
      font-weight: 900; color: #e0e0e0; line-height: 1;
    }
    .stat-value.ok  { color: #4caf7d; }
    .stat-value.warn{ color: #ff9800; }
    .stat-value.bad { color: #cc2200; }
    .modal-body { padding: 1.5rem 2rem 2rem; }

    /* ── Loan form ── */
    .loan-form-wrap {
      background: #111; border: 1px solid #1e1e1e;
      border-left: 3px solid var(--rust); padding: 1.5rem; margin-bottom: 2rem;
    }
    .loan-form-title {
      font-family: var(--font-mono); font-size: 0.55rem;
      letter-spacing: 0.25em; color: var(--rust); margin-bottom: 1.25rem; text-transform: uppercase;
    }
    .loan-form { display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: flex-end; }
    .loan-field { display: flex; flex-direction: column; gap: 0.35rem; flex: 1; min-width: 120px; }
    .loan-label {
      font-family: var(--font-mono); font-size: 0.5rem;
      letter-spacing: 0.15em; color: #888; text-transform: uppercase;
    }
    .loan-input {
      background: #0a0a0a; border: 1px solid #2a2a2a; border-radius: var(--radius);
      color: #e0e0e0; font-family: var(--font-mono); font-size: 0.8rem;
      padding: 0.5rem 0.75rem; outline: none; transition: border-color var(--transition);
    }
    .loan-input:focus { border-color: var(--rust); }
    .loan-input::placeholder { color: #444; font-style: italic; }
    .btn-loan {
      background: var(--rust); color: #f0f0f0; border: none;
      font-family: var(--font-mono); font-size: 0.65rem;
      letter-spacing: 0.2em; text-transform: uppercase;
      padding: 0.55rem 1.25rem; cursor: pointer;
      transition: background var(--transition); white-space: nowrap; align-self: flex-end;
    }
    .btn-loan:hover { background: var(--rust-dark); }

    /* ── Loans list + filter ── */
    .loans-header {
      display: flex; align-items: center; justify-content: space-between;
      flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1rem;
    }
    .loans-title {
      font-family: var(--font-mono); font-size: 0.55rem;
      letter-spacing: 0.25em; color: #666; text-transform: uppercase;
    }
    .loans-filters { display: flex; gap: 0.5rem; flex-wrap: wrap; }
    .filter-input {
      background: #0a0a0a; border: 1px solid #222; border-radius: var(--radius);
      color: #ccc; font-family: var(--font-mono); font-size: 0.7rem;
      padding: 0.35rem 0.65rem; outline: none; transition: border-color var(--transition);
    }
    .filter-input:focus { border-color: var(--rust); }
    .filter-input::placeholder { color: #444; font-style: italic; }
    .loan-row {
      display: grid; grid-template-columns: 1fr auto auto;
      gap: 0.5rem 1rem; align-items: center;
      padding: 0.85rem 0; border-bottom: 1px solid #151515;
    }
    .loan-row:last-child { border-bottom: none; }
    .loan-row.hidden { display: none; }
    .loan-aluno {
      font-family: var(--font-display); font-size: 0.9rem;
      font-weight: 700; color: #ddd;
    }
    .loan-sala {
      font-family: var(--font-mono); font-size: 0.58rem;
      color: #888; letter-spacing: 0.05em;
    }
    .loan-date { font-family: var(--font-mono); font-size: 0.6rem; color: #555; }
    .loan-devol {
      font-family: var(--font-mono); font-size: 0.6rem; color: #888; white-space: nowrap;
    }
    .loan-devol.atrasado { color: #cc4400; }
    .loan-devol.hoje     { color: #ff9800; }
    .btn-devolver {
      background: none; border: 1px solid #2a2a2a; color: #666;
      font-family: var(--font-mono); font-size: 0.55rem; letter-spacing: 0.1em;
      padding: 0.3rem 0.65rem; cursor: pointer; white-space: nowrap;
      transition: all var(--transition); text-transform: uppercase;
    }
    .btn-devolver:hover { border-color: #4caf7d; color: #4caf7d; }
    .no-loans {
      font-family: var(--font-mono); font-size: 0.7rem;
      color: #333; font-style: italic; padding: 1rem 0;
    }

    /* ── Nav link ── */
    
    .nav-link:hover { color: #313131; }
    .hero-sub { font-family: var(--font-mono); font-size: 0.9rem; color: #000000; }
    .hero-system { font-family: var(--font-mono); font-size: 0.9rem; letter-spacing: 0.15em; color: #f80000; margin-left: 10px; }
    .tabs {
    display: flex; gap: 0; margin-bottom: 2rem;
    border-bottom: 1px solid #1e1e1e;
    }
    .tab {
    background: none; border: none; border-bottom: 2px solid transparent;
    color: #000000; font-family: var(--font-mono); font-size: 0.85rem;
    letter-spacing: 0.15em; text-transform: uppercase;
    padding: 0.75rem 1.5rem; cursor: pointer;
    transition: all var(--transition); position: relative; bottom: -1px;
    }
    .tab:hover { color: #000000; }
    .tab.active { color: #000000; border-bottom-color: var(--rust); }
    .tab-panel { display: none; }
    .tab-panel.active { display: block; }
    .badge {
    background: var(--rust); color: #fff;
    font-size: 0.5rem; padding: 0.1rem 0.4rem;
    border-radius: 999px; margin-left: 0.4rem;
    vertical-align: middle;
    }
  </style>
</head>
<body>
<div class="noise"></div>

<header class="header">
  <div class="header-inner">
    <div class="logo-block">
      <span class="logo-pre">Biblioteca</span>
      <span class="logo-name">E.E. Ephigenia</span>
    </div>
    <nav class="header-nav">
      <a class="nav-link" href="index_alunos.php">Area Aluno</a>
      <div class="nav-dot"></div>
      <a href="cadastrar.php" class="nav-link">+ Registrar livro</a>
      <div class="nav-dot"></div>
      <span class="nav-year"><?= date('Y') ?></span>
    </nav>
  </div>
  <div class="header-line"></div>
</header>

<main class="main">
  <div class="hero">
    <p class="hero-system">Sistema de acervo</p>
    <h1 class="hero-title">Acer<em>vo</em></h1>
    <p class="hero-sub">Clique em um livro para ver detalhes, empréstimos e devoluções.</p>
  </div>

  <!-- Abas -->
  <div class="tabs">
    <button class="tab active" data-tab="acervo">Acervo</button>
    <button class="tab" data-tab="alunos">Busca por aluno</button>
    <button class="tab" data-tab="historico">Histórico</button>
    <button class="tab" data-tab="alertas">⚠ Alertas <span id="badge-alertas" class="badge" style="display:none"></span></button>
  </div>

  <div id="tab-acervo"  class="tab-panel active"><?php include __DIR__ . '/src/template/procurar_livros.blade.php'; ?></div>
  <div id="tab-alunos"  class="tab-panel"></div>
  <div id="tab-alertas" class="tab-panel"></div>
  <div id="tab-historico" class="tab-panel"></div>
</main>

<footer class="footer">
  <div class="footer-inner">
    <span class="footer-school">E.E. Ephigenia</span>
    <span class="footer-sep">/</span>
    <span class="footer-text">Sistema de Biblioteca</span>
    <span class="footer-sep">/</span>
    <span class="footer-text">Desenvolvido por Arthur A. 2 Reg 3</span>
  </div>
</footer>

<!-- ══════════════ MODAL ══════════════ -->
<div class="modal-overlay" id="modal-overlay">
  <div class="modal" id="modal" role="dialog" aria-modal="true">

    <!-- Header fixo -->
    <div class="modal-header">
      <div>
        <p class="modal-reg" id="modal-reg">—</p>
        <h2 class="modal-title" id="modal-title">—</h2>
      </div>
      <button class="modal-close" id="modal-close" type="button">✕ Fechar</button>
    </div>

    <!-- Stats -->
    <div class="modal-stats">
      <div class="stat-cell">
        <p class="stat-label">Total</p>
        <p class="stat-value" id="stat-total">—</p>
      </div>
      <div class="stat-cell">
        <p class="stat-label">Emprestados</p>
        <p class="stat-value" id="stat-emprestados">—</p>
      </div>
      <div class="stat-cell">
        <p class="stat-label">Disponíveis</p>
        <p class="stat-value" id="stat-disponiveis">—</p>
      </div>
    </div>

    <!-- Body -->
    <div class="modal-body">

      <!-- Formulário empréstimo -->
      <div class="loan-form-wrap">
        <p class="loan-form-title">— Registrar empréstimo</p>
        <div class="loan-form">
          <div class="loan-field">
            <label class="loan-label" for="loan-aluno">Nome do aluno</label>
            <input class="loan-input" id="loan-aluno" type="text" placeholder="ex: João Silva" autocomplete="off">
          </div>
          <div class="loan-field" style="max-width:110px">
            <label class="loan-label" for="loan-sala">Sala / Turma</label>
            <input class="loan-input" id="loan-sala" type="text" placeholder="2 REG 3" autocomplete="off">
          </div>
          <div class="loan-field" style="max-width:150px">
            <label class="loan-label" for="loan-devol">Devolução</label>
            <input class="loan-input" id="loan-devol" type="text" placeholder="DD/MM/AAAA" maxlength="10" autocomplete="off">
          </div>
          <button class="btn-loan" id="btn-emprestar" type="button">Emprestar →</button>
        </div>
        <p id="loan-msg" style="font-family:var(--font-mono);font-size:0.65rem;margin-top:0.75rem;color:#888;min-height:1rem;"></p>
      </div>

      <!-- 🔥 BOTÃO DELETE - CORRETO 🔥 -->
      <div style="background:#1a1a1a;border:1px solid #2a2a2a;border-left:3px solid #cc2200;padding:1.5rem;margin-bottom:2rem;border-radius:var(--radius);">
        <p style="font-family:var(--font-mono);font-size:0.55rem;letter-spacing:0.25em;color:#cc2200;margin-bottom:0.75rem;text-transform:uppercase;">— AÇÃO DE EMERGÊNCIA</p>
        <button id="btn-deletar" style="background:#cc2200;color:#fff;border:none;font-family:var(--font-mono);font-size:0.75rem;letter-spacing:0.15em;text-transform:uppercase;padding:0.75rem 1.5rem;cursor:pointer;width:100%;border-radius:var(--radius);transition:all 0.2s;">
          🗑️ APAGAR LIVRO DO SISTEMA
        </button>
        <p id="delete-msg" style="font-family:var(--font-mono);font-size:0.65rem;margin-top:0.75rem;color:#888;min-height:1.2rem;font-style:italic;">Remove permanentemente do banco.txt + todos os empréstimos relacionados.</p>
      </div>

      <!-- Lista de empréstimos ativos -->
      <div class="loans-header">
        <p class="loans-title">— Empréstimos ativos</p>
        <div class="loans-filters">
          <input class="filter-input" id="filter-aluno" type="text" placeholder="🔍 aluno ou sala…">
          <input class="loan-input" id="filter-data" type="text" placeholder="DD/MM/AAAA" maxlength="10" autocomplete="off">
        </div>
      </div>
      <div id="loans-list"><p class="no-loans">Nenhum empréstimo ativo.</p></div>

    </div><!-- /modal-body -->
  </div><!-- /modal -->
</div><!-- /modal-overlay -->

<script>
  // ── Garante que o DOM está pronto ────────────────────────────────────────
 (function () {
  'use strict';

  let modalRegistro = null;
  let searchTimer   = null;

  // ── Helpers ────────────────────────────────────────────────────────────
  function esc(s) {
    return String(s)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;')
      .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
  function fmtDate(iso) {
    if (!iso) return '—';
    const [y,m,d] = iso.split('-');
    return `${d}/${m}/${y}`;
  }
  function brToIso(br) {
    const [d,m,y] = br.split('/');
    return `${y}-${m}-${d}`;
  }
  function diasRestantes(iso) {
    const hoje = new Date(); hoje.setHours(0,0,0,0);
    const devol = new Date(iso + 'T00:00:00');
    return Math.round((devol - hoje) / 86400000);
  }
  function post(dados) {
    const fd = new FormData();
    Object.entries(dados).forEach(([k,v]) => fd.append(k,v));
    return fetch(window.location.pathname, { method:'POST', body:fd }).then(r=>r.json());
  }

  // ── Grid (aba Acervo) ──────────────────────────────────────────────────
  function renderGrid(livros) {
    const grid   = document.getElementById('grid');
    const status = document.getElementById('status-bar');
    if (!livros.length) {
      grid.innerHTML = '<p class="empty-state">Nenhum livro encontrado.</p>';
      status.textContent = '0 resultados';
      return;
    }
    status.textContent = `${livros.length} livro${livros.length!==1?'s':''}`;
    grid.innerHTML = livros.map(l => {
      const pct   = l.quantidade > 0 ? Math.round((l.disponiveis/l.quantidade)*100) : 0;
      const cls   = l.disponiveis===0 ? 'esgotado' : (l.disponiveis<=1 ? 'alerta' : '');
      const label = l.disponiveis===0
        ? '<span>0</span> disponíveis'
        : `<span>${l.disponiveis}</span> disponíve${l.disponiveis!==1?'is':'l'}`;
      return `
        <article class="book-card" onclick="window._abrirModal('${esc(l.registro)}')">
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

  async function buscarGrid(q) {
    try {
      const data = await post({ acao:'procurar_livros', busca: q||'' });
      if (data.success) renderGrid(data.livros);
    } catch {
      document.getElementById('grid').innerHTML =
        '<p class="empty-state">⚠ Erro ao carregar acervo.</p>';
    }
  }

  document.getElementById('search').addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => buscarGrid(this.value.trim()), 300);
  });

  buscarGrid(''); // carga inicial

  // ── Modal ──────────────────────────────────────────────────────────────
  const overlay  = document.getElementById('modal-overlay');
  const btnClose = document.getElementById('modal-close');

  function fecharModal() {
    overlay.classList.remove('open');
    modalRegistro = null;
    buscarGrid(document.getElementById('search').value.trim());
  }

  btnClose.addEventListener('click', fecharModal);
  overlay.addEventListener('click', function(e) { if (e.target===this) fecharModal(); });
  document.addEventListener('keydown', e => { if (e.key==='Escape') fecharModal(); });

  function aplicarFiltros() {
    const q    = document.getElementById('filter-aluno').value.trim().toLowerCase();
    const data = document.getElementById('filter-data').value;
    document.querySelectorAll('#loans-list .loan-row').forEach(row => {
      const aluno = (row.dataset.aluno||'').toLowerCase();
      const sala  = (row.dataset.sala ||'').toLowerCase();
      const devol = row.dataset.devol || '';
      const matchAluno = !q || aluno.includes(q) || sala.includes(q);
      const dataISO    = data.length === 10 ? brToIso(data) : '';
      const matchData  = !dataISO || devol === dataISO;
      row.classList.toggle('hidden', !matchAluno || !matchData);
    });
  }

  document.getElementById('filter-aluno').addEventListener('input', aplicarFiltros);
  document.getElementById('filter-data').addEventListener('input', function () {
    let v = this.value.replace(/\D/g,'');
    if (v.length > 2) v = v.slice(0,2) + '/' + v.slice(2);
    if (v.length > 5) v = v.slice(0,5) + '/' + v.slice(5);
    this.value = v.slice(0,10);
    aplicarFiltros();
  });

  function renderLoans(livro) {
    const extras = [
      livro.autor       ? `Autor: ${livro.autor}` : '',
      livro.prateleira  ? `Prateleira: ${livro.prateleira}` : '',
      livro.faixaEtaria ? `Nível: ${livro.faixaEtaria}` : '',
    ].filter(Boolean).join(' · ');
    document.getElementById('modal-reg').textContent   = `REG #${livro.registro}` + (extras ? ` · ${extras}` : '');
    document.getElementById('modal-title').textContent = livro.nome;
    document.getElementById('stat-total').textContent  = livro.quantidade;

    const el = document.getElementById('stat-emprestados');
    el.textContent = livro.emprestados;
    el.className   = 'stat-value' + (livro.emprestados>0?' warn':'');

    const ed = document.getElementById('stat-disponiveis');
    ed.textContent = livro.disponiveis;
    ed.className   = 'stat-value ' + (livro.disponiveis===0?'bad':livro.disponiveis<=1?'warn':'ok');

    document.getElementById('filter-aluno').value = '';
    document.getElementById('filter-data').value  = '';

    const lista = document.getElementById('loans-list');
    if (!livro.emprestimos || livro.emprestimos.length===0) {
      lista.innerHTML = '<p class="no-loans">Nenhum empréstimo ativo.</p>';
      return;
    }
    lista.innerHTML = livro.emprestimos.map(e => {
      const dias = diasRestantes(e.devolucao);
      let cls='', info=`Devolver até ${fmtDate(e.devolucao)}`;
      if (dias<0)       { cls='atrasado'; info=`⚠ Atrasado ${Math.abs(dias)} dia${Math.abs(dias)!==1?'s':''}`; }
      else if (dias===0){ cls='hoje';     info='⚠ Devolver HOJE'; }
      else if (dias<=2)   info=`Em ${dias} dia${dias!==1?'s':''} (${fmtDate(e.devolucao)})`;
      const sala = e.sala ? ` — ${esc(e.sala)}` : '';
      return `
        <div class="loan-row" id="row-${esc(e.id)}"
             data-aluno="${esc(e.aluno)}" data-sala="${esc(e.sala||'')}" data-devol="${esc(e.devolucao)}">
          <div>
            <p class="loan-aluno">${esc(e.aluno)}<span class="loan-sala">${sala}</span></p>
            <p class="loan-date">Retirada: ${fmtDate(e.retirada)}</p>
          </div>
          <p class="loan-devol ${cls}">${info}</p>
          <button class="btn-devolver" type="button" onclick="window._devolver('${esc(e.id)}')">Devolvido ✓</button>
        </div>`;
    }).join('');
  }

  async function abrirModal(registro) {
    modalRegistro = registro;
    document.getElementById('loan-aluno').value     = '';
    document.getElementById('loan-sala').value      = '';
    document.getElementById('loan-devol').value     = '';
    document.getElementById('loan-msg').textContent = '';

    const amanha = new Date(); amanha.setDate(amanha.getDate()+1);
    const _pad = n => String(n).padStart(2,'0');
    document.getElementById('loan-devol').placeholder =
      `mín: ${_pad(amanha.getDate())}/${_pad(amanha.getMonth()+1)}/${amanha.getFullYear()}`;

    try {
      const data = await post({ acao:'detalhes_livro', registro });
      if (data.success) {
        renderLoans(data.livro);
        overlay.classList.add('open');
        setTimeout(() => document.getElementById('loan-aluno').focus(), 300);
      }
    } catch { console.error('Erro ao carregar modal.'); }
  }

  window._abrirModal = abrirModal;

  // ── Máscara data devolução ─────────────────────────────────────────────
  document.getElementById('loan-devol').addEventListener('input', function () {
    let v = this.value.replace(/\D/g,'');
    if (v.length > 2) v = v.slice(0,2) + '/' + v.slice(2);
    if (v.length > 5) v = v.slice(0,5) + '/' + v.slice(5);
    this.value = v.slice(0,10);
  });

  // ── Emprestar ──────────────────────────────────────────────────────────
  document.getElementById('btn-emprestar').addEventListener('click', async () => {
    const aluno        = document.getElementById('loan-aluno').value.trim();
    const sala         = document.getElementById('loan-sala').value.trim();
    const devolucaoRaw = document.getElementById('loan-devol').value;
    const devolucao    = devolucaoRaw.length === 10 ? brToIso(devolucaoRaw) : '';
    const msg          = document.getElementById('loan-msg');

    if (!aluno || !devolucao) {
      msg.textContent = 'Preencha ao menos o nome e a data (DD/MM/AAAA).';
      msg.style.color = '#cc6600'; return;
    }
    const [dD,dM,dY] = devolucaoRaw.split('/');
    const dtDevol = new Date(+dY, +dM-1, +dD);
    const dtHoje  = new Date(); dtHoje.setHours(0,0,0,0);
    if (isNaN(dtDevol) || dtDevol <= dtHoje) {
      msg.textContent = 'A data de devolução deve ser a partir de amanhã.';
      msg.style.color = '#cc6600'; return;
    }
    const data = await post({ acao:'emprestar_livro', registro:modalRegistro, aluno, sala, devolucao });
    msg.textContent = data.msg;
    msg.style.color = data.success ? '#4caf7d' : '#cc4400';
    if (data.success) {
      document.getElementById('loan-aluno').value = '';
      document.getElementById('loan-sala').value  = '';
      document.getElementById('loan-devol').value = '';
      await abrirModal(modalRegistro);
    }
  });

  // ── Devolver ───────────────────────────────────────────────────────────
  window._devolver = async function(id) {
    const data = await post({ acao:'devolver_livro', id });
    if (data.success) await abrirModal(modalRegistro);
    else alert(data.msg);
  };

  // ── Deletar ────────────────────────────────────────────────────────────
  document.getElementById('btn-deletar').addEventListener('click', async () => {
    if (!confirm(`⚠️ APAGAR LIVRO PERMANENTEMENTE\n\nREG #${modalRegistro}\n"${document.getElementById('modal-title').textContent}"\n\nCONFIRMAR?`)) return;
    const btn = document.getElementById('btn-deletar');
    const msg = document.getElementById('delete-msg');
    btn.textContent = '⏳ Apagando...';
    btn.disabled = true;
    try {
      const data = await post({ acao:'deletar_livro', registro: modalRegistro });
      msg.textContent = data.msg;
      msg.style.color = data.success ? '#4caf7d' : '#cc4400';
      if (data.success) { setTimeout(fecharModal, 1500); }
      else { btn.textContent = '🗑️ APAGAR LIVRO DO SISTEMA'; btn.disabled = false; }
    } catch {
      msg.textContent = 'Erro de conexão';
      msg.style.color = '#cc4400';
      btn.textContent = '🗑️ APAGAR LIVRO DO SISTEMA';
      btn.disabled = false;
    }
  });

  // ── Abas ───────────────────────────────────────────────────────────────
  document.querySelectorAll('.tab').forEach(btn => {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
      this.classList.add('active');
      document.getElementById('tab-' + this.dataset.tab).classList.add('active');
      if (this.dataset.tab === 'alunos')    carregarAlunos('');
      if (this.dataset.tab === 'alertas')   carregarAlertas();
      if (this.dataset.tab === 'historico') iniciarHistorico();
    });
  });

  // ── Aba: Alunos ────────────────────────────────────────────────────────
  // Monta estrutura fixa UMA vez no carregamento da página
  document.getElementById('tab-alunos').innerHTML = `
    <div class="search-header" style="margin-bottom:1.5rem">
      <span class="section-label">ALUNOS</span>
      <div class="search-wrap">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
        <input id="search-aluno" type="text" placeholder="Nome ou sala…" autocomplete="off">
      </div>
      <span id="count-alunos" style="font-family:var(--font-mono);font-size:0.6rem;color:#666"></span>
    </div>
    <div id="resultado-alunos"></div>`;

  document.getElementById('search-aluno').addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => carregarAlunos(this.value.trim()), 300);
  });

  async function carregarAlunos(q) {
    const resultado = document.getElementById('resultado-alunos');
    const contador  = document.getElementById('count-alunos');
    if (!resultado) return;
    const data = await post({ acao:'buscar_aluno', busca: q });
    if (!data.success) return;
    const emps = data.emprestimos;
    if (contador) contador.textContent = `${emps.length} empréstimo${emps.length!==1?'s':''} ativo${emps.length!==1?'s':''}`;
    resultado.innerHTML = emps.length === 0
      ? '<p class="empty-state" style="padding:2rem;color:#333;font-style:italic;font-family:var(--font-mono);font-size:0.75rem">Nenhum empréstimo encontrado.</p>'
      : `<div class="book-grid" style="grid-template-columns:1fr">
          ${emps.map(e => {
            const dias = diasRestantes(e.devolucao);
            let cls='', info=`Devolver até ${fmtDate(e.devolucao)}`;
            if (dias<0)       { cls='atrasado'; info=`⚠ Atrasado ${Math.abs(dias)} dia${Math.abs(dias)!==1?'s':''}`; }
            else if (dias===0){ cls='hoje';     info='⚠ Devolver HOJE'; }
            else if (dias<=3)   info=`Em ${dias} dia${dias!==1?'s':''} (${fmtDate(e.devolucao)})`;
            return `
              <article class="book-card" onclick="window._abrirModal('${esc(e.registro)}')">
                <div class="book-card-accent"></div>
                <div class="book-card-body" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem">
                  <div>
                    <p class="book-title">${esc(e.aluno)}${e.sala?` <span style="font-size:0.7rem;font-family:var(--font-mono);color:#888">— ${esc(e.sala)}</span>`:''}</p>
                    <p class="book-reg">${esc(e.livro)} · REG #${esc(e.registro)} · Retirada: ${fmtDate(e.retirada)}</p>
                  </div>
                  <p class="loan-devol ${cls}" style="font-family:var(--font-mono);font-size:0.65rem">${info}</p>
                </div>
              </article>`;
          }).join('')}
        </div>`;
  }

  // ── Aba: Histórico ─────────────────────────────────────────────────────
  let historicoIniciado = false;

  function iniciarHistorico() {
    if (historicoIniciado) return;
    historicoIniciado = true;

    document.getElementById('tab-historico').innerHTML = `
      <div class="search-header" style="margin-bottom:1.5rem;flex-wrap:wrap;gap:0.75rem">
        <span class="section-label">HISTÓRICO</span>
        <div class="search-wrap">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
          </svg>
          <input id="search-hist" type="text" placeholder="Aluno, livro ou sala…" autocomplete="off">
        </div>
        <select id="filter-ano-hist" style="background:#111;border:1px solid #1e1e1e;color:#e0e0e0;font-family:var(--font-mono);font-size:0.8rem;padding:0.55rem 0.75rem;outline:none;border-radius:var(--radius)">
          <option value="">Todos os anos</option>
          ${[...Array(3)].map((_,i) => {
            const a = new Date().getFullYear() - i;
            return `<option value="${a}" ${i===0?'selected':''}>${a}</option>`;
          }).join('')}
        </select>
        <span id="count-hist" style="font-family:var(--font-mono);font-size:0.6rem;color:#666"></span>
      </div>
      <div style="display:grid;grid-template-columns:1fr 280px;gap:1.5rem;align-items:start">
        <div id="resultado-hist"></div>
        <div id="ranking-hist"></div>
      </div>`;

    document.getElementById('search-hist').addEventListener('input', function () {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => carregarHistorico(
        this.value.trim(),
        document.getElementById('filter-ano-hist').value
      ), 300);
    });
    document.getElementById('filter-ano-hist').addEventListener('change', function () {
      carregarHistorico(document.getElementById('search-hist').value.trim(), this.value);
    });

    carregarHistorico('', document.getElementById('filter-ano-hist').value);
  }

  async function carregarHistorico(q, ano) {
    const resultado = document.getElementById('resultado-hist');
    const ranking   = document.getElementById('ranking-hist');
    const contador  = document.getElementById('count-hist');
    if (!resultado) return;
    const data = await post({ acao:'buscar_historico', busca: q, ano });
    if (!data.success) return;
    const regs = data.registros;
    if (contador) contador.textContent = `${regs.length} empréstimo${regs.length!==1?'s':''}`;
    resultado.innerHTML = regs.length === 0
      ? '<p class="empty-state" style="padding:2rem;color:#333;font-style:italic;font-family:var(--font-mono);font-size:0.75rem">Nenhum registro encontrado.</p>'
      : `<div class="book-grid" style="grid-template-columns:1fr">
          ${regs.map(e => `
            <article class="book-card" onclick="window._abrirModal('${esc(e.registro)}')">
              <div class="book-card-accent"></div>
              <div class="book-card-body" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem">
                <div>
                  <p class="book-title">${esc(e.aluno)}${e.sala?` <span style="font-size:0.7rem;font-family:var(--font-mono);color:#888">— ${esc(e.sala)}</span>`:''}</p>
                  <p class="book-reg">${esc(e.livro)} · REG #${esc(e.registro)}</p>
                </div>
                <div style="text-align:right">
                  <p style="font-family:var(--font-mono);font-size:0.6rem;color:#666">Retirada: ${fmtDate(e.retirada)}</p>
                  <p style="font-family:var(--font-mono);font-size:0.6rem;color:#555">Dev: ${fmtDate(e.devolucao)}</p>
                </div>
              </div>
            </article>`).join('')}
        </div>`;
    ranking.innerHTML = data.ranking.length === 0 ? '' : `
      <div style="background:#111;border:1px solid #1e1e1e;border-top:3px solid var(--rust);padding:1.5rem">
        <p style="font-family:var(--font-mono);font-size:0.55rem;letter-spacing:0.25em;color:var(--rust);margin-bottom:1.25rem;text-transform:uppercase">— Top leitores</p>
        ${data.ranking.map((r,i) => `
          <div style="display:flex;justify-content:space-between;align-items:center;padding:0.6rem 0;border-bottom:1px solid #1a1a1a">
            <div>
              <span style="font-family:var(--font-mono);font-size:0.55rem;color:#555;margin-right:0.5rem">#${i+1}</span>
              <span style="font-family:var(--font-display);font-size:0.85rem;color:#ddd">${esc(r.aluno)}</span>
            </div>
            <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--rust);font-weight:500">${r.total} livro${r.total!==1?'s':''}</span>
          </div>`).join('')}
      </div>`;
  }

  // ── Aba: Alertas ───────────────────────────────────────────────────────
  async function carregarAlertas() {
    const panel = document.getElementById('tab-alertas');
    const data  = await post({ acao:'buscar_aluno', busca:'' });
    if (!data.success) return;

    const hoje      = data.emprestimos.filter(e => diasRestantes(e.devolucao) === 0);
    const atrasados = data.emprestimos.filter(e => diasRestantes(e.devolucao) < 0);
    const proximos  = data.emprestimos.filter(e => { const d=diasRestantes(e.devolucao); return d>0 && d<=3; });

    const total = hoje.length + atrasados.length + proximos.length;
    const badge = document.getElementById('badge-alertas');
    if (total > 0) { badge.textContent = total; badge.style.display = 'inline'; }
    else badge.style.display = 'none';

    function secao(titulo, cor, lista) {
      if (!lista.length) return '';
      return `
        <div style="margin-bottom:2rem">
          <p style="font-family:var(--font-mono);font-size:0.55rem;letter-spacing:0.25em;color:${cor};margin-bottom:1rem;text-transform:uppercase">— ${titulo} (${lista.length})</p>
          <div class="book-grid" style="grid-template-columns:1fr">
            ${lista.map(e => {
              const dias = diasRestantes(e.devolucao);
              let label;
              if      (dias < 0)  label = `⚠ Atrasado ${Math.abs(dias)} dia${Math.abs(dias)!==1?'s':''}`;
              else if (dias === 0) label = '⚠ Devolver HOJE';
              else                 label = `Devolver até ${fmtDate(e.devolucao)}`;
              return `
                <article class="book-card" onclick="window._abrirModal('${esc(e.registro)}')">
                  <div class="book-card-accent" style="background:${cor}"></div>
                  <div class="book-card-body" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem">
                    <div>
                      <p class="book-title">${esc(e.aluno)}${e.sala?` <span style="font-size:0.7rem;font-family:var(--font-mono);color:#888">— ${esc(e.sala)}</span>`:''}</p>
                      <p class="book-reg">${esc(e.livro)} · REG #${esc(e.registro)} · Retirada: ${fmtDate(e.retirada)}</p>
                    </div>
                    <p style="font-family:var(--font-mono);font-size:0.65rem;color:${cor};white-space:nowrap">${label}</p>
                  </div>
                </article>`;
            }).join('')}
          </div>
        </div>`;
    }

    panel.innerHTML = total === 0
      ? '<p style="font-family:var(--font-mono);font-size:0.75rem;color:#333;font-style:italic;padding:2rem">Tudo em dia! Nenhum alerta no momento.</p>'
      : secao('Atrasados', '#cc2200', atrasados)
      + secao('Vencem hoje', '#ff9800', hoje)
      + secao('Vencem em até 3 dias', '#ccaa00', proximos);
  }

  carregarAlertas();

})();
    // ── Emprestar ─────────────────────────────────────────────────────────
    document.getElementById('btn-emprestar').addEventListener('click', async () => {
      const aluno    = document.getElementById('loan-aluno').value.trim();
      const sala     = document.getElementById('loan-sala').value.trim();
      const devolucaoRaw = document.getElementById('loan-devol').value;
const devolucao    = devolucaoRaw.length === 10 ? brToIso(devolucaoRaw) : '';
      const msg      = document.getElementById('loan-msg');

      if (!aluno || !devolucao) {
        msg.textContent = 'Preencha ao menos o nome e a data (DD/MM/AAAA).';
        msg.style.color = '#cc6600'; return;
      }

      // Valida se a data não é passada nem hoje
      const [dD,dM,dY] = devolucaoRaw.split('/');
      const dtDevol = new Date(+dY, +dM-1, +dD);
      const dtHoje  = new Date(); dtHoje.setHours(0,0,0,0);
      if (isNaN(dtDevol) || dtDevol <= dtHoje) {
        msg.textContent = 'A data de devolução deve ser a partir de amanhã.';
        msg.style.color = '#cc6600'; return;
      }

      const data = await post({ acao:'emprestar_livro', registro:modalRegistro, aluno, sala, devolucao });
      msg.textContent = data.msg;
      msg.style.color = data.success ? '#4caf7d' : '#cc4400';

      if (data.success) {
        document.getElementById('loan-aluno').value = '';
        document.getElementById('loan-sala').value  = '';
        document.getElementById('loan-devol').value = '';
        await abrirModal(modalRegistro);
      }
    });

    // ── Devolver ──────────────────────────────────────────────────────────
    window._devolver = async function(id) {
      const data = await post({ acao:'devolver_livro', id });
      if (data.success) { await abrirModal(modalRegistro); }
      else alert(data.msg);
    };

        // ── DELETAR LIVRO ─────────────────────────────────────────────────────────
document.getElementById('btn-deletar').addEventListener('click', async () => {
  if (!confirm(`⚠️ APAGAR LIVRO PERMANENTEMENTE\n\nREG #${modalRegistro}\n"${document.getElementById('modal-title').textContent}"\n\n✅ Remove do banco.txt\n✅ Remove TODOS os empréstimos\n\nCONFIRMAR?`)) return;
  
  const btn = document.getElementById('btn-deletar');
  const msg = document.getElementById('delete-msg');
  btn.textContent = '⏳ Apagando...';
  btn.disabled = true;
  
  try {
    const data = await post({ acao: 'deletar_livro', registro: modalRegistro });
    msg.textContent = data.msg;
    msg.style.color = data.success ? '#4caf7d' : '#cc4400';
    
    if (data.success) {
      setTimeout(fecharModal, 1500); // Fecha e recarrega grid
    } else {
      btn.textContent = '🗑️ APAGAR LIVRO DO SISTEMA';
      btn.disabled = false;
    }
  } catch {
    msg.textContent = 'Erro de conexão';
    msg.style.color = '#cc4400';
    btn.textContent = '🗑️ APAGAR LIVRO DO SISTEMA';
    btn.disabled = false;
  }
});

  })();
</script>

</body>
</html>