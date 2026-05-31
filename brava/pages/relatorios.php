<?php
// =============================================================
// Relatórios — demonstrativo financeiro por período
// =============================================================
require_once __DIR__ . '/../includes/auth.php';
exigirLogin();

$db = getDB();

// Datas padrão: mês atual
$dataInicio = $_GET['data_inicio'] ?? date('Y-m-01');
$dataFim    = $_GET['data_fim']    ?? date('Y-m-t');

// ---------------------------------------------------------------
// RECEITAS — aluguéis pagos no período
// ---------------------------------------------------------------
$stmt = $db->prepare("
    SELECT
        a.data_pagamento,
        a.valor_pago,
        a.forma_pagamento,
        i.nome AS inquilino,
        q.numero AS quarto
    FROM alugueis a
    JOIN contratos c ON a.contrato_id = c.id
    JOIN inquilinos i ON c.inquilino_id = i.id
    JOIN quartos q ON c.quarto_id = q.id
    WHERE a.status IN ('pago','parcial')
      AND a.data_pagamento BETWEEN ? AND ?
    ORDER BY a.data_pagamento DESC
");
$stmt->execute([$dataInicio, $dataFim]);
$receitas = $stmt->fetchAll();
$totalReceitas = array_sum(array_column($receitas, 'valor_pago'));

// ---------------------------------------------------------------
// DESPESAS — contas pagas no período
// ---------------------------------------------------------------
$stmt = $db->prepare("
    SELECT titulo, categoria, valor, data_pagamento,
           q.numero AS quarto_numero
    FROM contas_pagar cp
    LEFT JOIN quartos q ON cp.quarto_id = q.id
    WHERE cp.status = 'pago'
      AND cp.data_pagamento BETWEEN ? AND ?
    ORDER BY cp.data_pagamento DESC
");
$stmt->execute([$dataInicio, $dataFim]);
$despesas = $stmt->fetchAll();
$totalDespesas = array_sum(array_column($despesas, 'valor'));

// ---------------------------------------------------------------
// ALUGUÉIS PENDENTES / ATRASADOS no período
// ---------------------------------------------------------------
$stmt = $db->prepare("
    SELECT
        a.data_vencimento, a.valor, a.status,
        i.nome AS inquilino, q.numero AS quarto
    FROM alugueis a
    JOIN contratos c ON a.contrato_id = c.id
    JOIN inquilinos i ON c.inquilino_id = i.id
    JOIN quartos q ON c.quarto_id = q.id
    WHERE a.status IN ('pendente','atrasado')
      AND a.data_vencimento BETWEEN ? AND ?
    ORDER BY a.data_vencimento ASC
");
$stmt->execute([$dataInicio, $dataFim]);
$inadimplentes = $stmt->fetchAll();
$totalInadimplente = array_sum(array_column($inadimplentes, 'valor'));

$saldo = $totalReceitas - $totalDespesas;

// Agrupamento de despesas por categoria para o resumo
$despesasPorCat = [];
foreach ($despesas as $d) {
    $despesasPorCat[$d['categoria']] = ($despesasPorCat[$d['categoria']] ?? 0) + $d['valor'];
}

$categorias = ['manutencao'=>'Manutenção','agua'=>'Água','luz'=>'Luz','internet'=>'Internet','imposto'=>'Imposto','seguro'=>'Seguro','outro'=>'Outro'];

$pageTitle = 'Relatórios';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-6">
    <h2 class="text-2xl font-black text-slate-800">Relatório Financeiro</h2>
    <p class="text-sm text-slate-500 mt-1">Selecione o período para gerar o demonstrativo.</p>
</div>

<!-- FILTRO DE PERÍODO -->
<form method="GET" class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 mb-6">
    <div class="flex flex-wrap gap-4 items-end">
        <div>
            <label class="label-form">Data início</label>
            <input type="date" name="data_inicio" value="<?= e($dataInicio) ?>" class="input-form w-auto">
        </div>
        <div>
            <label class="label-form">Data fim</label>
            <input type="date" name="data_fim" value="<?= e($dataFim) ?>" class="input-form w-auto">
        </div>
        <button type="submit" class="px-5 py-2.5 rounded-xl text-white font-bold text-sm hover:brightness-110 transition" style="background:#1e2d40">
            <i class="fas fa-chart-bar mr-1"></i> Gerar relatório
        </button>
        <!-- Botão de impressão -->
        <button type="button" onclick="window.print()" class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold text-sm hover:bg-slate-50">
            <i class="fas fa-print mr-1"></i> Imprimir
        </button>
    </div>
</form>

<!-- PERÍODO INFORMATIVO -->
<div class="text-sm font-bold text-slate-500 mb-5">
    Período: <span class="text-slate-800"><?= formatarData($dataInicio) ?> a <?= formatarData($dataFim) ?></span>
</div>

<!-- RESUMO EXECUTIVO (4 cards) -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-green-50 border border-green-100 rounded-2xl p-5">
        <div class="text-xs font-bold text-green-600 uppercase mb-2"><i class="fas fa-arrow-down mr-1"></i>Receitas</div>
        <div class="text-2xl font-black text-green-700"><?= formatarDinheiro($totalReceitas) ?></div>
        <div class="text-xs text-green-600 mt-1"><?= count($receitas) ?> pagamento(s)</div>
    </div>
    <div class="bg-red-50 border border-red-100 rounded-2xl p-5">
        <div class="text-xs font-bold text-red-600 uppercase mb-2"><i class="fas fa-arrow-up mr-1"></i>Despesas</div>
        <div class="text-2xl font-black text-red-700"><?= formatarDinheiro($totalDespesas) ?></div>
        <div class="text-xs text-red-600 mt-1"><?= count($despesas) ?> conta(s)</div>
    </div>
    <div class="<?= $saldo >= 0 ? 'bg-blue-50 border-blue-100' : 'bg-orange-50 border-orange-100' ?> border rounded-2xl p-5">
        <div class="text-xs font-bold <?= $saldo >= 0 ? 'text-blue-600' : 'text-orange-600' ?> uppercase mb-2">Saldo</div>
        <div class="text-2xl font-black <?= $saldo >= 0 ? 'text-blue-700' : 'text-orange-700' ?>"><?= formatarDinheiro($saldo) ?></div>
        <div class="text-xs <?= $saldo >= 0 ? 'text-blue-600' : 'text-orange-600' ?> mt-1"><?= $saldo >= 0 ? 'Positivo' : 'Negativo' ?></div>
    </div>
    <div class="bg-yellow-50 border border-yellow-100 rounded-2xl p-5">
        <div class="text-xs font-bold text-yellow-600 uppercase mb-2"><i class="fas fa-clock mr-1"></i>Inadimplência</div>
        <div class="text-2xl font-black text-yellow-700"><?= formatarDinheiro($totalInadimplente) ?></div>
        <div class="text-xs text-yellow-600 mt-1"><?= count($inadimplentes) ?> aluguel(is)</div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- COLUNA 1: Receitas detalhadas -->
    <div class="lg:col-span-2 space-y-6">

        <!-- RECEITAS -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                <i class="fas fa-arrow-trend-up text-green-500"></i>
                <h3 class="font-black text-slate-800">Receitas — Aluguéis Recebidos</h3>
            </div>
            <?php if (empty($receitas)): ?>
            <div class="px-5 py-8 text-center text-slate-400 text-sm font-semibold">Nenhum aluguel recebido no período.</div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-slate-50 bg-slate-50">
                        <th class="text-left px-5 py-2.5 text-xs font-black text-slate-500 uppercase">Inquilino</th>
                        <th class="text-left px-5 py-2.5 text-xs font-black text-slate-500 uppercase hidden sm:table-cell">Data</th>
                        <th class="text-left px-5 py-2.5 text-xs font-black text-slate-500 uppercase hidden md:table-cell">Forma</th>
                        <th class="text-right px-5 py-2.5 text-xs font-black text-slate-500 uppercase">Valor</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-50">
                    <?php foreach ($receitas as $r): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-2.5 font-semibold text-slate-800"><?= e($r['inquilino']) ?> <span class="text-slate-400 font-normal">— Q.<?= e($r['quarto']) ?></span></td>
                            <td class="px-5 py-2.5 text-slate-500 hidden sm:table-cell"><?= formatarData($r['data_pagamento']) ?></td>
                            <td class="px-5 py-2.5 text-slate-500 hidden md:table-cell"><?= e($r['forma_pagamento'] ?? '—') ?></td>
                            <td class="px-5 py-2.5 font-black text-green-600 text-right"><?= formatarDinheiro($r['valor_pago']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr class="border-t-2 border-slate-200">
                        <td colspan="3" class="px-5 py-3 font-black text-slate-600 text-sm">TOTAL RECEITAS</td>
                        <td class="px-5 py-3 font-black text-green-600 text-right text-base"><?= formatarDinheiro($totalReceitas) ?></td>
                    </tr></tfoot>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- DESPESAS -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                <i class="fas fa-receipt text-red-500"></i>
                <h3 class="font-black text-slate-800">Despesas Pagas</h3>
            </div>
            <?php if (empty($despesas)): ?>
            <div class="px-5 py-8 text-center text-slate-400 text-sm font-semibold">Nenhuma despesa registrada no período.</div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-slate-50 bg-slate-50">
                        <th class="text-left px-5 py-2.5 text-xs font-black text-slate-500 uppercase">Descrição</th>
                        <th class="text-left px-5 py-2.5 text-xs font-black text-slate-500 uppercase hidden sm:table-cell">Categoria</th>
                        <th class="text-left px-5 py-2.5 text-xs font-black text-slate-500 uppercase hidden md:table-cell">Data</th>
                        <th class="text-right px-5 py-2.5 text-xs font-black text-slate-500 uppercase">Valor</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-50">
                    <?php foreach ($despesas as $d): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-2.5 font-semibold text-slate-800">
                                <?= e($d['titulo']) ?>
                                <?php if ($d['quarto_numero']): ?><span class="text-xs text-slate-400"> — Q.<?= e($d['quarto_numero']) ?></span><?php endif; ?>
                            </td>
                            <td class="px-5 py-2.5 text-slate-500 hidden sm:table-cell"><?= $categorias[$d['categoria']] ?? $d['categoria'] ?></td>
                            <td class="px-5 py-2.5 text-slate-500 hidden md:table-cell"><?= formatarData($d['data_pagamento']) ?></td>
                            <td class="px-5 py-2.5 font-black text-red-600 text-right"><?= formatarDinheiro($d['valor']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr class="border-t-2 border-slate-200">
                        <td colspan="3" class="px-5 py-3 font-black text-slate-600 text-sm">TOTAL DESPESAS</td>
                        <td class="px-5 py-3 font-black text-red-600 text-right text-base"><?= formatarDinheiro($totalDespesas) ?></td>
                    </tr></tfoot>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- COLUNA 2: Resumo lateral -->
    <div class="space-y-4">

        <!-- Despesas por categoria -->
        <?php if (!empty($despesasPorCat)): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
            <h3 class="font-black text-slate-800 mb-4 text-sm">Despesas por Categoria</h3>
            <div class="space-y-3">
            <?php foreach ($despesasPorCat as $cat => $val): ?>
                <?php $pct = $totalDespesas > 0 ? round(($val/$totalDespesas)*100) : 0; ?>
                <div>
                    <div class="flex justify-between text-xs font-semibold mb-1">
                        <span class="text-slate-600"><?= $categorias[$cat] ?? $cat ?></span>
                        <span class="text-slate-800 font-black"><?= formatarDinheiro($val) ?></span>
                    </div>
                    <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all" style="width:<?= $pct ?>%;background:#e5a820"></div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Inadimplentes -->
        <?php if (!empty($inadimplentes)): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-red-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-red-100 flex items-center gap-2 bg-red-50">
                <i class="fas fa-triangle-exclamation text-red-500"></i>
                <h3 class="font-black text-red-800 text-sm">Inadimplência no período</h3>
            </div>
            <div class="divide-y divide-slate-50">
            <?php foreach ($inadimplentes as $in): ?>
                <div class="px-5 py-3">
                    <div class="font-bold text-slate-800 text-sm"><?= e($in['inquilino']) ?> — Q.<?= e($in['quarto']) ?></div>
                    <div class="flex justify-between text-xs mt-0.5">
                        <span class="text-slate-500">Venc. <?= formatarData($in['data_vencimento']) ?></span>
                        <span class="font-black text-red-600"><?= formatarDinheiro($in['valor']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- SALDO FINAL DESTAQUE -->
        <div class="rounded-2xl p-6 text-center <?= $saldo >= 0 ? 'bg-green-600' : 'bg-red-600' ?>">
            <div class="text-white/80 text-xs font-bold uppercase mb-2">Saldo do período</div>
            <div class="text-4xl font-black text-white"><?= formatarDinheiro($saldo) ?></div>
            <div class="text-white/70 text-xs mt-2"><?= formatarData($dataInicio) ?> a <?= formatarData($dataFim) ?></div>
        </div>
    </div>
</div>

<style>
.label-form { display:block; font-size:.75rem; font-weight:700; color:#475569; margin-bottom:.375rem; }
.input-form  { padding:.625rem .875rem; border-radius:.75rem; border:1px solid #e2e8f0; font-size:.875rem; font-weight:600; outline:none; transition:all .15s; }
.input-form:focus { border-color:#e5a820; box-shadow:0 0 0 3px rgba(229,168,32,.1); }

/* Estilos de impressão */
@media print {
    aside, header, form, button { display:none !important; }
    body { background:white; }
    main { padding:0; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
