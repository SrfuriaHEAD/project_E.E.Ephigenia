<?php
session_start();

$diretorio      = __DIR__ . '/db';
$arquivo        = $diretorio . '/banco.txt';
$arqEmprestimos = $diretorio . '/emprestimos.txt';

require __DIR__ . '/src/functions/procurar_livro.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Acervo — Biblioteca E.E. Ephigênia</title>
  <link rel="stylesheet" href="src/static/style.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,300;0,700;0,900;1,300;1,700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --cream:   #f5f0e8;
      --paper:   #faf7f2;
      --ink:     #1a1410;
      --ink-mid: #3d342a;
      --ink-low: #7a6e62;
      --blue:    #1a3a6b;
      --blue-lt: #e8eef7;
      --gold:    #c8902a;
      --gold-lt: #f5e6c8;
      --ok:      #2e6e4a;
      --danger:  #9b1c1c;
      --radius:  6px;
      --tr:      0.2s ease;
      --font-serif: 'Fraunces', Georgia, serif;
      --font-mono:  'Space Mono', monospace;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: var(--font-mono);
      background: var(--cream);
      color: var(--ink);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* ── HEADER ─────────────────────────────────────────────── */
    .header {
      background: var(--blue);
      padding: 0;
      position: sticky;
      top: 0;
      z-index: 200;
      box-shadow: 0 2px 12px rgba(0,0,0,0.18);
    }

    .header-inner {
      max-width: 1100px;
      margin: 0 auto;
      padding: 1rem 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
    }

    .logo {
      display: flex;
      flex-direction: column;
      gap: 0.1rem;
    }

    .logo-pre {
      font-size: 0.5rem;
      letter-spacing: 0.3em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.5);
    }

    .logo-name {
      font-family: var(--font-serif);
      font-size: 1.15rem;
      font-weight: 900;
      color: #fff;
      line-height: 1;
    }

    .header-search {
      position: relative;
      flex: 1;
      max-width: 360px;
    }

    .header-search svg {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: rgba(255,255,255,0.5);
      pointer-events: none;
    }

    .header-search input {
      width: 100%;
      background: rgba(255,255,255,0.12);
      border: 1px solid rgba(255,255,255,0.2);
      border-radius: 999px;
      color: #fff;
      font-family: var(--font-mono);
      font-size: 0.75rem;
      padding: 0.55rem 1rem 0.55rem 2.4rem;
      outline: none;
      transition: background var(--tr), border-color var(--tr);
    }

    .header-search input:focus {
      background: rgba(255,255,255,0.18);
      border-color: rgba(255,255,255,0.5);
    }

    .header-search input::placeholder { color: rgba(255,255,255,0.45); }

    .header-tag {
      font-size: 0.5rem;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.5);
      border: 1px solid rgba(255,255,255,0.2);
      padding: 0.25rem 0.65rem;
      border-radius: 2px;
    }

    /* ── HERO ───────────────────────────────────────────────── */
    .hero {
      background: var(--blue);
      padding: 3rem 2rem 3.5rem;
      position: relative;
      overflow: hidden;
    }

    .hero::after {
      content: '';
      position: absolute;
      inset: 0;
      background: radial-gradient(ellipse at 70% 50%, rgba(200,144,42,0.12) 0%, transparent 60%);
      pointer-events: none;
    }

    .hero-inner {
      max-width: 1100px;
      margin: 0 auto;
      position: relative;
      z-index: 1;
    }

    .hero-eyebrow {
      font-size: 0.55rem;
      letter-spacing: 0.35em;
      text-transform: uppercase;
      color: var(--gold);
      margin-bottom: 0.75rem;
    }

    .hero-title {
      font-family: var(--font-serif);
      font-size: clamp(2.5rem, 6vw, 4.5rem);
      font-weight: 900;
      color: #fff;
      line-height: 1.0;
      margin-bottom: 1rem;
    }

    .hero-title em {
      font-style: italic;
      color: var(--gold);
    }

    .hero-sub {
      font-size: 0.8rem;
      color: rgba(255,255,255,0.55);
      max-width: 480px;
      line-height: 1.7;
    }

    .hero-stats {
      display: flex;
      gap: 2.5rem;
      margin-top: 2rem;
      flex-wrap: wrap;
    }

    .hero-stat-val {
      font-family: var(--font-serif);
      font-size: 2rem;
      font-weight: 900;
      color: #fff;
      line-height: 1;
    }

    .hero-stat-lbl {
      font-size: 0.5rem;
      letter-spacing: 0.2em;
      color: rgba(255,255,255,0.4);
      text-transform: uppercase;
      margin-top: 0.25rem;
    }

    /* ── WAVE ───────────────────────────────────────────────── */
    .wave {
      display: block;
      background: var(--blue);
      line-height: 0;
    }
    .wave svg { display: block; width: 100%; }

    /* ── TOOLBAR ────────────────────────────────────────────── */
    .toolbar {
      max-width: 1100px;
      margin: 0 auto;
      padding: 1.5rem 2rem 0;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .toolbar-left {
      font-size: 0.6rem;
      letter-spacing: 0.15em;
      color: var(--ink-low);
    }

    .filter-row {
      display: flex;
      gap: 0.5rem;
    }

    .filter-btn {
      background: none;
      border: 1px solid var(--ink-low);
      border-radius: 999px;
      font-family: var(--font-mono);
      font-size: 0.55rem;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: var(--ink-low);
      padding: 0.3rem 0.85rem;
      cursor: pointer;
      transition: all var(--tr);
    }

    .filter-btn:hover,
    .filter-btn.active {
      background: var(--blue);
      border-color: var(--blue);
      color: #fff;
    }

    /* ── GRID ───────────────────────────────────────────────── */
    main {
      flex: 1;
      max-width: 1100px;
      width: 100%;
      margin: 0 auto;
      padding: 2rem 2rem 5rem;
    }

    #grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
      gap: 1.25rem;
    }

    /* ── CARD ───────────────────────────────────────────────── */
    .book-card {
      background: var(--paper);
      border: 1px solid rgba(0,0,0,0.1);
      border-radius: 10px;
      overflow: hidden;
      cursor: pointer;
      transition: transform var(--tr), box-shadow var(--tr);
      position: relative;
      display: flex;
      flex-direction: column;
    }

    .book-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 30px rgba(0,0,0,0.12);
    }

    /* colored spine strip at top */
    .book-spine {
      height: 6px;
      width: 100%;
      flex-shrink: 0;
    }

    .book-card-body {
      padding: 1.35rem 1.25rem 1.1rem;
      flex: 1;
      display: flex;
      flex-direction: column;
    }

    .book-reg-tag {
      font-size: 0.48rem;
      letter-spacing: 0.25em;
      text-transform: uppercase;
      color: var(--ink-low);
      margin-bottom: 0.5rem;
    }

    .book-title {
      font-family: var(--font-serif);
      font-size: 1.05rem;
      font-weight: 700;
      color: var(--ink);
      line-height: 1.25;
      margin-bottom: 0.4rem;
      flex: 1;
    }

    .book-author {
      font-size: 0.65rem;
      color: var(--ink-mid);
      margin-bottom: 1rem;
      font-style: italic;
    }

    .book-footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.5rem;
    }

    .avail-pill {
      font-size: 0.55rem;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      padding: 0.25rem 0.65rem;
      border-radius: 999px;
      font-weight: 700;
    }

    .avail-ok   { background: #d1fae5; color: var(--ok); }
    .avail-low  { background: #fef3c7; color: #92400e; }
    .avail-none { background: #fee2e2; color: var(--danger); }

    .avail-bar-wrap {
      flex: 1;
      height: 3px;
      background: rgba(0,0,0,0.08);
      border-radius: 99px;
      overflow: hidden;
    }

    .avail-bar { height: 100%; border-radius: 99px; transition: width 0.5s ease; }

    .empty-state {
      grid-column: 1/-1;
      padding: 4rem 2rem;
      text-align: center;
      font-size: 0.8rem;
      color: var(--ink-low);
      font-style: italic;
    }

    /* ── MODAL ──────────────────────────────────────────────── */
    .overlay {
      position: fixed;
      inset: 0;
      z-index: 500;
      background: rgba(20,15,10,0.7);
      backdrop-filter: blur(6px);
      display: flex;
      align-items: flex-end;
      justify-content: center;
      padding: 0;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.25s ease;
    }

    .overlay.open { opacity: 1; pointer-events: all; }

    .modal {
      background: var(--paper);
      width: 100%;
      max-width: 560px;
      max-height: 88vh;
      overflow-y: auto;
      border-radius: 18px 18px 0 0;
      transform: translateY(100%);
      transition: transform 0.35s cubic-bezier(0.32,0.72,0,1);
      position: relative;
    }

    @media (min-width: 640px) {
      .overlay { align-items: center; padding: 2rem; }
      .modal { border-radius: 14px; transform: translateY(24px) scale(0.97); }
    }

    .overlay.open .modal { transform: translateY(0) scale(1); }

    .modal-handle {
      width: 36px;
      height: 4px;
      background: rgba(0,0,0,0.15);
      border-radius: 2px;
      margin: 0.85rem auto 0;
    }

    @media (min-width: 640px) { .modal-handle { display: none; } }

    .modal-spine {
      height: 8px;
      width: 100%;
      margin-top: 0.5rem;
    }

    @media (min-width: 640px) { .modal-spine { margin-top: 0; border-radius: 12px 12px 0 0; } }

    .modal-header {
      padding: 1.5rem 1.75rem 1.25rem;
      border-bottom: 1px solid rgba(0,0,0,0.08);
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 1rem;
    }

    .modal-reg {
      font-size: 0.48rem;
      letter-spacing: 0.25em;
      color: var(--ink-low);
      text-transform: uppercase;
      margin-bottom: 0.35rem;
    }

    .modal-title {
      font-family: var(--font-serif);
      font-size: 1.55rem;
      font-weight: 900;
      color: var(--ink);
      line-height: 1.1;
    }

    .modal-author {
      font-family: var(--font-serif);
      font-style: italic;
      font-size: 0.9rem;
      color: var(--ink-mid);
      margin-top: 0.3rem;
    }

    .modal-close {
      background: rgba(0,0,0,0.06);
      border: none;
      border-radius: 999px;
      width: 32px; height: 32px;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer;
      color: var(--ink-mid);
      flex-shrink: 0;
      transition: background var(--tr);
      font-size: 1rem;
    }

    .modal-close:hover { background: rgba(0,0,0,0.12); }

    .modal-stats {
      display: grid;
      grid-template-columns: repeat(3,1fr);
      gap: 1px;
      background: rgba(0,0,0,0.07);
      border-bottom: 1px solid rgba(0,0,0,0.08);
    }

    .stat-cell {
      background: var(--paper);
      padding: 1rem 1.25rem;
      text-align: center;
    }

    .stat-val {
      font-family: var(--font-serif);
      font-size: 1.8rem;
      font-weight: 900;
      line-height: 1;
      color: var(--ink);
    }

    .stat-val.ok   { color: var(--ok); }
    .stat-val.warn { color: #b45309; }
    .stat-val.bad  { color: var(--danger); }

    .stat-lbl {
      font-size: 0.45rem;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: var(--ink-low);
      margin-top: 0.3rem;
    }

    .modal-body { padding: 1.5rem 1.75rem 2rem; }

    .loans-empty {
      font-size: 0.75rem;
      color: var(--ink-low);
      font-style: italic;
      padding: 1rem 0;
    }

    .loans-title {
      font-size: 0.5rem;
      letter-spacing: 0.25em;
      text-transform: uppercase;
      color: var(--ink-low);
      margin-bottom: 1rem;
    }

    .loan-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      padding: 0.75rem 0;
      border-bottom: 1px solid rgba(0,0,0,0.06);
    }
    .loan-row:last-child { border-bottom: none; }

    .loan-name {
      font-family: var(--font-serif);
      font-size: 0.9rem;
      font-weight: 700;
      color: var(--ink);
    }

    .loan-sala {
      font-size: 0.55rem;
      letter-spacing: 0.05em;
      color: var(--ink-low);
    }

    .loan-date {
      font-size: 0.55rem;
      color: var(--ink-low);
      white-space: nowrap;
    }

    .loan-date.late { color: var(--danger); font-weight: 700; }
    .loan-date.soon { color: #b45309; }

    /* ── FOOTER ─────────────────────────────────────────────── */
    .footer {
      background: var(--ink);
      color: rgba(255,255,255,0.4);
      padding: 1.25rem 2rem;
      font-size: 0.55rem;
      letter-spacing: 0.15em;
      text-align: center;
    }

    .footer span { color: rgba(255,255,255,0.7); }

    /* ── SKELETON ───────────────────────────────────────────── */
    @keyframes shimmer {
      0% { background-position: -400px 0; }
      100% { background-position: 400px 0; }
    }

    .skel {
      background: linear-gradient(90deg, rgba(0,0,0,0.05) 25%, rgba(0,0,0,0.1) 50%, rgba(0,0,0,0.05) 75%);
      background-size: 800px 100%;
      animation: shimmer 1.4s infinite;
      border-radius: 4px;
    }

    /* ── RESPONSIVE ─────────────────────────────────────────── */
    @media (max-width: 600px) {
      .hero { padding: 2rem 1.25rem 2.5rem; }
      .hero-title { font-size: 2.2rem; }
      main { padding: 1.5rem 1rem 4rem; }
      #grid { grid-template-columns: 1fr 1fr; gap: 0.85rem; }
      
      /* SEARCH MOBILE LINDO */
      .header-search {
        display: block !important;
        max-width: 220px;
        order: 3;
        margin-left: auto;
      }
      
      .header-search input {
        background: rgba(255,255,255,0.22) !important;
        border: 1px solid rgba(255,255,255,0.5) !important;
        border-radius: 25px !important;
        color: #fff !important;
        font-size: 15px !important;
        padding: 12px 16px 12px 42px !important;
        box-shadow: 0 4px 16px rgba(0,0,0,0.2);
        font-weight: 500;
      }
      
      .header-search input:focus {
        background: rgba(255,255,255,0.35) !important;
        border-color: #fff !important;
        box-shadow: 0 8px 25px rgba(0,0,0,0.25);
        transform: scale(1.03);
      }
      
      .header-search svg {
        left: 16px !important;
        width: 18px;
        height: 18px;
      }
      
      .header-inner {
        flex-wrap: wrap;
        padding: 0.8rem 1rem;
        gap: 0.75rem;
      }
      
      .header-tag { display: none; }
      .toolbar { padding: 1.25rem 1rem 0; }
    }

/* Cards mais vivos */
    .book-card {
      transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
    }

    .book-card:hover {
      transform: translateY(-8px) scale(1.02) !important;
      box-shadow: 0 20px 40px rgba(0,0,0,0.15) !important;
    }

  </style>
</head>
<body>

  <!-- HEADER -->
  <header class="header">
    <div class="header-inner">
      <div class="logo">
        <span class="logo-pre">Biblioteca</span>
        <span class="logo-name">E.E. Ephigênia</span>
      </div>
      <div class="header-search">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
        <input id="search-top" type="text" placeholder="Buscar livro ou autor…" autocomplete="off">
      </div>
      <a class="nav-link" href="index.php">Biblioteca</a>
    </div>
  </header>



  <!-- HERO -->
  <section class="hero">
    <div class="hero-inner">
      <p class="hero-eyebrow">— Acervo Digital</p>
      <h1 class="hero-title">Encontre seu<br><em>próximo livro.</em></h1>
      <p class="hero-sub">Explore o acervo da biblioteca, veja a disponibilidade em tempo real e descubra sua próxima leitura.</p>
      <div class="hero-stats">
        <div>
          <div class="hero-stat-val" id="stat-total">—</div>
          <div class="hero-stat-lbl">Títulos</div>
        </div>
        <div>
          <div class="hero-stat-val" id="stat-disp">—</div>
          <div class="hero-stat-lbl">Disponíveis</div>
        </div>
        <div>
          <div class="hero-stat-val" id="stat-emp">—</div>
          <div class="hero-stat-lbl">Emprestados</div>
        </div>
      </div>
    </div>
  </section>

  <!-- wave -->
  <div class="wave">
    <svg viewBox="0 0 1200 48" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M0,0 C300,48 900,48 1200,0 L1200,0 L0,0 Z" fill="#1a3a6b"/>
      <path d="M0,0 C300,48 900,48 1200,0 L1200,48 L0,48 Z" fill="#f5f0e8"/>
    </svg>
  </div>

  <!-- TOOLBAR -->
  <div class="toolbar">
    <span class="toolbar-left" id="count-label">Carregando…</span>
    <div class="filter-row">
      <button class="filter-btn active" data-filter="todos">Todos</button>
      <button class="filter-btn" data-filter="disp">Disponíveis</button>
    </div>
  </div>

  <!-- GRID -->
  <main>
    <div id="grid">
      <!-- skeletons enquanto carrega -->
      <?php for($i=0;$i<8;$i++): ?>
      <div class="book-card" style="pointer-events:none">
        <div class="book-spine skel"></div>
        <div class="book-card-body" style="gap:0.75rem;display:flex;flex-direction:column">
          <div class="skel" style="height:10px;width:40%"></div>
          <div class="skel" style="height:18px;width:80%"></div>
          <div class="skel" style="height:14px;width:60%"></div>
          <div style="display:flex;justify-content:space-between;margin-top:auto">
            <div class="skel" style="height:20px;width:70px;border-radius:999px"></div>
            <div class="skel" style="height:3px;width:60px;margin-top:9px"></div>
          </div>
        </div>
      </div>
      <?php endfor; ?>
    </div>
  </main>

  <!-- MODAL -->
  <div class="overlay" id="overlay">
    <div class="modal" id="modal">
      <div class="modal-handle"></div>
      <div class="modal-spine" id="modal-spine"></div>
      <div class="modal-header">
        <div>
          <p class="modal-reg" id="modal-reg"></p>
          <h2 class="modal-title" id="modal-title"></h2>
          <p class="modal-author" id="modal-author"></p>
        </div>
        <button class="modal-close" id="modal-close">✕</button>
      </div>
      <div class="modal-stats">
        <div class="stat-cell">
          <div class="stat-val" id="ms-total">—</div>
          <div class="stat-lbl">Total</div>
        </div>
        <div class="stat-cell">
          <div class="stat-val" id="ms-emp">—</div>
          <div class="stat-lbl">Emprestados</div>
        </div>
        <div class="stat-cell">
          <div class="stat-val" id="ms-disp">—</div>
          <div class="stat-lbl">Disponíveis</div>
        </div>
      </div>
      <div class="modal-body" id="modal-loans"></div>
    </div>
  </div>

  <!-- FOOTER -->
  <footer class="footer">
    <span>E.E. Ephigênia</span> — Sistema de Biblioteca Escolar
  </footer>

<script>
(function () {
  // ── paleta de cores para as spines dos livros ────────────────
  const PALETA = [
    '#1a3a6b','#c8902a','#2e6e4a','#8b3a3a','#3a5c8b',
    '#6b4f1a','#2a5c4f','#7a3a6b','#4a6b3a','#5c3a1a'
  ];

  function spineColor(registro) {
    const n = parseInt(registro, 10) || 0;
    return PALETA[n % PALETA.length];
  }

  // ── utils ────────────────────────────────────────────────────
  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c =>
      ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  function diasRestantes(iso) {
    const hoje = new Date(); hoje.setHours(0,0,0,0);
    return Math.round((new Date(iso) - hoje) / 86400000);
  }

  function fmtData(iso) {
    const [y,m,d] = iso.split('-');
    return `${d}/${m}/${y}`;
  }

  async function post(body) {
    const r = await fetch('', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams(body)
    });
    return r.json();
  }

  // ── estado ───────────────────────────────────────────────────
  let todosLivros = [];
  let filtroAtivo = 'todos';
  let searchQuery = '';

  // ── renderiza cards ──────────────────────────────────────────
  function renderGrid(livros) {
    const grid = document.getElementById('grid');

    if (!livros.length) {
      grid.innerHTML = '<p class="empty-state">Nenhum livro encontrado.</p>';
      document.getElementById('count-label').textContent = '0 títulos';
      return;
    }

    document.getElementById('count-label').textContent =
      `${livros.length} título${livros.length !== 1 ? 's' : ''}`;

    grid.innerHTML = livros.map(l => {
      const cor  = spineColor(l.registro);
      const pct  = l.quantidade > 0 ? Math.round((l.disponiveis / l.quantidade) * 100) : 0;
      const pillCls = l.disponiveis === 0 ? 'avail-none'
                    : l.disponiveis <= 1  ? 'avail-low'
                    :                       'avail-ok';
      const pillTxt = l.disponiveis === 0 ? 'Esgotado'
                    : l.disponiveis === 1  ? '1 disponível'
                    :                       `${l.disponiveis} disponíveis`;
      const barColor = l.disponiveis === 0 ? '#f87171'
                     : l.disponiveis <= 1  ? '#f59e0b'
                     :                      '#34d399';
      const autorHtml = l.autor
        ? `<p class="book-author">${esc(l.autor)}</p>`
        : `<p class="book-author" style="opacity:0.4">—</p>`;

      return `
        <article class="book-card" onclick="abrirModal('${esc(l.registro)}')">
          <div class="book-spine" style="background:${cor}"></div>
          <div class="book-card-body">
            <p class="book-reg-tag">REG #${esc(l.registro)}</p>
            <h3 class="book-title">${esc(l.nome)}</h3>
            ${autorHtml}
            <div class="book-footer">
              <span class="avail-pill ${pillCls}">${pillTxt}</span>
              <div class="avail-bar-wrap">
                <div class="avail-bar" style="width:${pct}%;background:${barColor}"></div>
              </div>
            </div>
          </div>
        </article>`;
    }).join('');
  }

  function aplicarFiltro() {
    let lista = todosLivros;
    if (searchQuery) {
      const q = searchQuery.toLowerCase();
      lista = lista.filter(l =>
        l.nome.toLowerCase().includes(q) ||
        String(l.registro).includes(q) ||
        (l.autor || '').toLowerCase().includes(q)
      );
    }
    if (filtroAtivo === 'disp') {
      lista = lista.filter(l => l.disponiveis > 0);
    }
    renderGrid(lista);
  }

  // ── carrega acervo ───────────────────────────────────────────
  async function carregarAcervo() {
    const data = await post({ acao: 'procurar_livros', busca: '' });
    if (!data.success) return;

    todosLivros = data.livros;

    const total = todosLivros.length;
    const disp  = todosLivros.reduce((a, l) => a + l.disponiveis, 0);
    const emp   = todosLivros.reduce((a, l) => a + l.emprestados, 0);

    document.getElementById('stat-total').textContent = total;
    document.getElementById('stat-disp').textContent  = disp;
    document.getElementById('stat-emp').textContent   = emp;

    aplicarFiltro();
  }

  carregarAcervo();

  // ── busca (header + top) ─────────────────────────────────────
  let timer;
  ['search-top'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', function () {
      searchQuery = this.value.trim();
      clearTimeout(timer);
      timer = setTimeout(aplicarFiltro, 250);
    });
  });

  // ── filtros ──────────────────────────────────────────────────
  document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      filtroAtivo = this.dataset.filter;
      aplicarFiltro();
    });
  });

  // ── modal ────────────────────────────────────────────────────
  const overlay = document.getElementById('overlay');
  const modal   = document.getElementById('modal');

  document.getElementById('modal-close').addEventListener('click', () => {
    overlay.classList.remove('open');
  });

  overlay.addEventListener('click', e => {
    if (e.target === overlay) overlay.classList.remove('open');
  });

  window.abrirModal = async function (registro) {
    // mostra estado vazio enquanto carrega
    document.getElementById('modal-reg').textContent   = `REG #${registro}`;
    document.getElementById('modal-title').textContent = '…';
    document.getElementById('modal-author').textContent = '';
    document.getElementById('ms-total').textContent    = '—';
    document.getElementById('ms-emp').textContent      = '—';
    document.getElementById('ms-disp').textContent     = '—';
    document.getElementById('modal-loans').innerHTML   = '';
    overlay.classList.add('open');

    const data = await post({ acao: 'detalhes_livro', registro });
    if (!data.success) {
      document.getElementById('modal-title').textContent = 'Erro ao carregar.';
      return;
    }

    const l   = data.livro;
    const cor = spineColor(l.registro);

    document.getElementById('modal-spine').style.background = cor;
    document.getElementById('modal-reg').textContent   = `REG #${esc(l.registro)}`;
    document.getElementById('modal-title').textContent = l.nome;
    document.getElementById('modal-author').textContent = l.autor ? l.autor : '';

    const dispEl = document.getElementById('ms-disp');
    dispEl.textContent = l.disponiveis;
    dispEl.className   = 'stat-val ' + (l.disponiveis === 0 ? 'bad' : l.disponiveis <= 1 ? 'warn' : 'ok');

    document.getElementById('ms-total').textContent = l.quantidade;
    document.getElementById('ms-emp').textContent   = l.emprestados;

    const body = document.getElementById('modal-loans');
    if (!l.emprestimos || !l.emprestimos.length) {
      body.innerHTML = '<p class="loans-empty">Nenhum empréstimo ativo.</p>';
      return;
    }

    body.innerHTML = `
      <p class="loans-title">— Empréstimos ativos (${l.emprestimos.length})</p>
      ${l.emprestimos.map(e => {
        const dias = diasRestantes(e.devolucao);
        let dataCls = '', dataInfo = `Devolver até ${fmtData(e.devolucao)}`;
        if (dias < 0)      { dataCls = 'late'; dataInfo = `⚠ Atrasado ${Math.abs(dias)}d`; }
        else if (dias === 0) { dataCls = 'soon'; dataInfo = '⚠ Devolver hoje'; }
        else if (dias <= 3)  { dataCls = 'soon'; dataInfo = `Em ${dias} dia${dias!==1?'s':''}`; }

        return `
          <div class="loan-row">
            <div>
              <p class="loan-name">${esc(e.aluno)}</p>
              <p class="loan-sala">${esc(e.sala || '—')}</p>
            </div>
            <p class="loan-date ${dataCls}">${dataInfo}</p>
          </div>`;
      }).join('')}`;
  };

  // ── swipe to close on mobile ─────────────────────────────────
  let startY = 0;
  modal.addEventListener('touchstart', e => { startY = e.touches[0].clientY; }, { passive: true });
  modal.addEventListener('touchmove', e => {
    const dy = e.touches[0].clientY - startY;
    if (dy > 60 && modal.scrollTop === 0) overlay.classList.remove('open');
  }, { passive: true });
})();
</script>
</body>
</html>
