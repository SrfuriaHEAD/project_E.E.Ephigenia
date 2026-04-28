<!-- ═══════════════════════════════════════════════════════════════════
     SEÇÃO: Câmera para captura de capa (sem IA por enquanto)
════════════════════════════════════════════════════════════════════ -->

<section class="scan-section">

  <div class="scan-card" id="scanCard">

    <div class="scan-card-header">
      <span class="form-card-number">00</span>
      <span class="form-card-title">CAPTURAR CAPA DO LIVRO</span>
      <span class="scan-badge" style="background:#555">📷</span>
    </div>

    <p class="scan-desc">
      Tire uma foto da capa para referência visual. O formulário deve ser preenchido manualmente abaixo.
    </p>

    <!-- Área de câmera / preview -->
    <div class="camera-area" id="cameraArea">

      <!-- Estado inicial: botões de ação -->
      <div class="camera-idle" id="cameraIdle">
        <div class="camera-icon-wrap">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
            <circle cx="12" cy="13" r="4"/>
          </svg>
        </div>
        <p class="camera-hint">Câmera ou arquivo de imagem</p>
        <div class="scan-btn-group">
          <button class="btn-scan" type="button" id="btnCamera">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
              <circle cx="12" cy="13" r="4"/>
            </svg>
            Abrir câmera
          </button>
          <button class="btn-scan btn-scan--outline" type="button" id="btnUpload">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
              <polyline points="17 8 12 3 7 8"/>
              <line x1="12" y1="3" x2="12" y2="15"/>
            </svg>
            Enviar imagem
          </button>
        </div>
        <input type="file" id="fileInput" accept="image/*" style="display:none">
      </div>

      <!-- Stream da câmera ao vivo -->
      <div class="camera-live" id="cameraLive" style="display:none">
        <video id="videoEl" autoplay playsinline muted></video>
        <div class="camera-overlay">
          <div class="camera-frame"></div>
        </div>
        <div class="camera-controls">
          <button class="btn-capture" type="button" id="btnCapture" title="Capturar">
            <span class="capture-ring"></span>
          </button>
          <button class="btn-scan btn-scan--outline btn-sm" type="button" id="btnCancelCamera">Cancelar</button>
        </div>
      </div>

      <!-- Preview da imagem capturada -->
      <div class="camera-preview" id="cameraPreview" style="display:none">
        <img id="previewImg" src="" alt="Capa capturada">
        <canvas id="captureCanvas" style="display:none"></canvas>
        <div class="preview-actions">
          <button class="btn-scan btn-scan--outline btn-sm" type="button" id="btnRetake">Tirar outra foto</button>
        </div>
      </div>

    </div><!-- /camera-area -->

  </div><!-- /scan-card -->

</section>

<section class="form-section" id="formSection">

  <div class="form-card">
    <div class="form-card-header">
      <span class="form-card-number">01</span>
      <span class="form-card-title">REGISTRAR NOVO VOLUME</span>
    </div>

    <form class="form" method="POST" action="">
      <input type="hidden" name="acao" value="registrar_livro">

      <div class="field-group">
        <label class="field-label" for="f-nome">Título do livro</label>
        <div class="field-wrap">
          <input class="field-input" type="text" id="f-nome" name="nome"
                 placeholder="ex: Dom Casmurro" required autocomplete="off">
          <span class="field-bar"></span>
        </div>
      </div>

      <div class="field-group">
        <label class="field-label" for="f-autor">Autor(es)</label>
        <div class="field-wrap">
          <input class="field-input" type="text" id="f-autor" name="autor"
                 placeholder="ex: Machado de Assis" autocomplete="off">
          <span class="field-bar"></span>
        </div>
      </div>

      <div style="display:flex;gap:1rem;flex-wrap:wrap">
        <div class="field-group" style="flex:2;min-width:160px">
          <label class="field-label" for="f-editora">Editora</label>
          <div class="field-wrap">
            <input class="field-input" type="text" id="f-editora" name="editora"
                   placeholder="ex: Companhia das Letras" autocomplete="off">
            <span class="field-bar"></span>
          </div>
        </div>

        <div class="field-group" style="flex:1;min-width:100px">
          <label class="field-label" for="f-ano">Ano</label>
          <div class="field-wrap">
            <input class="field-input" type="number" id="f-ano" name="ano"
                   placeholder="ex: 2019" min="1000" max="2099" autocomplete="off">
            <span class="field-bar"></span>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:1rem;flex-wrap:wrap">
        <div class="field-group" style="flex:1;min-width:130px">
          <label class="field-label" for="f-quantidade">Qtd. de exemplares</label>
          <div class="field-wrap">
            <input class="field-input" type="number" id="f-quantidade" name="quantidade"
                   placeholder="ex: 3" required min="1">
            <span class="field-bar"></span>
          </div>
        </div>

        <div class="field-group" style="flex:2;min-width:160px">
          <label class="field-label" for="f-prateleira">Prateleira</label>
          <div class="field-wrap">
            <select class="field-input" id="f-prateleira" name="prateleira"
                    style="appearance:none;-webkit-appearance:none;cursor:pointer">
              <option value="">— Selecionar —</option>
              <optgroup label="Prateleira 1">
                <option value="1A">1A</option>
                <option value="1B">1B</option>
                <option value="1C">1C</option>
                <option value="1D">1D</option>
                <option value="1E">1E</option>
              </optgroup>
              <optgroup label="Prateleira 2">
                <option value="2A">2A</option>
                <option value="2B">2B</option>
                <option value="2C">2C</option>
                <option value="2D">2D</option>
                <option value="2E">2E</option>
              </optgroup>
              <optgroup label="Prateleira 3">
                <option value="3A">3A</option>
                <option value="3B">3B</option>
                <option value="3C">3C</option>
                <option value="3D">3D</option>
                <option value="3E">3E</option>
              </optgroup>
              <optgroup label="Prateleira 4">
                <option value="4A">4A</option>
                <option value="4B">4B</option>
                <option value="4C">4C</option>
                <option value="4D">4D</option>
                <option value="4E">4E</option>
              </optgroup>
            </select>
            <span class="field-bar"></span>
          </div>
        </div>

        <div class="field-group" style="flex:2;min-width:200px">
          <label class="field-label" for="f-faixa-etaria">Classificação / Gênero</label>
          <div class="field-wrap">
            <select class="field-input" id="f-faixa-etaria" name="faixa_etaria"
                    style="appearance:none;-webkit-appearance:none;cursor:pointer">
              <option value="">— Selecionar —</option>
              <option value="1A">1A — Infanto Juvenil</option>
              <option value="2A">2A — Conto</option>
              <option value="3A">3A — Ficção Científica</option>
              <option value="4A">4A — Romance</option>
              <option value="5A">5A — Literatura Brasileira</option>
              <option value="6A">6A — Poesia</option>
              <option value="guerra">Guerra</option>
              <option value="TODOS">Todas as idades</option>
            </select>
            <span class="field-bar"></span>
          </div>
        </div>
      </div>

      <div class="form-actions">
        <button class="btn-submit" type="submit">
          <span class="btn-text">Registrar volume</span>
          <span class="btn-icon">→</span>
        </button>
      </div>
    </form>
  </div>

  <aside class="form-aside">
    <div class="aside-card">
      <div class="aside-icon">📋</div>
      <p class="aside-title">Instruções</p>
      <ul class="aside-list">
        <li>O título deve ser único no acervo.</li>
        <li>Autor, editora e ano são opcionais.</li>
        <li>Prateleira e faixa etária são opcionais.</li>
        <li>Quantidade mínima: 1 exemplar.</li>
        <li>Use a câmera acima para preencher automaticamente.</li>
      </ul>
    </div>
    <div class="aside-deco">
      <div class="deco-line"></div>
      <span class="deco-text">E.E. EPHIGENIA</span>
      <div class="deco-line"></div>
    </div>
  </aside>

</section>


<!-- ═══════════════════════════════════════════════════════════════════
     CSS adicional para o módulo de scan
════════════════════════════════════════════════════════════════════ -->
<style>
/* ── Scan Section ─────────────────────────────────────────────────── */
.scan-section {
  max-width: 900px;
  margin: 0 auto 2rem;
  padding: 0 1.5rem;
}

.scan-card {
  background: var(--surface, #fff);
  border: 1.5px solid var(--border, #e0e0e0);
  border-radius: 2px;
  padding: 2rem;
  position: relative;
  overflow: hidden;
}

.scan-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: linear-gradient(90deg, #111 0%, #555 100%);
}

.scan-card-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.scan-badge {
  background: #111;
  color: #fff;
  font-family: var(--font-mono, monospace);
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  padding: 0.15rem 0.5rem;
  border-radius: 2px;
  margin-left: auto;
}

.scan-desc {
  font-size: 0.85rem;
  color: #555;
  margin-bottom: 1.5rem;
  line-height: 1.5;
}

/* ── Camera Area ──────────────────────────────────────────────────── */
.camera-area {
  border: 1.5px dashed var(--border, #ccc);
  border-radius: 2px;
  min-height: 220px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--bg-subtle, #fafafa);
  position: relative;
  overflow: hidden;
  transition: border-color 0.2s;
}

.camera-idle {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  padding: 2rem;
  text-align: center;
}

.camera-icon-wrap {
  opacity: 0.35;
  margin-bottom: 0.25rem;
}

.camera-hint {
  font-family: var(--font-mono, monospace);
  font-size: 0.75rem;
  color: #888;
  letter-spacing: 0.05em;
}

.scan-btn-group {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
  justify-content: center;
  margin-top: 0.5rem;
}

/* ── Live Camera ──────────────────────────────────────────────────── */
.camera-live {
  width: 100%;
  position: relative;
}

.camera-live video {
  width: 100%;
  max-height: 400px;
  object-fit: cover;
  display: block;
}

.camera-overlay {
  position: absolute;
  inset: 0;
  pointer-events: none;
  display: flex;
  align-items: center;
  justify-content: center;
}

.camera-frame {
  width: 60%;
  aspect-ratio: 2/3;
  border: 2px solid rgba(255,255,255,0.8);
  border-radius: 4px;
  box-shadow: 0 0 0 9999px rgba(0,0,0,0.35);
}

.camera-controls {
  position: absolute;
  bottom: 1rem;
  left: 0; right: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
}

.btn-capture {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: #fff;
  border: 3px solid #111;
  cursor: pointer;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: transform 0.1s;
}

.btn-capture:active { transform: scale(0.93); }

.capture-ring {
  display: block;
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: #111;
  transition: background 0.15s;
}

.btn-capture:hover .capture-ring { background: #444; }

/* ── Preview ──────────────────────────────────────────────────────── */
.camera-preview {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
}

.camera-preview img {
  max-height: 320px;
  max-width: 100%;
  object-fit: contain;
  border: 1px solid var(--border, #ddd);
  border-radius: 2px;
}

.preview-actions {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
  justify-content: center;
}

/* ── Loading ──────────────────────────────────────────────────────── */
.camera-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  padding: 2.5rem;
}

.scan-spinner {
  width: 36px;
  height: 36px;
  border: 3px solid #e0e0e0;
  border-top-color: #111;
  border-radius: 50%;
  animation: spinIt 0.7s linear infinite;
}

@keyframes spinIt { to { transform: rotate(360deg); } }

.loading-text {
  font-family: var(--font-mono, monospace);
  font-size: 0.85rem;
  font-weight: 500;
  color: #111;
}

.loading-sub {
  font-size: 0.75rem;
  color: #888;
}

/* ── Buttons ──────────────────────────────────────────────────────── */
.btn-scan {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.55rem 1.1rem;
  background: #111;
  color: #fff;
  font-family: var(--font-mono, monospace);
  font-size: 0.78rem;
  font-weight: 500;
  letter-spacing: 0.04em;
  border: 1.5px solid #111;
  border-radius: 2px;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}

.btn-scan:hover { background: #333; border-color: #333; }

.btn-scan--outline {
  background: transparent;
  color: #111;
}

.btn-scan--outline:hover { background: #f0f0f0; }

.btn-sm { padding: 0.4rem 0.85rem; font-size: 0.72rem; }

/* ── Result ───────────────────────────────────────────────────────── */
.scan-result {
  margin-top: 1.5rem;
  border: 1.5px solid #111;
  border-radius: 2px;
  overflow: hidden;
  animation: fadeUp 0.35s ease;
}

@keyframes fadeUp {
  from { opacity: 0; transform: translateY(8px); }
  to   { opacity: 1; transform: translateY(0); }
}

.result-header {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.75rem 1rem;
  background: #111;
  color: #fff;
  font-family: var(--font-mono, monospace);
  font-size: 0.8rem;
  letter-spacing: 0.05em;
}

.result-icon { font-size: 1rem; }

.result-confidence {
  margin-left: auto;
  font-size: 0.68rem;
  opacity: 0.7;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.result-fields {
  padding: 1rem 1.25rem;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 0.75rem 1.5rem;
}

.rf-item { display: flex; flex-direction: column; gap: 0.2rem; }

.rf-label {
  font-family: var(--font-mono, monospace);
  font-size: 0.65rem;
  letter-spacing: 0.1em;
  color: #888;
  text-transform: uppercase;
}

.rf-value {
  font-size: 0.9rem;
  font-weight: 500;
  color: #111;
}

.result-actions {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
  padding: 0.75rem 1.25rem 1.25rem;
  border-top: 1px solid #e0e0e0;
}

/* ── Error ────────────────────────────────────────────────────────── */
.scan-error {
  margin-top: 1.5rem;
  padding: 1.25rem;
  border: 1.5px solid #e00;
  border-radius: 2px;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  background: #fff5f5;
  animation: fadeUp 0.3s ease;
}

.error-icon {
  font-size: 1.1rem;
  color: #e00;
  flex-shrink: 0;
}

#scanErrorMsg {
  font-size: 0.85rem;
  color: #c00;
  flex: 1;
}

/* ── Highlight when form is filled ───────────────────────────────── */
.field-input.ia-filled {
  border-color: #111 !important;
  background: #f8f8f0 !important;
  transition: background 0.4s;
}
</style>


<!-- ═══════════════════════════════════════════════════════════════════

<!-- ═══════════════════════════════════════════════════════════════════
     JavaScript do módulo de câmera (sem IA)
════════════════════════════════════════════════════════════════════ -->
<script>
(function() {
  const cameraIdle    = document.getElementById('cameraIdle');
  const cameraLive    = document.getElementById('cameraLive');
  const cameraPreview = document.getElementById('cameraPreview');
  const videoEl       = document.getElementById('videoEl');
  const captureCanvas = document.getElementById('captureCanvas');
  const previewImg    = document.getElementById('previewImg');
  const fileInput     = document.getElementById('fileInput');

  let stream = null;

  function show(el) { el.style.display = ''; }
  function hide(el) { el.style.display = 'none'; }

  function setView(view) {
    hide(cameraIdle); hide(cameraLive); hide(cameraPreview);
    if (view) show(view);
  }

  function stopStream() {
    if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
  }

  document.getElementById('btnCamera').addEventListener('click', async () => {
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 } }
      });
      videoEl.srcObject = stream;
      setView(cameraLive);
    } catch (e) {
      alert('Não foi possível acessar a câmera: ' + e.message);
    }
  });

  document.getElementById('btnUpload').addEventListener('click', () => fileInput.click());

  fileInput.addEventListener('change', () => {
    const file = fileInput.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
      previewImg.src = e.target.result;
      setView(cameraPreview);
    };
    reader.readAsDataURL(file);
  });

  document.getElementById('btnCapture').addEventListener('click', () => {
    const w = videoEl.videoWidth, h = videoEl.videoHeight;
    captureCanvas.width = w; captureCanvas.height = h;
    captureCanvas.getContext('2d').drawImage(videoEl, 0, 0, w, h);
    previewImg.src = captureCanvas.toDataURL('image/jpeg', 0.92);
    stopStream();
    setView(cameraPreview);
  });

  document.getElementById('btnCancelCamera').addEventListener('click', () => {
    stopStream(); setView(cameraIdle);
  });

  document.getElementById('btnRetake').addEventListener('click', () => {
    fileInput.value = '';
    setView(cameraIdle);
  });

})();
</script>