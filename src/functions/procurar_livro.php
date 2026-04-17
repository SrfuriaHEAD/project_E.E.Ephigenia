<?php
// src/functions/procurar_livro.php
// Ações: procurar_livros | buscar_aluno | detalhes_livro
// Depende de $arquivo e $arqEmprestimos definidos no index.php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
$acao = $_POST['acao'] ?? '';
if (!in_array($acao, ['procurar_livros', 'buscar_aluno', 'detalhes_livro'])) return;

ob_clean();
header('Content-Type: application/json; charset=utf-8');

// ── helpers ───────────────────────────────────────────────────────────────────

function parse_livros(string $arquivo): array {
    if (!file_exists($arquivo)) return [];
    $livros = [];
    foreach (file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
        if (str_starts_with($linha, '---')) continue;
        if (!preg_match('/Nome:\s*(.+?)\s*\|\s*Registro:\s*([^\|]+?)\s*\|\s*Quantidade:\s*(\d+)/', $linha, $m)) continue;

        $nome     = trim($m[1]);
        $registro = trim($m[2]);
        $qtd      = (int)$m[3];

        $autor = '';
        if (preg_match('/Autor:\s*(.+?)(?:\s*\||$)/', $linha, $ma)) $autor = trim($ma[1]);
        $editora = '';
        if (preg_match('/Editora:\s*(.+?)(?:\s*\||$)/', $linha, $me)) $editora = trim($me[1]);
        $ano = '';
        if (preg_match('/Ano:\s*(\d+)/', $linha, $mano)) $ano = trim($mano[1]);
        $prateleira = '';
        if (preg_match('/Prateleira:\s*(.+?)(?:\s*\||$)/', $linha, $mp)) $prateleira = trim($mp[1]);
        $faixaEtaria = '';
        if (preg_match('/FaixaEtaria:\s*(.+?)(?:\s*\||$)/', $linha, $mf)) $faixaEtaria = trim($mf[1]);

        $livros[] = [
            'nome'        => $nome,
            'registro'    => $registro,
            'quantidade'  => $qtd,
            'autor'       => $autor,
            'editora'     => $editora,
            'ano'         => $ano,
            'prateleira'  => $prateleira,
            'faixaEtaria' => $faixaEtaria,
        ];
    }
    return $livros;
}

function parse_emprestimos(string $arquivo): array {
    if (!file_exists($arquivo)) return [];
    $lista = [];
    foreach (file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
        if (str_starts_with($linha, '---')) continue;
        if (preg_match('/ID:\s*(\S+)\s*\|\s*Registro:\s*(\S+)\s*\|\s*Aluno:\s*(.+?)\s*\|\s*Sala:\s*(.*?)\s*\|\s*Retirada:\s*(\S+)\s*\|\s*Devolucao:\s*(\S+)/', $linha, $m)) {
            $lista[] = [
                'id'        => $m[1],
                'registro'  => $m[2],
                'aluno'     => $m[3],
                'sala'      => $m[4],
                'retirada'  => $m[5],
                'devolucao' => $m[6],
            ];
        }
    }
    return $lista;
}

// ── ação: listar/buscar ───────────────────────────────────────────────────────

if ($acao === 'procurar_livros') {
    $busca       = strtolower(trim($_POST['busca'] ?? ''));
    $livros      = parse_livros($arquivo);
    $emprestimos = parse_emprestimos($arqEmprestimos);

    $empCount = [];
    foreach ($emprestimos as $e) {
        $empCount[$e['registro']] = ($empCount[$e['registro']] ?? 0) + 1;
    }
    foreach ($livros as &$l) {
        $l['emprestados'] = $empCount[$l['registro']] ?? 0;
        $l['disponiveis'] = max(0, $l['quantidade'] - $l['emprestados']);
    }
    unset($l);

    if ($busca !== '') {
        $livros = array_values(array_filter(
            $livros,
            fn($l) => str_contains(strtolower($l['nome']), $busca)
                   || str_contains(strtolower($l['registro']), $busca)
                   || str_contains(strtolower($l['autor']), $busca)
        ));
    }
    echo json_encode(['success' => true, 'livros' => $livros], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── ação: buscar por aluno ────────────────────────────────────────────────────

if ($acao === 'buscar_aluno') {
    $busca  = strtolower(trim($_POST['busca'] ?? ''));
    $livros = parse_livros($arquivo);
    $emps   = parse_emprestimos($arqEmprestimos);

    $idx = [];
    foreach ($livros as $l) $idx[$l['registro']] = $l['nome'];

    $resultado = [];
    foreach ($emps as $e) {
        if (!$busca
            || str_contains(strtolower($e['aluno']),      $busca)
            || str_contains(strtolower($e['sala'] ?? ''), $busca)
        ) {
            $resultado[] = array_merge($e, ['livro' => $idx[$e['registro']] ?? 'Livro desconhecido']);
        }
    }
    echo json_encode(['success' => true, 'emprestimos' => $resultado], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── ação: detalhes + empréstimos de um livro ─────────────────────────────────

if ($acao === 'detalhes_livro') {
    $registro    = trim($_POST['registro'] ?? '');
    $livros      = parse_livros($arquivo);
    $emprestimos = parse_emprestimos($arqEmprestimos);

    $livro = null;
    foreach ($livros as $l) {
        if ($l['registro'] === $registro) { $livro = $l; break; }
    }

    if (!$livro) {
        echo json_encode(['success' => false, 'msg' => 'Livro não encontrado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $ativos = array_values(array_filter($emprestimos, fn($e) => $e['registro'] === $registro));
    $livro['emprestados'] = count($ativos);
    $livro['disponiveis'] = max(0, $livro['quantidade'] - $livro['emprestados']);
    $livro['emprestimos'] = $ativos;

    echo json_encode(['success' => true, 'livro' => $livro], JSON_UNESCAPED_UNICODE);
    exit;
}