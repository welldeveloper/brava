<?php
// =============================================================
// Dashboard — visão geral do sistema
// =============================================================
require_once __DIR__ . '/../includes/auth.php';
exigirLogin();

$db = getDB();
$hoje = date('Y-m-d');
$limiteAlerta = date('Y-m-d', strtotime('+' . ALERTA_DIAS_ANTES . ' days'));

// --- TOTALIZADORES ---

// Total de quartos e ocupação
$totalQuartos    = $db->query('SELECT COUNT(*) FROM quartos')->fetchColumn();
$quartosOcupados = $db->query("SELECT COUNT(*) FROM quartos WHERE status = 'ocupado'")->fetchColumn();
$quartosLivres   = $db->query("SELECT COUNT(*) FROM quartos WHERE status = 'disponivel'")->fetchColumn();

// Total de inquilinos ativos
$totalInquilinos = $db->query('SELECT COUNT(*) FROM inquilinos WHERE ativo = 1')->fetchColumn();

// Receita do mês atual (aluguéis pagos)
$mesAtual = date('Y-m-01');
$stmt = $db->prepare("
    SELECT COALESCE(SUM(valor_pago), 0)
    FROM alugueis
    WHERE competencia = ? AND status = 'pago'
");
$stmt->execute([$mesAtual]);
$receitaMes = $stmt->fetchColumn();

// Total de contas a pagar pendentes
$stmt = $db->prepare("SELECT COALESCE(SUM(valor), 0) FROM contas_pagar WHERE status = 'pendente'");
$stmt->execute();
$contasPendentes = $stmt->fetchColumn();

// --- ALUGUÉIS VENCIDOS (atrasados) ---
$stmt = $db->prepare("
    SELECT
        a.id, a.data_vencimento, a.valor,
        i.nome AS inquilino, i.telefone,
        q.numero AS quarto
    FROM alugueis a
    JOIN contratos c ON a.contrato_id = c.id
    JOIN inquilinos i ON c.inquilino_id = i.id
    JOIN quartos q ON c.quarto_id = q.id
    WHERE a.status IN ('pendente','atrasado')
      AND a.data_vencimento < ?
    ORDER BY a.data_vencimento ASC
    LIMIT 10
");
$stmt->execute([$hoje]);
$alugueisVencidos = $stmt->fetchAll();

// --- ALUGUÉIS PRÓXIMOS A VENCER ---
$stmt = $db->prepare("
    SELECT
        a.id, a.data_vencimento, a.valor,
        i.nome AS inquilino, i.telefone,
        q.numero AS quarto
    FROM alugueis a
    JOIN contratos c ON a.contrato_id = c.id
    JOIN inquilinos i ON c.inquilino_id = i.id
    JOIN quartos q ON c.quarto_id = q.id
    WHERE a.status = 'pendente'
      AND a.data_vencimento BETWEEN ? AND ?
    ORDER BY a.data_vencimento ASC
    LIMIT 10
");
$stmt->execute([$hoje, $limiteAlerta]);
$alugueisProximos = $stmt->fetchAll();

// --- CONTAS A PAGAR PRÓXIMAS ---
$stmt = $db->prepare("
    SELECT titulo, categoria, valor, data_vencimento
    FROM contas_pagar
    WHERE status = 'pendente'
      AND data_vencimento <= ?
    ORDER BY data_vencimento ASC
    LIMIT 5
");
$stmt->execute([$limiteAlerta]);
$contasProximas = $stmt->fetchAll();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ============================================================
     CARDS DE RESUMO
     ============================================================ -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

    <!-- Quartos ocupados -->
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Ocupação</span>
            <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background:#1e2d40">
                <i class="fas fa-door-open text-yellow-400 text-sm"></i>
            </div>
        </div>
        <div class="text-3xl font-black" style="color:#1e2d40"><?= $quartosOcupados ?><span class="text-base font-semibold text-slate-400">/<?= $totalQuartos ?></span></div>
        <p class="text-xs text-slate-500 mt-1"><?= $quartosLivres ?> disponível<?= $quartosLivres != 1 ? 'is' : '' ?></p>
    </div>

    <!-- Inquilinos -->
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Inquilinos</span>
            <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background:#1e2d40">
                <i class="fas fa-users text-yellow-400 text-sm"></i>
            </div>
        </div>
        <div class="text-3xl font-black" style="color:#1e2d40"><?= $totalInquilinos ?></div>
        <p class="text-xs text-slate-500 mt-1">ativos no momento</p>
    </div>

    <!-- Receita do mês -->
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Receita/mês</span>
            <div class="w-9 h-9 rounded-xl flex items-center justify-center bg-green-600">
                <i class="fas fa-arrow-trend-up text-white text-sm"></i>
            </div>
        </div>
        <div class="text-2xl font-black text-green-700"><?= formatarDinheiro($receitaMes) ?></div>
        <p class="text-xs text-slate-500 mt-1">aluguéis pagos em <?= date('m/Y') ?></p>
    </div>

    <!-- Contas pendentes -->
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Contas</span>
            <div class="w-9 h-9 rounded-xl flex items-center justify-center bg-orange-500">
                <i class="fas fa-receipt text-white text-sm"></i>
            </div>
        </div>
        <div class="text-2xl font-black text-orange-600"><?= formatarDinheiro($contasPendentes) ?></div>
        <p class="text-xs text-slate-500 mt-1">a pagar pendentes</p>
    </div>
</div>

<!-- ============================================================
     ALERTAS: VENCIDOS + PRÓXIMOS
     ============================================================ -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

    <!-- ALUGUÉIS VENCIDOS -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <i class="fas fa-triangle-exclamation text-red-500"></i>
                <h2 class="font-black text-slate-800">Aluguéis Vencidos</h2>
            </div>
            <span class="bg-red-100 text-red-700 text-xs font-bold px-2 py-1 rounded-full">
                <?= count($alugueisVencidos) ?>
            </span>
        </div>

        <div class="divide-y divide-slate-50">
            <?php if (empty($alugueisVencidos)): ?>
                <div class="px-5 py-8 text-center text-slate-400">
                    <i class="fas fa-circle-check text-2xl text-green-400 mb-2"></i>
                    <p class="text-sm font-semibold">Nenhum aluguel vencido!</p>
                </div>
            <?php else: foreach ($alugueisVencidos as $al): ?>
                <!-- Card piscando para chamar atenção -->
                <div class="vencido-pisca px-5 py-3 border border-red-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-bold text-slate-800 text-sm"><?= e($al['inquilino']) ?></div>
                            <div class="text-xs text-slate-500">Quarto <?= e($al['quarto']) ?> · Venceu <?= formatarData($al['data_vencimento']) ?></div>
                        </div>
                        <div class="text-right">
                            <div class="font-black text-red-600 text-sm"><?= formatarDinheiro($al['valor']) ?></div>
                            <?php if ($al['telefone']): ?>
                            <a href="https://wa.me/55<?= preg_replace('/\D/', '', $al['telefone']) ?>?text=<?= urlencode('Olá ' . $al['inquilino'] . ', seu aluguel do quarto ' . $al['quarto'] . ' venceu em ' . formatarData($al['data_vencimento']) . '. Por favor, entre em contato para regularizar.') ?>"
                               target="_blank"
                               class="text-xs text-green-600 hover:text-green-700 font-semibold">
                                <i class="fab fa-whatsapp"></i> Cobrar
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- PRÓXIMOS A VENCER -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <i class="fas fa-clock text-yellow-500"></i>
                <h2 class="font-black text-slate-800">Vencem em <?= ALERTA_DIAS_ANTES ?> dias</h2>
            </div>
            <span class="bg-yellow-100 text-yellow-700 text-xs font-bold px-2 py-1 rounded-full">
                <?= count($alugueisProximos) ?>
            </span>
        </div>

        <div class="divide-y divide-slate-50">
            <?php if (empty($alugueisProximos)): ?>
                <div class="px-5 py-8 text-center text-slate-400">
                    <i class="fas fa-calendar-check text-2xl text-slate-300 mb-2"></i>
                    <p class="text-sm font-semibold">Nada vencendo em breve.</p>
                </div>
            <?php else: foreach ($alugueisProximos as $al): ?>
                <div class="px-5 py-3 bg-yellow-50/50 hover:bg-yellow-50 transition-colors">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-bold text-slate-800 text-sm"><?= e($al['inquilino']) ?></div>
                            <div class="text-xs text-slate-500">Quarto <?= e($al['quarto']) ?> · Vence <?= formatarData($al['data_vencimento']) ?></div>
                        </div>
                        <div class="text-right">
                            <div class="font-black text-slate-700 text-sm"><?= formatarDinheiro($al['valor']) ?></div>
                            <?php if ($al['telefone']): ?>
                            <a href="https://wa.me/55<?= preg_replace('/\D/', '', $al['telefone']) ?>?text=<?= urlencode('Olá ' . $al['inquilino'] . ', lembrando que seu aluguel do quarto ' . $al['quarto'] . ' vence em ' . formatarData($al['data_vencimento']) . '. Qualquer dúvida, entre em contato!') ?>"
                               target="_blank"
                               class="text-xs text-green-600 hover:text-green-700 font-semibold">
                                <i class="fab fa-whatsapp"></i> Lembrar
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<!-- CONTAS A PAGAR PRÓXIMAS -->
<?php if (!empty($contasProximas)): ?>
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
        <i class="fas fa-receipt text-orange-500"></i>
        <h2 class="font-black text-slate-800">Contas a Pagar — Próximos <?= ALERTA_DIAS_ANTES ?> dias</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <tbody class="divide-y divide-slate-50">
            <?php foreach ($contasProximas as $conta): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-5 py-3 font-semibold text-slate-800"><?= e($conta['titulo']) ?></td>
                    <td class="px-5 py-3 text-slate-500"><?= e(ucfirst($conta['categoria'])) ?></td>
                    <td class="px-5 py-3 text-slate-500"><?= formatarData($conta['data_vencimento']) ?></td>
                    <td class="px-5 py-3 font-black text-orange-600 text-right"><?= formatarDinheiro($conta['valor']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
