<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// ══ CONFIG ══════════════════════════════════════════════════════════════════
$diretorio      = __DIR__ . '/db';
$arquivo        = $diretorio . '/banco.txt';
$arqEmprestimos = $diretorio . '/emprestimos.txt';

// ══ ROTEAMENTO POST — deve vir ANTES de qualquer HTML ═══════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // Limpa qualquer output acidental antes de responder JSON
    ob_start();

    // ── helpers compartilhados ───────────────────────────────────────────────

    function parse_livros(string $arq): array {
        if (!file_exists($arq)) return [];
        $livros = [];
        foreach (file($arq, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
            if (str_starts_with($linha, '---')) continue;
            if (!preg_match('/Nome:\s*(.+?)\s*\|\s*Registro:\s*([^\|]+?)\s*\|\s*Quantidade:\s*(\d+)/', $linha, $m)) continue;
            $nome     = trim($m[1]);
            $registro = trim($m[2]);
            $quantidade   = (int)$m[3];
            $qtd      = (int)$m[3];
            $autor = '';      if (preg_match('/Autor:\s*(.+?)(?:\s*\||$)/',       $linha, $ma))   $autor       = trim($ma[1]);
            $editora = '';    if (preg_match('/Editora:\s*(.+?)(?:\s*\||$)/',     $linha, $me))   $editora     = trim($me[1]);
            $ano = '';        if (preg_match('/Ano:\s*(\d+)/',                    $linha, $mano)) $ano         = trim($mano[1]);
            $prateleira = ''; if (preg_match('/Prateleira:\s*(.+?)(?:\s*\||$)/', $linha, $mp))   $prateleira  = trim($mp[1]);
            $faixaEtaria = '';if (preg_match('/FaixaEtaria:\s*(.+?)(?:\s*\||$)/',$linha, $mf))   $faixaEtaria = trim($mf[1]);

            $livros[] = compact('nome','registro','quantidade','autor','editora','ano','prateleira','faixaEtaria');
        }
        return $livros;
    }

    function parse_emprestimos(string $arq): array {
        if (!file_exists($arq)) return [];
        $lista = [];
        foreach (file($arq, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
            if (str_starts_with($linha, '---')) continue;
            if (preg_match('/ID:\s*(\S+)\s*\|\s*Registro:\s*(\S+)\s*\|\s*Aluno:\s*(.+?)\s*\|\s*Sala:\s*(.*?)\s*\|\s*Retirada:\s*(\S+)\s*\|\s*Devolucao:\s*(\S+)/', $linha, $m)) {
                $lista[] = ['id'=>$m[1],'registro'=>$m[2],'aluno'=>$m[3],'sala'=>$m[4],'retirada'=>$m[5],'devolucao'=>$m[6]];
            }
        }
        return $lista;
    }

    function json_out(array $payload): never {
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── procurar_livros ──────────────────────────────────────────────────────
    if ($acao === 'procurar_livros') {
        $busca       = strtolower(trim($_POST['busca'] ?? ''));
        $livros      = parse_livros($arquivo);
        $emprestimos = parse_emprestimos($arqEmprestimos);

        $empCount = [];
        foreach ($emprestimos as $e) $empCount[$e['registro']] = ($empCount[$e['registro']] ?? 0) + 1;
        foreach ($livros as &$l) {
            $l['emprestados'] = $empCount[$l['registro']] ?? 0;
            $l['disponiveis'] = max(0, $l['quantidade'] - $l['emprestados']);
        }
        unset($l);

        if ($busca !== '') {
            $livros = array_values(array_filter($livros, fn($l) =>
                str_contains(strtolower($l['nome']),      $busca) ||
                str_contains(strtolower($l['registro']),  $busca) ||
                str_contains(strtolower($l['autor']),     $busca)
            ));
        }
        json_out(['success' => true, 'livros' => $livros]);
    }

    // ── buscar_aluno ─────────────────────────────────────────────────────────
    if ($acao === 'buscar_aluno') {
        $busca     = strtolower(trim($_POST['busca']     ?? ''));
        $categoria = strtolower(trim($_POST['categoria'] ?? ''));
        $livros    = parse_livros($arquivo);
        $emps      = parse_emprestimos($arqEmprestimos);

        // índice por registro: nome + faixaEtaria
        $idx = [];
        foreach ($livros as $l) $idx[$l['registro']] = ['nome' => $l['nome'], 'faixaEtaria' => strtolower($l['faixaEtaria'] ?? '')];

        // lista de categorias disponíveis para o select
        $cats = [];
        foreach ($livros as $l) {
            if ($l['faixaEtaria']) $cats[$l['faixaEtaria']] = true;
        }
        ksort($cats);

        $resultado = [];
        foreach ($emps as $e) {
            $info = $idx[$e['registro']] ?? ['nome'=>'Livro desconhecido','faixaEtaria'=>''];
            $matchBusca = !$busca
                || str_contains(strtolower($e['aluno']),      $busca)
                || str_contains(strtolower($e['sala'] ?? ''), $busca)
                || str_contains(strtolower($info['nome']),    $busca);
            $matchCat = !$categoria || $info['faixaEtaria'] === $categoria;
            if ($matchBusca && $matchCat) {
                $resultado[] = array_merge($e, ['livro' => $info['nome'], 'faixaEtaria' => $info['faixaEtaria']]);
            }
        }
        json_out(['success' => true, 'emprestimos' => $resultado, 'categorias' => array_keys($cats)]);
    }

    // ── detalhes_livro ───────────────────────────────────────────────────────
    if ($acao === 'detalhes_livro') {
        $registro    = trim($_POST['registro'] ?? '');
        $livros      = parse_livros($arquivo);
        $emprestimos = parse_emprestimos($arqEmprestimos);

        $livro = null;
        foreach ($livros as $l) { if ($l['registro'] === $registro) { $livro = $l; break; } }
        if (!$livro) json_out(['success' => false, 'msg' => 'Livro não encontrado.']);

        $ativos = array_values(array_filter($emprestimos, fn($e) => $e['registro'] === $registro));
        $livro['emprestados'] = count($ativos);
        $livro['disponiveis'] = max(0, $livro['quantidade'] - $livro['emprestados']);
        $livro['emprestimos'] = $ativos;
        json_out(['success' => true, 'livro' => $livro]);
    }

    // ── emprestar_livro ──────────────────────────────────────────────────────
    if ($acao === 'emprestar_livro') {
        $registro  = trim($_POST['registro']  ?? '');
        $aluno     = trim($_POST['aluno']     ?? '');
        $sala      = trim($_POST['sala']      ?? '');
        $devolucao = trim($_POST['devolucao'] ?? '');

        if (!$registro || !$aluno || !$devolucao) {
            json_out(['success' => false, 'msg' => 'Campos obrigatórios faltando.']);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $devolucao)) {
            json_out(['success' => false, 'msg' => 'Data de devolução inválida.']);
        }
        if ($devolucao <= date('Y-m-d')) {
            json_out(['success' => false, 'msg' => 'A devolução deve ser a partir de amanhã.']);
        }

        // Verifica se o livro existe e tem estoque
        $livros      = parse_livros($arquivo);
        $emprestimos = parse_emprestimos($arqEmprestimos);
        $livro = null;
        foreach ($livros as $l) { if ($l['registro'] === $registro) { $livro = $l; break; } }
        if (!$livro) json_out(['success' => false, 'msg' => 'Livro não encontrado.']);

        $empCount = 0;
        foreach ($emprestimos as $e) { if ($e['registro'] === $registro) $empCount++; }
        if ($empCount >= $livro['quantidade']) {
            json_out(['success' => false, 'msg' => 'Nenhum exemplar disponível no momento.']);
        }

        $id       = date('Ymd') . rand(1000, 9999);
        $retirada = date('Y-m-d');
        $linha    = "ID: $id | Registro: $registro | Aluno: $aluno | Sala: $sala | Retirada: $retirada | Devolucao: $devolucao\n";

        if (file_put_contents($arqEmprestimos, $linha, FILE_APPEND | LOCK_EX) === false) {
            json_out(['success' => false, 'msg' => 'Erro ao salvar empréstimo.']);
        }
        json_out(['success' => true, 'msg' => 'Empréstimo registrado com sucesso!']);
    }

    // ── devolver_livro ───────────────────────────────────────────────────────
    if ($acao === 'devolver_livro') {
        $id = trim($_POST['id'] ?? '');
        if (!$id) json_out(['success' => false, 'msg' => 'ID inválido.']);

        if (!file_exists($arqEmprestimos)) json_out(['success' => false, 'msg' => 'Arquivo de empréstimos não encontrado.']);

        $linhas  = file($arqEmprestimos, FILE_IGNORE_NEW_LINES);
        $novas   = [];
        $achou   = false;
        foreach ($linhas as $linha) {
            if (str_starts_with($linha, '---')) { $novas[] = $linha; continue; }
            if (preg_match('/ID:\s*' . preg_quote($id, '/') . '\b/', $linha)) { $achou = true; continue; }
            $novas[] = $linha;
        }
        if (!$achou) json_out(['success' => false, 'msg' => 'Empréstimo não encontrado.']);

        file_put_contents($arqEmprestimos, implode("\n", $novas) . "\n", LOCK_EX);
        json_out(['success' => true, 'msg' => 'Devolução registrada!']);
    }

    // ── deletar_livro ────────────────────────────────────────────────────────
    if ($acao === 'deletar_livro') {
        $registro = trim($_POST['registro'] ?? '');
        if (!$registro) json_out(['success' => false, 'msg' => 'Registro inválido.']);

        // Remove do banco.txt
        if (file_exists($arquivo)) {
            $linhas = file($arquivo, FILE_IGNORE_NEW_LINES);
            $novas  = array_filter($linhas, fn($l) =>
                str_starts_with($l, '---') ||
                !preg_match('/Registro:\s*' . preg_quote($registro, '/') . '\b/', $l)
            );
            file_put_contents($arquivo, implode("\n", array_values($novas)) . "\n", LOCK_EX);
        }

        // Remove empréstimos relacionados
        if (file_exists($arqEmprestimos)) {
            $linhas = file($arqEmprestimos, FILE_IGNORE_NEW_LINES);
            $novas  = array_filter($linhas, fn($l) =>
                str_starts_with($l, '---') ||
                !preg_match('/Registro:\s*' . preg_quote($registro, '/') . '\b/', $l)
            );
            file_put_contents($arqEmprestimos, implode("\n", array_values($novas)) . "\n", LOCK_EX);
        }

        json_out(['success' => true, 'msg' => 'Livro removido do sistema.']);
    }

    // ── editar_livro ─────────────────────────────────────────────────────────
    if ($acao === 'editar_livro') {
        $registro    = trim($_POST['registro']    ?? '');
        $nome        = htmlspecialchars(trim($_POST['nome']         ?? ''), ENT_QUOTES, 'UTF-8');
        $autor       = htmlspecialchars(trim($_POST['autor']        ?? ''), ENT_QUOTES, 'UTF-8');
        $editora     = htmlspecialchars(trim($_POST['editora']      ?? ''), ENT_QUOTES, 'UTF-8');
        $ano         = trim($_POST['ano']         ?? '');
        $quantidade  = trim($_POST['quantidade']  ?? '');
        $prateleira  = htmlspecialchars(trim($_POST['prateleira']   ?? ''), ENT_QUOTES, 'UTF-8');
        $faixaEtaria = htmlspecialchars(trim($_POST['faixa_etaria'] ?? ''), ENT_QUOTES, 'UTF-8');

        if (!$registro || !$nome || !$quantidade)
            json_out(['success' => false, 'msg' => 'Titulo, quantidade e registro sao obrigatorios.']);

        if (!file_exists($arquivo))
            json_out(['success' => false, 'msg' => 'Banco de dados nao encontrado.']);

        $linhas = file($arquivo, FILE_IGNORE_NEW_LINES);
        $novas  = [];
        $achou  = false;
        foreach ($linhas as $linha) {
            if (str_starts_with($linha, '---')) { $novas[] = $linha; continue; }
            if (preg_match('/Registro:\s*' . preg_quote($registro, '/') . '\b/', $linha)) {
                $achou  = true;
                $nova   = "Nome: $nome | Registro: $registro | Quantidade: $quantidade";
                if ($autor)       $nova .= " | Autor: $autor";
                if ($editora)     $nova .= " | Editora: $editora";
                if ($ano)         $nova .= " | Ano: $ano";
                if ($prateleira)  $nova .= " | Prateleira: $prateleira";
                if ($faixaEtaria) $nova .= " | FaixaEtaria: $faixaEtaria";
                $novas[] = $nova;
            } else {
                $novas[] = $linha;
            }
        }
        if (!$achou) json_out(['success' => false, 'msg' => 'Livro nao encontrado.']);
        file_put_contents($arquivo, implode("\n", $novas) . "\n", LOCK_EX);
        json_out(['success' => true, 'msg' => 'Livro atualizado com sucesso!']);
    }

    // ── renovar_emprestimo ───────────────────────────────────────────────────
    if ($acao === 'renovar_emprestimo') {
        $id        = trim($_POST['id']        ?? '');
        $devolucao = trim($_POST['devolucao'] ?? '');

        if (!$id || !$devolucao)
            json_out(['success' => false, 'msg' => 'Dados invalidos.']);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $devolucao) || $devolucao <= date('Y-m-d'))
            json_out(['success' => false, 'msg' => 'Data de devolucao invalida (deve ser futura).']);

        if (!file_exists($arqEmprestimos))
            json_out(['success' => false, 'msg' => 'Arquivo de emprestimos nao encontrado.']);

        $linhas = file($arqEmprestimos, FILE_IGNORE_NEW_LINES);
        $novas  = [];
        $achou  = false;
        foreach ($linhas as $linha) {
            if (preg_match('/ID:\s*' . preg_quote($id, '/') . '\b/', $linha)) {
                $achou = true;
                $linha = preg_replace('/Devolucao:\s*\S+/', "Devolucao: $devolucao", $linha);
            }
            $novas[] = $linha;
        }
        if (!$achou) json_out(['success' => false, 'msg' => 'Emprestimo nao encontrado.']);
        file_put_contents($arqEmprestimos, implode("\n", $novas) . "\n", LOCK_EX);
        json_out(['success' => true, 'msg' => 'Emprestimo renovado!']);
    }

    // ── listar_categorias ────────────────────────────────────────────────────
    if ($acao === 'listar_categorias') {
        $livros = parse_livros($arquivo);
        $cats   = [];
        foreach ($livros as $l) {
            if ($l['faixaEtaria']) $cats[$l['faixaEtaria']] = true;
        }
        ksort($cats);
        json_out(['success' => true, 'categorias' => array_keys($cats)]);
    }

    // ── buscar_historico ─────────────────────────────────────────────────────
    if ($acao === 'buscar_historico') {
        $busca = strtolower(trim($_POST['busca'] ?? ''));
        $ano   = trim($_POST['ano'] ?? '');

        $arqHist = $diretorio . '/historico.txt';
        $livros  = parse_livros($arquivo);
        $idx     = [];
        foreach ($livros as $l) $idx[$l['registro']] = $l['nome'];

        $todos = [];

        // ── Tenta ler historico.txt (formato completo com Livro: e Ano:) ──────
        if (file_exists($arqHist)) {
            foreach (file($arqHist, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
                if (str_starts_with($linha, '---')) continue;
                // Formato: ID: ... | Registro: ... | Livro: ... | Aluno: ... | Sala: ... | Retirada: ... | Devolucao: ... | Ano: ...
                if (!preg_match('/ID:\s*(\S+)\s*\|\s*Registro:\s*(\S+)\s*\|\s*Livro:\s*(.+?)\s*\|\s*Aluno:\s*(.+?)\s*\|\s*Sala:\s*(.*?)\s*\|\s*Retirada:\s*(\S+)\s*\|\s*Devolucao:\s*(\S+)(?:\s*\|\s*Ano:\s*(\d+))?/', $linha, $m)) continue;
                $anoReg = $m[8] ?? substr($m[6], 0, 4); // usa campo Ano: ou extrai do Retirada
                if ($ano && $anoReg !== $ano) continue;
                $nomeLivro = trim($m[3]);
                if ($busca
                    && !str_contains(strtolower($m[4]), $busca)
                    && !str_contains(strtolower($m[5]), $busca)
                    && !str_contains(strtolower($nomeLivro),  $busca)) continue;
                $todos[] = [
                    'id'        => $m[1],
                    'registro'  => $m[2],
                    'livro'     => $nomeLivro,
                    'aluno'     => trim($m[4]),
                    'sala'      => trim($m[5]),
                    'retirada'  => $m[6],
                    'devolucao' => $m[7],
                    'ano'       => $anoReg,
                ];
            }
        }

        // ── Fallback: emprestimos.txt ativos (se histórico vazio) ─────────────
        if (empty($todos)) {
            foreach (file($arqEmprestimos, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
                if (str_starts_with($linha, '---')) continue;
                if (!preg_match('/ID:\s*(\S+)\s*\|\s*Registro:\s*(\S+)\s*\|\s*Aluno:\s*(.+?)\s*\|\s*Sala:\s*(.*?)\s*\|\s*Retirada:\s*(\S+)\s*\|\s*Devolucao:\s*(\S+)/', $linha, $m)) continue;
                $anoReg = substr($m[5], 0, 4);
                if ($ano && $anoReg !== $ano) continue;
                $nomeLivro = $idx[$m[2]] ?? 'Desconhecido';
                if ($busca
                    && !str_contains(strtolower($m[3]), $busca)
                    && !str_contains(strtolower($m[4]), $busca)
                    && !str_contains(strtolower($nomeLivro),  $busca)) continue;
                $todos[] = ['id'=>$m[1],'registro'=>$m[2],'livro'=>$nomeLivro,'aluno'=>$m[3],'sala'=>$m[4],'retirada'=>$m[5],'devolucao'=>$m[6],'ano'=>$anoReg];
            }
        }

        // Ranking
        $contagem = [];
        foreach ($todos as $r) $contagem[$r['aluno']] = ($contagem[$r['aluno']] ?? 0) + 1;
        arsort($contagem);
        $ranking = [];
        foreach (array_slice($contagem, 0, 10, true) as $aluno => $total) {
            $ranking[] = ['aluno' => $aluno, 'total' => $total];
        }

        json_out(['success' => true, 'registros' => array_reverse($todos), 'ranking' => $ranking]);
    }

    // Ação desconhecida
    json_out(['success' => false, 'msg' => 'Ação desconhecida.']);
}
// ════════════════════════════════════════════════════════════════════════════
// HTML (GET requests only below)
// ════════════════════════════════════════════════════════════════════════════
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Acervo — Biblioteca E.E. Ephigenia</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
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
      letter-spacing: 0.15em; color: #070707; margin-left: auto;
    }
    /* ── Sort controls ── */
    .sort-bar {
      display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;
      margin-bottom: 1rem;
    }
    .sort-label {
      font-family: var(--font-mono); font-size: 0.55rem;
      letter-spacing: 0.15em; color: #666; text-transform: uppercase;
    }
    .sort-btn {
      background: #111; border: 1px solid #1e1e1e; color: #c0c0c0;
      font-family: var(--font-mono); font-size: 0.6rem;
      letter-spacing: 0.1em; padding: 0.3rem 0.75rem; cursor: pointer;
      border-radius: var(--radius); transition: all var(--transition);
      text-transform: uppercase;
    }
    .sort-btn:hover { border-color: #555; color: #ccc; }
    .sort-btn.active { border-color: var(--rust); color: #006eff; }
    .sort-btn .arrow { margin-left: 0.3rem; font-size: 0.7rem; }
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
    .book-badges { display: flex; flex-wrap: wrap; gap: 0.3rem; margin: 0.45rem 0 0.35rem; }
    .book-badge {
      font-family: var(--font-mono); font-size: 0.8rem; letter-spacing: 0.1em;
      padding: 0.15rem 0.45rem; border-radius: 2px; text-transform: uppercase; white-space: nowrap;
    }
    .book-badge--prat  { background: #1a1a1a; color: #888; border: 1px solid #2a2a2a; }
    .book-badge--faixa { background: #1c1008; color: #c87941; border: 1px solid #3a2010; }
    .book-genre-badge {
      display: inline-flex; align-items: center; gap: 0;
      margin: 0.45rem 0 0.35rem;
      border: 1px solid #3a2010; border-radius: 2px; overflow: hidden;
    }
    .genre-code {
      background: #c87941; color: #0f0f0f;
      font-family: var(--font-mono); font-size: 0.65rem; font-weight: 700;
      letter-spacing: 0.1em; padding: 0.2rem 0.5rem; line-height: 1;
    }
    .genre-name {
      background: #1c1008; color: #c87941;
      font-family: var(--font-mono); font-size: 0.62rem;
      letter-spacing: 0.06em; padding: 0.2rem 0.55rem; line-height: 1;
    }
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
      width: 100%; max-width: 720px; max-height: 90vh;
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
      font-family: var(--font-mono); font-size: 0.75rem;
      letter-spacing: 0.25em; color: red; margin-bottom: 0.35rem;
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

    /* ── MODAL TABS ── */
    .modal-tabs { display: flex; gap: 0; border-bottom: 1px solid #1e1e1e; margin-bottom: 1.5rem; }
    .modal-tab {
      background: none; border: none; border-bottom: 2px solid transparent;
      color: #666; font-family: var(--font-mono); font-size: 0.7rem;
      letter-spacing: 0.12em; text-transform: uppercase;
      padding: 0.6rem 1.1rem; cursor: pointer; transition: all var(--transition);
      position: relative; bottom: -1px;
    }
    .modal-tab:hover { color: #aaa; }
    .modal-tab.active { color: var(--rust); border-bottom-color: var(--rust); }
    .modal-panel { display: none; }
    .modal-panel.active { display: block; }

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
      display: flex; align-items: center; gap: 0.3rem;
    }
    .loan-input {
      background: #0a0a0a; border: 1px solid #2a2a2a; border-radius: var(--radius);
      color: #e0e0e0; font-family: var(--font-mono); font-size: 0.8rem;
      padding: 0.5rem 0.75rem; outline: none; transition: border-color var(--transition);
    }
    .loan-input:focus { border-color: var(--rust); }
    .loan-input::placeholder { color: #444; font-style: italic; }
    .loan-select {
      background: #0a0a0a; border: 1px solid #2a2a2a; border-radius: var(--radius);
      color: #e0e0e0; font-family: var(--font-mono); font-size: 0.8rem;
      padding: 0.5rem 0.75rem; outline: none; transition: border-color var(--transition);
      appearance: none; -webkit-appearance: none; cursor: pointer; width: 100%;
    }
    .loan-select:focus { border-color: var(--rust); }
    .btn-loan {
      background: var(--rust); color: #f0f0f0; border: none;
      font-family: var(--font-mono); font-size: 0.65rem;
      letter-spacing: 0.2em; text-transform: uppercase;
      padding: 0.55rem 1.25rem; cursor: pointer;
      transition: background var(--transition); white-space: nowrap; align-self: flex-end;
      border-radius: var(--radius);
    }
    .btn-loan:hover { background: var(--rust-dark, #7a2000); }

    /* ── Edit form ── */
    .edit-form-wrap {
      background: #0d0d0d; border: 1px solid #1e1e1e;
      border-left: 3px solid #4a8fff; padding: 1.5rem; margin-bottom: 1.5rem;
    }
    .edit-form-title {
      font-family: var(--font-mono); font-size: 0.55rem;
      letter-spacing: 0.25em; color: #4a8fff; margin-bottom: 1.25rem; text-transform: uppercase;
    }
    .edit-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
    .edit-field { display: flex; flex-direction: column; gap: 0.35rem; }
    .edit-field.full { grid-column: 1 / -1; }
    .edit-label {
      font-family: var(--font-mono); font-size: 0.5rem;
      letter-spacing: 0.15em; color: #777; text-transform: uppercase;
    }
    .edit-input {
      background: #0a0a0a; border: 1px solid #2a2a2a; border-radius: var(--radius);
      color: #e0e0e0; font-family: var(--font-mono); font-size: 0.8rem;
      padding: 0.5rem 0.75rem; outline: none; transition: border-color var(--transition);
    }
    .edit-input:focus { border-color: #4a8fff; }
    .edit-select {
      background: #0a0a0a; border: 1px solid #2a2a2a; border-radius: var(--radius);
      color: #e0e0e0; font-family: var(--font-mono); font-size: 0.8rem;
      padding: 0.5rem 0.75rem; outline: none; width: 100%;
      appearance: none; -webkit-appearance: none; cursor: pointer;
    }
    .edit-select:focus { border-color: #4a8fff; }
    .btn-save {
      background: #4a8fff; color: #fff; border: none;
      font-family: var(--font-mono); font-size: 0.65rem;
      letter-spacing: 0.2em; text-transform: uppercase;
      padding: 0.6rem 1.5rem; cursor: pointer;
      transition: background var(--transition); border-radius: var(--radius); margin-top: 0.75rem;
    }
    .btn-save:hover { background: #2a6fdf; }

    /* ── Loans list ── */
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
      display: grid; grid-template-columns: 1fr auto auto auto;
      gap: 0.5rem 0.75rem; align-items: center;
      padding: 0.85rem 0; border-bottom: 1px solid #151515;
    }
    .loan-row:last-child { border-bottom: none; }
    .loan-row.hidden { display: none; }
    .loan-aluno { font-family: var(--font-display); font-size: 0.9rem; font-weight: 700; color: #ddd; }
    .loan-sala { font-family: var(--font-mono); font-size: 0.58rem; color: #888; letter-spacing: 0.05em; }
    .loan-date { font-family: var(--font-mono); font-size: 0.6rem; color: #555; }
    .loan-devol { font-family: var(--font-mono); font-size: 0.6rem; color: #888; white-space: nowrap; }
    .loan-devol.atrasado { color: #cc4400; }
    .loan-devol.hoje     { color: #ff9800; }
    .btn-devolver {
      background: none; border: 1px solid #2a2a2a; color: #666;
      font-family: var(--font-mono); font-size: 0.55rem; letter-spacing: 0.1em;
      padding: 0.3rem 0.65rem; cursor: pointer; white-space: nowrap;
      transition: all var(--transition); text-transform: uppercase; border-radius: var(--radius);
    }
    .btn-devolver:hover { border-color: #4caf7d; color: #4caf7d; }
    .btn-renovar {
      background: none; border: 1px solid #2a2a2a; color: #666;
      font-family: var(--font-mono); font-size: 0.55rem; letter-spacing: 0.1em;
      padding: 0.3rem 0.65rem; cursor: pointer; white-space: nowrap;
      transition: all var(--transition); text-transform: uppercase; border-radius: var(--radius);
    }
    .btn-renovar:hover { border-color: #4a8fff; color: #4a8fff; }
    .no-loans { font-family: var(--font-mono); font-size: 0.7rem; color: #333; font-style: italic; padding: 1rem 0; }

    /* ── Renovar popup ── */
    .renovar-inline {
      display: none; grid-column: 1 / -1;
      background: #0d0d0d; border: 1px solid #1e1e1e; border-left: 3px solid #4a8fff;
      padding: 0.85rem 1rem; margin: 0.35rem 0; border-radius: var(--radius);
    }
    .renovar-inline.visible { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
    .renovar-inline select {
      background: #0a0a0a; border: 1px solid #2a2a2a; border-radius: var(--radius);
      color: #e0e0e0; font-family: var(--font-mono); font-size: 0.75rem;
      padding: 0.4rem 0.65rem; outline: none;
    }
    .renovar-inline input[type=date] {
      background: #0a0a0a; border: 1px solid #2a2a2a; border-radius: var(--radius);
      color: #e0e0e0; font-family: var(--font-mono); font-size: 0.75rem;
      padding: 0.4rem 0.65rem; outline: none;
    }
    .renovar-inline label {
      font-family: var(--font-mono); font-size: 0.55rem;
      letter-spacing: 0.12em; color: #4a8fff; text-transform: uppercase;
    }
    .btn-renovar-confirm {
      background: #4a8fff; color: #fff; border: none;
      font-family: var(--font-mono); font-size: 0.6rem;
      letter-spacing: 0.15em; text-transform: uppercase;
      padding: 0.4rem 0.9rem; cursor: pointer; border-radius: var(--radius);
      transition: background var(--transition);
    }
    .btn-renovar-confirm:hover { background: #2a6fdf; }
    .btn-renovar-cancel {
      background: none; border: 1px solid #333; color: #666;
      font-family: var(--font-mono); font-size: 0.6rem; letter-spacing: 0.1em;
      text-transform: uppercase; padding: 0.4rem 0.75rem;
      cursor: pointer; border-radius: var(--radius);
    }
    .renovar-msg { font-family: var(--font-mono); font-size: 0.6rem; color: #888; }

    /* ── Tooltip ── */
    .tip {
      display: inline-flex; align-items: center; justify-content: center;
      width: 14px; height: 14px; background: #222; border: 1px solid #333;
      border-radius: 50%; font-family: var(--font-mono); font-size: 0.55rem;
      color: #888; cursor: help; position: relative; flex-shrink: 0;
    }
    .tip::after {
      content: attr(data-tip);
      position: absolute; bottom: calc(100% + 6px); left: 50%;
      transform: translateX(-50%);
      background: #1a1a1a; border: 1px solid #2a2a2a;
      color: #ccc; font-family: var(--font-mono); font-size: 0.65rem;
      padding: 0.4rem 0.75rem; border-radius: 3px; white-space: nowrap;
      pointer-events: none; opacity: 0; transition: opacity 0.2s;
      max-width: 220px; white-space: pre-line; text-align: center;
      z-index: 9999;
    }
    .tip:hover::after { opacity: 1; }

    /* ── Tabs ── */
    .nav-link:hover { color: #313131; }
    .hero-sub    { font-family: var(--font-mono); font-size: 0.9rem; color: #000000; }
    .hero-system { font-family: var(--font-mono); font-size: 0.9rem; letter-spacing: 0.15em; color: #f80000; margin-left: 10px; }
    .tabs { display: flex; gap: 0; margin-bottom: 2rem; border-bottom: 1px solid #1e1e1e; }
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
      border-radius: 999px; margin-left: 0.4rem; vertical-align: middle;
    }

    /* ── Filtro genero select (aba busca) ── */
    .filter-select {
      background: #111; border: 1px solid #1e1e1e; color: #e0e0e0;
      font-family: var(--font-mono); font-size: 0.8rem;
      padding: 0.55rem 0.75rem; outline: none; border-radius: var(--radius);
      cursor: pointer; min-width: 160px;
    }
    .filter-select:focus { border-color: var(--rust); }

    /* ── TomSelect edit overrides ── */
    .edit-ts .ts-wrapper { width: 100%; }
    .edit-ts .ts-control {
      background: #0a0a0a !important; border: 1px solid #2a2a2a !important;
      border-radius: var(--radius) !important; color: #e0e0e0 !important;
      font-family: var(--font-mono) !important; font-size: 0.8rem !important;
      padding: 0.4rem 0.75rem !important; min-height: unset !important;
    }
    .edit-ts .ts-control input { color: #e0e0e0 !important; }
    .edit-ts .ts-dropdown { background: #111 !important; border: 1px solid #2a2a2a !important; font-family: var(--font-mono) !important; font-size: 0.8rem !important; }
    .edit-ts .ts-dropdown .option:hover,
    .edit-ts .ts-dropdown .option.active { background: #1e1e1e !important; color: #e0e0e0 !important; }
    .edit-ts .ts-wrapper.focus .ts-control { border-color: #4a8fff !important; }
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

  <div class="tabs">
    <button class="tab active" data-tab="acervo">Acervo</button>
    <button class="tab" data-tab="alunos">Busca</button>
    <button class="tab" data-tab="historico">Histórico</button>
    <button class="tab" data-tab="alertas">⚠ Alertas <span id="badge-alertas" class="badge" style="display:none"></span></button>
  </div>

  <!-- Aba Acervo -->
  <div id="tab-acervo" class="tab-panel active">
    <div class="search-section">
      <div class="search-header">
        <span class="section-label">ACERVO</span>
        <div class="search-wrap">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
          </svg>
          <input id="search" type="text" placeholder="Título, autor ou registro… " autocomplete="off">
        </div>
        <span class="tip" data-tip="Digite qualquer parte do título,&#10;autor ou número do registro.&#10;Maiúsculas e minúsculas são iguais.">?</span>
        <select id="filter-cat-acervo" class="filter-select">
          <option value="">— Todas as categorias —</option>
        </select>
        <span id="status-bar">carregando…</span>
      </div>
      <div class="sort-bar">
        <span class="sort-label">Ordenar:</span>
        <button class="sort-btn active" data-sort="nome" data-dir="asc">Título <span class="arrow">↑</span></button>
        <button class="sort-btn" data-sort="registro" data-dir="asc">Registro <span class="arrow">↑</span></button>
        <button class="sort-btn" data-sort="disponiveis" data-dir="desc">Disponíveis <span class="arrow">↓</span></button>
      </div>
      <div class="book-grid" id="grid">
        <p class="empty-state">Carregando acervo…</p>
      </div>
    </div>
  </div>

  <!-- Aba Busca (alunos + categoria) -->
  <div id="tab-alunos" class="tab-panel"></div>

  <!-- Aba Alertas -->
  <div id="tab-alertas" class="tab-panel"></div>

  <!-- Aba Histórico -->
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

<!-- ══ MODAL ══════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-overlay">
  <div class="modal" id="modal" role="dialog" aria-modal="true">

    <div class="modal-header">
      <div>
        <p class="modal-reg" id="modal-reg">—</p>
        <h2 class="modal-title" id="modal-title">—</h2>
        <p id="modal-prateleira" style="font-family:var(--font-mono);font-size:0.7rem;color:#c87941;margin-top:0.35rem;letter-spacing:0.05em;min-height:1rem;"></p>
      </div>
      <button class="modal-close" id="modal-close" type="button">✕ Fechar</button>
    </div>

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

    <div class="modal-body">
      <!-- Modal internal tabs -->
      <div class="modal-tabs">
        <button class="modal-tab active" data-panel="emprestimo">📖 Emprestar</button>
        <button class="modal-tab" data-panel="editar">✏️ Editar livro</button>
        <button class="modal-tab" data-panel="ativos">🔖 Empréstimos ativos</button>
      </div>

      <!-- Panel: emprestar -->
      <div class="modal-panel active" id="mpanel-emprestimo">
        <div class="loan-form-wrap">
          <p class="loan-form-title">— Registrar novo empréstimo</p>
          <div class="loan-form">
            <div class="loan-field">
              <label class="loan-label" for="loan-aluno">
                Nome do aluno
                <span class="tip" data-tip="Nome completo do aluno.&#10;Ex: João Silva">?</span>
              </label>
              <input class="loan-input" id="loan-aluno" type="text" placeholder="ex: João Silva" autocomplete="off">
            </div>
            <div class="loan-field" style="max-width:110px">
              <label class="loan-label" for="loan-sala">
                Sala / Turma
                <span class="tip" data-tip="Ex: 2 REG 3 ou 3B">?</span>
              </label>
              <input class="loan-input" id="loan-sala" type="text" placeholder="2 REG 3" autocomplete="off">
            </div>
            <div class="loan-field" style="max-width:190px">
              <label class="loan-label" for="loan-devol-preset">
                Devolução
                <span class="tip" data-tip="Escolha 5 ou 10 dias,&#10;ou selecione uma data específica.">?</span>
              </label>
              <select class="loan-select" id="loan-devol-preset">
                <option value="">— Escolha o prazo —</option>
                <option value="5">5 dias</option>
                <option value="10">10 dias</option>
                <option value="custom">Outra data…</option>
              </select>
            </div>
            <div class="loan-field" style="max-width:160px;display:none" id="loan-devol-custom-wrap">
              <label class="loan-label" for="loan-devol-custom">Data exata</label>
              <input class="loan-input" id="loan-devol-custom" type="date" autocomplete="off">
            </div>
          </div>
          <p id="loan-preview" style="font-family:var(--font-mono);font-size:0.6rem;color:#888;margin-top:0.5rem;min-height:1rem;"></p>
          <div style="margin-top:1rem">
            <button class="btn-loan" id="btn-emprestar" type="button">Emprestar →</button>
          </div>
          <p id="loan-msg" style="font-family:var(--font-mono);font-size:0.65rem;margin-top:0.75rem;color:#888;min-height:1rem;"></p>
        </div>
      </div>

      <!-- Panel: editar -->
      <div class="modal-panel" id="mpanel-editar">
        <div class="edit-form-wrap">
          <p class="edit-form-title">✏️ — Editar dados do livro</p>
          <div class="edit-grid">
            <div class="edit-field full">
              <label class="edit-label">Título</label>
              <input class="edit-input" id="edit-nome" type="text" placeholder="Título do livro">
            </div>
            <div class="edit-field">
              <label class="edit-label">Autor(es)</label>
              <input class="edit-input" id="edit-autor" type="text" placeholder="ex: Machado de Assis">
            </div>
            <div class="edit-field">
              <label class="edit-label">Editora</label>
              <input class="edit-input" id="edit-editora" type="text" placeholder="ex: Companhia das Letras">
            </div>
            <div class="edit-field">
              <label class="edit-label">Ano</label>
              <input class="edit-input" id="edit-ano" type="number" min="1000" max="2099" placeholder="ex: 2019">
            </div>
            <div class="edit-field">
              <label class="edit-label">Qtd. exemplares</label>
              <input class="edit-input" id="edit-quantidade" type="number" min="1" placeholder="ex: 3">
            </div>
            <div class="edit-field edit-ts">
              <label class="edit-label">Prateleira
                <span class="tip" data-tip="Digite ou escolha a prateleira.&#10;Ex: 2C, 3A">?</span>
              </label>
              <select id="edit-prateleira" class="edit-select">
                <option value="">— Sem prateleira —</option>
                <?php foreach(['A','B','C','D','E'] as $l) for($n=1;$n<=5;$n++): ?>
                <option value="<?=$l.$n?>"><?=$l.$n?></option>
                <?php endfor; ?>
              </select>
            </div>
            <div class="edit-field edit-ts">
              <label class="edit-label">Gênero / Classificação
                <span class="tip" data-tip="Categoria do livro.&#10;Você pode digitar um novo gênero.">?</span>
              </label>
              <select id="edit-faixa" class="edit-select">
                <option value="">— Sem categoria —</option>
                <option value="1A">1A — Infanto Juvenil</option>
                <option value="2A">2A — Conto</option>
                <option value="3A">3A — Ficção Científica</option>
                <option value="4A">4A — Romance</option>
                <option value="5A">5A — Literatura Brasileira</option>
                <option value="6A">6A — Poesia</option>
                <option value="guerra">Guerra</option>
                <option value="TODOS">Todas as idades</option>
              </select>
            </div>
          </div>
          <button class="btn-save" id="btn-salvar-edicao" type="button">💾 Salvar alterações</button>
          <p id="edit-msg" style="font-family:var(--font-mono);font-size:0.65rem;margin-top:0.75rem;color:#888;min-height:1rem;"></p>
        </div>

        <!-- Deletar livro -->
        <div style="background:#1a1a1a;border:1px solid #2a2a2a;border-left:3px solid #cc2200;padding:1.5rem;border-radius:var(--radius);">
          <p style="font-family:var(--font-mono);font-size:0.55rem;letter-spacing:0.25em;color:#cc2200;margin-bottom:0.75rem;text-transform:uppercase;">— Ação de emergência</p>
          <button id="btn-deletar" style="background:#cc2200;color:#fff;border:none;font-family:var(--font-mono);font-size:0.75rem;letter-spacing:0.15em;text-transform:uppercase;padding:0.75rem 1.5rem;cursor:pointer;width:100%;border-radius:var(--radius);transition:all 0.2s;">
            🗑️ Apagar livro do sistema
          </button>
          <p id="delete-msg" style="font-family:var(--font-mono);font-size:0.65rem;margin-top:0.75rem;color:#888;min-height:1.2rem;font-style:italic;">Remove permanentemente do banco.txt + todos os empréstimos relacionados.</p>
        </div>
      </div>

      <!-- Panel: empréstimos ativos -->
      <div class="modal-panel" id="mpanel-ativos">
        <div class="loans-header">
          <p class="loans-title">— Empréstimos ativos</p>
          <div class="loans-filters">
            <input class="filter-input" id="filter-aluno" type="text" placeholder="🔍 aluno ou sala…">
            <span class="tip" data-tip="Filtre por nome do aluno&#10;ou sala. Sem distinção de&#10;maiúsculas/minúsculas.">?</span>
          </div>
        </div>
        <div id="loans-list"><p class="no-loans">Nenhum empréstimo ativo.</p></div>
      </div>
    </div><!-- /modal-body -->
  </div>
</div>

<script>
(function () {
  'use strict';

  let modalRegistro = null;
  let modalLivroData = null;
  let searchTimer   = null;
  let sortField     = 'nome';
  let sortDir       = 'asc';
  let allLivros     = [];

  // ── TomSelect for edit fields ────────────────────────────────────────────
  let tsEditPrat = null, tsEditFaixa = null;

  // ── Helpers ────────────────────────────────────────────────────────────────
  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c =>
      ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }
  function fmtDate(iso) {
    if (!iso) return '—';
    const [y,m,d] = iso.split('-');
    return `${d}/${m}/${y}`;
  }
  function addDays(n) {
    const d = new Date(); d.setDate(d.getDate() + n);
    return d.toISOString().slice(0,10);
  }
  function diasRestantes(iso) {
    const [y, m, d] = iso.split('-').map(Number);
    const dev  = new Date(y, m - 1, d);
    const hoje = new Date(); hoje.setHours(0, 0, 0, 0);
    return Math.round((dev - hoje) / 86400000);
  }
  function post(dados) {
    const fd = new FormData();
    Object.entries(dados).forEach(([k,v]) => fd.append(k, v));
    return fetch(window.location.pathname, { method:'POST', body:fd }).then(r => r.json());
  }
  function normalizeStr(s) { return String(s ?? '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,''); }

  // ── SORT ──────────────────────────────────────────────────────────────────
  document.querySelectorAll('.sort-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      const newField = this.dataset.sort;
      if (sortField === newField) {
        sortDir = sortDir === 'asc' ? 'desc' : 'asc';
        this.dataset.dir = sortDir;
      } else {
        sortField = newField;
        sortDir   = this.dataset.dir || 'asc';
        document.querySelectorAll('.sort-btn').forEach(b => b.classList.remove('active'));
      }
      this.classList.add('active');
      this.querySelector('.arrow').textContent = sortDir === 'asc' ? '↑' : '↓';
      renderGrid(allLivros);
    });
  });

  function sortLivros(livros) {
    return [...livros].sort((a, b) => {
      let va = a[sortField], vb = b[sortField];
      if (typeof va === 'string') { va = normalizeStr(va); vb = normalizeStr(vb); }
      else { va = Number(va); vb = Number(vb); }
      if (va < vb) return sortDir === 'asc' ? -1 : 1;
      if (va > vb) return sortDir === 'asc' ?  1 : -1;
      return 0;
    });
  }

  // ── Grid ──────────────────────────────────────────────────────────────────
  const GENERO = {
    '1A': ['1A', 'Infanto Juvenil'], '2A': ['2A', 'Conto'],
    '3A': ['3A', 'Ficção Científica'], '4A': ['4A', 'Romance'],
    '5A': ['5A', 'Literatura Brasileira'], '6A': ['6A', 'Poesia'],
    'guerra': ['', 'Guerra'], 'TODOS': ['', 'Todas as idades'],
    'Infantil': ['', 'Infantil'], 'Jovem': ['', 'Jovem'], 'Adulto': ['', 'Adulto'],
  };

  function renderGrid(livros) {
    const grid   = document.getElementById('grid');
    const status = document.getElementById('status-bar');
    allLivros = livros;
    const sorted = sortLivros(livros);
    if (!sorted.length) {
      grid.innerHTML = '<p class="empty-state">Nenhum livro encontrado.</p>';
      status.textContent = '0 resultados';
      return;
    }
    status.textContent = `${sorted.length} livro${sorted.length !== 1 ? 's' : ''}`;
    grid.innerHTML = sorted.map(l => {
      const pct   = l.quantidade > 0 ? Math.round((l.disponiveis / l.quantidade) * 100) : 0;
      const cls   = l.disponiveis === 0 ? 'esgotado' : (l.disponiveis <= 1 ? 'alerta' : '');
      const label = l.disponiveis === 0
        ? '<span>0</span> disponíveis'
        : `<span>${l.disponiveis}</span> disponíve${l.disponiveis !== 1 ? 'is' : 'l'}`;
      const genInfo   = l.faixaEtaria ? (GENERO[l.faixaEtaria] || ['', l.faixaEtaria]) : null;
      const faixaBadge = genInfo
        ? `<div class="book-genre-badge">
             ${genInfo[0] ? `<span class="genre-code">${esc(genInfo[0])}</span>` : ''}
             <span class="genre-name">${esc(genInfo[1])}</span>
           </div>` : '';
      return `
        <article class="book-card" onclick="window._abrirModal('${esc(l.registro)}')">
          <div class="book-card-accent"></div>
          <div class="book-card-body">
            <p class="book-title">${esc(l.nome)}</p>
            <p class="book-reg">REG #${esc(l.registro)}</p>
            ${faixaBadge}
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
    const data = await post({ acao: 'procurar_livros', busca: q || '' });
    if (!data.success) { document.getElementById('grid').innerHTML = '<p class="empty-state">Erro ao carregar acervo.</p>'; return; }
    
    const cat = document.getElementById('filter-cat-acervo').value.toLowerCase();
    const livros = cat
      ? data.livros.filter(l => (l.faixaEtaria || '').toLowerCase() === cat)
      : data.livros;
    
    renderGrid(livros);
  } catch (e) {
    document.getElementById('grid').innerHTML = '<p class="empty-state">⚠ Erro de conexão.</p>';
  }
}

  document.getElementById('search').addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => buscarGrid(this.value.trim()), 300);
  });

  document.getElementById('filter-cat-acervo').addEventListener('change', function () {
    buscarGrid(document.getElementById('search').value.trim());
  });

  async function popularCatAcervo() {
    const data = await post({ acao: 'listar_categorias' });
    const sel  = document.getElementById('filter-cat-acervo');
    if (!data.success) return;
    data.categorias.forEach(c => {
      const g     = GENERO[c];
      const label = g ? (g[0] ? `${g[0]} — ${g[1]}` : g[1]) : c;
      const opt   = document.createElement('option');
      opt.value       = c;
      opt.textContent = label;
      sel.appendChild(opt);
    });
  }

  popularCatAcervo();
  buscarGrid('');

  // ── Modal tabs ────────────────────────────────────────────────────────────
  document.querySelectorAll('.modal-tab').forEach(btn => {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.modal-tab').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.modal-panel').forEach(p => p.classList.remove('active'));
      this.classList.add('active');
      document.getElementById('mpanel-' + this.dataset.panel).classList.add('active');
    });
  });

  // ── Modal ─────────────────────────────────────────────────────────────────
  const overlay  = document.getElementById('modal-overlay');
  const btnClose = document.getElementById('modal-close');

  function fecharModal() {
    overlay.classList.remove('open');
    modalRegistro  = null;
    modalLivroData = null;
    buscarGrid(document.getElementById('search').value.trim());
  }

  btnClose.addEventListener('click', fecharModal);
  overlay.addEventListener('click', e => { if (e.target === overlay) fecharModal(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') fecharModal(); });

  // ── Devolver data preset ───────────────────────────────────────────────────
  const presetSel    = document.getElementById('loan-devol-preset');
  const customWrap   = document.getElementById('loan-devol-custom-wrap');
  const customInput  = document.getElementById('loan-devol-custom');
  const loanPreview  = document.getElementById('loan-preview');

  function updatePreview() {
    const iso = getDevISO();
    if (iso) loanPreview.textContent = `Devolução: ${fmtDate(iso)} (${diasRestantes(iso)} dia${diasRestantes(iso) !== 1 ? 's' : ''} a partir de hoje)`;
    else     loanPreview.textContent = '';
  }

  function getDevISO() {
    const v = presetSel.value;
    if (v === '5')  return addDays(5);
    if (v === '10') return addDays(10);
    if (v === 'custom') return customInput.value || '';
    return '';
  }

  presetSel.addEventListener('change', function() {
    if (this.value === 'custom') {
      customWrap.style.display = '';
      customInput.min = addDays(1);
      customInput.focus();
    } else {
      customWrap.style.display = 'none';
    }
    updatePreview();
  });
  customInput.addEventListener('change', updatePreview);

  // ── Emprestar ─────────────────────────────────────────────────────────────
  document.getElementById('btn-emprestar').addEventListener('click', async () => {
    const aluno    = document.getElementById('loan-aluno').value.trim();
    const sala     = document.getElementById('loan-sala').value.trim();
    const devolucao = getDevISO();
    const msg      = document.getElementById('loan-msg');

    if (!aluno) {
      msg.textContent = '⚠ Preencha o nome do aluno.'; msg.style.color = '#cc6600'; return;
    }
    if (!devolucao) {
      msg.textContent = '⚠ Escolha o prazo de devolução.'; msg.style.color = '#cc6600'; return;
    }
    if (devolucao <= new Date().toISOString().slice(0,10)) {
      msg.textContent = '⚠ A data de devolução deve ser a partir de amanhã.'; msg.style.color = '#cc6600'; return;
    }
    const data = await post({ acao:'emprestar_livro', registro:modalRegistro, aluno, sala, devolucao });
    msg.textContent = data.msg;
    msg.style.color = data.success ? '#4caf7d' : '#cc4400';
    if (data.success) {
      document.getElementById('loan-aluno').value = '';
      document.getElementById('loan-sala').value  = '';
      presetSel.value = '';
      customWrap.style.display = 'none';
      loanPreview.textContent = '';
      await abrirModal(modalRegistro);
    }
  });

  // ── Filtro empréstimos ativos ──────────────────────────────────────────────
  function aplicarFiltros() {
    const q = document.getElementById('filter-aluno').value.trim().toLowerCase();
    document.querySelectorAll('#loans-list .loan-row').forEach(row => {
      const aluno = normalizeStr(row.dataset.aluno || '');
      const sala  = normalizeStr(row.dataset.sala  || '');
      row.classList.toggle('hidden', q && !aluno.includes(normalizeStr(q)) && !sala.includes(normalizeStr(q)));
    });
  }
  document.getElementById('filter-aluno').addEventListener('input', aplicarFiltros);

  // ── Render loans list ──────────────────────────────────────────────────────
  function renderLoans(livro) {
    const genInfo  = livro.faixaEtaria ? (GENERO[livro.faixaEtaria] || ['', livro.faixaEtaria]) : null;
    const genLabel = genInfo ? (genInfo[0] ? `${genInfo[0]} — ${genInfo[1]}` : genInfo[1]) : '';
    const extras   = [livro.autor ? `Autor: ${livro.autor}` : '', genLabel ? `Gênero: ${genLabel}` : ''].filter(Boolean).join(' · ');

    document.getElementById('modal-reg').textContent     = `REG #${livro.registro}` + (extras ? ` · ${extras}` : '');
    document.getElementById('modal-title').textContent   = livro.nome;
    document.getElementById('modal-prateleira').textContent = livro.prateleira ? `📚 Prateleira: ${livro.prateleira}` : '';
    document.getElementById('stat-total').textContent    = livro.quantidade;

    const elEmp  = document.getElementById('stat-emprestados');
    elEmp.textContent = livro.emprestados;
    elEmp.className   = 'stat-value' + (livro.emprestados > 0 ? ' warn' : '');

    const elDisp = document.getElementById('stat-disponiveis');
    elDisp.textContent = livro.disponiveis;
    elDisp.className   = 'stat-value ' + (livro.disponiveis === 0 ? 'bad' : livro.disponiveis <= 1 ? 'warn' : 'ok');

    document.getElementById('filter-aluno').value = '';

    // Populate edit form
    document.getElementById('edit-nome').value      = livro.nome        || '';
    document.getElementById('edit-autor').value     = livro.autor       || '';
    document.getElementById('edit-editora').value   = livro.editora     || '';
    document.getElementById('edit-ano').value       = livro.ano         || '';
    document.getElementById('edit-quantidade').value= livro.quantidade  || '';
    // TomSelect: destroy & recreate
    if (tsEditPrat)  { tsEditPrat.destroy();  tsEditPrat  = null; }
    if (tsEditFaixa) { tsEditFaixa.destroy(); tsEditFaixa = null; }
    // Reset select values before recreating
    const selPrat = document.getElementById('edit-prateleira');
    const selFaixa = document.getElementById('edit-faixa');
    selPrat.value  = livro.prateleira  || '';
    selFaixa.value = livro.faixaEtaria || '';

    tsEditPrat = new TomSelect('#edit-prateleira', {
      create: true, sortField: false, placeholder: '— Selecionar ou digitar —'
    });
    tsEditFaixa = new TomSelect('#edit-faixa', {
      create: true, sortField: false, placeholder: '— Selecionar ou digitar —'
    });
    // Restore values after TomSelect init
    tsEditPrat.setValue(livro.prateleira   || '');
    tsEditFaixa.setValue(livro.faixaEtaria || '');

    document.getElementById('edit-msg').textContent = '';

    const lista = document.getElementById('loans-list');
    if (!livro.emprestimos || livro.emprestimos.length === 0) {
      lista.innerHTML = '<p class="no-loans">Nenhum empréstimo ativo no momento.</p>';
      return;
    }
    lista.innerHTML = livro.emprestimos.map(e => {
      const dias = diasRestantes(e.devolucao);
      let cls = '', info = `Devolver até ${fmtDate(e.devolucao)}`;
      if (dias < 0)        { cls = 'atrasado'; info = `⚠ Atrasado ${Math.abs(dias)} dia${Math.abs(dias) !== 1 ? 's' : ''}`; }
      else if (dias === 0) { cls = 'hoje';     info = '⚠ Devolver HOJE'; }
      else if (dias <= 2)    info = `Em ${dias} dia${dias !== 1 ? 's' : ''} (${fmtDate(e.devolucao)})`;
      const sala = e.sala ? ` — ${esc(e.sala)}` : '';
      return `
        <div class="loan-row" id="row-${esc(e.id)}"
             data-aluno="${esc(e.aluno)}" data-sala="${esc(e.sala||'')}" data-devol="${esc(e.devolucao)}">
          <div>
            <p class="loan-aluno">${esc(e.aluno)}<span class="loan-sala">${sala}</span></p>
            <p class="loan-date">Retirada: ${fmtDate(e.retirada)}</p>
          </div>
          <p class="loan-devol ${cls}">${info}</p>
          <button class="btn-renovar" type="button" onclick="window._abrirRenovar('${esc(e.id)}', this)">🔄 Renovar</button>
          <button class="btn-devolver" type="button" onclick="window._devolver('${esc(e.id)}')">✓ Devolvido</button>
        </div>
        <div class="renovar-inline" id="renovar-${esc(e.id)}">
          <label>Prazo:</label>
          <select id="rsel-${esc(e.id)}" onchange="window._updateRenovarInput('${esc(e.id)}')">
            <option value="">— escolha —</option>
            <option value="5">5 dias</option>
            <option value="10">10 dias</option>
            <option value="custom">Outra data…</option>
          </select>
          <input type="date" id="rdate-${esc(e.id)}" style="display:none" min="${addDays(1)}">
          <button class="btn-renovar-confirm" onclick="window._confirmarRenovar('${esc(e.id)}')">Confirmar</button>
          <button class="btn-renovar-cancel" onclick="window._fecharRenovar('${esc(e.id)}')">Cancelar</button>
          <span class="renovar-msg" id="rmsg-${esc(e.id)}"></span>
        </div>`;
    }).join('');
  }

  async function abrirModal(registro) {
    modalRegistro = registro;
    document.getElementById('loan-aluno').value = '';
    document.getElementById('loan-sala').value  = '';
    document.getElementById('loan-msg').textContent  = '';
    presetSel.value = '';
    customWrap.style.display = 'none';
    loanPreview.textContent = '';

    // reset to first modal tab
    document.querySelectorAll('.modal-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.modal-panel').forEach(p => p.classList.remove('active'));
    document.querySelector('.modal-tab[data-panel="emprestimo"]').classList.add('active');
    document.getElementById('mpanel-emprestimo').classList.add('active');

    try {
      const data = await post({ acao: 'detalhes_livro', registro });
      if (data.success) {
        modalLivroData = data.livro;
        renderLoans(data.livro);
        overlay.classList.add('open');
        setTimeout(() => document.getElementById('loan-aluno').focus(), 300);
      } else {
        alert(data.msg || 'Erro ao carregar livro.');
      }
    } catch (e) { console.error('Erro ao abrir modal:', e); }
  }

  window._abrirModal = abrirModal;

  // ── Devolver ───────────────────────────────────────────────────────────────
  window._devolver = async function (id) {
    if (!confirm('Confirmar devolução deste empréstimo?')) return;
    const data = await post({ acao: 'devolver_livro', id });
    if (data.success) await abrirModal(modalRegistro);
    else alert(data.msg);
  };

  // ── Renovar inline ─────────────────────────────────────────────────────────
  window._abrirRenovar = function(id, btn) {
    // close any other open renovar panels
    document.querySelectorAll('.renovar-inline.visible').forEach(el => el.classList.remove('visible'));
    document.getElementById('renovar-' + id).classList.add('visible');
  };

  window._fecharRenovar = function(id) {
    document.getElementById('renovar-' + id).classList.remove('visible');
  };

  window._updateRenovarInput = function(id) {
    const sel = document.getElementById('rsel-' + id);
    const inp = document.getElementById('rdate-' + id);
    inp.style.display = sel.value === 'custom' ? '' : 'none';
  };

  window._confirmarRenovar = async function(id) {
    const sel  = document.getElementById('rsel-' + id);
    const inp  = document.getElementById('rdate-' + id);
    const msg  = document.getElementById('rmsg-' + id);
    let devolucao = '';
    if      (sel.value === '5')      devolucao = addDays(5);
    else if (sel.value === '10')     devolucao = addDays(10);
    else if (sel.value === 'custom') devolucao = inp.value;

    if (!devolucao) { msg.textContent = '⚠ Escolha uma data.'; msg.style.color = '#cc6600'; return; }
    if (devolucao <= new Date().toISOString().slice(0,10)) {
      msg.textContent = '⚠ Data deve ser futura.'; msg.style.color = '#cc6600'; return;
    }
    msg.textContent = '…'; msg.style.color = '#888';
    const data = await post({ acao: 'renovar_emprestimo', id, devolucao });
    if (data.success) {
      msg.textContent = '✓ ' + data.msg; msg.style.color = '#4caf7d';
      setTimeout(() => abrirModal(modalRegistro), 800);
    } else {
      msg.textContent = '⚠ ' + data.msg; msg.style.color = '#cc4400';
    }
  };

  // ── Salvar edição ──────────────────────────────────────────────────────────
  document.getElementById('btn-salvar-edicao').addEventListener('click', async () => {
    const nome       = document.getElementById('edit-nome').value.trim();
    const autor      = document.getElementById('edit-autor').value.trim();
    const editora    = document.getElementById('edit-editora').value.trim();
    const ano        = document.getElementById('edit-ano').value.trim();
    const quantidade = document.getElementById('edit-quantidade').value.trim();
    const prateleira = tsEditPrat  ? tsEditPrat.getValue()  : document.getElementById('edit-prateleira').value;
    const faixa      = tsEditFaixa ? tsEditFaixa.getValue() : document.getElementById('edit-faixa').value;
    const msg        = document.getElementById('edit-msg');

    if (!nome || !quantidade) {
      msg.textContent = '⚠ Título e quantidade são obrigatórios.'; msg.style.color = '#cc6600'; return;
    }
    msg.textContent = '…'; msg.style.color = '#888';
    const data = await post({
      acao: 'editar_livro', registro: modalRegistro,
      nome, autor, editora, ano, quantidade,
      prateleira, faixa_etaria: faixa
    });
    msg.textContent = data.success ? '✓ ' + data.msg : '⚠ ' + data.msg;
    msg.style.color = data.success ? '#4caf7d' : '#cc4400';
    if (data.success) {
      document.getElementById('modal-title').textContent = nome;
      buscarGrid(document.getElementById('search').value.trim());
    }
  });

  // ── Deletar ────────────────────────────────────────────────────────────────
  document.getElementById('btn-deletar').addEventListener('click', async () => {
    if (!confirm(`⚠️ APAGAR LIVRO PERMANENTEMENTE\n\nREG #${modalRegistro}\n"${document.getElementById('modal-title').textContent}"\n\nCONFIRMAR?`)) return;
    const btn = document.getElementById('btn-deletar');
    const msg = document.getElementById('delete-msg');
    btn.textContent = '⏳ Apagando...'; btn.disabled = true;
    try {
      const data = await post({ acao: 'deletar_livro', registro: modalRegistro });
      msg.textContent = data.msg; msg.style.color = data.success ? '#4caf7d' : '#cc4400';
      if (data.success) setTimeout(fecharModal, 1500);
      else { btn.textContent = '🗑️ Apagar livro do sistema'; btn.disabled = false; }
    } catch {
      msg.textContent = 'Erro de conexão'; msg.style.color = '#cc4400';
      btn.textContent = '🗑️ Apagar livro do sistema'; btn.disabled = false;
    }
  });

  // ── Abas ───────────────────────────────────────────────────────────────────
  document.querySelectorAll('.tab').forEach(btn => {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
      this.classList.add('active');
      document.getElementById('tab-' + this.dataset.tab).classList.add('active');
      if (this.dataset.tab === 'alunos')    iniciarBusca();
      if (this.dataset.tab === 'alertas')   carregarAlertas();
      if (this.dataset.tab === 'historico') iniciarHistorico();
    });
  });

  // ── Aba: Busca (alunos + categoria) ─────────────────────────────────────────
  let buscaIniciada = false;
  let catSelect = null;

  async function iniciarBusca() {
    if (!buscaIniciada) {
      buscaIniciada = true;

      document.getElementById('tab-alunos').innerHTML = `
        <div class="search-header" style="margin-bottom:1.5rem;flex-wrap:wrap;gap:0.75rem">
          <span class="section-label">BUSCA</span>
          <div class="search-wrap">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
            </svg>
            <input id="search-aluno" type="text" placeholder="Nome, sala ou título do livro… )" autocomplete="off">
          </div>
          <span class="tip" data-tip="Busca por aluno, sala/turma&#10;ou título do livro.&#10;Não diferencia maiúsculas.">?</span>
          
          <span id="count-alunos" style="font-family:var(--font-mono);font-size:0.6rem;color:#666"></span>
        </div>
        <div id="resultado-alunos"></div>`;

      document.getElementById('search-aluno').addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => carregarAlunos(this.value.trim(), document.getElementById('filter-cat').value), 300);
      });
      
    }
    carregarAlunos('', '');
  }

  async function carregarAlunos(q, cat) {
    const resultado = document.getElementById('resultado-alunos');
    const contador  = document.getElementById('count-alunos');
    if (!resultado) return;
    const data = await post({ acao: 'buscar_aluno', busca: q, categoria: cat });
    if (!data.success) return;
    const emps = data.emprestimos;
    if (contador) contador.textContent = `${emps.length} empréstimo${emps.length !== 1 ? 's' : ''} ativo${emps.length !== 1 ? 's' : ''}`;
    resultado.innerHTML = emps.length === 0
      ? '<p class="empty-state" style="padding:2rem;color:#333;font-style:italic;font-family:var(--font-mono);font-size:0.75rem">Nenhum empréstimo encontrado.</p>'
      : `<div class="book-grid" style="grid-template-columns:1fr">
          ${emps.map(e => {
            const dias = diasRestantes(e.devolucao);
            let cls = '', info = `Devolver até ${fmtDate(e.devolucao)}`;
            if (dias < 0)        { cls = 'atrasado'; info = `⚠ Atrasado ${Math.abs(dias)} dia${Math.abs(dias) !== 1 ? 's' : ''}`; }
            else if (dias === 0) { cls = 'hoje';     info = '⚠ Devolver HOJE'; }
            else if (dias <= 3)    info = `Em ${dias} dia${dias !== 1 ? 's' : ''} (${fmtDate(e.devolucao)})`;
            const genInfo = e.faixaEtaria ? (GENERO[e.faixaEtaria] || ['', e.faixaEtaria]) : null;
            const catBadge = genInfo
              ? `<span style="font-family:var(--font-mono);font-size:0.6rem;color:#c87941;margin-left:0.5rem">
                   [${esc(genInfo[0] || genInfo[1])}]
                 </span>` : '';
            return `
              <article class="book-card" onclick="window._abrirModal('${esc(e.registro)}')">
                <div class="book-card-accent"></div>
                <div class="book-card-body" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem">
                  <div>
                    <p class="book-title">${esc(e.aluno)}${e.sala ? ` <span style="font-size:0.7rem;font-family:var(--font-mono);color:#888">— ${esc(e.sala)}</span>` : ''}</p>
                    <p class="book-reg">${esc(e.livro)}${catBadge} · REG #${esc(e.registro)} · Retirada: ${fmtDate(e.retirada)}</p>
                  </div>
                  <p class="loan-devol ${cls}" style="font-family:var(--font-mono);font-size:0.65rem">${info}</p>
                </div>
              </article>`;
          }).join('')}
        </div>`;
  }

  // ── Aba: Histórico ──────────────────────────────────────────────────────────
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
      searchTimer = setTimeout(() => carregarHistorico(this.value.trim(), document.getElementById('filter-ano-hist').value), 300);
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
    const data = await post({ acao: 'buscar_historico', busca: q, ano });
    if (!data.success) return;
    const regs = data.registros;
    if (contador) contador.textContent = `${regs.length} empréstimo${regs.length !== 1 ? 's' : ''}`;
    resultado.innerHTML = regs.length === 0
      ? '<p class="empty-state" style="padding:2rem;color:#333;font-style:italic;font-family:var(--font-mono);font-size:0.75rem">Nenhum registro encontrado.</p>'
      : `<div class="book-grid" style="grid-template-columns:1fr">
          ${regs.map(e => `
            <article class="book-card" onclick="window._abrirModal('${esc(e.registro)}')">
              <div class="book-card-accent"></div>
              <div class="book-card-body" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem">
                <div>
                  <p class="book-title">${esc(e.aluno)}${e.sala ? ` <span style="font-size:0.7rem;font-family:var(--font-mono);color:#888">— ${esc(e.sala)}</span>` : ''}</p>
                  <p class="book-reg">${esc(e.livro)} · REG #${esc(e.registro)}</p>
                </div>
                <div style="text-align:right">
                  <p style="font-family:var(--font-mono);font-size:0.6rem;color:#666">Retirada: ${fmtDate(e.retirada)}</p>
                  <p style="font-family:var(--font-mono);font-size:0.6rem;color:#555">Dev: ${fmtDate(e.devolucao)}</p>
                </div>
              </div>
            </article>`).join('')}
        </div>`;
    ranking.innerHTML = !data.ranking.length ? '' : `
      <div style="background:#111;border:1px solid #1e1e1e;border-top:3px solid var(--rust);padding:1.5rem">
        <p style="font-family:var(--font-mono);font-size:0.55rem;letter-spacing:0.25em;color:var(--rust);margin-bottom:1.25rem;text-transform:uppercase">— Top leitores</p>
        ${data.ranking.map((r,i) => `
          <div style="display:flex;justify-content:space-between;align-items:center;padding:0.6rem 0;border-bottom:1px solid #1a1a1a">
            <div>
              <span style="font-family:var(--font-mono);font-size:0.55rem;color:#555;margin-right:0.5rem">#${i+1}</span>
              <span style="font-family:var(--font-display);font-size:0.85rem;color:#ddd">${esc(r.aluno)}</span>
            </div>
            <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--rust);font-weight:500">${r.total} livro${r.total !== 1 ? 's' : ''}</span>
          </div>`).join('')}
      </div>`;
  }

  // ── Aba: Alertas ────────────────────────────────────────────────────────────
  async function carregarAlertas() {
    const panel = document.getElementById('tab-alertas');
    const data  = await post({ acao: 'buscar_aluno', busca: '' });
    if (!data.success) return;

    const hoje      = data.emprestimos.filter(e => diasRestantes(e.devolucao) === 0);
    const atrasados = data.emprestimos.filter(e => diasRestantes(e.devolucao) < 0);
    const proximos  = data.emprestimos.filter(e => { const d = diasRestantes(e.devolucao); return d > 0 && d <= 3; });

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
              if      (dias < 0)  label = `⚠ Atrasado ${Math.abs(dias)} dia${Math.abs(dias) !== 1 ? 's' : ''}`;
              else if (dias === 0) label = '⚠ Devolver HOJE';
              else                 label = `Devolver até ${fmtDate(e.devolucao)}`;
              return `
                <article class="book-card" onclick="window._abrirModal('${esc(e.registro)}')">
                  <div class="book-card-accent" style="background:${cor}"></div>
                  <div class="book-card-body" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem">
                    <div>
                      <p class="book-title">${esc(e.aluno)}${e.sala ? ` <span style="font-size:0.7rem;font-family:var(--font-mono);color:#888">— ${esc(e.sala)}</span>` : ''}</p>
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
</script>
</body>
</html>