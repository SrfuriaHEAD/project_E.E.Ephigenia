<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>E.E. Ephigenia de Jesus Werneck — Página Inicial</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=DM+Mono:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="src/static/style.css">
  <style>

    html, body {
    max-width: 100vw;
    overflow-x: hidden;
    }

    /* ── Hero Banner ── */
    .home-hero {
      position: relative;
      height: 520px;
      overflow: hidden;
      border-radius: var(--radius);
      margin-bottom: 2rem;
      box-shadow: var(--shadow-md);
    }

    .hero-slides {
      display: flex;
      height: 100%;
      transition: transform 0.7s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .hero-slide {
      flex-shrink: 0;
      width: 100%;
      height: 100%;
      position: relative;
    }

    .hero-slide img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    .hero-slide-overlay {
      position: absolute;
      inset: 0;
      background: linear-gradient(
        135deg,
        rgba(26, 75, 140, 0.78) 0%,
        rgba(18, 54, 122, 0.45) 50%,
        rgba(0,0,0,0.25) 100%
      );
    }

    .hero-slide-content {
      position: absolute;
      inset: 0;
      display: flex;
      flex-direction: column;
      justify-content: flex-end;
      padding: 2.5rem 3rem;
      gap: 0.4rem;
    }

    .hero-slide-tag {
      font-family: var(--font-mono);
      font-size: 0.55rem;
      letter-spacing: 0.35em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.7);
    }

    .hero-slide-title {
      font-family: var(--font-display);
      font-size: clamp(1.6rem, 4vw, 2.6rem);
      font-weight: 900;
      color: #fff;
      line-height: 1.1;
      max-width: 600px;
    }

    .hero-controls {
      position: absolute;
      bottom: 1.5rem;
      right: 2rem;
      display: flex;
      gap: 0.5rem;
      align-items: center;
    }

    .hero-dot {
      width: 8px; height: 8px;
      border-radius: 50%;
      background: rgba(255,255,255,0.4);
      border: none;
      cursor: pointer;
      transition: all 0.2s ease;
      padding: 0;
    }
    .hero-dot.active {
      background: #fff;
      width: 24px;
      border-radius: 4px;
    }

    .hero-arrow {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      background: rgba(255,255,255,0.15);
      border: 1px solid rgba(255,255,255,0.3);
      color: #fff;
      font-size: 1.2rem;
      width: 44px; height: 44px;
      border-radius: 50%;
      cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      transition: background 0.2s ease;
      backdrop-filter: blur(4px);
    }
    .hero-arrow:hover { background: rgba(255,255,255,0.3); }
    .hero-arrow.prev { left: 1.25rem; }
    .hero-arrow.next { right: 1.25rem; }

    /* ── CTA contato (movido para cima) ── */
    .cta-strip {
      background: var(--blue);
      border-radius: var(--radius);
      padding: 2rem 2.5rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1.5rem;
      flex-wrap: wrap;
      margin-bottom: 2rem;
      box-shadow: var(--shadow-md);
    }
    .cta-text-block { flex: 1; min-width: 200px; }
    .cta-eyebrow {
      font-family: var(--font-mono);
      font-size: 0.5rem;
      letter-spacing: 0.3em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.6);
      margin-bottom: 0.3rem;
    }
    .cta-heading {
      font-family: var(--font-display);
      font-size: 1.6rem;
      font-weight: 900;
      color: #fff;
      line-height: 1.1;
    }
    .cta-links {
      display: flex;
      flex-direction: column;
      gap: 0.6rem;
    }
    .cta-link-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      text-decoration: none;
    }
    .cta-link-icon {
      width: 32px; height: 32px;
      border-radius: var(--radius);
      background: rgba(255,255,255,0.15);
      display: flex; align-items: center; justify-content: center;
      font-size: 0.9rem;
      flex-shrink: 0;
      transition: background 0.2s ease;
    }
    .cta-link-item:hover .cta-link-icon { background: rgba(255,255,255,0.3); }
    .cta-link-text {
      font-family: var(--font-mono);
      font-size: 0.8rem;
      color: rgba(255,255,255,0.9);
      transition: color 0.2s ease;
    }
    .cta-link-item:hover .cta-link-text { color: #fff; }

    /* ── Info grid ── */
    .info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 1px;
      background: var(--border);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      box-shadow: var(--shadow-sm);
      margin-bottom: 2.5rem;
    }
    .info-cell {
      background: var(--bg-card);
      padding: 1.75rem 1.5rem;
      transition: background var(--transition);
    }
    .info-cell:hover { background: var(--blue-muted); }
    .info-cell-icon { font-size: 1.4rem; margin-bottom: 0.75rem; }
    .info-cell-label {
      font-family: var(--font-mono);
      font-size: 0.5rem;
      letter-spacing: 0.25em;
      text-transform: uppercase;
      color: var(--blue);
      margin-bottom: 0.4rem;
    }
    .info-cell-value {
      font-family: var(--font-display);
      font-size: 1rem;
      font-weight: 700;
      color: var(--text-main);
      line-height: 1.3;
    }
    .info-cell-sub {
      font-family: var(--font-mono);
      font-size: 0.7rem;
      color: var(--text-muted);
      margin-top: 0.3rem;
    }

    /* ── Section heading ── */
    .section-heading {
      display: flex;
      align-items: center;
      gap: 1.5rem;
      margin-bottom: 1.75rem;
    }
    .section-heading-label {
      font-family: var(--font-mono);
      font-size: 0.55rem;
      letter-spacing: 0.3em;
      text-transform: uppercase;
      color: var(--blue);
      white-space: nowrap;
    }
    .section-heading-line { flex: 1; height: 1px; background: var(--border); }
    .section-heading-title {
      font-family: var(--font-display);
      font-size: 1.5rem;
      font-weight: 900;
      color: var(--text-main);
    }

    /* ── Galeria: grid misto vídeo + foto ── */
    .media-section { margin-bottom: 3rem; }

    .media-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 1.25rem;
    }

    /* Card de vídeo autoplay */
    .video-card {
      border-radius: var(--radius);
      overflow: hidden;
      box-shadow: var(--shadow-sm);
      background: #000;
      position: relative;
    }
    .video-card video {
      width: 100%;
      height: 220px;
      object-fit: cover;
      display: block;
    }
    .video-card-label {
      position: absolute;
      top: 0.75rem;
      left: 0.75rem;
      background: var(--danger);
      color: #fff;
      font-family: var(--font-mono);
      font-size: 0.5rem;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      padding: 0.25rem 0.6rem;
      border-radius: 2px;
    }
    .video-card-body {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-top: none;
      padding: 0.9rem 1.25rem;
    }
    .video-card-title {
      font-family: var(--font-display);
      font-size: 0.9rem;
      font-weight: 700;
      color: var(--text-main);
      line-height: 1.35;
      margin-bottom: 0.3rem;
    }
    .video-card-meta {
      font-family: var(--font-mono);
      font-size: 0.6rem;
      color: var(--text-muted);
    }

    /* Card de foto */
    .media-card {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      box-shadow: var(--shadow-sm);
      transition: box-shadow var(--transition), transform var(--transition);
    }
    .media-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
    .media-card-thumb {
      position: relative;
      height: 220px;
      overflow: hidden;
      background: var(--surface);
    }
    .media-card-thumb img {
      width: 100%; height: 100%;
      object-fit: cover; display: block;
      transition: transform 0.4s ease;
    }
    .media-card:hover .media-card-thumb img { transform: scale(1.04); }
    .media-card-type {
      position: absolute;
      top: 0.75rem; left: 0.75rem;
      background: var(--blue);
      color: #fff;
      font-family: var(--font-mono);
      font-size: 0.5rem;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      padding: 0.25rem 0.6rem;
      border-radius: 2px;
    }
    .media-card-body { padding: 1rem 1.25rem; }
    .media-card-title {
      font-family: var(--font-display);
      font-size: 0.9rem;
      font-weight: 700;
      color: var(--text-main);
      line-height: 1.35;
      margin-bottom: 0.4rem;
    }
    .media-card-meta {
      font-family: var(--font-mono);
      font-size: 0.6rem;
      color: var(--text-muted);
    }

    /* ── Horários ── */
    .schedule-section { margin-bottom: 3rem; }
    .schedule-table {
      width: 100%;
      border-collapse: collapse;
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      box-shadow: var(--shadow-sm);
    }
    .schedule-table thead tr { background: var(--blue); color: #fff; }
    .schedule-table th {
      font-family: var(--font-mono);
      font-size: 0.55rem;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      padding: 1rem 1.5rem;
      text-align: left;
      font-weight: 500;
    }
    .schedule-table td {
      font-family: var(--font-mono);
      font-size: 0.8rem;
      color: var(--text-main);
      padding: 0.85rem 1.5rem;
      border-bottom: 1px solid var(--surface);
    }
    .schedule-table tbody tr:last-child td { border-bottom: none; }
    .schedule-table tbody tr:hover td { background: var(--blue-muted); }
    .day-name { font-weight: 500; color: var(--text-main); }
    .day-closed { color: var(--danger); font-style: italic; }
    .day-note { font-size: 0.6rem; color: var(--text-muted); display: block; margin-top: 0.2rem; }
    .schedule-time { font-family: var(--font-mono); font-size: 0.85rem; color: var(--blue); font-weight: 500; }


    .media-card-destaque {
      grid-column: 1 / -1; /* Faz o card ocupar a linha inteira da grid */
    }
    
    .media-card-jardim-thumb {
  position: relative;
  height: 400px;
  overflow: hidden;
  background-color: #1a4b8c; /* Cor de fundo para as laterais */
}

.media-card-jardim-thumb img {
  width: 100%;
  height: 100%;
  object-fit: contain; /* Mostra a foto inteira sem zoom */
  display: block;
}

    .logo {
      width: 98px;
      height: 98px;
      border-radius: 30%;
      object-fit: cover;
      margin-left: 1.5rem;
    }
    /* ── Responsive ── */
    @media (max-width: 900px) {
      .info-grid { grid-template-columns: 1fr 1fr; }
      .cta-strip { flex-direction: column; align-items: flex-start; }
    }
    @media (max-width: 600px) {
      .home-hero { height: 340px; }
      .hero-slide-content { padding: 1.5rem; }
      .info-grid { grid-template-columns: 1fr; }
      .schedule-table th, .schedule-table td { padding: 0.75rem 1rem; }
    }
  </style>
</head>
<body>

  <div class="noise"></div>

  <header class="header">
    <div class="header-inner">
      <div class="logo-block">
        <span class="logo-pre">Escola Estadual</span>
        <span class="logo-name">Ephigenia de Jesus Werneck</span>
      </div>
      <img class="logo" src="src/static/logo.png" alt="Formatura dos alunos" />
      <nav class="header-nav">
        <span class="nav-tag">Santa Luzia · MG</span>
        <span class="nav-dot"></span>
        <a href="acervo.php" class="nav-link">📚 Biblioteca</a>
        <span class="nav-dot"></span>
        <span class="nav-year"><?= date('Y') ?></span>
      </nav>
    </div>
    <div class="header-line"></div>
  </header>

  <main class="main">

    <!-- ── Hero Carousel ── -->
    <section class="home-hero" id="hero">
      <div class="hero-slides" id="heroSlides">

        <div class="hero-slide">
          <img src="src/static/formatura_1.png" alt="Formatura dos alunos do Ensino Médio" />
          <div class="hero-slide-overlay"></div>
          <div class="hero-slide-content">
            <span class="hero-slide-tag">12 · 12 · 2025 — Evento Especial</span>
            <h2 class="hero-slide-title">Formatura dos Alunos do Ensino Médio</h2>
          </div>
        </div>

        <div class="hero-slide">
          <img src="src/static/volei_1.png" alt="Jogos Escolares de Minas Gerais" />
          <div class="hero-slide-overlay"></div>
          <div class="hero-slide-content">
            <span class="hero-slide-tag">Esporte Escolar — Destaque</span>
            <h2 class="hero-slide-title">Abertura dos Jogos Escolares de Minas Gerais em Santa Luzia</h2>
          </div>
        </div>

        <div class="hero-slide">
          <img src="src/static/volei_2.png" alt="Nossa equipe nos Jogos Escolares" />
          <div class="hero-slide-overlay"></div>
          <div class="hero-slide-content">
            <span class="hero-slide-tag">Jogos Escolares — Nossa Equipe</span>
            <h2 class="hero-slide-title">Competência e Dedicação dentro e fora de sala de aula</h2>
          </div>
        </div>

      </div>

      <button class="hero-arrow prev" id="heroPrev" aria-label="Anterior">&#8592;</button>
      <button class="hero-arrow next" id="heroNext" aria-label="Próximo">&#8594;</button>

      <div class="hero-controls">
        <button class="hero-dot active" data-idx="0" aria-label="Slide 1"></button>
        <button class="hero-dot" data-idx="1" aria-label="Slide 2"></button>
        <button class="hero-dot" data-idx="2" aria-label="Slide 3"></button>
      </div>
    </section>

    <!-- ── Fale com a Escola (logo abaixo do hero) ── -->
    <div class="cta-strip">
      <div class="cta-text-block">
        <div class="cta-eyebrow">// Entre em contato</div>
        <h3 class="cta-heading">Fale com a Escola</h3>
      </div>
    </div>

    <!-- ── Info rápida ── -->
    <div class="info-grid">
      <div class="info-cell">
        <div class="info-cell-icon">📞</div>
        <div class="info-cell-label">Telefone</div>
        <div class="info-cell-value">(31) 3950-0531</div>
        <div class="info-cell-sub">Atendimento seg–sáb</div>
      </div>
      <div class="info-cell">
        <div class="info-cell-icon">📧</div>
        <div class="info-cell-label">E-mail</div>
        <div class="info-cell-value" style="font-size:0.82rem;">escola.351040@educacao.mg.gov.br</div>
        <div class="info-cell-sub">SEE · Minas Gerais</div>
      </div>
      <div class="info-cell">
        <div class="info-cell-icon">📍</div>
        <div class="info-cell-label">Endereço</div>
        <div class="info-cell-value" style="font-size:0.88rem;">Av. Esmeralda, 98</div>
        <div class="info-cell-sub">Dona Rosarinha · Santa Luzia – MG · 33080-310</div>
      </div>
    </div>

    <!-- ── Galeria: vídeos autoplay + fotos ── -->
    <section class="media-section">
      <div class="section-heading">
        <span class="section-heading-label">// Galeria</span>
        <div class="section-heading-line"></div>
        <h2 class="section-heading-title">Momentos da Nossa Escola</h2>
      </div>

      <div class="media-grid">

        <!-- Vídeo 1 — Convite Feira -->
        <div class="video-card">
          <video autoplay muted loop playsinline preload="metadata">
            <source src="src/static/A E. E. Ephigenia de Jesus Werneck convida os pais e responsáveis para prestigiarem a nossa Feir.mp4" type="video/mp4">
          </video>
          <span class="video-card-label">Vídeo</span>
          <div class="video-card-body">
            <div class="video-card-title">Convite — Feira da Escola</div>
            <div class="video-card-meta">E.E. Ephigenia de Jesus Werneck · evento</div>
          </div>
        </div>

        <!-- Vídeo 2 — Jogos Escolares -->
        <div class="video-card">
          <video autoplay muted loop playsinline preload="metadata">
            <source src="src/static/Abertura dos Jogos Escolares de Minas Gerais em Santa Luzia!😁💪.mp4" type="video/mp4">
          </video>
          <span class="video-card-label">Vídeo</span>
          <div class="video-card-body">
            <div class="video-card-title">Abertura dos Jogos Escolares de MG em Santa Luzia 🥳🤸</div>
            <div class="video-card-meta">Esporte escolar · Santa Luzia</div>
          </div>
        </div>

        <!-- Vídeo 3 — Formatura -->
        <div class="video-card">
          <video autoplay muted loop playsinline preload="metadata">
            <source src="src/static/Nessa sexta-feira (12-12-2025) tivemos uma noite especial . A formatura dos nossos alunos do Ens.mp4" type="video/mp4">
          </video>
          <span class="video-card-label">Vídeo</span>
          <div class="video-card-body">
            <div class="video-card-title">Formatura — Uma Noite Especial (12/12/2025)</div>
            <div class="video-card-meta">Ensino Médio · cerimônia de formatura</div>
          </div>
        </div>

        <!-- Foto — Formatura -->
        <div class="media-card">
          <div class="media-card-thumb">
            <img src="src/static/formatura_1.png" alt="Formatura dos alunos" />
            <span class="media-card-type">Foto</span>
          </div>
          <div class="media-card-body">
            <div class="media-card-title">Formatura — Registros da Noite</div>
            <div class="media-card-meta">12/12/2025 · cerimônia oficial</div>
          </div>
        </div>

        <!-- Foto — Vôlei 1 -->
        <div class="media-card">
          <div class="media-card-thumb">
            <img src="src/static/volei_1.png" alt="Jogos Escolares — Vôlei" />
            <span class="media-card-type">Foto</span>
          </div>
          <div class="media-card-body">
            <div class="media-card-title">Jogos Escolares — Equipe de Vôlei</div>
            <div class="media-card-meta">Jogos Escolares de MG · Santa Luzia</div>
          </div>
        </div>

        <!-- Foto — Vôlei 2 -->
        <div class="media-card">
          <div class="media-card-thumb">
            <img src="src/static/volei_2.png" alt="Nossa equipe de vôlei em ação" />
            <span class="media-card-type">Foto</span>
          </div>
          <div class="media-card-body">
            <div class="media-card-title">Nossa Equipe em Ação</div>
            <div class="media-card-meta">Competição estadual · esporte escolar</div>
          </div>
        </div>

        <div class="media-card media-card-destaque">
          <div class="media-card-jardim-thumb">
            <img src="src/static/fotojardim_1.png" alt="Nosso jardim" />
            <span class="media-card-type">Foto</span>
          </div>
          <div class="media-card-body"> 
            <div class="media-card-title">Nosso Jardim</div>
            <div class="media-card-meta">Espaço verde</div>
          </div>
        </div>

      </div> </section>

    <!-- ── Horários de Funcionamento ── -->
    <section class="schedule-section">
      <div class="section-heading">
        <span class="section-heading-label">// Horários</span>
        <div class="section-heading-line"></div>
        <h2 class="section-heading-title">Funcionamento</h2>
      </div>

      <table class="schedule-table">
        <thead>
          <tr>
            <th>Dia da semana</th>
            <th>Horário</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><span class="day-name">Segunda-feira</span></td>
            <td><span class="schedule-time">07:00 – 17:20</span></td>
          </tr>
          <tr>
            <td>
              <span class="day-name">Terça-feira</span>
            </td>
            <td><span class="schedule-time">07:00 – 17:20</span></td>
          </tr>
          <tr>
            <td><span class="day-name">Quarta-feira</span></td>
            <td><span class="schedule-time">07:00 – 17:20</span></td>
          </tr>
          <tr>
            <td><span class="day-name">Quinta-feira</span></td>
            <td><span class="schedule-time">07:00 – 17:20</span></td>
          </tr>
          <tr>
            <td><span class="day-name">Sexta-feira</span></td>
            <td><span class="schedule-time">07:00 – 17:20</span></td>
          </tr>
          <tr>
            <td><span class="day-name">Sábado</span></td>
            <td><span class="schedule-time">07:00 – 12:00</span></td>
          </tr>
          <tr>
            <td><span class="day-name day-closed">Domingo</span></td>
            <td><span class="day-closed">Fechado</span></td>
          </tr>
        </tbody>
      </table>
    </section>

  </main>

  <footer class="footer">
    <div class="footer-inner">
      <span class="footer-school">E.E. Ephigenia de Jesus Werneck</span>
      <span class="footer-sep">·</span>
      <span class="footer-text">Av. Esmeralda, 98 — Dona Rosarinha, Santa Luzia MG</span>
      <span class="footer-sep">·</span>
      <span class="footer-text">(31) 3950-0531</span>
      <span class="footer-sep">·</span>
      <span class="footer-text">SEE-MG · <?= date('Y') ?></span>
    </div>
  </footer>

  <script>
  /* ── Carousel ── */
  const slides  = document.getElementById('heroSlides');
  const dots    = document.querySelectorAll('.hero-dot');
  let current   = 0;
  const total   = 3;
  let autoTimer;

  function goTo(idx) {
    current = (idx + total) % total;
    slides.style.transform = `translateX(-${current * 100}%)`;
    dots.forEach((d, i) => d.classList.toggle('active', i === current));
  }

  function startAuto() {
    autoTimer = setInterval(() => goTo(current + 1), 5000);
  }

  document.getElementById('heroPrev').addEventListener('click', () => { clearInterval(autoTimer); goTo(current - 1); startAuto(); });
  document.getElementById('heroNext').addEventListener('click', () => { clearInterval(autoTimer); goTo(current + 1); startAuto(); });
  dots.forEach(d => d.addEventListener('click', () => { clearInterval(autoTimer); goTo(+d.dataset.idx); startAuto(); }));
  startAuto();
  </script>

</body>
</html>