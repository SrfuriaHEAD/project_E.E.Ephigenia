<section class="form-section">

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
        <label class="field-label" for="f-registro">Nº de registro</label>
        <div class="field-wrap">
          <input class="field-input" type="number" id="f-registro" name="registro"
                 placeholder="ex: 1042" required min="1">
          <span class="field-bar"></span>
        </div>
      </div>

      <div class="field-group">
        <label class="field-label" for="f-quantidade">Quantidade de exemplares</label>
        <div class="field-wrap">
          <input class="field-input" type="number" id="f-quantidade" name="quantidade"
                 placeholder="ex: 3" required min="1">
          <span class="field-bar"></span>
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
        <li>O nº de registro não pode se repetir.</li>
        <li>Quantidade mínima: 1 exemplar.</li>
      </ul>
    </div>
    <div class="aside-deco">
      <div class="deco-line"></div>
      <span class="deco-text">E.E. EPHIGÊNIA</span>
      <div class="deco-line"></div>
    </div>
  </aside>

</section>