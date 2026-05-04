<?php
session_start();

$diretorio      = __DIR__ . '/db';
$arquivo        = $diretorio . '/banco.txt';
$arqEmprestimos = $diretorio . '/emprestimos.txt';

require __DIR__ . '/src/functions/cadastrar_livro.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cadastrar — Biblioteca E.E. Ephigenia</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="src/static/style.css">
  <style>
    .hero-sub { font-family: var(--font-mono); font-size: 0.9rem; color: #000000; }
    .nav-link:hover { color: #313131; }
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
      <span class="nav-tag">Cadastro</span>
      <!-- <a class="nav-link" href="index_alunos.php">Area Aluno</a> -->
      <div class="nav-dot"></div>
      <a href="acervo.php" class="nav-link">← Ver acervo</a>
      <div class="nav-dot"></div>
      <a href="dashboard.php" class="nav-link">Painel de Controle</a>
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
    <span class="footer-school">E.E. Ephigenia</span>
    <span class="footer-sep">/</span>
    <span class="footer-text">Sistema de Biblioteca</span>
    <span class="footer-sep">/</span>
    <span class="footer-text">Desenvolvido por Arthur A. 2 Reg 3</span>
    <span class="footer-text" style="flex: 1; text-align: right; color: #ffffff;">&copy; <?= date('Y') ?> Todos os direitos reservados</span>
    <span class="nav-year"><?= date('Y') ?></span>
  </div>
</footer>

<script>
(function () {
  'use strict';

  // ── Tabs ──────────────────────────────────────────────────────────────────
  document.querySelectorAll('.tab').forEach(btn => {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
      this.classList.add('active');
      document.getElementById('tab-' + this.dataset.tab).classList.add('active');
    });
  });

  // ── Estado câmera ─────────────────────────────────────────────────────────
  let stream      = null;
  let capturedB64 = null;
  let capturedFull= null;
  let livroGemini = null;

  const video       = document.getElementById('cam-video');
  const preview     = document.getElementById('cam-preview');
  const placeholder = document.getElementById('cam-placeholder');
  const camGuide    = document.getElementById('cam-guide');
  const bottomBar   = document.getElementById('cam-bottom-bar');
  const confirmOvl  = document.getElementById('cam-confirm');
  const btnShutter  = document.getElementById('btn-shutter');
  const btnStart    = document.getElementById('btn-start-cam');
  const btnRetake   = document.getElementById('btn-retake');
  const btnScan     = document.getElementById('btn-scan');
  const fileInput   = document.getElementById('file-input');
  const statusEl    = document.getElementById('scan-status');
  const resultBox   = document.getElementById('gemini-result');

  function setStatus(msg, cls = '') {
    statusEl.textContent = msg;
    statusEl.className   = 'scan-status ' + cls;
  }

  // Toque no placeholder ativa câmera
  placeholder.addEventListener('click', () => {
    if (!stream && !capturedB64) ativarCamera();
  });

  btnStart.addEventListener('click', () => {
    if (stream) {
      pararCamera();
    } else {
      ativarCamera();
    }
  });

  async function ativarCamera() {
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'environment', aspectRatio: 9/16 },
        audio: false
      });
      video.srcObject = stream;
      video.style.display = 'block';
      placeholder.style.display = 'none';
      preview.style.display = 'none';
      camGuide.style.display = 'block';
      bottomBar.style.display = 'flex';
      confirmOvl.classList.remove('visible');
      btnShutter.disabled = false;
      btnStart.textContent = '⏹ Parar câmera';
      setStatus('Enquadre a capa e toque no círculo.');
    } catch (e) {
      setStatus('Não foi possível acessar a câmera: ' + e.message, 'err');
    }
  }

  function pararCamera() {
    if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
    video.srcObject = null;
    video.style.display = 'none';
    placeholder.style.display = 'flex';
    camGuide.style.display = 'none';
    bottomBar.style.display = 'none';
    confirmOvl.classList.remove('visible');
    btnStart.textContent = 'Ativar câmera';
    setStatus('—');
  }

  // Botão shutter → captura e mostra overlay de confirmação
  btnShutter.addEventListener('click', () => {
    const canvas = document.createElement('canvas');
    // Captura na proporção 9:16 centralizada
    const vw = video.videoWidth  || 640;
    const vh = video.videoHeight || 1138;
    canvas.width  = vw;
    canvas.height = vh;
    canvas.getContext('2d').drawImage(video, 0, 0, vw, vh);
    capturedFull = canvas.toDataURL('image/jpeg', 0.88);
    capturedB64  = capturedFull.split(',')[1];

    // Mostra preview por baixo do overlay
    preview.src = capturedFull;
    preview.style.display = 'block';
    // Mantém vídeo visível até confirmar
    confirmOvl.classList.add('visible');
    setStatus('Use o botão para analisar ou tire outra foto.');
  });

  // "Tirar outra" — descarta e reativa câmera
  btnRetake.addEventListener('click', () => {
    capturedB64  = null;
    capturedFull = null;
    livroGemini  = null;
    preview.style.display = 'none';
    preview.src  = '';
    confirmOvl.classList.remove('visible');
    resultBox.classList.remove('visible');
    setStatus('Enquadre a capa e toque no círculo.');
    // Câmera já continua rodando
  });

  // Upload de arquivo
  fileInput.addEventListener('change', () => {
    const file = fileInput.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
      capturedFull = e.target.result;
      capturedB64  = capturedFull.split(',')[1];
      // Mostra preview + overlay de confirmação
      preview.src = capturedFull;
      preview.style.display = 'block';
      placeholder.style.display = 'none';
      camGuide.style.display = 'none';
      bottomBar.style.display = 'none';
      confirmOvl.classList.add('visible');
      if (stream) pararCamera();
      else { video.style.display = 'none'; }
      setStatus('Imagem carregada. Confirme ou envie outra.');
    };
    reader.readAsDataURL(file);
  });

  // ── Identificar via Gemini ────────────────────────────────────────────────
  btnScan.addEventListener('click', async () => {
    if (!capturedB64) return;

    // Fecha overlay e mostra loading
    confirmOvl.classList.remove('visible');
    resultBox.classList.remove('visible');
    setStatus('Analisando capa com IA…');

    try {
      const res = await fetch('cadastrar.php', {
        method:  'POST',
        headers: {
          'Content-Type':     'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ acao: 'identificar_capa', imagem: capturedB64 })
      });

      // Garante que o body é JSON puro antes de parsear
      const text = await res.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch (_) {
        setStatus('Resposta inválida do servidor. Verifique os logs PHP.', 'err');
        console.error('Resposta bruta:', text);
        return;
      }

      if (data.status === 'encontrado') {
        livroGemini = data.livro;
        document.getElementById('g-titulo').textContent = livroGemini.titulo || '—';
        const autores = (livroGemini.autores || []).join(', ') || '—';
        const editora = livroGemini.editora || '';
        const ano     = livroGemini.ano     || '';
        document.getElementById('g-meta').textContent =
          autores + (editora ? ' · ' + editora : '') + (ano ? ' · ' + ano : '');
        resultBox.classList.add('visible');
        setStatus('Livro identificado com sucesso!', 'ok');
      } else if (data.status === 'nao_identificado') {
        setStatus('Não foi possível identificar o livro. Tente outra foto.', 'err');
        confirmOvl.classList.add('visible'); // permite tentar outra foto
      } else {
        setStatus('Erro: ' + (data.msg || data.status), 'err');
      }
    } catch (e) {
      setStatus('Erro de comunicação: ' + e.message, 'err');
    }
  });

  // ── Preencher formulário manual com dados da IA ───────────────────────────
  document.getElementById('btn-usar-dados').addEventListener('click', () => {
    if (!livroGemini) return;

    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelector('[data-tab="manual"]').classList.add('active');
    document.getElementById('tab-manual').classList.add('active');

    setField('f-nome',    livroGemini.titulo || '');
    setField('f-autor',   (livroGemini.autores || []).join(', '));
    setField('f-editora', livroGemini.editora || '');
    setField('f-ano',     livroGemini.ano     || '');
  });

  function setField(id, val) {
    const el = document.getElementById(id);
    if (el) el.value = val;
  }

})();
</script>
</body>
</html>