<?php
session_start();

// ══ CONFIG ══════════════════════════════
$diretorio     = __DIR__ . '/db';
$arquivo       = $diretorio . '/banco.txt';
$arqEmprestimos = $diretorio . '/emprestimos.txt';

// ══ ROTAS ═══════════════════════════════
require __DIR__ . '/src/functions/procurar_livro.php';
require __DIR__ . '/src/functions/emprestar_livro.php';
require __DIR__ . '/src/functions/devolver_livro.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Acervo — Biblioteca E.E. Ephigênia</title>
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
      background: rgba(0,0,0,0.85); backdrop-filter: blur(4px);
      display: flex; align-items: center; justify-content: center;
      padding: 1rem; opacity: 0; pointer-events: none;
      transition: opacity 0.25s ease;
    }
    .modal-overlay.open { opacity: 1; pointer-events: all; }

    .modal {
      background: #0f0f0f; border: 1px solid #1e1e1e; border-top: 3px solid var(--rust);
      width: 100%; max-width: 680px; max-height: 90vh;
      overflow-y: auto; position: relative;
      transform: translateY(20px); transition: transform 0.25s ease;
    }
    .modal-overlay.open .modal { transform: translateY(0); }

    .modal-header {
      display: flex; align-items: flex-start; justify-content: space-between;
      padding: 2rem 2rem 1.25rem; border-bottom: 1px solid #1a1a1a; gap: 1rem;
    }
    .modal-title-block {}
    .modal-reg {
      font-family: var(--font-mono); font-size: 0.55rem;
      letter-spacing: 0.25em; color: var(--rust); margin-bottom: 0.4rem;
    }
    .modal-title {
      font-family: var(--font-display); font-size: 1.6rem;
      font-weight: 900; color: #f0f0f0; line-height: 1.1;
    }
    .modal-close {
      background: none; border: 1px solid #2a2a2a; color: #888;
      font-family: var(--font-mono); font-size: 0.7rem; cursor: pointer;
      padding: 0.4rem 0.75rem; transition: all var(--transition); flex-shrink: 0;
      letter-spacing: 0.1em;
    }
    .modal-close:hover { border-color: var(--rust); color: var(--rust); }

    .modal-stats {
      display: grid; grid-template-columns: repeat(3, 1fr);
      gap: 1px; background: #1a1a1a; margin: 0;
      border-bottom: 1px solid #1a1a1a;
    }
    .stat-cell {
      background: #0f0f0f; padding: 1.25rem 1.5rem;
    }
    .stat-label {
      font-family: var(--font-mono); font-size: 0.5rem;
      letter-spacing: 0.2em; color: #555; margin-bottom: 0.4rem;
      text-transform: uppercase;
    }
    .stat-value {
      font-family: var(--font-display); font-size: 1.8rem;
      font-weight: 900; color: #e0e0e0; line-height: 1;
    }
    .stat-value.ok { color: #4caf7d; }
    .stat-value.warn { color: #ff9800; }
    .stat-value.bad { color: #cc2200; }

    .modal-body { padding: 1.5rem 2rem 2rem; }

    /* ── Loan form ── */
    .loan-form-wrap {
      background: #111; border: 1px solid #1e1e1e;
      border-left: 3px solid var(--rust); padding: 1.5rem; margin-bottom: 2rem;
    }
    .loan-form-title {
      font-family: var(--font-mono); font-size: 0.55rem;
      letter-spacing: 0.25em; color: var(--rust); margin-bottom: 1.25rem;
      text-transform: uppercase;
    }
    .loan-form { display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: flex-end; }
    .loan-field { display: flex; flex-direction: column; gap: 0.35rem; flex: 1; min-width: 130px; }
    .loan-label {
      font-family: var(--font-mono); font-size: 0.5rem;
      letter-spacing: 0.15em; color: #888; text-transform: uppercase;
    }
    .loan-input {
      background: #0a0a0a; border: 1px solid #2a2a2a; border-radius: var(--radius);
      color: #e0e0e0; font-family: var(--font-mono); font-size: 0.8rem;
      padding: 0.5rem 0.75rem; outline: none;
      transition: border-color var(--transition);
    }
    .loan-input:focus { border-color: var(--rust); }
    .loan-input::placeholder { color: #444; font-style: italic; }
    .btn-loan {
      background: var(--rust); color: #f0f0f0; border: none;
      font-family: var(--font-mono); font-size: 0.65rem;
      letter-spacing: 0.2em; text-transform: uppercase;
      padding: 0.55rem 1.25rem; cursor: pointer;
      transition: background var(--transition); white-space: nowrap;
    }
    .btn-loan:hover { background: var(--rust-dark); }

    /* ── Loans list ── */
    .loans-title {
      font-family: var(--font-mono); font-size: 0.55rem;
      letter-spacing: 0.25em; color: #666; margin-bottom: 1rem;
      text-transform: uppercase;
    }
    .loan-row {
      display: grid; grid-template-columns: 1fr auto auto auto;
      gap: 0.5rem 1rem; align-items: center;
      padding: 0.85rem 0; border-bottom: 1px solid #151515;
    }
    .loan-row:last-child { border-bottom: none; }
    .loan-aluno {
      font-family: var(--font-display); font-size: 0.95rem;
      font-weight: 700; color: #ddd;
    }
    .loan-date {
      font-family: var(--font-mono); font-size: 0.6rem; color: #555;
    }
    .loan-devol {
      font-family: var(--font-mono); font-size: 0.6rem; color: #888;
      white-space: nowrap;
    }
    .loan-devol.atrasado { color: #cc4400; }
    .loan-devol.hoje { color: #ff9800; }
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
    .nav-link {
      font-family: var(--font-mono); font-size: 0.6rem;
      letter-spacing: 0.15em; color: #555; text-decoration: none;
      display: flex; align-items: center; gap: 0.5rem;
      transition: color var(--transition);
    }
    .nav-link:hover { color: var(--rust); }
    .nav-link .nl-arrow { font-size: 0.8rem; }

    .hero-sub {
      font-family: var(--font-mono); font-size: 0.9rem; color: #fcfcfc;
    }
  </style>
</head>
<body>
<div class="noise"></div>

<header class="header">
  <div class="header-inner">
    <div class="logo-block">
      <span class="logo-pre">Biblioteca</span>
      <span class="logo-name">E.E. Ephigênia</span>
    </div>
    <nav class="header-nav">
      <span class="nav-tag">Acervo</span>
      <div class="nav-dot"></div>
      <a href="cadastrar.php" class="nav-link"><span class="nl-arrow">+</span> Registrar livro</a>
      <div class="nav-dot"></div>
      <span class="nav-year"><?= date('Y') ?></span>
    </nav>
  </div>
  <div class="header-line"></div>
</header>

<main class="main">
  <div class="hero">
    <p class="hero-label">Sistema de acervo</p>
    <h1 class="hero-title">Acer<em>vo</em></h1>
    <p class="hero-sub">Clique em um livro para ver detalhes, empréstimos e devoluções.</p>
  </div>

  <?php include __DIR__ . '/src/template/procurar_livros.blade.php'; ?>
</main>

<footer class="footer">
  <div class="footer-inner">
    <span class="footer-school">E.E. Ephigênia</span>
    <span class="footer-sep">/</span>
    <span class="footer-text">Sistema de Biblioteca</span>
    <span class="footer-sep">/</span>
    <span class="footer-text">Desenvolvido por Arthur A. 2 Reg 3</span>
  </div>
</footer>

<!-- ── MODAL ── -->
<div class="modal-overlay" id="modal-overlay">
  <div class="modal" id="modal">
    <div class="modal-header">
      <div class="modal-title-block">
        <p class="modal-reg" id="modal-reg">—</p>
        <h2 class="modal-title" id="modal-title">—</h2>
      </div>
      <button class="modal-close" id="modal-close">ESC ✕</button>
    </div>
    <div class="modal-stats" id="modal-stats">
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
        <p class="stat-value" id="stat-disponíveis">—</p>
      </div>
    </div>
    <div class="modal-body">
      <div class="loan-form-wrap">
        <p class="loan-form-title">— Registrar empréstimo</p>
        <div class="loan-form">
          <div class="loan-field">
            <label class="loan-label">Nome do aluno</label>
            <input class="loan-input" id="loan-aluno" type="text" placeholder="ex: João Silva" autocomplete="off">
          </div>
          <div class="loan-field" style="max-width:150px">
            <label class="loan-label">Devolução</label>
            <input class="loan-input" id="loan-devol" type="date">
          </div>
          <button class="btn-loan" id="btn-emprestar">Emprestar →</button>
        </div>
        <p id="loan-msg" style="font-family:var(--font-mono);font-size:0.65rem;margin-top:0.75rem;color:#888;min-height:1rem;"></p>
      </div>

      <p class="loans-title">— Empréstimos ativos</p>
      <div id="loans-list"><p class="no-loans">Nenhum empréstimo ativo.</p></div>
    </div>
  </div>
</div>

</body>
</html>