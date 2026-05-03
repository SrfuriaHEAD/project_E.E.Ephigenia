<?php
session_start();

$diretorio      = __DIR__ . '/db';
$arquivo        = $diretorio . '/banco.txt';
$arqEmprestimos = $diretorio . '/emprestimos.txt';
$arqHistorico   = $diretorio . '/historico.txt';

// ── Parsers ────────────────────────────────────────────────────────────────
function parse_livros_dash(string $arquivo): array {
    if (!file_exists($arquivo)) return [];
    $livros = [];
    foreach (file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
        if (str_starts_with($linha, '---')) continue;
        if (!preg_match('/Nome:\s*(.+?)\s*\|\s*Registro:\s*([^\|]+?)\s*\|\s*Quantidade:\s*(\d+)/', $linha, $m)) continue;
        $l = ['nome' => trim($m[1]), 'registro' => trim($m[2]), 'quantidade' => (int)$m[3],
              'autor' => '', 'prateleira' => '', 'faixaEtaria' => ''];
        if (preg_match('/Autor:\s*(.+?)(?:\s*\||$)/', $linha, $ma))      $l['autor']       = trim($ma[1]);
        if (preg_match('/Prateleira:\s*(.+?)(?:\s*\||$)/', $linha, $mp)) $l['prateleira']  = trim($mp[1]);
        if (preg_match('/FaixaEtaria:\s*(.+?)(?:\s*\||$)/', $linha, $mf))$l['faixaEtaria'] = trim($mf[1]);
        $livros[] = $l;
    }
    return $livros;
}

function parse_emprestimos_dash(string $arquivo): array {
    if (!file_exists($arquivo)) return [];
    $lista = [];
    foreach (file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
        if (str_starts_with($linha, '---')) continue;
        if (preg_match('/ID:\s*(\S+)\s*\|\s*Registro:\s*(\S+)\s*\|\s*Aluno:\s*(.+?)\s*\|\s*Sala:\s*(.*?)\s*\|\s*Retirada:\s*(\S+)\s*\|\s*Devolucao:\s*(\S+)/', $linha, $m)) {
            $lista[] = ['id'=>$m[1],'registro'=>$m[2],'aluno'=>$m[3],'sala'=>$m[4],'retirada'=>$m[5],'devolucao'=>$m[6]];
        }
    }
    return $lista;
}

function parse_historico_dash(string $arquivo): array {
    if (!file_exists($arquivo)) return [];
    $lista = [];
    foreach (file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
        if (str_starts_with($linha, '---')) continue;
        if (preg_match('/ID:\s*(\S+)\s*\|\s*Registro:\s*(\S+)\s*\|\s*Livro:\s*(.+?)\s*\|\s*Aluno:\s*(.+?)\s*\|\s*Sala:\s*(.*?)\s*\|\s*Retirada:\s*(\S+)\s*\|\s*Devolucao:\s*(\S+)\s*\|\s*Ano:\s*(\d+)/', $linha, $m)) {
            $lista[] = ['id'=>$m[1],'registro'=>$m[2],'livro'=>$m[3],'aluno'=>$m[4],'sala'=>$m[5],'retirada'=>$m[6],'devolucao'=>$m[7],'ano'=>$m[8]];
        }
    }
    return $lista;
}

$livros      = parse_livros_dash($arquivo);
$emprestimos = parse_emprestimos_dash($arqEmprestimos);
$historico   = parse_historico_dash($arqHistorico);
$hoje        = date('Y-m-d');

// ── Métricas ───────────────────────────────────────────────────────────────
$totalTitulos   = count($livros);
$totalExemplares= array_sum(array_column($livros, 'quantidade'));
$totalEmprestados = count($emprestimos);
$totalDisponiveis = $totalExemplares - $totalEmprestados;

$alunosAtivos = count(array_unique(array_column($emprestimos, 'aluno')));

$emAtraso = array_filter($emprestimos, fn($e) => $e['devolucao'] < $hoje);
$vencendo  = array_filter($emprestimos, fn($e) => $e['devolucao'] >= $hoje && $e['devolucao'] <= date('Y-m-d', strtotime('+7 days')));

// ── Empréstimos por registro (contagem) ────────────────────────────────────
$empCount = [];
foreach ($emprestimos as $e) $empCount[$e['registro']] = ($empCount[$e['registro']] ?? 0) + 1;

// ── Gráfico 1: Faixa Etária ────────────────────────────────────────────────
$faixas = [];
foreach ($livros as $l) {
    $f = $l['faixaEtaria'] ?: 'Sem categoria';
    $faixas[$f] = ($faixas[$f] ?? 0) + 1;
}
arsort($faixas);

// ── Gráfico 2: Por prateleira ──────────────────────────────────────────────
$prateleiras_count = [];
foreach ($livros as $l) {
    $p = $l['prateleira'] ?: 'Sem prateleira';
    $prateleiras_count[$p] = ($prateleiras_count[$p] ?? 0) + 1;
}
ksort($prateleiras_count);

// ── Gráfico 3: Disponíveis vs emprestados (top 12) ─────────────────────────
$livrosDisp = [];
foreach ($livros as $l) {
    $emp = $empCount[$l['registro']] ?? 0;
    $livrosDisp[] = ['nome'=>substr($l['nome'],0,28), 'total'=>$l['quantidade'], 'emp'=>$emp, 'disp'=>max(0,$l['quantidade']-$emp)];
}
usort($livrosDisp, fn($a,$b) => $b['emp'] - $a['emp']);
$top12 = array_slice($livrosDisp, 0, 12);

// ── Gráfico 4: Por sala ─────────────────────────────────────────────────────
$salas = [];
foreach ($emprestimos as $e) {
    $s = $e['sala'] ?: 'Sem sala';
    $salas[$s] = ($salas[$s] ?? 0) + 1;
}
ksort($salas);

// ── Ranking mais emprestados (histórico) ────────────────────────────────────
$rankLivros = [];
foreach ($historico as $h) $rankLivros[$h['livro']] = ($rankLivros[$h['livro']] ?? 0) + 1;
foreach ($emprestimos as $e) {
    $nome = '';
    foreach ($livros as $l) { if ($l['registro'] === $e['registro']) { $nome = $l['nome']; break; } }
    if ($nome) $rankLivros[$nome] = ($rankLivros[$nome] ?? 0) + 0; // já conta no histórico
}
arsort($rankLivros);
$topLivros = array_slice($rankLivros, 0, 8, true);
$maxRank   = max(array_values($topLivros) ?: [1]);

// ── Prateleiras para mapa visual ──────────────────────────────────────────
// Organiza: colunas A-E, andares 1-5
$colunas = ['A','B','C','D','E'];
$andares  = [1,2,3,4,5];
$mapaPrateleiras = [];
foreach ($livros as $l) {
    $p = strtoupper($l['prateleira']);
    if (preg_match('/^([A-E])(\d)$/', $p, $pm)) {
        $col   = $pm[1];
        $andar = (int)$pm[2];
        if (!isset($mapaPrateleiras[$col][$andar])) $mapaPrateleiras[$col][$andar] = [];
        $mapaPrateleiras[$col][$andar][] = $l['nome'];
    }
}

// ── Dados para JS ──────────────────────────────────────────────────────────
$jsLivros      = json_encode($livros,      JSON_UNESCAPED_UNICODE);
$jsEmprestimos = json_encode($emprestimos, JSON_UNESCAPED_UNICODE);
$jsHistorico   = json_encode($historico,   JSON_UNESCAPED_UNICODE);
$jsFaixas      = json_encode($faixas,      JSON_UNESCAPED_UNICODE);
$jsPrateleiras = json_encode($prateleiras_count, JSON_UNESCAPED_UNICODE);
$jsTop12       = json_encode($top12,       JSON_UNESCAPED_UNICODE);
$jsSalas       = json_encode($salas,       JSON_UNESCAPED_UNICODE);
$jsEmAtraso    = json_encode(array_values($emAtraso), JSON_UNESCAPED_UNICODE);
$jsVencendo    = json_encode(array_values($vencendo),  JSON_UNESCAPED_UNICODE);
$jsTopLivros   = json_encode($topLivros,   JSON_UNESCAPED_UNICODE);
$jsMapaPrat    = json_encode($mapaPrateleiras, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — Biblioteca E.E. Ephigenia</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=DM+Mono:wght@400;500&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
:root {
  --bg:       #0e0e0e;
  --surface:  #161616;
  --surface2: #1e1e1e;
  --border:   #2a2a2a;
  --rust:     #b5451b;
  --rust2:    #d4572a;
  --rust-dim: rgba(181,69,27,.15);
  --gold:     #c9a84c;
  --text:     #e8e2d9;
  --muted:    #7a7268;
  --ok:       #3d9970;
  --warn:     #c9a84c;
  --danger:   #c0392b;
  --font-serif: 'Playfair Display', Georgia, serif;
  --font-sans:  'Instrument Sans', sans-serif;
  --font-mono:  'DM Mono', monospace;
  --sidebar-w: 260px;
  --header-h:  56px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{background:#f4f6f9;color:var(--text);font-family:var(--font-sans);min-height:100vh;overflow-x:hidden}

/* ── Noise overlay ─────────────────────── */
body::before{content:'';position:fixed;inset:0;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");pointer-events:none;z-index:0;opacity:.4}

/* ── Sidebar ──────────────────────────── */
.sidebar{position:fixed;left:0;top:0;bottom:0;width:var(--sidebar-w);background:var(--surface);border-right:1px solid var(--border);z-index:200;display:flex;flex-direction:column;transform:translateX(-100%);transition:transform .3s cubic-bezier(.4,0,.2,1)}
.sidebar.open{transform:translateX(0)}
.sidebar-logo{padding:24px 20px 16px;border-bottom:1px solid var(--border)}
.sidebar-logo .pre{font-family:var(--font-mono);font-size:.7rem;color:var(--muted);letter-spacing:.1em;text-transform:uppercase}
.sidebar-logo .name{font-family:var(--font-serif);font-size:1.25rem;color:var(--text);display:block;margin-top:2px}
.sidebar-logo .name em{color:var(--rust2);font-style:italic}
.sidebar-nav{flex:1;overflow-y:auto;padding:8px 0}
.nav-section{padding:16px 20px 6px;font-family:var(--font-mono);font-size:.65rem;color:var(--muted);letter-spacing:.12em;text-transform:uppercase}
.nav-item{display:flex;align-items:center;gap:10px;padding:10px 20px;cursor:pointer;border-left:2px solid transparent;transition:all .2s;font-size:.88rem;color:var(--muted)}
.nav-item:hover,.nav-item.active{color:var(--text);border-left-color:var(--rust);background:var(--rust-dim)}
.nav-item svg{flex-shrink:0;opacity:.7}
.nav-item.active svg{opacity:1}
.sidebar-footer{padding:16px 20px;border-top:1px solid var(--border);font-family:var(--font-mono);font-size:.68rem;color:var(--muted)}

/* ── Header ───────────────────────────── */
.header{position:fixed;top:0;left:0;right:0;height:var(--header-h);background:rgba(14,14,14,.92);backdrop-filter:blur(12px);border-bottom:1px solid var(--border);z-index:100;display:flex;align-items:center;gap:16px;padding:0 20px}
.hamburger{background:none;border:none;cursor:pointer;color:var(--text);padding:6px;display:flex;flex-direction:column;gap:5px;border-radius:4px;transition:background .2s}
.hamburger:hover{background:var(--rust-dim)}
.hamburger span{display:block;width:20px;height:1.5px;background:currentColor;transition:all .3s}
.hamburger.open span:nth-child(1){transform:translateY(6.5px) rotate(45deg)}
.hamburger.open span:nth-child(2){opacity:0}
.hamburger.open span:nth-child(3){transform:translateY(-6.5px) rotate(-45deg)}
.header-title{font-family:var(--font-serif);font-size:1.1rem}
.header-title em{color:var(--rust2);font-style:italic}
.header-right{margin-left:auto;display:flex;align-items:center;gap:8px}
.header-date{font-family:var(--font-mono);font-size:.72rem;color:var(--muted)}
.btn-export-top{background:var(--rust);border:none;color:#fff;font-family:var(--font-mono);font-size:.72rem;padding:6px 14px;border-radius:4px;cursor:pointer;transition:background .2s}
.btn-export-top:hover{background:var(--rust2)}

/* ── Overlay ──────────────────────────── */
.overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:150;opacity:0;pointer-events:none;transition:opacity .3s}
.overlay.visible{opacity:1;pointer-events:all}

/* ── Main content ─────────────────────── */
.main{margin-top:var(--header-h);padding:28px 24px;min-height:calc(100vh - var(--header-h));position:relative;z-index:1}
.section{display:none}
.section.active{display:block}

/* ── Section titles ──────────────────── */
.section-header{margin-bottom:20px}
.section-label{font-family:var(--font-mono);font-size:.68rem;color:var(--rust);letter-spacing:.12em;text-transform:uppercase}
.section-title{font-family:var(--font-serif);font-size:1.8rem;margin-top:4px;line-height:1.2}
.section-title em{color:var(--rust2);font-style:italic}
.section-sub{font-size:.82rem;color:var(--muted);margin-top:4px;font-family:var(--font-mono)}

/* ── Cards de métricas ───────────────── */
.metrics-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:28px}
.metric-card{background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:16px;position:relative;overflow:hidden}
.metric-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:var(--rust)}
.metric-card.ok::before{background:var(--ok)}
.metric-card.warn::before{background:var(--warn)}
.metric-card.danger::before{background:var(--danger)}
.metric-label{font-family:var(--font-mono);font-size:.65rem;color:var(--muted);letter-spacing:.08em;text-transform:uppercase;margin-bottom:8px}
.metric-value{font-family:var(--font-serif);font-size:2rem;font-weight:700;line-height:1}
.metric-sub{font-family:var(--font-mono);font-size:.68rem;color:var(--muted);margin-top:6px}

/* ── Chart grid ──────────────────────── */
.charts-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:28px}
.chart-card{background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:20px}
.chart-card.full{grid-column:1/-1}
.chart-title{font-family:var(--font-mono);font-size:.72rem;color:var(--rust);letter-spacing:.08em;text-transform:uppercase;margin-bottom:4px}
.chart-sub{font-size:.75rem;color:var(--muted);margin-bottom:16px;font-family:var(--font-mono)}
.chart-wrap{position:relative;height:240px}
.chart-wrap.tall{height:340px}

/* ── Atraso / vencendo ───────────────── */
.alerts-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:28px}
.alert-card{background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:16px}
.alert-title{font-family:var(--font-mono);font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;margin-bottom:12px}
.alert-title.danger{color:var(--danger)}
.alert-title.warn{color:var(--warn)}
.alert-list{display:flex;flex-direction:column;gap:8px;max-height:240px;overflow-y:auto}
.alert-item{background:var(--surface2);border-radius:4px;padding:10px 12px;border-left:2px solid var(--danger)}
.alert-item.warn{border-left-color:var(--warn)}
.alert-aluno{font-size:.85rem;font-weight:600}
.alert-meta{font-family:var(--font-mono);font-size:.68rem;color:var(--muted);margin-top:3px}
.alert-badge{font-family:var(--font-mono);font-size:.65rem;padding:2px 7px;border-radius:2px;background:rgba(192,57,43,.2);color:var(--danger);margin-top:5px;display:inline-block}
.alert-badge.warn{background:rgba(201,168,76,.2);color:var(--warn)}
.empty-alert{font-family:var(--font-mono);font-size:.75rem;color:var(--muted);padding:16px;text-align:center}

/* ── Ranking livros ──────────────────── */
.ranking-card{background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:20px;margin-bottom:28px}
.ranking-item{display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid var(--border)}
.ranking-item:last-child{border-bottom:none}
.rank-num{font-family:var(--font-mono);font-size:.75rem;color:var(--muted);width:20px;text-align:right;flex-shrink:0}
.rank-name{font-size:.85rem;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rank-bar-wrap{width:120px;height:6px;background:var(--surface2);border-radius:3px;flex-shrink:0}
.rank-bar{height:100%;border-radius:3px;background:var(--rust);transition:width .6s}
.rank-count{font-family:var(--font-mono);font-size:.72rem;color:var(--rust);width:24px;text-align:right;flex-shrink:0}

/* ── Prateleiras ─────────────────────── */
.shelves-section{margin-bottom:28px}
.shelves-instruction{font-family:var(--font-mono);font-size:.72rem;color:var(--muted);margin-bottom:16px}
.shelves-container{display:flex;gap:20px;overflow-x:auto;padding-bottom:8px}
.shelf-column{display:flex;flex-direction:column;gap:0;flex-shrink:0}
.shelf-col-label{font-family:var(--font-serif);font-size:1.1rem;color:var(--rust2);text-align:center;margin-bottom:8px;font-style:italic}
.shelf-slots{display:flex;flex-direction:column;gap:6px}
.shelf-slot{width:72px;height:52px;background:var(--surface);border:1px solid var(--border);border-radius:4px;display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:default;position:relative;transition:all .2s}
.shelf-slot.has-books{border-color:var(--rust);cursor:pointer}
.shelf-slot.has-books:hover{background:var(--rust-dim);border-color:var(--rust2);transform:scale(1.06)}
.shelf-slot-label{font-family:var(--font-mono);font-size:.62rem;color:var(--muted)}
.shelf-slot.has-books .shelf-slot-label{color:var(--rust2)}
.shelf-slot-count{font-family:var(--font-serif);font-size:1rem;font-weight:700;margin-top:2px}
.shelf-slot.has-books .shelf-slot-count{color:var(--text)}
.shelf-slot.empty .shelf-slot-count{color:var(--border)}

/* Tooltip */
.shelf-tooltip{position:fixed;background:#1a1a1a;border:1px solid var(--rust);border-radius:6px;padding:10px 14px;font-size:.78rem;z-index:9999;pointer-events:none;max-width:220px;opacity:0;transition:opacity .15s;box-shadow:0 8px 32px rgba(0,0,0,.6)}
.shelf-tooltip.visible{opacity:1}
.shelf-tooltip strong{display:block;font-family:var(--font-mono);color:var(--rust);font-size:.65rem;letter-spacing:.08em;margin-bottom:6px}
.shelf-tooltip ul{padding-left:14px;display:flex;flex-direction:column;gap:3px}
.shelf-tooltip li{color:var(--text);line-height:1.3}

/* Shelf divider */
.shelf-divider{width:1px;background:var(--border);margin:0 4px;align-self:stretch;flex-shrink:0}

/* ── Historico ───────────────────────── */
.hist-table-wrap{overflow-x:auto}
.hist-table{width:100%;border-collapse:collapse;font-size:.82rem}
.hist-table th{font-family:var(--font-mono);font-size:.65rem;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);padding:8px 12px;border-bottom:1px solid var(--border);text-align:left;white-space:nowrap}
.hist-table td{padding:9px 12px;border-bottom:1px solid rgba(42,42,42,.5);vertical-align:top}
.hist-table tr:hover td{background:var(--surface2)}
.badge{font-family:var(--font-mono);font-size:.63rem;padding:2px 8px;border-radius:2px;display:inline-block}
.badge.ok{background:rgba(61,153,112,.15);color:var(--ok)}
.badge.danger{background:rgba(192,57,43,.15);color:var(--danger)}

/* ── Exportar ─────────────────────────── */
.export-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px}
.export-card{background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:20px;display:flex;flex-direction:column;gap:12px}
.export-card-title{font-family:var(--font-mono);font-size:.72rem;color:var(--rust);letter-spacing:.08em;text-transform:uppercase}
.export-card-desc{font-size:.82rem;color:var(--muted);flex:1}
.btn{border:none;cursor:pointer;font-family:var(--font-mono);font-size:.75rem;padding:9px 16px;border-radius:4px;transition:all .2s;display:inline-flex;align-items:center;gap:6px;width:100%;justify-content:center}
.btn-rust{background:var(--rust);color:#fff}
.btn-rust:hover{background:var(--rust2)}
.btn-outline{background:transparent;color:var(--text);border:1px solid var(--border)}
.btn-outline:hover{border-color:var(--rust);color:var(--rust)}
.email-input{background:var(--surface2);border:1px solid var(--border);color:var(--text);font-family:var(--font-mono);font-size:.78rem;padding:8px 12px;border-radius:4px;width:100%;outline:none;transition:border-color .2s}
.email-input:focus{border-color:var(--rust)}
.email-input::placeholder{color:var(--muted)}

/* ── Scrollbar ───────────────────────── */
::-webkit-scrollbar{width:4px;height:4px}
::-webkit-scrollbar-track{background:var(--bg)}
::-webkit-scrollbar-thumb{background:var(--border);border-radius:2px}
::-webkit-scrollbar-thumb:hover{background:var(--muted)}

/* ── Responsive ──────────────────────── */
@media(max-width:700px){
  .charts-grid,.alerts-grid{grid-template-columns:1fr}
  .charts-grid .full{grid-column:1}
  .metrics-grid{grid-template-columns:repeat(2,1fr)}
}
</style>
</head>
<body>

<!-- Tooltip prateleira -->
<div class="shelf-tooltip" id="shelfTooltip"></div>

<!-- Overlay -->
<div class="overlay" id="overlay"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <span class="pre">Biblioteca</span>
    <span class="name" style="color: #333;">E.E. <em>Ephigenia</em></span>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section">Visão Geral</div>
    <div class="nav-item active" data-section="overview">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      Dashboard geral
    </div>
    <div class="nav-section">Análises</div>
    <div class="nav-item" data-section="charts">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      Gráficos
    </div>
    <div class="nav-item" data-section="alertas">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      Alertas de prazo
    </div>
    <div class="nav-item" data-section="ranking">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      Ranking de livros
    </div>
    <div class="nav-item" data-section="prateleiras">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="4"/><rect x="2" y="10" width="20" height="4"/><rect x="2" y="17" width="20" height="4"/></svg>
      Mapa de prateleiras
    </div>
    <div class="nav-section">Registros</div>
    <div class="nav-item" data-section="historico">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      Histórico
    </div>
    <div class="nav-section">Sistema</div>
    <div class="nav-item" data-section="exportar">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Exportar dados
    </div>
    <div class="nav-item" onclick="window.location.href='acervo.php'">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
      Ir para acervo
    </div>
  </nav>
  <div class="sidebar-footer">Dashboard v1.0 · <?= date('Y') ?></div>
</aside>

<!-- Header -->
<header class="header">
  <button class="hamburger" id="hamburger" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
  <div class="header-title" style="color: #ffffff;">Biblio<em>teca</em> — Dashboard</div>
  <div class="header-right">
    <div class="nav-dot"></div>
    <button class="btn-export-top" onclick="showSection('exportar')">↓ Exportar</button>
    <div class="nav-dot"></div>
    <button class="btn" onclick="window.location.href='acervo.php'">Ir para acervo</button>
  </div>
</header>

<main class="main">

  <!-- ── OVERVIEW ─────────────────────────────── -->
  <div class="section active" id="sec-overview">
    <div class="section-header">
      <div class="section-label">Visão Geral</div>
      <h1 class="section-title" style="color: #333;">Dash<em>board</em></h1>
      <div class="section-sub">Atualizado · Leitura ao vivo dos arquivos</div>
    </div>

    <div class="metrics-grid">
      <div class="metric-card">
        <div class="metric-label">Títulos</div>
        <div class="metric-value"><?= $totalTitulos ?></div>
        <div class="metric-sub">no acervo</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">Exemplares</div>
        <div class="metric-value"><?= $totalExemplares ?></div>
        <div class="metric-sub">total de volumes</div>
      </div>
      <div class="metric-card<?= $totalEmprestados > 0 ? ' warn' : '' ?>">
        <div class="metric-label">Emprestados</div>
        <div class="metric-value"><?= $totalEmprestados ?></div>
        <div class="metric-sub">empréstimos ativos</div>
      </div>
      <div class="metric-card ok">
        <div class="metric-label">Disponíveis</div>
        <div class="metric-value"><?= $totalDisponiveis ?></div>
        <div class="metric-sub">para empréstimo</div>
      </div>
      <div class="metric-card<?= count($emAtraso) > 0 ? ' danger' : ' ok' ?>">
        <div class="metric-label">Em Atraso</div>
        <div class="metric-value"><?= count($emAtraso) ?></div>
        <div class="metric-sub">devoluções vencidas</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">Alunos Ativos</div>
        <div class="metric-value"><?= $alunosAtivos ?></div>
        <div class="metric-sub">com livros em mãos</div>
      </div>
    </div>

    <!-- Mini charts no overview -->
    <div class="charts-grid">
      <div class="chart-card">
        <div class="chart-title">Faixa Etária</div>
        <div class="chart-sub">Distribuição do acervo por categoria</div>
        <div class="chart-wrap"><canvas id="chartFaixa"></canvas></div>
      </div>
      <div class="chart-card">
        <div class="chart-title">Por Prateleira</div>
        <div class="chart-sub">Títulos em cada prateleira</div>
        <div class="chart-wrap"><canvas id="chartPrat"></canvas></div>
      </div>
      <div class="chart-card full">
        <div class="chart-title">Disponíveis vs Emprestados</div>
        <div class="chart-sub">Top 12 títulos por movimentação</div>
        <div class="chart-wrap tall"><canvas id="chartDisp"></canvas></div>
      </div>
      <div class="chart-card full">
        <div class="chart-title">Empréstimos por Sala / Turma</div>
        <div class="chart-sub">Quantos empréstimos ativos cada sala tem</div>
        <div class="chart-wrap"><canvas id="chartSala"></canvas></div>
      </div>
    </div>
  </div>

  <!-- ── GRÁFICOS ──────────────────────────────── -->
  <div class="section" id="sec-charts">
    <div class="section-header">
      <div class="section-label">Análises</div>
      <h1 class="section-title" style="color: #333;">Grá<em>ficos</em></h1>
      <div class="section-sub">Visualizações completas do acervo</div>
    </div>
    <div class="charts-grid">
      <div class="chart-card">
        <div class="chart-title">Faixa Etária — Distribuição do acervo</div>
        <div class="chart-sub">Pizza com todas as categorias</div>
        <div class="chart-wrap"><canvas id="chartFaixa2"></canvas></div>
      </div>
      <div class="chart-card">
        <div class="chart-title">Por Prateleira</div>
        <div class="chart-sub">Quantidade de títulos por prateleira</div>
        <div class="chart-wrap"><canvas id="chartPrat2"></canvas></div>
      </div>
      <div class="chart-card full">
        <div class="chart-title">Disponíveis vs Emprestados — Top 12</div>
        <div class="chart-sub">Barras empilhadas por título</div>
        <div class="chart-wrap tall"><canvas id="chartDisp2"></canvas></div>
      </div>
      <div class="chart-card full">
        <div class="chart-title">Empréstimos por Turma</div>
        <div class="chart-sub">Volume de empréstimos ativos por sala</div>
        <div class="chart-wrap"><canvas id="chartSala2"></canvas></div>
      </div>
    </div>
  </div>

  <!-- ── ALERTAS ───────────────────────────────── -->
  <div class="section" id="sec-alertas">
    <div class="section-header">
      <div class="section-label">Alertas de Prazo</div>
      <h1 class="section-title" style="color: #333;">Moni<em>toramento</em></h1>
      <div class="section-sub">Devoluções vencidas e próximas do vencimento</div>
    </div>
    <div class="alerts-grid">
      <div class="alert-card">
        <div class="alert-title danger">⚠ Em Atraso (<?= count($emAtraso) ?>)</div>
        <div class="alert-list" id="listaAtraso"></div>
      </div>
      <div class="alert-card">
        <div class="alert-title warn">⏰ Vencendo em 7 dias (<?= count($vencendo) ?>)</div>
        <div class="alert-list" id="listaVencendo"></div>
      </div>
    </div>
  </div>

  <!-- ── RANKING ───────────────────────────────── -->
  <div class="section" id="sec-ranking">
    <div class="section-header">
      <div class="section-label">Ranking</div>
      <h1 class="section-title" style="color: #333;">Mais Em<em>prestados</em></h1>
      <div class="section-sub">Livros com maior movimentação no histórico</div>
    </div>
    <div class="ranking-card" id="rankingList"></div>
  </div>

  <!-- ── PRATELEIRAS ──────────────────────────── -->
  <div class="section" id="sec-prateleiras">
    <div class="section-header">
      <div class="section-label">Mapa Físico</div>
      <h1 class="section-title" style="color: #333;">Prate<em>leiras</em></h1>
      <div class="section-sub">Passe o cursor sobre um andar para ver os títulos</div>
    </div>
    <div class="shelves-instruction">A = andar 1 (topo) → 5 (base) · Colunas: A B C D E</div>
    <div class="shelves-container" id="shelvesContainer"></div>
  </div>

  <!-- ── HISTÓRICO ─────────────────────────────── -->
  <div class="section" id="sec-historico">
    <div class="section-header">
      <div class="section-label">Registros</div>
      <h1 class="section-title" style="color: #333;">His<em>tórico</em></h1>
      <div class="section-sub">Todos os empréstimos realizados</div>
    </div>
    <div class="chart-card" style="margin-bottom:20px">
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <input type="text" id="histSearch" class="email-input" placeholder="Buscar aluno ou livro…" style="flex:1;min-width:160px">
        <input type="number" id="histAno" class="email-input" placeholder="Ano" style="width:90px" value="<?= date('Y') ?>">
        <button class="btn btn-rust" onclick="renderHistorico()">Filtrar</button>
      </div>
    </div>
    <div class="chart-card">
      <div class="hist-table-wrap">
        <table class="hist-table">
          <thead><tr><th>#</th><th>Livro</th><th>Aluno</th><th>Sala</th><th>Retirada</th><th>Devolução</th><th>Status</th></tr></thead>
          <tbody id="histTbody"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ── EXPORTAR ──────────────────────────────── -->
  <div class="section" id="sec-exportar">
    <div class="section-header">
      <div class="section-label">Exportação</div>
      <h1 class="section-title" style="color: #333;">Ex<em>portar</em></h1>
      <div class="section-sub">Baixe ou envie relatórios do acervo</div>
    </div>
    <div class="export-grid">
      <div class="export-card">
        <div class="export-card-title">📊 CSV</div>
        <div class="export-card-desc">Tabela completa de empréstimos ativos com status, aluno, sala e prazos.</div>
        <button class="btn btn-rust" onclick="exportCSV()">↓ Baixar CSV</button>
      </div>
      <div class="export-card">
        <div class="export-card-title">📝 DOCX / Word</div>
        <div class="export-card-desc">Relatório gerencial formatado com métricas, alertas e ranking — pronto para impressão.</div>
        <button class="btn btn-rust" onclick="exportDOCX()">↓ Baixar DOCX</button>
      </div>
      <div class="export-card">
        <div class="export-card-title">🖨️ Imprimir</div>
        <div class="export-card-desc">Abre a janela de impressão com o relatório formatado. Use "Salvar como PDF" no navegador.</div>
        <button class="btn btn-outline" onclick="imprimirRelatorio()">Imprimir / PDF</button>
      </div>
      <div class="export-card">
        <div class="export-card-title">✉️ Enviar por E-mail</div>
        <div class="export-card-desc">Abre seu cliente de e-mail com o relatório no corpo da mensagem.</div>
        <input type="email" class="email-input" id="emailDest" placeholder="destinatario@email.com">
        <button class="btn btn-rust" onclick="enviarEmail()">Enviar por Gmail</button>
      </div>
    </div>
  </div>

</main>

<script>
// ── Dados PHP → JS ────────────────────────────────────────────────────────
const LIVROS      = <?= $jsLivros ?>;
const EMPRESTIMOS = <?= $jsEmprestimos ?>;
const HISTORICO   = <?= $jsHistorico ?>;
const FAIXAS      = <?= $jsFaixas ?>;
const PRATELEIRAS_CNT = <?= $jsPrateleiras ?>;
const TOP12       = <?= $jsTop12 ?>;
const SALAS       = <?= $jsSalas ?>;
const EM_ATRASO   = <?= $jsEmAtraso ?>;
const VENCENDO    = <?= $jsVencendo ?>;
const TOP_LIVROS  = <?= $jsTopLivros ?>;
const MAPA_PRAT   = <?= $jsMapaPrat ?>;
const HOJE        = '<?= $hoje ?>';

// ── Paleta ───────────────────────────────────────────────────────────────
const RUST    = '#b5451b';
const RUST2   = '#d4572a';
const OK      = '#3d9970';
const WARN    = '#c9a84c';
const DANGER  = '#c0392b';
const MUTED   = '#7a7268';
const SURFACE = '#1e1e1e';
const TEXT    = '#e8e2d9';
const BORDER  = '#2a2a2a';

const PALETTE = ['#b5451b','#c9a84c','#3d9970','#2980b9','#8e44ad','#16a085','#d35400','#27ae60','#2c3e50','#e74c3c'];

Chart.defaults.color = '#7a7268';
Chart.defaults.font.family = "'DM Mono', monospace";
Chart.defaults.font.size   = 11;

// ── Navegação ────────────────────────────────────────────────────────────
function showSection(id) {
  document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
  document.getElementById('sec-' + id).classList.add('active');
  document.querySelectorAll('.nav-item').forEach(n => {
    n.classList.toggle('active', n.dataset.section === id);
  });
  closeSidebar();
  if (id === 'alertas')     renderAlertas();
  if (id === 'ranking')     renderRanking();
  if (id === 'prateleiras') renderPrateleiras();
  if (id === 'historico')   renderHistorico();
}

document.querySelectorAll('.nav-item[data-section]').forEach(item => {
  item.addEventListener('click', () => showSection(item.dataset.section));
});

// ── Sidebar / Hamburger ──────────────────────────────────────────────────
const sidebar   = document.getElementById('sidebar');
const hamburger = document.getElementById('hamburger');
const overlay   = document.getElementById('overlay');

function openSidebar()  { sidebar.classList.add('open'); overlay.classList.add('visible'); hamburger.classList.add('open'); }
function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('visible'); hamburger.classList.remove('open'); }

hamburger.addEventListener('click', () => sidebar.classList.contains('open') ? closeSidebar() : openSidebar());
overlay.addEventListener('click', closeSidebar);

// ── Helpers ───────────────────────────────────────────────────────────────
function nomelivro(reg) {
  const l = LIVROS.find(x => x.registro == reg);
  return l ? l.nome : 'Livro desconhecido';
}
function diasAtraso(data) {
  const d  = new Date(data + 'T00:00:00');
  const hj = new Date(HOJE + 'T00:00:00');
  return Math.floor((hj - d) / 86400000);
}

// ── Charts factory ────────────────────────────────────────────────────────
function makeDonut(id, labels, data) {
  const ctx = document.getElementById(id);
  if (!ctx) return;
  return new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels,
      datasets: [{ data, backgroundColor: PALETTE, borderColor: '#0e0e0e', borderWidth: 2 }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { position: 'right', labels: { boxWidth: 10, padding: 10, color: TEXT } }
      }
    }
  });
}

function makeBar(id, labels, datasets, opts = {}) {
  const ctx = document.getElementById(id);
  if (!ctx) return;
  return new Chart(ctx, {
    type: 'bar',
    data: { labels, datasets },
    options: {
      responsive: true, maintainAspectRatio: false,
      indexAxis: opts.horizontal ? 'y' : 'x',
      scales: {
        x: { stacked: opts.stacked, grid: { color: BORDER }, ticks: { color: MUTED, maxRotation: 40 } },
        y: { stacked: opts.stacked, grid: { color: BORDER }, ticks: { color: MUTED } }
      },
      plugins: { legend: { labels: { color: TEXT, boxWidth: 10 } } }
    }
  });
}

// ── Init charts ───────────────────────────────────────────────────────────
window.addEventListener('DOMContentLoaded', () => {
  // Faixa etária (donut)
  const fKeys = Object.keys(FAIXAS);
  const fVals = Object.values(FAIXAS);
  makeDonut('chartFaixa',  fKeys, fVals);
  makeDonut('chartFaixa2', fKeys, fVals);

  // Prateleiras (barras)
  const pKeys = Object.keys(PRATELEIRAS_CNT);
  const pVals = Object.values(PRATELEIRAS_CNT);
  const pDataset = [{ label: 'Títulos', data: pVals, backgroundColor: RUST, borderRadius: 3 }];
  makeBar('chartPrat',  pKeys, pDataset);
  makeBar('chartPrat2', pKeys, pDataset);

  // Disp vs Emp (stacked horizontal)
  const t12Labels = TOP12.map(x => x.nome);
  const t12Disp   = TOP12.map(x => x.disp);
  const t12Emp    = TOP12.map(x => x.emp);
  const dispDs = [
    { label: 'Disponíveis', data: t12Disp, backgroundColor: OK,    borderRadius: 2 },
    { label: 'Emprestados', data: t12Emp,  backgroundColor: RUST2, borderRadius: 2 }
  ];
  makeBar('chartDisp',  t12Labels, dispDs, { horizontal: true, stacked: true });
  makeBar('chartDisp2', t12Labels, dispDs, { horizontal: true, stacked: true });

  // Por sala (barras)
  const sKeys = Object.keys(SALAS);
  const sVals = Object.values(SALAS);
  const sDataset = [{ label: 'Empréstimos', data: sVals, backgroundColor: PALETTE, borderRadius: 3 }];
  makeBar('chartSala',  sKeys, sDataset);
  makeBar('chartSala2', sKeys, sDataset);

  // Alertas, ranking e prateleiras ao carregar overview
  renderAlertas();
  renderRanking();
  renderPrateleiras();
  renderHistorico();
});

// ── Alertas ───────────────────────────────────────────────────────────────
function renderAlertas() {
  const la = document.getElementById('listaAtraso');
  const lv = document.getElementById('listaVencendo');
  if (!la || !lv) return;

  la.innerHTML = '';
  lv.innerHTML = '';

  if (EM_ATRASO.length === 0) {
    la.innerHTML = '<div class="empty-alert">Nenhuma devolução em atraso ✓</div>';
  } else {
    EM_ATRASO.forEach(e => {
      const dias = diasAtraso(e.devolucao);
      la.innerHTML += `<div class="alert-item">
        <div class="alert-aluno">${e.aluno}</div>
        <div class="alert-meta">${nomelivro(e.registro)} · Sala ${e.sala}</div>
        <div class="alert-meta">Devolver: ${formatData(e.devolucao)}</div>
        <span class="alert-badge">${dias} dia${dias>1?'s':''} em atraso</span>
      </div>`;
    });
  }

  if (VENCENDO.length === 0) {
    lv.innerHTML = '<div class="empty-alert">Nenhum vencimento nos próximos 7 dias</div>';
  } else {
    VENCENDO.forEach(e => {
      const diff = Math.ceil((new Date(e.devolucao+'T00:00:00') - new Date(HOJE+'T00:00:00')) / 86400000);
      lv.innerHTML += `<div class="alert-item warn">
        <div class="alert-aluno">${e.aluno}</div>
        <div class="alert-meta">${nomelivro(e.registro)} · Sala ${e.sala}</div>
        <div class="alert-meta">Devolver: ${formatData(e.devolucao)}</div>
        <span class="alert-badge warn">em ${diff} dia${diff>1?'s':''}</span>
      </div>`;
    });
  }
}

function formatData(s) {
  const [y,m,d] = s.split('-');
  return `${d}/${m}/${y}`;
}

// ── Ranking ───────────────────────────────────────────────────────────────
function renderRanking() {
  const el = document.getElementById('rankingList');
  if (!el) return;
  const entries = Object.entries(TOP_LIVROS);
  const max = entries[0]?.[1] || 1;
  el.innerHTML = entries.map(([nome, cnt], i) => `
    <div class="ranking-item">
      <span class="rank-num">${i+1}</span>
      <span class="rank-name">${nome}</span>
      <div class="rank-bar-wrap"><div class="rank-bar" style="width:${Math.round((cnt/max)*100)}%"></div></div>
      <span class="rank-count">${cnt}</span>
    </div>
  `).join('');
  if (!entries.length) el.innerHTML = '<div class="empty-alert">Sem dados no histórico ainda.</div>';
}

// ── Prateleiras verticais ─────────────────────────────────────────────────
function renderPrateleiras() {
  const container = document.getElementById('shelvesContainer');
  if (!container) return;
  const colunas = ['A','B','C','D','E'];
  const andares  = [1,2,3,4,5];

  container.innerHTML = '';
  colunas.forEach((col, ci) => {
    // Divisor entre colunas
    if (ci > 0) {
      const div = document.createElement('div');
      div.className = 'shelf-divider';
      container.appendChild(div);
    }

    const colEl = document.createElement('div');
    colEl.className = 'shelf-column';

    const label = document.createElement('div');
    label.className = 'shelf-col-label';
    label.textContent = col;
    colEl.appendChild(label);

    const slots = document.createElement('div');
    slots.className = 'shelf-slots';

    andares.forEach(andar => {
      const key   = `${col}${andar}`;
      const livros = MAPA_PRAT[col]?.[andar] || [];
      const slot   = document.createElement('div');
      slot.className = 'shelf-slot ' + (livros.length > 0 ? 'has-books' : 'empty');

      slot.innerHTML = `<span class="shelf-slot-label">${key}</span>
        <span class="shelf-slot-count">${livros.length > 0 ? livros.length : '—'}</span>`;

      if (livros.length > 0) {
        slot.addEventListener('mouseenter', (ev) => showTooltip(ev, key, livros));
        slot.addEventListener('mousemove',  (ev) => moveTooltip(ev));
        slot.addEventListener('mouseleave', hideTooltip);
        slot.addEventListener('touchstart', (ev) => {
          showTooltip(ev.touches[0], key, livros);
          setTimeout(hideTooltip, 2500);
        });
      }
      slots.appendChild(slot);
    });

    colEl.appendChild(slots);
    container.appendChild(colEl);
  });
}

const tooltip = document.getElementById('shelfTooltip');
function showTooltip(ev, key, livros) {
  tooltip.innerHTML = `<strong>PRATELEIRA ${key}</strong><ul>${livros.map(l=>`<li>${l}</li>`).join('')}</ul>`;
  moveTooltip(ev);
  tooltip.classList.add('visible');
}
function moveTooltip(ev) {
  const x = ev.clientX + 14;
  const y = ev.clientY - 10;
  tooltip.style.left = Math.min(x, window.innerWidth - 240) + 'px';
  tooltip.style.top  = Math.max(y, 8) + 'px';
}
function hideTooltip() { tooltip.classList.remove('visible'); }

// ── Histórico ─────────────────────────────────────────────────────────────
function renderHistorico() {
  const tbody  = document.getElementById('histTbody');
  if (!tbody) return;
  const busca  = (document.getElementById('histSearch')?.value || '').toLowerCase();
  const ano    = document.getElementById('histAno')?.value || '';

  let dados = [...HISTORICO].reverse();
  if (busca) dados = dados.filter(h => h.aluno.toLowerCase().includes(busca) || h.livro.toLowerCase().includes(busca));
  if (ano)   dados = dados.filter(h => h.ano === ano);

  if (!dados.length) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:24px;font-family:var(--font-mono);font-size:.78rem">Nenhum registro encontrado.</td></tr>';
    return;
  }

  tbody.innerHTML = dados.map((h, i) => {
    const atrasado = h.devolucao < HOJE;
    return `<tr>
      <td style="font-family:var(--font-mono);color:var(--muted)">${i+1}</td>
      <td>${h.livro}</td>
      <td>${h.aluno}</td>
      <td style="font-family:var(--font-mono);font-size:.72rem">${h.sala}</td>
      <td style="font-family:var(--font-mono);font-size:.72rem">${formatData(h.retirada)}</td>
      <td style="font-family:var(--font-mono);font-size:.72rem">${formatData(h.devolucao)}</td>
      <td><span class="badge ${atrasado?'danger':'ok'}">${atrasado?'Em atraso':'Devolvido'}</span></td>
    </tr>`;
  }).join('');
}

// ── Exportação ────────────────────────────────────────────────────────────
function exportCSV() {
  const header = ['ID','Registro','Livro','Aluno','Sala','Retirada','Devolucao','Status'];
  const rows   = EMPRESTIMOS.map(e => {
    const livro = nomelivro(e.registro);
    const status = e.devolucao < HOJE ? 'EM ATRASO' : 'No prazo';
    return [e.id, e.registro, livro, e.aluno, e.sala, e.retirada, e.devolucao, status]
           .map(v => `"${String(v).replace(/"/g,'""')}"`).join(',');
  });
  const csv = '\uFEFF' + [header.join(','), ...rows].join('\n');
  downloadBlob(csv, 'emprestimos-biblioteca.csv', 'text/csv;charset=utf-8');
}

function gerarRelatorio() {
  const hoje = new Date().toLocaleDateString('pt-BR');
  const total = LIVROS.length;
  const empAtivos = EMPRESTIMOS.length;
  const atraso = EM_ATRASO.length;

  let txt = `RELATÓRIO — BIBLIOTECA E.E. EPHIGENIA\n`;
  txt += `Gerado em: ${hoje}\n${'='.repeat(48)}\n\n`;
  txt += `MÉTRICAS GERAIS\n${'-'.repeat(32)}\n`;
  txt += `Títulos no acervo: ${total}\n`;
  txt += `Exemplares totais: ${LIVROS.reduce((s,l)=>s+l.quantidade,0)}\n`;
  txt += `Empréstimos ativos: ${empAtivos}\n`;
  txt += `Em atraso: ${atraso}\n\n`;

  txt += `EMPRÉSTIMOS EM ATRASO\n${'-'.repeat(32)}\n`;
  if (EM_ATRASO.length) {
    EM_ATRASO.forEach(e => {
      txt += `• ${e.aluno} — ${nomelivro(e.registro)} (prazo: ${formatData(e.devolucao)})\n`;
    });
  } else { txt += 'Nenhum.\n'; }
  txt += '\n';

  txt += `RANKING — MAIS EMPRESTADOS\n${'-'.repeat(32)}\n`;
  Object.entries(TOP_LIVROS).forEach(([nome,cnt],i) => {
    txt += `${i+1}. ${nome}: ${cnt} empréstimo(s)\n`;
  });

  return txt;
}

function exportDOCX() {
  // Relatório em formato RTF (abre no Word)
  const rel = gerarRelatorio();
  const rtf = `{\\rtf1\\ansi\\deff0
{\\fonttbl{\\f0 Arial;}}
{\\colortbl ;\\red181\\green69\\blue27;}
\\f0\\fs22
{\\cf1\\b BIBLIOTECA E.E. EPHIGENIA — RELATÓRIO GERENCIAL\\b0\\cf0}\\par\\par
${rel.split('\n').map(l => l.replace(/[\\{}]/g,'\\$&') + '\\par').join('\n')}
}`;
  downloadBlob(rtf, 'relatorio-biblioteca.rtf', 'application/rtf');
}

function imprimirRelatorio() {
  const rel = gerarRelatorio();
  const win = window.open('', '_blank');
  win.document.write(`<!DOCTYPE html><html><head><meta charset="UTF-8">
<title>Relatório Biblioteca</title>
<style>body{font-family:monospace;font-size:13px;white-space:pre-wrap;padding:32px;color:#111}
h1{font-size:16px;margin-bottom:8px}</style>
</head><body>${rel.replace(/</g,'&lt;')}</body></html>`);
  win.document.close();
  win.print();
}

function enviarEmail() {
  const dest = document.getElementById('emailDest').value.trim();
  const rel  = encodeURIComponent(gerarRelatorio());
  const sub  = encodeURIComponent('Relatório — Biblioteca E.E. Ephigenia');
  window.open(`mailto:${dest}?subject=${sub}&body=${rel}`);
}

function downloadBlob(content, filename, mime) {
  const blob = new Blob([content], { type: mime });
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a');
  a.href = url; a.download = filename; a.click();
  URL.revokeObjectURL(url);
}
</script>
</body>
</html>