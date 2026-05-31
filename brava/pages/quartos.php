<?php
// =============================================================
// Quartos — listagem, criação e edição
// =============================================================
require_once __DIR__ . '/../includes/auth.php';
exigirLogin();

$db = getDB();
$acao = $_GET['acao'] ?? 'listar';

// ---------------------------------------------------------------
// PROCESSAR FORMULÁRIOS (POST)
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $acaoPost = $_POST['acao'] ?? '';

    // Salvar tipo de quarto
    if ($acaoPost === 'salvar_tipo') {
        $nome  = trim($_POST['nome'] ?? '');
        $desc  = trim($_POST['descricao'] ?? '');
        $valor = (float)($_POST['valor_padrao'] ?? 0);

        if (empty($nome)) {
            flash('erro', 'Informe o nome do tipo.');
        } else {
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                $stmt = $db->prepare('UPDATE tipos_quarto SET nome=?, descricao=?, valor_padrao=? WHERE id=?');
                $stmt->execute([$nome, $desc, $valor, $id]);
            } else {
                $stmt = $db->prepare('INSERT INTO tipos_quarto (nome, descricao, valor_padrao) VALUES (?,?,?)');
                $stmt->execute([$nome, $desc, $valor]);
            }
            flash('sucesso', 'Tipo de quarto salvo com sucesso!');
        }
        redirecionar('pages/quartos.php?acao=tipos');
    }

    // Salvar quarto
    if ($acaoPost === 'salvar_quarto') {
        $numero = trim($_POST['numero'] ?? '');
        $tipoId = (int)($_POST['tipo_id'] ?? 0);
        $valor  = (float)($_POST['valor_aluguel'] ?? 0);
        $desc   = trim($_POST['descricao'] ?? '');
        $status = $_POST['status'] ?? 'disponivel';

        if (empty($numero) || !$tipoId) {
            flash('erro', 'Número e tipo do quarto são obrigatórios.');
            redirecionar('pages/quartos.php?acao=novo');
        }

        $id = (int)($_POST['id'] ?? 0);
        try {
            if ($id) {
                $stmt = $db->prepare('UPDATE quartos SET numero=?, tipo_id=?, valor_aluguel=?, descricao=?, status=? WHERE id=?');
                $stmt->execute([$numero, $tipoId, $valor, $desc, $status, $id]);
            } else {
                $stmt = $db->prepare('INSERT INTO quartos (numero, tipo_id, valor_aluguel, descricao, status) VALUES (?,?,?,?,?)');
                $stmt->execute([$numero, $tipoId, $valor, $desc, $status]);
            }
            flash('sucesso', 'Quarto salvo com sucesso!');
        } catch (PDOException $e) {
            flash('erro', 'Número de quarto já existe.');
        }
        redirecionar('pages/quartos.php');
    }
}

// ---------------------------------------------------------------
// CARREGAR DADOS PARA EXIBIÇÃO
// ---------------------------------------------------------------
$tipos = $db->query('SELECT * FROM tipos_quarto ORDER BY nome')->fetchAll();

if ($acao === 'listar' || $acao === '') {
    $quartos = $db->query("
        SELECT q.*, t.nome AS tipo_nome,
               COALESCE(i.nome, '—') AS inquilino_nome
        FROM quartos q
        JOIN tipos_quarto t ON q.tipo_id = t.id
        LEFT JOIN contratos c ON c.quarto_id = q.id AND c.status = 'ativo'
        LEFT JOIN inquilinos i ON c.inquilino_id = i.id
        ORDER BY q.numero
    ")->fetchAll();
}

if ($acao === 'editar' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $db->prepare('SELECT * FROM quartos WHERE id = ?');
    $stmt->execute([$id]);
    $quarto = $stmt->fetch();
}

$pageTitle = 'Quartos';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($acao === 'tipos'): ?>
<!-- ============================================================
     TIPOS DE QUARTO
     ============================================================ -->
<div class="flex items-center justify-between mb-6">
    <div>
        <a href="<?= BASE_URL ?>/pages/quartos.php" class="text-slate-500 hover:text-slate-700 text-sm"><i class="fas fa-arrow-left mr-1"></i> Quartos</a>
        <h2 class="text-2xl font-black text-slate-800 mt-1">Tipos de Quarto</h2>
    </div>
    <button onclick="document.getElementById('modalTipo').classList.remove('hidden')"
            class="px-4 py-2 rounded-xl text-white font-bold text-sm flex items-center gap-2 hover:brightness-110 transition"
            style="background:#1e2d40">
        <i class="fas fa-plus"></i> Novo tipo
    </button>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($tipos as $t): ?>
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-start justify-between">
        <div>
            <div class="font-black text-slate-800"><?= e($t['nome']) ?></div>
            <div class="text-xs text-slate-500 mt-1"><?= e($t['descricao']) ?></div>
            <div class="text-sm font-bold mt-2" style="color:#e5a820"><?= formatarDinheiro($t['valor_padrao']) ?></div>
        </div>
        <button onclick='editarTipo(<?= json_encode($t) ?>)' class="text-slate-400 hover:text-slate-700 text-xs">
            <i class="fas fa-pen"></i>
        </button>
    </div>
    <?php endforeach; ?>
</div>

<!-- Modal tipo -->
<div id="modalTipo" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-black text-slate-800" id="modalTipoTitulo">Novo Tipo</h3>
            <button onclick="document.getElementById('modalTipo').classList.add('hidden')" class="text-slate-400 hover:text-slate-700">✕</button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="acao" value="salvar_tipo">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="id" id="tipoId" value="">
            <div>
                <label class="label-form">Nome do tipo</label>
                <input type="text" name="nome" id="tipoNome" required class="input-form" placeholder="Ex: Suíte, Solteiro...">
            </div>
            <div>
                <label class="label-form">Descrição</label>
                <textarea name="descricao" id="tipoDesc" rows="2" class="input-form" placeholder="Opcional"></textarea>
            </div>
            <div>
                <label class="label-form">Valor padrão (R$)</label>
                <input type="number" name="valor_padrao" id="tipoValor" step="0.01" min="0" class="input-form" placeholder="0,00">
            </div>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="document.getElementById('modalTipo').classList.add('hidden')"
                        class="flex-1 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold text-sm hover:bg-slate-50">Cancelar</button>
                <button type="submit" class="flex-1 py-2.5 rounded-xl text-white font-bold text-sm hover:brightness-110" style="background:#1e2d40">Salvar</button>
            </div>
        </form>
    </div>
</div>

<?php elseif ($acao === 'novo' || $acao === 'editar'): ?>
<!-- ============================================================
     FORMULÁRIO DE QUARTO
     ============================================================ -->
<div class="mb-6">
    <a href="<?= BASE_URL ?>/pages/quartos.php" class="text-slate-500 hover:text-slate-700 text-sm"><i class="fas fa-arrow-left mr-1"></i> Quartos</a>
    <h2 class="text-2xl font-black text-slate-800 mt-1"><?= $acao === 'editar' ? 'Editar Quarto' : 'Novo Quarto' ?></h2>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 max-w-xl">
    <form method="POST" class="p-6 space-y-4">
        <input type="hidden" name="acao" value="salvar_quarto">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="id" value="<?= e($quarto['id'] ?? '') ?>">

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="label-form">Número / Identificador</label>
                <input type="text" name="numero" required class="input-form"
                       value="<?= e($quarto['numero'] ?? '') ?>" placeholder="Ex: 101, A2">
            </div>
            <div>
                <label class="label-form">Tipo do quarto</label>
                <select name="tipo_id" required class="input-form">
                    <option value="">Selecione...</option>
                    <?php foreach ($tipos as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= ($quarto['tipo_id'] ?? '') == $t['id'] ? 'selected' : '' ?>>
                        <?= e($t['nome']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="label-form">Valor do Aluguel (R$)</label>
                <input type="number" name="valor_aluguel" step="0.01" min="0" required class="input-form"
                       value="<?= e($quarto['valor_aluguel'] ?? '') ?>" placeholder="0,00">
            </div>
            <div>
                <label class="label-form">Status</label>
                <select name="status" class="input-form">
                    <option value="disponivel" <?= ($quarto['status'] ?? '') === 'disponivel' ? 'selected' : '' ?>>Disponível</option>
                    <option value="ocupado"    <?= ($quarto['status'] ?? '') === 'ocupado'    ? 'selected' : '' ?>>Ocupado</option>
                    <option value="manutencao" <?= ($quarto['status'] ?? '') === 'manutencao' ? 'selected' : '' ?>>Em Manutenção</option>
                </select>
            </div>
        </div>

        <div>
            <label class="label-form">Descrição / Observações</label>
            <textarea name="descricao" rows="3" class="input-form" placeholder="Detalhes do quarto..."><?= e($quarto['descricao'] ?? '') ?></textarea>
        </div>

        <div class="flex gap-3 pt-2">
            <a href="<?= BASE_URL ?>/pages/quartos.php"
               class="flex-1 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold text-sm text-center hover:bg-slate-50">Cancelar</a>
            <button type="submit" class="flex-1 py-2.5 rounded-xl text-white font-bold text-sm hover:brightness-110 transition" style="background:#1e2d40">
                <i class="fas fa-floppy-disk mr-1"></i> Salvar quarto
            </button>
        </div>
    </form>
</div>

<?php else: ?>
<!-- ============================================================
     LISTAGEM DE QUARTOS
     ============================================================ -->
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <h2 class="text-2xl font-black text-slate-800">Quartos</h2>
    <div class="flex gap-2">
        <a href="?acao=tipos" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 font-bold text-sm hover:bg-slate-50">
            <i class="fas fa-tags mr-1"></i> Tipos
        </a>
        <a href="?acao=novo" class="px-4 py-2 rounded-xl text-white font-bold text-sm flex items-center gap-2 hover:brightness-110 transition" style="background:#1e2d40">
            <i class="fas fa-plus"></i> Novo quarto
        </a>
    </div>
</div>

<!-- Legenda de status -->
<div class="flex gap-4 mb-4 text-xs font-semibold">
    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-green-400"></span> Disponível</span>
    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-blue-400"></span> Ocupado</span>
    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-orange-400"></span> Manutenção</span>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
    <?php foreach ($quartos as $q):
        $statusColor = ['disponivel'=>'bg-green-100 text-green-700', 'ocupado'=>'bg-blue-100 text-blue-700', 'manutencao'=>'bg-orange-100 text-orange-700'];
        $dotColor    = ['disponivel'=>'bg-green-400', 'ocupado'=>'bg-blue-400', 'manutencao'=>'bg-orange-400'];
        $statusLabel = ['disponivel'=>'Disponível', 'ocupado'=>'Ocupado', 'manutencao'=>'Manutenção'];
    ?>
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 hover:shadow-md transition-shadow">
        <div class="flex items-start justify-between mb-3">
            <div>
                <div class="text-lg font-black" style="color:#1e2d40">Quarto <?= e($q['numero']) ?></div>
                <div class="text-xs text-slate-500"><?= e($q['tipo_nome']) ?></div>
            </div>
            <span class="flex items-center gap-1.5 text-xs font-bold px-2 py-1 rounded-full <?= $statusColor[$q['status']] ?>">
                <span class="w-2 h-2 rounded-full <?= $dotColor[$q['status']] ?>"></span>
                <?= $statusLabel[$q['status']] ?>
            </span>
        </div>

        <div class="text-xl font-black mb-1" style="color:#e5a820"><?= formatarDinheiro($q['valor_aluguel']) ?></div>

        <?php if ($q['status'] === 'ocupado'): ?>
        <div class="text-xs text-slate-500 mb-3">
            <i class="fas fa-user mr-1"></i> <?= e($q['inquilino_nome']) ?>
        </div>
        <?php endif; ?>

        <a href="?acao=editar&id=<?= $q['id'] ?>"
           class="mt-3 block text-center py-2 rounded-xl border border-slate-200 text-slate-600 text-xs font-bold hover:bg-slate-50 transition">
            <i class="fas fa-pen mr-1"></i> Editar
        </a>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<style>
.label-form { display:block; font-size:.75rem; font-weight:700; color:#475569; margin-bottom:.375rem; }
.input-form  { width:100%; padding:.625rem .875rem; border-radius:.75rem; border:1px solid #e2e8f0; font-size:.875rem; font-weight:600; outline:none; transition:border-color .15s,box-shadow .15s; }
.input-form:focus { border-color:#e5a820; box-shadow:0 0 0 3px rgba(229,168,32,.1); }
</style>

<script>
function editarTipo(t) {
    document.getElementById('tipoId').value    = t.id;
    document.getElementById('tipoNome').value  = t.nome;
    document.getElementById('tipoDesc').value  = t.descricao || '';
    document.getElementById('tipoValor').value = t.valor_padrao;
    document.getElementById('modalTipoTitulo').textContent = 'Editar Tipo';
    document.getElementById('modalTipo').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
