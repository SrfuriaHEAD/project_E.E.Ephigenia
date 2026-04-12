<?php
session_start();

$diretorio     = __DIR__ . '/db';
$arquivo       = $diretorio . '/banco.txt';
$arqEmprestimos = $diretorio . '/emprestimos.txt';

require __DIR__ . '/src/functions/cadastrar_livro.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cadastrar — Biblioteca E.E. Ephigênia</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="src/static/style.css">
  <style>
    .hero-sub { font-family: var(--font-mono); font-size: 0.9rem; color: #fcfcfc; }
    .nav-link {
      font-family: var(--font-mono); font-size: 0.6rem; letter-spacing: 0.15em;
      color: #555; text-decoration: none; display: flex; align-items: center;
      gap: 0.5rem; transition: color var(--transition);
    }
    .nav-link:hover { color: var(--rust); }
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
      <span class="nav-tag">Cadastro</span>
      <div class="nav-dot"></div>
      <a href="index.php" class="nav-link">← Ver acervo</a>
      <div class="nav-dot"></div>
      <span class="nav-year"><?= date('Y') ?></span>
    </nav>
  </div>
  <div class="header-line"></div>
</header>

<main class="main">
  <div class="hero">
    <p class="hero-label">Sistema de acervo</p>
    <h1 class="hero-title">Cadas<em>tro</em></h1>
    <p class="hero-sub">Registre novos volumes no acervo da biblioteca.</p>
  </div>

  <?php include __DIR__ . '/src/template/cadastrar_livros.blade.php'; ?>
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
</body>
</html>