<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? SISTEMA_NOME) ?> — <?= SISTEMA_NOME ?></title>
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/logo.jpg" type="image/jpeg">

    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Configuração do tema Brava -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        navy:  { DEFAULT: '#1e2d40', 50: '#f0f4f8', 100: '#d9e4ef', 500: '#2d4a6b', 700: '#1e2d40', 900: '#0f1a26' },
                        gold:  { DEFAULT: '#e5a820', light: '#f5c842', dark: '#c4901a' },
                    },
                    fontFamily: {
                        sans: ['"Nunito"', 'sans-serif'],
                        display: ['"Nunito"', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Ícones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        /* Animação piscando para aluguéis vencidos */
        @keyframes pulse-red {
            0%, 100% { background-color: #fef2f2; border-color: #fca5a5; }
            50%       { background-color: #fee2e2; border-color: #f87171; }
        }
        .vencido-pisca { animation: pulse-red 1.5s ease-in-out infinite; }

        /* Scrollbar customizada */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #94a3b8; border-radius: 3px; }

        /* Sidebar active link */
        .nav-link.active { background: rgba(229,168,32,0.15); color: #e5a820 !important; }
        .nav-link.active i { color: #e5a820; }
    </style>
</head>
<body class="bg-slate-100 font-sans text-navy-700 min-h-screen">

<!-- ============================================================
     LAYOUT PRINCIPAL: Sidebar + Conteúdo
     ============================================================ -->
<div class="flex h-screen overflow-hidden">

    <!-- SIDEBAR -->
    <aside id="sidebar" class="w-64 bg-navy-700 flex flex-col shadow-2xl transition-all duration-300 z-50
                               fixed inset-y-0 left-0 lg:relative lg:translate-x-0
                               -translate-x-full" style="background:#1e2d40">

        <!-- Logo -->
        <div class="p-5 border-b border-white/10 flex items-center gap-3">
            <img src="<?= BASE_URL ?>/assets/img/logo.jpg" alt="Brava" class="w-12 h-12 rounded-full object-cover">
            <div>
                <div class="text-white font-black text-xl leading-none">BRAVA</div>
                <div class="text-gold text-xs font-semibold mt-0.5" style="color:#e5a820">Gestão de Kitnet</div>
            </div>
        </div>

        <!-- Navegação -->
        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
            <?php
            $paginaAtual = basename($_SERVER['PHP_SELF'], '.php');
            $menu = [
                ['href' => 'dashboard',   'icon' => 'fa-gauge-high',    'label' => 'Dashboard'],
                ['href' => 'inquilinos',  'icon' => 'fa-users',         'label' => 'Inquilinos'],
                ['href' => 'quartos',     'icon' => 'fa-door-open',     'label' => 'Quartos'],
                ['href' => 'contratos',   'icon' => 'fa-file-contract', 'label' => 'Contratos'],
                ['href' => 'alugueis',    'icon' => 'fa-money-bill',    'label' => 'Aluguéis'],
                ['href' => 'contas',      'icon' => 'fa-receipt',       'label' => 'Contas a Pagar'],
                ['href' => 'relatorios',  'icon' => 'fa-chart-bar',     'label' => 'Relatórios'],
            ];
            foreach ($menu as $item):
                $ativo = ($paginaAtual === $item['href']) ? 'active' : '';
            ?>
            <a href="<?= BASE_URL ?>/pages/<?= $item['href'] ?>.php"
               class="nav-link <?= $ativo ?> flex items-center gap-3 px-4 py-2.5 rounded-lg text-slate-300 hover:text-white hover:bg-white/10 transition-all text-sm font-semibold">
                <i class="fas <?= $item['icon'] ?> w-5 text-center text-slate-400"></i>
                <?= $item['label'] ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <!-- Rodapé da sidebar -->
        <div class="p-4 border-t border-white/10">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white"
                     style="background:#e5a820">
                    <?= strtoupper(substr(nomeUsuario(), 0, 1)) ?>
                </div>
                <span class="text-slate-300 text-sm font-semibold truncate"><?= e(nomeUsuario()) ?></span>
            </div>
            <a href="<?= BASE_URL ?>/logout.php"
               class="flex items-center gap-2 text-slate-400 hover:text-red-400 text-xs transition-colors">
                <i class="fas fa-right-from-bracket"></i> Sair do sistema
            </a>
        </div>
    </aside>

    <!-- OVERLAY mobile -->
    <div id="overlay" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden" onclick="toggleSidebar()"></div>

    <!-- ÁREA PRINCIPAL -->
    <div class="flex-1 flex flex-col overflow-hidden">

        <!-- TOPBAR -->
        <header class="bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-4">
                <!-- Botão menu mobile -->
                <button onclick="toggleSidebar()" class="lg:hidden text-slate-500 hover:text-navy-700">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <h1 class="text-xl font-black text-navy-700"><?= e($pageTitle ?? 'Dashboard') ?></h1>
            </div>
            <div class="flex items-center gap-4 text-sm text-slate-500">
                <i class="fas fa-calendar-day"></i>
                <?= date('d/m/Y') ?>
            </div>
        </header>

        <!-- FLASH MESSAGE -->
        <?php $flash = getFlash(); if ($flash): ?>
        <div id="flash-msg" class="mx-6 mt-4 px-4 py-3 rounded-lg text-sm font-semibold flex items-center gap-2
            <?= $flash['tipo'] === 'sucesso' ? 'bg-green-100 text-green-800 border border-green-300' : 'bg-red-100 text-red-800 border border-red-300' ?>">
            <i class="fas <?= $flash['tipo'] === 'sucesso' ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
            <?= e($flash['mensagem']) ?>
            <button onclick="document.getElementById('flash-msg').remove()" class="ml-auto opacity-60 hover:opacity-100">✕</button>
        </div>
        <?php endif; ?>

        <!-- CONTEÚDO DA PÁGINA -->
        <main class="flex-1 overflow-y-auto p-6">
