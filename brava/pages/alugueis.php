<?php
// =============================================================
// Aluguéis — listagem e registro de pagamentos
// =============================================================
require_once __DIR__ . '/../includes/auth.php';
exigirLogin();

$db   = getDB();
$hoje = date('Y-m-d');

// ---------------------------------------------------------------
// PROCESSAR PAGAMENTO
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $acaoPost = $_POST['acao'] ?? '';

    if ($acaoPost === 'registrar_pagamento') {
        $id          = (int)$_POST['id'];
        $valorPago   = (float)$_POST['valor_pago'];
        $dataPag     = $_POST['data_pagamento'];
        $formaPag    = trim($_POST['forma_pagamento'] ?? '');
        $obs         = trim($_POST['observacoes'] ?? '');

        // Busca o valor original para saber se é pagamento total ou parcial
        $stmt = $db->prepare('SELECT valor FROM alugueis WHERE id = ?');
        $stmt->execute([$id]);
        $aluguel = $stmt->fetch();

        if ($aluguel) {
            $status = $valorPago >= $aluguel['valor'] ? 'pago' : 'parcial';

            $stmt = $db->prepare("
                UPDATE alugueis
                SET valor_pago = ?, data_pagamento = ?, forma_pagamento = ?, observacoes = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([$valorPago, $dataPag, $formaPag, $obs, $status, $id]);
            flash('sucesso', 'Pagamento registrado com sucesso!');
        }

        redirecionar('pages/alugueis.php');
    }

    // Gerar aluguel manualmente para mês específico
    if ($acaoPost === 'gerar_aluguel') {
        $contratoId  = (int)$_POST['contrato_id'];
        $competencia = $_POST['competencia']; // YYYY-MM

        // Busca dados do contrato
        $stmt = $db->prepare("SELECT * FROM contratos WHERE id = ? AND status = 'ativo'");
        $stmt->execute([$contratoId]);
        $contrato = $stmt->fetch();

        if ($contrato) {
            $compDate   = $competencia . '-01';
            $diaVenc    = $contrato['dia_vencimento'];
            $mes        = (int)date('m', strtotime($compDate));
            $ano        = (int)date('Y', strtotime($compDate));
            $dataVenc   = date('Y-m-d', mktime(0,0,0,$mes,$diaVenc,$ano));

            try {
                $stmt = $db->prepare("INSERT INTO alugueis (contrato_id, competencia, data_vencimento, valor) VALUES (?,?,?,?)");
                $stmt->execute([$contratoId, $compDate, $dataVenc, $contrato['valor_aluguel']]);
                flash('sucesso', 'Aluguel gerado!');
            } catch (PDOException $e) {
                flash('erro', 'Aluguel já existe para este mês/contrato.');
            }
        }
        redirecionar('pages/alugueis.php');
    }

    // Atualiza automaticamente status de atrasados
    $db->prepare("
        UPDATE alugueis SET status = 'atrasado'
        WHERE status = 'pendente' AND data_vencimento < ?
    ")->execute([$hoje]);
}

// ---------------------------------------------------------------
// FILTROS
// ---------------------------------------------------------------
$filtroStatus = $_GET['status'] ?? '';
$filtroMes    = $_GET['mes']    ?? date('Y-m');

$where  = ['a.competencia = ?'];
$params = [$filtroMes . '-01'];

if ($filtroStatus) {
    $where[]  = 'a.status = ?';
    $params[] = $filtroStatus;
}

$whereStr = implode(' AND ', $where);

$alugueis = $db->prepare("
    SELECT
        a.id, a.competencia, a.data_vencimento, a.data_pagamento,
        a.valor, a.valor_pago, a.status, a.forma_pagamento,
        i.nome AS inquilino, i.telefone,
        q.numero AS quarto
    FROM alugueis a
    JOIN contratos c ON a.contrato_id = c.id
    JOIN inquilinos i ON c.inquilino_id = i.id
    JOIN quartos q ON c.quarto_id = q.id
    WHERE $whereStr
    ORDER BY a.status ASC, a.data_vencimento ASC
");
$alugueis->execute($params);
$alugueis = $alugueis->fetchAll();

// Totais do mês filtrado
$totais = ['pendente'=>0,'pago'=>0,'atrasado'=>0,'parcial'=>0,'total_valor'=>0,'total_recebido'=>0];
foreach ($alugueis as $a) {
    $totais[$a['status']] = ($totais[$a['status']] ?? 0) + 1;
    $totais['total_valor']    += $a['valor'];
    $totais['total_recebido'] += $a['valor_pago'];
}

// Contratos ativos para geração manual
$contratos = $db->query("
    SELECT c.id, i.nome AS inquilino, q.numero AS quarto
    FROM contratos c
    JOIN inquilinos i ON c.inquilino_id = i.id
    JOIN quartos q ON c.quarto_id = q.id
    WHERE c.status = 'ativo'
    ORDER BY i.nome
")->fetchAll();

$pageTitle = 'Aluguéis';
require_once __DIR__ . '/../includes/header.php';

// Mapa de cores por status
$statusConfig = [
    'pendente' => ['bg'=>'bg-yellow-100','text'=>'text-yellow-700','label'=>'Pendente'],
    'pago'     => ['bg'=>'bg-green-100', 'text'=>'text-green-700', 'label'=>'Pago'],
    'atrasado' => ['bg'=>'bg-red-100',   'text'=>'text-red-700',   'label'=>'Atrasado'],
    'parcial'  => ['bg'=>'bg-blue-100',  'text'=>'text-blue-700',  'label'=>'Parcial'],
];
?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <h2 class="text-2xl font-black text-slate-800">Aluguéis</h2>
    <button onclick="document.getElementById('modalGerar').classList.remove('hidden')"
            class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 font-bold text-sm hover:bg-slate-50">
        <i class="fas fa-plus-circle mr-1"></i> Gerar aluguel
    </button>
</div>

<!-- MINI TOTAIS -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
    <?php
    $cards = [
        ['label'=>'Pendentes',  'value'=>$totais['pendente'], 'color'=>'text-yellow-600','bg'=>'bg-yellow-50'],
        ['label'=>'Pagos',      'value'=>$totais['pago'],     'color'=>'text-green-600', 'bg'=>'bg-green-50'],
        ['label'=>'Atrasados',  'value'=>$totais['atrasado'], 'color'=>'text-red-600',   'bg'=>'bg-red-50'],
        ['label'=>'Total recebido','value'=>formatarDinheiro($totais['total_recebido']), 'color'=>'text-slate-800','bg'=>'bg-white'],
    ];
    foreach ($cards as $c): ?>
    <div class="<?= $c['bg'] ?> rounded-xl p-4 border border-slate-100">
        <div class="text-xs font-bold text-slate-500 mb-1"><?= $c['label'] ?></div>
        <div class="text-xl font-black <?= $c['color'] ?>"><?= $c['value'] ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- FILTROS -->
<form method="GET" class="flex flex-wrap gap-3 mb-5">
    <input type="month" name="mes" value="<?= e($filtroMes) ?>"
           class="px-3 py-2 rounded-xl border border-slate-200 text-sm font-semibold outline-none focus:border-yellow-400">
    <select name="status" class="px-3 py-2 rounded-xl border border-slate-200 text-sm font-semibold outline-none focus:border-yellow-400">
        <option value="">Todos os status</option>
        <option value="pendente" <?= $filtroStatus==='pendente'?'selected':'' ?>>Pendente</option>
        <option value="pago"     <?= $filtroStatus==='pago'    ?'selected':'' ?>>Pago</option>
        <option value="atrasado" <?= $filtroStatus==='atrasado'?'selected':'' ?>>Atrasado</option>
        <option value="parcial"  <?= $filtroStatus==='parcial' ?'selected':'' ?>>Parcial</option>
    </select>
    <button type="submit" class="px-4 py-2 rounded-xl text-white font-bold text-sm hover:brightness-110" style="background:#1e2d40">
        <i class="fas fa-filter mr-1"></i> Filtrar
    </button>
</form>

<!-- TABELA -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50">
                    <th class="text-left px-5 py-3 text-xs font-black text-slate-500 uppercase">Inquilino</th>
                    <th class="text-left px-5 py-3 text-xs font-black text-slate-500 uppercase hidden sm:table-cell">Quarto</th>
                    <th class="text-left px-5 py-3 text-xs font-black text-slate-500 uppercase hidden md:table-cell">Vencimento</th>
                    <th class="text-left px-5 py-3 text-xs font-black text-slate-500 uppercase">Valor</th>
                    <th class="text-left px-5 py-3 text-xs font-black text-slate-500 uppercase">Status</th>
                    <th class="text-right px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
            <?php if (empty($alugueis)): ?>
                <tr><td colspan="6" class="text-center py-10 text-slate-400 font-semibold">Nenhum aluguel encontrado para este período.</td></tr>
            <?php else: foreach ($alugueis as $a):
                $cfg = $statusConfig[$a['status']] ?? $statusConfig['pendente'];
                $vencido = $a['status'] === 'atrasado';
            ?>
                <tr class="hover:bg-slate-50 transition-colors <?= $vencido ? 'vencido-pisca' : '' ?>">
                    <td class="px-5 py-3">
                        <div class="font-bold text-slate-800"><?= e($a['inquilino']) ?></div>
                        <?php if ($a['telefone']): ?>
                        <a href="https://wa.me/55<?= preg_replace('/\D/', '', $a['telefone']) ?>?text=<?= urlencode('Olá ' . $a['inquilino'] . ', seu aluguel de ' . date('m/Y', strtotime($a['competencia'])) . ' está ' . ($vencido ? 'em atraso' : 'próximo do vencimento') . '. Valor: ' . formatarDinheiro($a['valor'])) ?>"
                           target="_blank" class="text-xs text-green-600 hover:text-green-700 font-semibold">
                            <i class="fab fa-whatsapp"></i> WhatsApp
                        </a>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 font-semibold text-slate-600 hidden sm:table-cell">Quarto <?= e($a['quarto']) ?></td>
                    <td class="px-5 py-3 text-slate-500 hidden md:table-cell"><?= formatarData($a['data_vencimento']) ?></td>
                    <td class="px-5 py-3">
                        <div class="font-black text-slate-800"><?= formatarDinheiro($a['valor']) ?></div>
                        <?php if ($a['valor_pago'] > 0 && $a['status'] !== 'pago'): ?>
                        <div class="text-xs text-green-600 font-semibold">Pago: <?= formatarDinheiro($a['valor_pago']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3">
                        <span class="px-2 py-1 rounded-full text-xs font-bold <?= $cfg['bg'] ?> <?= $cfg['text'] ?>">
                            <?= $cfg['label'] ?>
                        </span>
                        <?php if ($a['data_pagamento']): ?>
                        <div class="text-xs text-slate-400 mt-0.5"><?= formatarData($a['data_pagamento']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <?php if ($a['status'] !== 'pago'): ?>
                        <button onclick='abrirPagamento(<?= json_encode($a) ?>)'
                                class="text-xs font-bold px-3 py-1.5 rounded-lg text-white hover:brightness-110 transition" style="background:#e5a820">
                            <i class="fas fa-money-bill mr-1"></i> Pagar
                        </button>
                        <?php else: ?>
                        <span class="text-xs text-slate-400"><?= e($a['forma_pagamento'] ?? '') ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL REGISTRAR PAGAMENTO -->
<div id="modalPag" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="font-black text-slate-800">Registrar Pagamento</h3>
                <p class="text-sm text-slate-500" id="pagInquilinoLabel"></p>
            </div>
            <button onclick="document.getElementById('modalPag').classList.add('hidden')" class="text-slate-400 hover:text-slate-700">✕</button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="acao" value="registrar_pagamento">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="id" id="pagId">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label-form">Valor pago (R$)</label>
                    <input type="number" name="valor_pago" id="pagValor" step="0.01" min="0" required class="input-form">
                </div>
                <div>
                    <label class="label-form">Data do pagamento</label>
                    <input type="date" name="data_pagamento" value="<?= date('Y-m-d') ?>" required class="input-form">
                </div>
            </div>
            <div>
                <label class="label-form">Forma de pagamento</label>
                <select name="forma_pagamento" class="input-form">
                    <option value="Pix">Pix</option>
                    <option value="Dinheiro">Dinheiro</option>
                    <option value="Transferência">Transferência</option>
                    <option value="Boleto">Boleto</option>
                    <option value="Cartão">Cartão</option>
                </select>
            </div>
            <div>
                <label class="label-form">Observações</label>
                <input type="text" name="observacoes" class="input-form" placeholder="Opcional">
            </div>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="document.getElementById('modalPag').classList.add('hidden')"
                        class="flex-1 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold text-sm hover:bg-slate-50">Cancelar</button>
                <button type="submit" class="flex-1 py-2.5 rounded-xl text-white font-bold text-sm hover:brightness-110" style="background:#1e2d40">
                    <i class="fas fa-check mr-1"></i> Confirmar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL GERAR ALUGUEL -->
<div id="modalGerar" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-black text-slate-800">Gerar Aluguel</h3>
            <button onclick="document.getElementById('modalGerar').classList.add('hidden')" class="text-slate-400 hover:text-slate-700">✕</button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="acao" value="gerar_aluguel">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <div>
                <label class="label-form">Contrato / Inquilino</label>
                <select name="contrato_id" required class="input-form">
                    <?php foreach ($contratos as $ct): ?>
                    <option value="<?= $ct['id'] ?>">Quarto <?= e($ct['quarto']) ?> — <?= e($ct['inquilino']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="label-form">Mês de competência</label>
                <input type="month" name="competencia" value="<?= date('Y-m') ?>" required class="input-form">
            </div>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="document.getElementById('modalGerar').classList.add('hidden')"
                        class="flex-1 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold text-sm hover:bg-slate-50">Cancelar</button>
                <button type="submit" class="flex-1 py-2.5 rounded-xl text-white font-bold text-sm hover:brightness-110" style="background:#1e2d40">Gerar</button>
            </div>
        </form>
    </div>
</div>

<style>
.label-form { display:block; font-size:.75rem; font-weight:700; color:#475569; margin-bottom:.375rem; }
.input-form  { width:100%; padding:.625rem .875rem; border-radius:.75rem; border:1px solid #e2e8f0; font-size:.875rem; font-weight:600; outline:none; transition:all .15s; }
.input-form:focus { border-color:#e5a820; box-shadow:0 0 0 3px rgba(229,168,32,.1); }
</style>

<script>
function abrirPagamento(a) {
    document.getElementById('pagId').value    = a.id;
    document.getElementById('pagValor').value = a.valor;
    document.getElementById('pagInquilinoLabel').textContent = a.inquilino + ' — Quarto ' + a.quarto + ' — Venc. ' + a.data_vencimento;
    document.getElementById('modalPag').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
