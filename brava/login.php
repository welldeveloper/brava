<?php
// =============================================================
// Página de Login
// =============================================================
require_once __DIR__ . '/includes/auth.php';
iniciarSessao();

// Se já estiver logado, vai direto para o dashboard
if (!empty($_SESSION['usuario_id'])) {
    redirecionar('pages/dashboard.php');
}

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (empty($email) || empty($senha)) {
        $erro = 'Preencha todos os campos.';
    } else {
        $erro = fazerLogin($email, $senha);
        if ($erro === null) {
            redirecionar('pages/dashboard.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= SISTEMA_NOME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: 'Nunito', sans-serif; }
        .bg-login {
            background: #1e2d40;
            background-image: radial-gradient(ellipse at 20% 50%, rgba(229,168,32,0.08) 0%, transparent 60%),
                              radial-gradient(ellipse at 80% 20%, rgba(229,168,32,0.05) 0%, transparent 50%);
        }
    </style>
</head>
<body class="bg-login min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md">

        <!-- Card de login -->
        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">

            <!-- Cabeçalho com logo -->
            <div class="p-8 text-center" style="background:#1e2d40">
                <img src="<?= BASE_URL ?>/assets/img/logo.jpg" alt="Brava"
                     class="w-28 h-28 rounded-full object-cover mx-auto mb-4 ring-4 ring-yellow-400/30">
                <h1 class="text-3xl font-black text-white">BRAVA</h1>
                <p class="text-yellow-400 text-sm font-semibold mt-1">Sistema de Gestão de Kitnet</p>
            </div>

            <!-- Formulário -->
            <div class="p-8">
                <h2 class="text-xl font-black text-slate-800 mb-6">Entrar no sistema</h2>

                <?php if ($erro): ?>
                <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm font-semibold flex items-center gap-2">
                    <i class="fas fa-circle-xmark"></i> <?= e($erro) ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-bold text-slate-600 mb-1.5">E-mail</label>
                            <div class="relative">
                                <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                                <input type="email" name="email" required
                                       value="<?= e($_POST['email'] ?? '') ?>"
                                       placeholder="seu@email.com"
                                       class="w-full pl-11 pr-4 py-3 rounded-xl border border-slate-200 focus:border-yellow-400 focus:ring-2 focus:ring-yellow-100 outline-none transition text-sm font-semibold">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-600 mb-1.5">Senha</label>
                            <div class="relative">
                                <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                                <input type="password" name="senha" id="senhaInput" required
                                       placeholder="••••••••"
                                       class="w-full pl-11 pr-12 py-3 rounded-xl border border-slate-200 focus:border-yellow-400 focus:ring-2 focus:ring-yellow-100 outline-none transition text-sm font-semibold">
                                <button type="button" onclick="toggleSenha()"
                                        class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                    <i class="fas fa-eye text-sm" id="olhoIcon"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="submit"
                            class="mt-6 w-full py-3 rounded-xl font-black text-white text-sm tracking-wide transition-all hover:brightness-110 active:scale-95"
                            style="background:#e5a820">
                        <i class="fas fa-right-to-bracket mr-2"></i> Entrar
                    </button>
                </form>

                <p class="text-center text-xs text-slate-400 mt-6">
                    Acesso restrito a administradores
                </p>
            </div>
        </div>
    </div>

    <script>
    function toggleSenha() {
        const input = document.getElementById('senhaInput');
        const icon  = document.getElementById('olhoIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }
    </script>
</body>
</html>
