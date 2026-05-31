<?php
// =============================================================
// Contratos — cria vínculo inquilino ↔ quarto e gera aluguéis
// =============================================================
require_once __DIR__ . '/../includes/auth.php';
exigirLogin();

$db   = getDB();
$acao = $_GET['acao'] ?? 'listar';

// ---------------------------------------------------------------
// PROCESSAR FORMULÁRIOS
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $acaoPost = $_POST['acao'] ?? '';

    if ($acaoPost === 'salvar_contrato') {
        $inquilinoId  = (int)$_POST['inquilino_id'];
        $quartoId     = (int)$_POST['quarto_id'];
        $dataInicio   = $_POST['data_inicio'];
        $diaVenc      = (int)$_POST['dia_vencimento'];
        $valor        = (float)$_POST['valor_aluguel'];
        $deposito     = (float)($_POST['deposito'] ?? 0);
        $obs          = trim($_POST['observacoes'] ?? '');

        if (!$inquilinoId || !$quartoId || !$dataInicio || !$diaVenc || !$valor) {
            flash('erro', 'Preencha todos os campos obrigatórios.');
            redirecionar("pages/contratos.php?acao=novo&inquilino_id=$inquilinoId");
        }

        // Verifica se quarto ainda está disponível
        $stmt = $db->prepare("SELECT status FROM quartos WHERE id = ?");
        $stmt->execute([$quartoId]);
        $quarto = $stmt->fetch();

        if (!$quarto || $quarto['status'] !== 'disponivel') {
            flash('erro', 'Quarto não está disponível.');
            redirecionar("pages/contratos.php?acao=novo&inquilino_id=$inquilinoId");
        }

        // Usa transação: ou tudo salva, ou nada salva
        $db->beginTransaction();
        try {
            // Cria o contrato
            $stmt = $db->prepare("
                INSERT INTO contratos (inquilino_id, quarto_id, data_inicio, dia_vencimento, valor_aluguel, deposito, observacoes)
                VALUES (?,?,?,?,?,?,?)
            ");
            $stmt->execute([$inquilinoId,$quartoId,$dataInicio,$diaVenc,$valor,$deposito,$obs]);
            $contratoId = $db->lastInsertId();

            // Marca quarto como ocupado
            $db->prepare("UPDATE quartos SET status = 'ocupado' WHERE id = ?")->execute([$quartoId]);

            // Gera o primeiro aluguel do mês
            $competencia   = date('Y-m-01', strtotime($dataInicio));
            $dataVencimento = date('Y-m-d', mktime(0,0,0, date('m', strtotime($dataInicio)), $diaVenc, date('Y', strtotime($dataInicio))));

            $stmt = $db->prepare("
                INSERT IGNORE INTO alugueis (contrato_id, competencia, data_vencimento, valor)
                VALUES (?,?,?,?)
            ");
            $stmt->execute([$contratoId,$competencia,$dataVencimento,$valor]);

            $db->commit();
            flash('sucesso', 'Contrato criado e primeiro aluguel gerado!');
        } catch (Exception $e) {
            $db->rollBack();
            flash('erro', 'Erro ao criar contrato: ' . $e->getMessage());
        }

        redirecionar('pages/contratos.php');
    }

    // Encerrar contrato
    if ($acaoPost === 'encerrar') {
        $id = (int)$_POST['id'];
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("UPDATE contratos SET status = 'encerrado' WHERE id = ?");
            $stmt->execute([$id]);

            // Libera o quarto
            $stmt = $db->prepare("
                UPDATE quartos SET status = 'disponivel'
                WHERE id = (SELECT quarto_id FROM contratos WHERE id = ?)
            ");
            $stmt->execute([$id]);
            $db->commit();
            flash('sucesso', 'Contrato encerrado e quarto liberado.');
        } catch (Exception $e) {
            $db->rollBack();
            flash('erro', 'Erro ao encerrar contrato.');
        }
        redirecionar('pages/contratos.php');
    }
}

// ---------------------------------------------------------------
// CARREGAR DADOS
// ---------------------------------------------------------------
if ($acao === 'listar') {
    $contratos = $db->query("
        SELECT c.*, i.nome AS inquilino, q.numero AS quarto, t.nome AS tipo
        FROM contratos c
        JOIN inquilinos i ON c.inquilino_id = i.id
        JOIN quartos q ON c.quarto_id = q.id
        JOIN tipos_quarto t ON q.tipo_id = t.id
        ORDER BY c.status ASC, c.data_inicio DESC
    ")->fetchAll();
}

if ($acao === 'novo') {
    // Somente quartos disponíveis
    $quartosDisponiveis = $db->query("
        SELECT q.id, q.numero, q.valor_aluguel, t.nome AS tipo
        FROM quartos q
        JOIN tipos_quarto t ON q.tipo_id = t.id
        WHERE q.status = 'disponivel'
        ORDER BY q.numero
    ")->fetchAll();

    $inquilinos = $db->query("SELECT id, nome FROM inquilinos WHERE ativo = 1 ORDER BY nome")->fetchAll();

    // Pré-selecionar inquilino se vier da URL
    $preInquilinoId = (int)($_GET['inquilino_id'] ?? 0);
}

$pageTitle = 'Contratos';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($acao === 'novo'): ?>
<!-- ============================================================
     FORMULÁRIO DE CONTRATO
     ============================================================ -->
<div class="mb-6">
    <a href="<?= BASE_URL ?>/pages/contratos.php" class="text-slate-500 hover:text-slate-700 text-sm"><i class="fas fa-arrow-left mr-1"></i> Contratos</a>
    <h2 class="text-2xl font-black text-slate-800 mt-1">Novo Contrato</h2>
    <p class="text-sm text-slate-500 mt-1">Vincule um inquilino a um quarto disponível.</p>
</div>

<?php if (empty($quartosDisponiveis)): ?>
<div class="bg-yellow-50 border border-yellow-200 rounded-2xl p-6 text-center text-yellow-800">
    <i class="fas fa-triangle-exclamation text-3xl mb-3"></i>
    <p class="font-black text-lg">Nenhum quarto disponível</p>
    <p class="text-sm mt-1">Todos os quartos estão ocupados ou em manutenção.</p>
    <a href="<?= BASE_URL ?>/pages/quartos.php" class="mt-4 inline-block px-4 py-2 rounded-xl text-white font-bold text-sm" style="background:#1e2d40">Ver Quartos</a>
</div>
<?php else: ?>
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 max-w-2xl">
    <form method="POST" class="p-6 space-y-4">
        <input type="hidden" name="acao" value="salvar_contrato">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="label-form">Inquilino *</label>
                <select name="inquilino_id" required class="input-form">
                    <option value="">Selecione...</option>
                    <?php foreach ($inquilinos as $inq): ?>
                    <option value="<?= $inq['id'] ?>" <?= $inq['id'] == $preInquilinoId ? 'selected' : '' ?>>
                        <?= e($inq['nome']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Seleção de quarto: mostra apenas disponíveis -->
            <div>
                <label class="label-form">Quarto disponível *</label>
                <select name="quarto_id" required class="input-form" onchange="preencherValor(this)">
                    <option value="">Selecione...</option>
                    <?php foreach ($quartosDisponiveis as $q): ?>
                    <option value="<?= $q['id'] ?>" data-valor="<?= $q['valor_aluguel'] ?>">
                        Quarto <?= e($q['numero']) ?> — <?= e($q['tipo']) ?> (<?= formatarDinheiro($q['valor_aluguel']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="label-form">Data de início *</label>
                <input type="date" name="data_inicio" required class="input-form" value="<?= date('Y-m-d') ?>">
            </div>
            <div>
                <label class="label-form">Dia do vencimento *</label>
                <input type="number" name="dia_vencimento" required class="input-form" min="1" max="28" value="10" placeholder="Ex: 10">
                <p class="text-xs text-slate-400 mt-1">Dia do mês (1-28)</p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="label-form">Valor do aluguel *</label>
                <input type="number" name="valor_aluguel" id="valorAluguel" step="0.01" min="0" required class="input-form" placeholder="0,00">
            </div>
            <div>
                <label class="label-form">Depósito caução</label>
                <input type="number" name="deposito" step="0.01" min="0" class="input-form" placeholder="0,00">
            </div>
        </div>

        <div>
            <label class="label-form">Observações</label>
            <textarea name="observacoes" rows="2" class="input-form" placeholder="Condições especiais, etc."></textarea>
        </div>

        <div class="flex gap-3 pt-2">
            <a href="<?= BASE_URL ?>/pages/contratos.php"
               class="flex-1 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold text-sm text-center hover:bg-slate-50">Cancelar</a>
            <button type="submit" class="flex-1 py-2.5 rounded-xl text-white font-bold text-sm hover:brightness-110 transition" style="background:#1e2d40">
                <i class="fas fa-file-contract mr-1"></i> Criar contrato
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ============================================================
     LISTAGEM DE CONTRATOS
     ============================================================ -->
<div class="flex items-center justify-between mb-6">
    <h2 class="text-2xl font-black text-slate-800">Contratos</h2>
    <a href="?acao=novo" class="px-4 py-2 rounded-xl text-white font-bold text-sm flex items-center gap-2 hover:brightness-110 transition" style="background:#1e2d40">
        <i class="fas fa-plus"></i> Novo contrato
    </a>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50">
                    <th class="text-left px-5 py-3 text-xs font-black text-slate-500 uppercase">Inquilino</th>
                    <th class="text-left px-5 py-3 text-xs font-black text-slate-500 uppercase hidden sm:table-cell">Quarto</th>
                    <th class="text-left px-5 py-3 text-xs font-black text-slate-500 uppercase hidden md:table-cell">Início</th>
                    <th class="text-left px-5 py-3 text-xs font-black text-slate-500 uppercase hidden md:table-cell">Vencimento</th>
                    <th class="text-left px-5 py-3 text-xs font-black text-slate-500 uppercase">Valor</th>
                    <th class="text-left px-5 py-3 text-xs font-black text-slate-500 uppercase">Status</th>
                    <th class="text-right px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
            <?php foreach ($contratos as $c):
                $statusCor = $c['status'] === 'ativo' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500';
            ?>
                <tr class="hover:bg-slate-50 transition-colors <?= $c['status'] === 'encerrado' ? 'opacity-60' : '' ?>">
                    <td class="px-5 py-3 font-bold text-slate-800"><?= e($c['inquilino']) ?></td>
                    <td class="px-5 py-3 hidden sm:table-cell">
                        <span class="font-semibold">Quarto <?= e($c['quarto']) ?></span>
                        <div class="text-xs text-slate-400"><?= e($c['tipo']) ?></div>
                    </td>
                    <td class="px-5 py-3 text-slate-500 hidden md:table-cell"><?= formatarData($c['data_inicio']) ?></td>
                    <td class="px-5 py-3 text-slate-500 hidden md:table-cell">Dia <?= e($c['dia_vencimento']) ?></td>
                    <td class="px-5 py-3 font-black" style="color:#1e2d40"><?= formatarDinheiro($c['valor_aluguel']) ?></td>
                    <td class="px-5 py-3">
                        <span class="px-2 py-1 rounded-full text-xs font-bold <?= $statusCor ?>">
                            <?= ucfirst($c['status']) ?>
                        </span>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <?php if ($c['status'] === 'ativo'): ?>
                        <form method="POST" onsubmit="return confirm('Encerrar este contrato? O quarto será liberado.')">
                            <input type="hidden" name="acao" value="encerrar">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button type="submit" class="text-red-400 hover:text-red-600 text-xs font-bold px-3 py-1.5 rounded-lg hover:bg-red-50 transition">
                                <i class="fas fa-ban mr-1"></i>Encerrar
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<style>
.label-form { display:block; font-size:.75rem; font-weight:700; color:#475569; margin-bottom:.375rem; }
.input-form  { width:100%; padding:.625rem .875rem; border-radius:.75rem; border:1px solid #e2e8f0; font-size:.875rem; font-weight:600; outline:none; transition:all .15s; }
.input-form:focus { border-color:#e5a820; box-shadow:0 0 0 3px rgba(229,168,32,.1); }
</style>

<script>
// Preenche automaticamente o valor do aluguel ao selecionar quarto
function preencherValor(select) {
    const opt = select.options[select.selectedIndex];
    const valor = opt.getAttribute('data-valor') || '';
    document.getElementById('valorAluguel').value = valor;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
