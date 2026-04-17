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
          <label class="field-label" for="f-registro">Nº de Registro</label>
          <div class="field-wrap">
            <input class="field-input" type="text" id="f-registro" name="registro"
                   placeholder="ex: 23" required autocomplete="off">
            <span class="field-bar"></span>
          </div>
        </div>

        <div class="field-group" style="flex:1;min-width:130px">
          <label class="field-label" for="f-quantidade">Qtd. de exemplares</label>
          <div class="field-wrap">
            <input class="field-input" type="number" id="f-quantidade" name="quantidade"
                   placeholder="ex: 3" required min="1">
            <span class="field-bar"></span>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:1rem;flex-wrap:wrap">
        <div class="field-group" style="flex:2;min-width:160px">
          <label class="field-label" for="f-prateleira">Prateleira</label>
          <div class="field-wrap">
            <input class="field-input" type="text" id="f-prateleira" name="prateleira"
                   placeholder="ex: A1, B3, Literatura…" autocomplete="off">
            <span class="field-bar"></span>
          </div>
        </div>

        <div class="field-group" style="flex:2;min-width:160px">
          <label class="field-label" for="f-faixa-etaria">Faixa etária / Nível</label>
          <div class="field-wrap">
            <select class="field-input" id="f-faixa-etaria" name="faixa_etaria"
                    style="appearance:none;-webkit-appearance:none;cursor:pointer">
              <option value="">— Selecionar —</option>
              <option value="1A">1º Ano</option>
              <option value="2A">2º Ano</option>
              <option value="3A">3º Ano</option>
              <option value="4A">4º Ano</option>
              <option value="5A">5º Ano</option>
              <option value="6A">6º Ano</option>
              <option value="7A">7º Ano</option>
              <option value="8A">8º Ano</option>
              <option value="9A">9º Ano</option>
              <option value="EM1">EM — 1ª série</option>
              <option value="EM2">EM — 2ª série</option>
              <option value="EM3">EM — 3ª série</option>
              <option value="LIT">Literatura</option>
              <option value="INF">Infantil</option>
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
        <li>O nº de registro não pode se repetir.</li>
        <li>Autor, editora e ano são opcionais.</li>
        <li>Prateleira e faixa etária são opcionais.</li>
        <li>Quantidade mínima: 1 exemplar.</li>
      </ul>
    </div>
    <div class="aside-deco">
      <div class="deco-line"></div>
      <span class="deco-text">E.E. EPHIGENIA</span>
      <div class="deco-line"></div>
    </div>
  </aside>

</section>