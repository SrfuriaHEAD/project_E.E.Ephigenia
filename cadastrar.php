<?php
session_start();

// ── CONFIG ───────────────────────────────────────────────────────────────────
define("GEMINI_KEY", "AIzaSyAdEbNjCWW-Q7OWNOQORbDzO3kbCc0ShiY");
define("BOOKS_KEY",  "");

$diretorio      = __DIR__ . '/db';
$arquivo        = $diretorio . '/banco.txt';
$arqEmprestimos = $diretorio . '/emprestimos.txt';

// ── AJAX: identificar capa ────────────────────────────────────────────────────
// CORREÇÃO: Lê o body JSON ANTES de qualquer checagem, nunca usa $_POST para requests JSON
$rawBody = file_get_contents("php://input");
$body    = !empty($rawBody) ? json_decode($rawBody, true) : null;

if (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    is_array($body) &&
    ($body['acao'] ?? '') === 'identificar_capa'
) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    $imagem = $body['imagem'] ?? '';

    if (!$imagem) {
        echo json_encode(['status' => 'erro', 'msg' => 'Imagem ausente.']);
        exit;
    }

    if (strpos($imagem, ',') !== false) {
        $imagem = explode(',', $imagem, 2)[1];
    }
    $imagem = preg_replace('/\s+/', '', $imagem);

    if (!base64_decode($imagem, true)) {
        echo json_encode(['status' => 'erro', 'msg' => 'Base64 inválido.']);
        exit;
    }

    $prompt =
        'This image shows the cover of a book. ' .
        'Identify the book and respond ONLY with pure JSON, no markdown, no extra text, ' .
        'exactly in this format: ' .
        '{"titulo":"...","autores":["..."],"editora":"...","ano":"..."} ' .
        'Use the original language for titulo. ' .
        'If you cannot identify the book, respond only: {"identificado":false}';

    [$texto, $erro] = chamarGemini($imagem, $prompt);

    if ($texto === null) {
        echo json_encode(['status' => 'erro_gemini', 'msg' => $erro]);
        exit;
    }

    $jsonLimpo = extraiJson($texto);
    $info      = json_decode($jsonLimpo, true);

    if (!$info || (isset($info['identificado']) && $info['identificado'] === false)) {
        echo json_encode(['status' => 'nao_identificado']);
        exit;
    }

    $titulo = trim($info['titulo'] ?? '');
    if (mb_strlen($titulo) < 2) {
        echo json_encode(['status' => 'sem_titulo']);
        exit;
    }

    $autor = trim(($info['autores'] ?? [])[0] ?? '');
    $livro = null;

    if ($autor) $livro = buscarBooks("intitle:$titulo inauthor:$autor");
    if (!$livro) $livro = buscarBooks("intitle:$titulo");
    if (!$livro) $livro = buscarBooks($titulo . ($autor ? " $autor" : ''));

    if (!$livro) {
        $livro = [
            'titulo'  => $titulo,
            'autores' => $info['autores'] ?? [],
            'editora' => $info['editora'] ?? '',
            'ano'     => $info['ano']     ?? '',
        ];
    }

    echo json_encode(['status' => 'encontrado', 'livro' => $livro], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── CADASTRO via POST normal ──────────────────────────────────────────────────
require __DIR__ . '/src/functions/cadastrar_livro.php';


// ══════════════════════════════════════════════════════════════════════════════
// FUNÇÕES GEMINI / BOOKS
// ══════════════════════════════════════════════════════════════════════════════

function chamarGemini(string $base64, string $prompt): array {
    if (!GEMINI_KEY || strlen(GEMINI_KEY) < 20) {
        return [null, 'Chave Gemini não configurada.'];
    }
    $payload = json_encode([
        'contents' => [[
            'parts' => [
                ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => $base64]],
                ['text' => $prompt]
            ]
        ]],
        'generationConfig' => ['temperature' => 0.05, 'maxOutputTokens' => 512]
    ]);
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . GEMINI_KEY;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp     = curl_exec($ch);
    $curlErro = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$resp) return [null, "curl error ($httpCode): $curlErro"];

    $data = json_decode($resp, true);
    if (isset($data['error'])) return [null, 'Gemini: ' . ($data['error']['message'] ?? 'erro')];

    $partes = $data['candidates'][0]['content']['parts'] ?? [];
    foreach ($partes as $p) {
        if (!empty($p['text'])) return [$p['text'], null];
    }
    return [null, 'Gemini não retornou texto.'];
}

function extraiJson(string $texto): string {
    $texto = trim(preg_replace(['/^```(?:json)?\s*/i', '/\s*```\s*$/m'], '', $texto));
    if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)?\}/s', $texto, $m)) return $m[0];
    return $texto;
}

function buscarBooks(string $query): ?array {
    if (mb_strlen(trim($query)) < 2) return null;
    $key = BOOKS_KEY ? '&key=' . BOOKS_KEY : '';
    $data = null;
    foreach (['&langRestrict=pt', ''] as $lang) {
        $url  = 'https://www.googleapis.com/books/v1/volumes?q=' . urlencode($query) . '&maxResults=1' . $lang . $key;
        $ctx  = stream_context_create(['http' => ['timeout' => 12]]);
        $resp = @file_get_contents($url, false, $ctx);
        if (!$resp) continue;
        $d = json_decode($resp, true);
        if (!empty($d['items'])) { $data = $d; break; }
    }
    if (empty($data['items'])) return null;

    $info  = $data['items'][0]['volumeInfo'] ?? [];
    $titulo = trim($info['title'] ?? '');
    if (mb_strlen($titulo) < 2) return null;

    $ano = null;
    if (!empty($info['publishedDate'])) {
        preg_match('/(\d{4})/', $info['publishedDate'], $m);
        $ano = $m[1] ?? null;
    }

    return [
        'titulo'  => $titulo,
        'autores' => array_values(array_filter($info['authors'] ?? [])),
        'editora' => $info['publisher'] ?? '',
        'ano'     => $ano ?? '',
    ];
}
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

    /* ── Tabs ── */
    .tabs { display: flex; gap: 0; margin-bottom: 2rem; border-bottom: 1px solid #1e1e1e; }
    .tab {
      background: none; border: none; border-bottom: 2px solid transparent;
      color: #000000; font-family: var(--font-mono); font-size: 0.75rem;
      letter-spacing: 0.15em; text-transform: uppercase;
      padding: 0.75rem 1.5rem; cursor: pointer;
      transition: all var(--transition); position: relative; bottom: -1px;
    }
    .tab:hover { color: #313131; }
    .tab.active { color: #313131; border-bottom-color: var(--rust); }
    .tab-panel { display: none; }
    .tab-panel.active { display: block; }

    /* ── Nova interface de câmera ── */
    .cam-wrapper {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 1rem;
    }

    /* Janela da câmera: proporção 9:16 (livros são retrato) */
    .cam-viewport {
      position: relative;
      width: 100%;
      max-width: 340px;
      aspect-ratio: 9 / 16;
      background: #050505;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 8px 40px rgba(0,0,0,0.6);
    }

    .cam-viewport video,
    .cam-viewport img.cam-preview-img {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .cam-viewport img.cam-preview-img { display: none; }

    /* Placeholder central */
    .cam-placeholder {
      position: absolute;
      inset: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 0.75rem;
      z-index: 2;
      background: #0a0a0a;
      cursor: pointer;
    }
    .cam-placeholder-icon { font-size: 3rem; }
    .cam-placeholder-text {
      font-family: var(--font-mono);
      font-size: 0.6rem;
      color: #555;
      letter-spacing: 0.15em;
      text-align: center;
      padding: 0 1rem;
    }

    /* Moldura guia da capa (enquadramento) */
    .cam-guide {
      position: absolute;
      inset: 12px;
      border: 1.5px dashed rgba(255,255,255,0.15);
      border-radius: 6px;
      pointer-events: none;
      z-index: 3;
    }
    /* Cantos da moldura */
    .cam-guide::before,
    .cam-guide::after {
      content: '';
      position: absolute;
      width: 18px; height: 18px;
      border-color: var(--rust);
      border-style: solid;
    }
    .cam-guide::before {
      top: -1px; left: -1px;
      border-width: 2px 0 0 2px;
      border-radius: 3px 0 0 0;
    }
    .cam-guide::after {
      bottom: -1px; right: -1px;
      border-width: 0 2px 2px 0;
      border-radius: 0 0 3px 0;
    }

    /* Barra inferior com botão circular — fica dentro do viewport */
    .cam-bottom-bar {
      position: absolute;
      bottom: 0; left: 0; right: 0;
      height: 110px;
      background: linear-gradient(to top, rgba(0,0,0,0.85) 60%, transparent);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 10;
    }

    /* Botão circular de captura */
    .btn-shutter {
      width: 68px; height: 68px;
      border-radius: 50%;
      background: transparent;
      border: 3px solid #fff;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer;
      transition: transform 0.15s, opacity 0.15s;
      position: relative;
    }
    .btn-shutter::after {
      content: '';
      width: 52px; height: 52px;
      border-radius: 50%;
      background: #fff;
      transition: background 0.15s, transform 0.15s;
    }
    .btn-shutter:active::after { background: #ccc; transform: scale(0.93); }
    .btn-shutter:disabled { opacity: 0.3; cursor: not-allowed; }
    .btn-shutter:disabled::after { background: #666; }

    /* Tela de confirmação (overlay) — aparece após capturar */
    .cam-confirm-overlay {
      position: absolute;
      inset: 0;
      background: rgba(0,0,0,0.6);
      display: none;
      flex-direction: column;
      align-items: center;
      justify-content: flex-end;
      padding-bottom: 24px;
      gap: 12px;
      z-index: 20;
      backdrop-filter: blur(2px);
    }
    .cam-confirm-overlay.visible { display: flex; }

    .cam-confirm-label {
      font-family: var(--font-mono);
      font-size: 0.6rem;
      color: rgba(255,255,255,0.7);
      letter-spacing: 0.15em;
      text-transform: uppercase;
      margin-bottom: 4px;
    }

    .cam-confirm-btns {
      display: flex;
      gap: 12px;
    }

    .btn-confirm {
      padding: 0.6rem 1.4rem;
      border-radius: 100px;
      font-family: var(--font-mono);
      font-size: 0.65rem;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      border: none;
      cursor: pointer;
      transition: opacity 0.15s, transform 0.1s;
    }
    .btn-confirm:active { transform: scale(0.96); }
    .btn-confirm.secondary {
      background: rgba(255,255,255,0.12);
      color: #fff;
      border: 1px solid rgba(255,255,255,0.25);
    }
    .btn-confirm.primary {
      background: var(--rust);
      color: #fff;
    }
    .btn-confirm.primary:hover { opacity: 0.88; }

    /* Controles externos (fora do viewport) */
    .cam-external-controls {
      display: flex;
      gap: 0.75rem;
      flex-wrap: wrap;
      justify-content: center;
      width: 100%;
      max-width: 340px;
    }

    .btn-cam {
      background: #111; border: 1px solid #2a2a2a; color: #aaa;
      font-family: var(--font-mono); font-size: 0.6rem; letter-spacing: 0.15em;
      text-transform: uppercase; padding: 0.5rem 1rem;
      cursor: pointer; transition: all var(--transition); border-radius: 4px;
    }
    .btn-cam:hover { border-color: var(--rust); color: var(--rust); }
    .btn-cam.primary { background: var(--rust); border-color: var(--rust); color: #fff; }
    .btn-cam.primary:hover { opacity: 0.88; }
    .btn-cam:disabled { opacity: 0.35; cursor: not-allowed; }

    .scan-status {
      font-family: var(--font-mono); font-size: 0.65rem; color: #888;
      min-height: 1.2rem; letter-spacing: 0.05em; text-align: center;
    }
    .scan-status.ok  { color: #4caf7d; }
    .scan-status.err { color: #cc4400; }

    /* ── Preview resultado Gemini ── */
    .gemini-result {
      display: none; background: #111; border: 1px solid #1e1e1e;
      border-left: 3px solid #4caf7d; padding: 1.25rem 1.5rem; margin-top: 1.25rem;
      width: 100%;
    }
    .gemini-result.visible { display: block; }
    .gemini-result-label {
      font-family: var(--font-mono); font-size: 0.5rem; letter-spacing: 0.25em;
      color: #4caf7d; text-transform: uppercase; margin-bottom: 0.75rem;
    }
    .gemini-book-title {
      font-family: var(--font-display); font-size: 1.1rem; font-weight: 700;
      color: #e0e0e0; margin-bottom: 0.25rem;
    }
    .gemini-book-meta {
      font-family: var(--font-mono); font-size: 0.65rem; color: #888;
    }
    .gemini-apply {
      margin-top: 1rem; display: flex; gap: 0.5rem; align-items: center;
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
      <span class="nav-tag">Cadastro</span>
      <a class="nav-link" href="index_alunos.php">Area Aluno</a>
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

  <!-- Abas -->
  <div class="tabs">
    <button class="tab active" data-tab="manual">✏ Manual</button>
    <button class="tab" data-tab="camera">📷 Identificar pela capa</button>
  </div>

  <!-- ── ABA MANUAL ─────────────────────────────────────────────────────── -->
  <div id="tab-manual" class="tab-panel active">
    <?php include __DIR__ . '/src/template/cadastrar_livros.blade.php'; ?>
  </div>

  <!-- ── ABA CÂMERA ────────────────────────────────────────────────────── -->
  <div id="tab-camera" class="tab-panel">
    <section class="form-section">

      <div class="form-card">
        <div class="form-card-header">
          <span class="form-card-number">01</span>
          <span class="form-card-title">IDENTIFICAR PELA CAPA</span>
        </div>

        <div class="cam-wrapper">

          <!-- Viewport 9:16 -->
          <div class="cam-viewport" id="cam-viewport">
            <video id="cam-video" autoplay playsinline muted style="display:none"></video>
            <img id="cam-preview" class="cam-preview-img" alt="preview">

            <!-- Placeholder -->
            <div class="cam-placeholder" id="cam-placeholder">
              <div class="cam-placeholder-icon">📷</div>
              <p class="cam-placeholder-text">TOQUE PARA ATIVAR A CÂMERA</p>
            </div>

            <!-- Moldura guia (visível só com câmera ativa) -->
            <div class="cam-guide" id="cam-guide" style="display:none"></div>

            <!-- Botão shutter (dentro do viewport, inferior) -->
            <div class="cam-bottom-bar" id="cam-bottom-bar" style="display:none">
              <button class="btn-shutter" id="btn-shutter" type="button" title="Capturar foto"></button>
            </div>

            <!-- Overlay de confirmação -->
            <div class="cam-confirm-overlay" id="cam-confirm">
              <p class="cam-confirm-label">Usar esta foto?</p>
              <div class="cam-confirm-btns">
                <button class="btn-confirm secondary" id="btn-retake" type="button">↺ Tirar outra</button>
                <button class="btn-confirm primary"    id="btn-scan"   type="button">🔍 Analisar com IA</button>
              </div>
            </div>
          </div><!-- /cam-viewport -->

          <!-- Controles externos -->
          <div class="cam-external-controls">
            <button class="btn-cam" id="btn-start-cam" type="button">Ativar câmera</button>
            <label class="btn-cam" style="cursor:pointer">
              📁 Enviar arquivo
              <input type="file" id="file-input" accept="image/*" style="display:none">
            </label>
          </div>

          <p class="scan-status" id="scan-status">—</p>

          <!-- Resultado Gemini -->
          <div class="gemini-result" id="gemini-result">
            <p class="gemini-result-label">✓ Livro identificado</p>
            <p class="gemini-book-title" id="g-titulo">—</p>
            <p class="gemini-book-meta"  id="g-meta">—</p>
            <div class="gemini-apply">
              <button class="btn-cam primary" id="btn-usar-dados" type="button">Usar estes dados →</button>
              <span style="font-family:var(--font-mono);font-size:0.6rem;color:#555">
                Preenche o formulário para você revisar antes de salvar.
              </span>
            </div>
          </div>

        </div><!-- /cam-wrapper -->

      </div><!-- /form-card -->

      <aside class="form-aside">
        <div class="aside-card">
          <div class="aside-icon">📖</div>
          <p class="aside-title">Como funciona</p>
          <ul class="aside-list">
            <li>Ative a câmera ou envie uma foto da capa.</li>
            <li>Enquadre a capa na moldura e toque no círculo.</li>
            <li>Confirme a foto ou tire outra.</li>
            <li>A IA preenche título, autor, editora e ano.</li>
            <li>Revise os dados e salve normalmente.</li>
          </ul>
        </div>
        <div class="aside-deco">
          <div class="deco-line"></div>
          <span class="deco-text">E.E. EPHIGENIA</span>
          <div class="deco-line"></div>
        </div>
      </aside>

    </section>
  </div><!-- /tab-camera -->

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