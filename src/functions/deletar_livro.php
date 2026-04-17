<?php
function deletarLivro($registro) {
    global $arquivo, $arqEmprestimos;

    if (!file_exists($arquivo))
        return ['success' => false, 'msg' => 'Banco de dados não encontrado.'];

    $livros = [];
    $achou  = false;

    foreach (file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
        if (str_starts_with($linha, '---')) { $livros[] = $linha; continue; }
        // Extrai o registro da linha
        preg_match('/Registro:\s*([^\|]+)/i', $linha, $m);
        $reg = trim($m[1] ?? '');
        if ($reg === $registro) { $achou = true; continue; }
        $livros[] = $linha;
    }

    $emprestimos = [];
    if (file_exists($arqEmprestimos)) {
        foreach (file($arqEmprestimos, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
            if (str_starts_with($linha, '---')) { $emprestimos[] = $linha; continue; }
            preg_match('/Registro:\s*([^\|]+)/i', $linha, $m);
            $reg = trim($m[1] ?? '');
            if ($reg === $registro) continue; // remove
            $emprestimos[] = $linha;
        }
    }

    file_put_contents($arquivo,       implode(PHP_EOL, $livros)       . PHP_EOL);
    file_put_contents($arqEmprestimos, implode(PHP_EOL, $emprestimos) . PHP_EOL);

    return [
        'success' => $achou,
        'msg'     => $achou
            ? "Livro REG #$registro apagado com sucesso!"
            : "Livro REG #$registro não encontrado."
    ];
}
?>