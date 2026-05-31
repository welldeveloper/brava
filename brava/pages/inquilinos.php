<?php
// =============================================================
// Inquilinos — cadastro e listagem
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

    $nome     = trim($_POST['nome'] ?? '');
    $cpf      = trim($_POST['cpf'] ?? '') ?: null;
    $rg       = trim($_POST['rg'] ?? '') ?: null;
    $telefone = trim($_POST['telefone'] ?? '') ?: null;
    $email    = trim($_POST['email'] ?? '') ?: null;
    $obs      = trim($_POST['observacoes'] ?? '') ?: null;

    if (empty($nome)) {
        flash('erro', 'O nome do inquilino é obrigatório.');
        redirecionar('pages/inquilinos.php?acao=novo');
    }

    $id = (int)($_POST['id'] ?? 0);

    try {
        if ($id) {
            $stmt = $db->prepare('UPDATE inquilinos SET nome=?,cpf=?,rg=?,telefone=?,email=?,observacoes=? WHERE id=?');
            $stmt->execute([$nome,$cpf,$rg,$telefone,$email,$obs,$id]);
            flash('sucesso', 'Inquilino atualizado com sucesso!');
        } else {
            $stmt = $db->prepare('INSERT INTO inquilinos (nome,cpf,rg,telefone,email,observacoes) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$nome,$cpf,$rg,$telefone,$email,$obs]);
            $novoId = $db->lastInsertId();
            flash('sucesso', 'Inquilino cadastrado! Agora crie o contrato.');
            redirecionar("pages/contratos.php?acao=novo&inquilino_id={$novoId}");
        }
    } catch (PDOException $e) {
        flash('erro', 'CPF já cadastrado no sistema.');
    }

    redirecionar('pages/inquilinos.php');
}

// ---------------------------------------------------------------
// CARREGAR DADOS
// ---------------------------------------------------------------
if ($acao === 'listar') {
    $busca = trim($_GET['q'] ?? '');
    if ($busca) {
        $stmt = $db->prepare("
            SELECT i.*, q.numero AS quarto
            FROM inquilinos i
            LEFT JOIN contratos c ON c.inquilino_id = i.id AND c.status = 'ativo'
            LEFT JOIN quartos q ON c.quarto_id = q.id
            WHERE i.ativo = 1 AND (i.nome LIKE ? OR i.cpf LIKE ? OR i.telefone LIKE ?)
            ORDER BY i.nome
        ");
        $like = "%$busca%";
        $stmt->execute([$like,$like,$like]);
    } else {
        $stmt = $db->query("
            SELECT i.*, q.numero AS quarto
            FROM inquilinos i
            LEFT JOIN contratos c ON c.inquilino_id = i.id AND c.status = 'ativo'
            LEFT JOIN quartos q ON c.quarto_id = q.id
            WHERE i.ativo = 1
            ORDER BY i.nome
        ");
    }
    $inquilinos = $stmt->fetchAll();
}

if ($acao === 'editar' && isset($_GET['id'])) {
    $stmt = $db->prepare('SELECT * FROM inquilinos WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $inquilino = $stmt->fetch();
}

$pageTitle = 'Inquilinos';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($acao === 'novo' || $acao === 'editar'): ?>
<!-- ============================================================
     FORMULÁRIO DE INQUILINO
     ============================================================ -->
<div class="mb-6">
    <a href="<?= BASE_URL ?>/pages/inquilinos.php" class="text-slate-500 hover:text-slate-700 text-sm"><i class="fas fa-arrow-left mr-1"></i> Inquilinos</a>
    <h2 class="text-2xl font-black text-slate-800 mt-1"><?= $acao === 'editar' ? 'Editar Inquilino' : 'Novo Inquilino' ?></h2>
    <?php if ($acao === 'novo'): ?>
    <p class="text-sm text-slate-500 mt-1">Após cadastrar, você será redirecionado para criar o contrato e vincular o quarto.</p>
    <?php endif; ?>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 max-w-2xl">
    <form method="POST" class="p-6 space-y-4">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="id" value="<?= e($inquilino['id'] ?? '') ?>">

        <div>
            <label class="label-form">Nome completo *</label>
            <input type="text" name="nome" required class="input-form"
                   value="<?= e($inquilino['nome'] ?? '') ?>" placeholder="Nome do inquilino">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="label-form">CPF</label>
                <input type="text" name="cpf" class="input-form" maxlength="14"
                       value="<?= e($inquilino['cpf'] ?? '') ?>" placeholder="000.000.000-00"
                       oninput="mascaraCPF(this)">
            </div>
            <div>
                <label class="label-form">RG</label>
                <input type="text" name="rg" class="input-form"
                       value="<?= e($inquilino['rg'] ?? '') ?>" placeholder="00.000.000-0">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="label-form">Telefone / WhatsApp</label>
                <input type="text" name="telefone" class="input-form"
                       value="<?= e($inquilino['telefone'] ?? '') ?>" placeholder="(00) 00000-0000"
                       oninput="mascaraTelefone(this)">
            </div>
            <div>
                <label class="label-form">E-mail</label>
                <input type="email" name="email" class="input-form"
                       value="<?= e($inquilino['email'] ?? '') ?>" placeholder="email@exemplo.com">
            </div>
        </div>

        <div>
            <label class="label-form">Observações</label>
            <textarea name="observacoes" rows="3" class="input-form" placeholder="Informações adicionais..."><?= e($inquilino['observacoes'] ?? '') ?></textarea>
        </div>

        <div class="flex gap-3 pt-2">
            <a href="<?= BASE_URL ?>/pages/inquilinos.php"
               class="flex-1 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold text-sm text-center hover:bg-slate-50">Cancelar</a>
            <button type="submit" class="flex-1 py-2.5 rounded-xl text-white font-bold text-sm hover:brightness-110 transition" style="background:#1e2d40">
                <i class="fas fa-floppy-disk mr-1"></i> Salvar
            </button>
        </div>
    </form>
</div>

<?php else: ?>
<!-- ============================================================
     LISTAGEM DE INQUILINOS
     ============================================================ -->
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <h2 class="text-2xl font-black text-slate-800">Inquilinos</h2>
    <a href="?acao=novo" class="px-4 py-2 rounded-xl text-white font-bold text-sm flex items-center gap-2 hover:brightness-110 transition" style="background:#1e2d40">
        <i class="fas fa-plus"></i> Novo inquilino
    </a>
</div>

<!-- Busca -->
<form method="GET" class="mb-5">
    <div class="relative max-w-sm">
        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
        <input type="text" name="q" value="<?= e($_GET['q'] ?? '') ?>"
               placeholder="Buscar por nome, CPF ou telefone..."
               class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-slate-200 focus:border-yellow-400 focus:ring-2 focus:ring-yellow-100 outline-none text-sm font-semibold">
    </div>
</form>

<?php if (empty($inquilinos)): ?>
<div class="text-center py-16 text-slate-400">
    <i class="fas fa-users text-4xl mb-3 text-slate-300"></i>
    <p class="font-bold">Nenhum inquilino encontrado.</p>
</div>
<?php else: ?>
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100">
                    <th class="text-left px-5 py-3 text-xs font-black text-slate-500 uppercase tracking-wider">Nome</th>
                    <th class="text-left px-5 py-3 text-xs font-black text-slate-500 uppercase tracking-wider hidden md:table-cell">CPF</th>
                    <th class="text-left px-5 py-3 text-xs font-black text-slate-500 uppercase tracking-wider hidden sm:table-cell">Telefone</th>
                    <th class="text-left px-5 py-3 text-xs font-black text-slate-500 uppercase tracking-wider">Quarto</th>
                    <th class="text-right px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
            <?php foreach ($inquilinos as $inq): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-black shrink-0"
                                 style="background:#1e2d40">
                                <?= strtoupper(substr($inq['nome'], 0, 1)) ?>
                            </div>
                            <span class="font-bold text-slate-800"><?= e($inq['nome']) ?></span>
                        </div>
                    </td>
                    <td class="px-5 py-3 text-slate-500 hidden md:table-cell"><?= e($inq['cpf'] ?? '—') ?></td>
                    <td class="px-5 py-3 hidden sm:table-cell">
                        <?php if ($inq['telefone']): ?>
                        <a href="https://wa.me/55<?= preg_replace('/\D/', '', $inq['telefone']) ?>"
                           target="_blank" class="text-green-600 hover:text-green-700 font-semibold">
                            <i class="fab fa-whatsapp mr-1"></i><?= e($inq['telefone']) ?>
                        </a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="px-5 py-3">
                        <?php if ($inq['quarto']): ?>
                            <span class="px-2 py-1 rounded-lg text-xs font-bold" style="background:rgba(229,168,32,.15);color:#c4901a">
                                Quarto <?= e($inq['quarto']) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-slate-400 text-xs">Sem contrato</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <a href="?acao=editar&id=<?= $inq['id'] ?>"
                           class="text-slate-400 hover:text-slate-700 text-xs font-bold px-3 py-1.5 rounded-lg hover:bg-slate-100 transition">
                            <i class="fas fa-pen mr-1"></i> Editar
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<style>
.label-form { display:block; font-size:.75rem; font-weight:700; color:#475569; margin-bottom:.375rem; }
.input-form  { width:100%; padding:.625rem .875rem; border-radius:.75rem; border:1px solid #e2e8f0; font-size:.875rem; font-weight:600; outline:none; transition:all .15s; }
.input-form:focus { border-color:#e5a820; box-shadow:0 0 0 3px rgba(229,168,32,.1); }
</style>

<script>
function mascaraCPF(input) {
    let v = input.value.replace(/\D/g,'').slice(0,11);
    v = v.replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d{1,2})$/,'$1-$2');
    input.value = v;
}
function mascaraTelefone(input) {
    let v = input.value.replace(/\D/g,'').slice(0,11);
    if (v.length <= 10) v = v.replace(/(\d{2})(\d{4})(\d{0,4})/,'($1) $2-$3');
    else               v = v.replace(/(\d{2})(\d{5})(\d{0,4})/,'($1) $2-$3');
    input.value = v;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
