<?php
// =============================================================
// Contas a Pagar — despesas, manutenção, água, etc.
// =============================================================
require_once __DIR__ . '/../includes/auth.php';
exigirLogin();

$db   = getDB();
$hoje = date('Y-m-d');

// Atualiza status para "atrasado" se passou do vencimento
$db->prepare("UPDATE contas_pagar SET status='atrasado' WHERE status='pendente' AND data_vencimento < ?")->execute([$hoje]);

// ---------------------------------------------------------------
// PROCESSAR FORMULÁRIOS
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $acaoPost = $_POST['acao'] ?? '';

    if ($acaoPost === 'salvar') {
        $titulo    = trim($_POST['titulo'] ?? '');
        $categoria = $_POST['categoria'] ?? 'outro';
        $desc      = trim($_POST['descricao'] ?? '');
        $valor     = (float)($_POST['valor'] ?? 0);
        $vencto    = $_POST['data_vencimento'];
        $quartoId  = ((int)($_POST['quarto_id'] ?? 0)) ?: null;
        $recorrente= isset($_POST['recorrente']) ? 1 : 0;

        if (empty($titulo) || !$valor || !$vencto) {
            flash('erro', 'Preencha os campos obrigatórios.');
        } else {
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                $stmt = $db->prepare("UPDATE contas_pagar SET titulo=?,categoria=?,descricao=?,valor=?,data_vencimento=?,quarto_id=?,recorrente=? WHERE id=?");
                $stmt->execute([$titulo,$categoria,$desc,$valor,$vencto,$quartoId,$recorrente,$id]);
            } else {
                $stmt = $db->prepare("INSERT INTO contas_pagar (titulo,categoria,descricao,valor,data_vencimento,quarto_id,recorrente) VALUES (?,?,?,?,?,?,?)");
                $stmt->execute([$titulo,$categoria,$desc,$valor,$vencto,$quartoId,$recorrente]);
            }
            flash('sucesso', 'Conta salva com sucesso!');
        }
        redirecionar('pages/contas.php');
    }

    if ($acaoPost === 'pagar') {
        $id     = (int)$_POST['id'];
        $dataPag = $_POST['data_pagamento'] ?? $hoje;
        $stmt = $db->prepare("UPDATE contas_pagar SET status='pago', data_pagamento=? WHERE id=?");
        $stmt->execute([$dataPag, $id]);

        // Se é recorrente, gera a próxima para o mês seguinte
        $stmt = $db->prepare("SELECT * FROM contas_pagar WHERE id=?");
        $stmt->execute([$id]);
        $conta = $stmt->fetch();
        if ($conta && $conta['recorrente']) {
            $novoVenc = date('Y-m-d', strtotime($conta['data_vencimento'] . ' +1 month'));
            $stmt = $db->prepare("INSERT INTO contas_pagar (titulo,categoria,descricao,valor,data_vencimento,quarto_id,recorrente) VALUES (?,?,?,?,?,?,1)");
            $stmt->execute([$conta['titulo'],$conta['categoria'],$conta['descricao'],$conta['valor'],$novoVenc,$conta['quarto_id']]);
        }

        flash('sucesso', 'Pagamento confirmado!');
        redirecionar('pages/contas.php');
    }

    if ($acaoPost === 'excluir') {
        $id = (int)$_POST['id'];
        $db->prepare("DELETE FROM contas_pagar WHERE id=?")->execute([$id]);
        flash('sucesso', 'Conta removida.');
        redirecionar('pages/contas.php');
    }
}

// ---------------------------------------------------------------
// CARREGAR DADOS
// ---------------------------------------------------------------
$filtroStatus = $_GET['status'] ?? 'pendente';
$filtroCategoria = $_GET['categoria'] ?? '';

$where  = ['1=1'];
$params = [];

if ($filtroStatus) { $where[] = 'c.status = ?'; $params[] = $filtroStatus; }
if ($filtroCategoria) { $where[] = 'c.categoria = ?'; $params[] = $filtroCategoria; }

$sql = "SELECT c.*, q.numero AS quarto_numero FROM contas_pagar c
        LEFT JOIN quartos q ON c.quarto_id = q.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY c.data_vencimento ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$contas = $stmt->fetchAll();

// Total das contas filtradas
$totalFiltrado = array_sum(array_column($contas, 'valor'));

// Quartos para select
$quartos = $db->query("SELECT id, numero FROM quartos ORDER BY numero")->fetchAll();

$categorias = ['manutencao'=>'Manutenção','agua'=>'Água','luz'=>'Luz','internet'=>'Internet','imposto'=>'Imposto','seguro'=>'Seguro','outro'=>'Outro'];
$statusConfig = [
    'pendente' => ['bg'=>'bg-yellow-100','text'=>'text-yellow-700','label'=>'Pendente'],
    'pago'     => ['bg'=>'bg-green-100', 'text'=>'text-green-700', 'label'=>'Pago'],
    'atrasado' => ['bg'=>'bg-red-100',   'text'=>'text-red-700',   'label'=>'Atrasado'],
];

$pageTitle = 'Contas a Pagar';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <h2 class="text-2xl font-black text-slate-800">Contas a Pagar</h2>
    <button onclick="document.getElementById('modalConta').classList.remove('hidden')"
            class="px-4 py-2 rounded-xl text-white font-bold text-sm flex items-center gap-2 hover:brightness-110 transition" style="background:#1e2d40">
        <i class="fas fa-plus"></i> Nova conta
    </button>
</div>

<!-- FILTROS -->
<form method="GET" class="flex flex-wrap gap-3 mb-5">
    <select name="status" class="px-3 py-2 rounded-xl border border-slate-200 text-sm font-semibold outline-none focus:border-yellow-400">
        <option value="">Todos</option>
        <option value="pendente"  <?= $filtroStatus==='pendente' ?'selected':'' ?>>Pendentes</option>
        <option value="pago"      <?= $filtroStatus==='pago'     ?'selected':'' ?>>Pagas</option>
        <option value="atrasado"  <?= $filtroStatus==='atrasado' ?'selected':'' ?>>Atrasadas</option>
    </select>
    <select name="categoria" class="px-3 py-2 rounded-xl border border-slate-200 text-sm font-semibold outline-none focus:border-yellow-400">
        <option value="">Todas categorias</option>
        <?php foreach ($categorias as $k => $v): ?>
        <option value="<?= $k ?>" <?= $filtroCategoria===$k?'selected':'' ?>><?= $v ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="px-4 py-2 rounded-xl text-white font-bold text-sm hover:brightness-110" style="background:#1e2d40">
        <i class="fas fa-filter mr-1"></i> Filtrar
    </button>
</form>

<?php if (!empty($contas)): ?>
<div class="text-sm font-bold text-slate-600 mb-3">
    Total filtrado: <span class="text-lg font-black" style="color:#e5a820"><?= formatarDinheiro($totalFiltrado) ?></span>
</div>
<?php endif; ?>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50">
                    <th class="text-left px-5 py-3 text-xs font-black text-slate-500 uppercase">Título</th>
                    <th class="text-left px-5 py-3 text-xs font-black text-slate-500 uppercase hidden md:table-cell">Categoria</th>
                    <th class="text-left px-5 py-3 text-xs font-black text-slate-500 uppercase hidden sm:table-cell">Vencimento</th>
                    <th class="text-left px-5 py-3 text-xs font-black text-slate-500 uppercase">Valor</th>
                    <th class="text-left px-5 py-3 text-xs font-black text-slate-500 uppercase">Status</th>
                    <th class="text-right px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
            <?php if (empty($contas)): ?>
                <tr><td colspan="6" class="text-center py-10 text-slate-400 font-semibold">Nenhuma conta encontrada.</td></tr>
            <?php else: foreach ($contas as $c):
                $cfg = $statusConfig[$c['status']] ?? $statusConfig['pendente'];
            ?>
                <tr class="hover:bg-slate-50 transition-colors <?= $c['status']==='atrasado' ? 'vencido-pisca' : '' ?>">
                    <td class="px-5 py-3">
                        <div class="font-bold text-slate-800"><?= e($c['titulo']) ?></div>
                        <?php if ($c['quarto_numero']): ?>
                        <div class="text-xs text-slate-400">Quarto <?= e($c['quarto_numero']) ?></div>
                        <?php endif; ?>
                        <?php if ($c['recorrente']): ?>
                        <span class="text-xs text-blue-500 font-semibold"><i class="fas fa-rotate-right"></i> Recorrente</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 hidden md:table-cell text-slate-500"><?= $categorias[$c['categoria']] ?? $c['categoria'] ?></td>
                    <td class="px-5 py-3 text-slate-500 hidden sm:table-cell"><?= formatarData($c['data_vencimento']) ?></td>
                    <td class="px-5 py-3 font-black text-slate-800"><?= formatarDinheiro($c['valor']) ?></td>
                    <td class="px-5 py-3">
                        <span class="px-2 py-1 rounded-full text-xs font-bold <?= $cfg['bg'] ?> <?= $cfg['text'] ?>">
                            <?= $cfg['label'] ?>
                        </span>
                        <?php if ($c['data_pagamento']): ?>
                        <div class="text-xs text-slate-400 mt-0.5"><?= formatarData($c['data_pagamento']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-right flex items-center justify-end gap-2">
                        <?php if ($c['status'] !== 'pago'): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="acao" value="pagar">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <input type="hidden" name="data_pagamento" value="<?= $hoje ?>">
                            <button type="submit" class="text-xs font-bold px-3 py-1.5 rounded-lg text-white hover:brightness-110" style="background:#e5a820">
                                <i class="fas fa-check mr-1"></i>Pagar
                            </button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" class="inline" onsubmit="return confirm('Excluir esta conta?')">
                            <input type="hidden" name="acao" value="excluir">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button type="submit" class="text-xs text-red-400 hover:text-red-600 px-2 py-1.5 rounded-lg hover:bg-red-50 transition">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL NOVA CONTA -->
<div id="modalConta" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-black text-slate-800">Nova Conta a Pagar</h3>
            <button onclick="document.getElementById('modalConta').classList.add('hidden')" class="text-slate-400 hover:text-slate-700">✕</button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="acao" value="salvar">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

            <div>
                <label class="label-form">Título *</label>
                <input type="text" name="titulo" required class="input-form" placeholder="Ex: Conta de luz de maio">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label-form">Categoria *</label>
                    <select name="categoria" class="input-form">
                        <?php foreach ($categorias as $k=>$v): ?>
                        <option value="<?= $k ?>"><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="label-form">Quarto (opcional)</label>
                    <select name="quarto_id" class="input-form">
                        <option value="">Geral (todos)</option>
                        <?php foreach ($quartos as $q): ?>
                        <option value="<?= $q['id'] ?>">Quarto <?= e($q['numero']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label-form">Valor (R$) *</label>
                    <input type="number" name="valor" step="0.01" min="0" required class="input-form" placeholder="0,00">
                </div>
                <div>
                    <label class="label-form">Vencimento *</label>
                    <input type="date" name="data_vencimento" required class="input-form" value="<?= $hoje ?>">
                </div>
            </div>

            <div>
                <label class="label-form">Descrição</label>
                <textarea name="descricao" rows="2" class="input-form" placeholder="Detalhes adicionais..."></textarea>
            </div>

            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="recorrente" class="w-4 h-4 rounded accent-yellow-500">
                <span class="text-sm font-semibold text-slate-600">Conta recorrente (gerar próximo mês automaticamente ao pagar)</span>
            </label>

            <div class="flex gap-3 pt-2">
                <button type="button" onclick="document.getElementById('modalConta').classList.add('hidden')"
                        class="flex-1 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold text-sm hover:bg-slate-50">Cancelar</button>
                <button type="submit" class="flex-1 py-2.5 rounded-xl text-white font-bold text-sm hover:brightness-110" style="background:#1e2d40">
                    <i class="fas fa-floppy-disk mr-1"></i> Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.label-form { display:block; font-size:.75rem; font-weight:700; color:#475569; margin-bottom:.375rem; }
.input-form  { width:100%; padding:.625rem .875rem; border-radius:.75rem; border:1px solid #e2e8f0; font-size:.875rem; font-weight:600; outline:none; transition:all .15s; }
.input-form:focus { border-color:#e5a820; box-shadow:0 0 0 3px rgba(229,168,32,.1); }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
