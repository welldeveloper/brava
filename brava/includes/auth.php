<?php
// =============================================================
// Funções de autenticação e controle de sessão
// =============================================================

require_once __DIR__ . '/config.php';

/**
 * Inicia a sessão de forma segura.
 * Chame no topo de qualquer página que precise de sessão.
 */
function iniciarSessao(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_httponly' => true,   // Impede acesso via JavaScript
            'cookie_secure'   => false,  // Mude para true em HTTPS
            'use_strict_mode' => true,
        ]);
    }
}

/**
 * Verifica se o usuário está logado.
 * Redireciona para login se não estiver.
 */
function exigirLogin(): void {
    iniciarSessao();
    if (empty($_SESSION['usuario_id'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

/**
 * Faz o login do usuário: valida credenciais e cria sessão.
 * Retorna mensagem de erro ou null em caso de sucesso.
 */
function fazerLogin(string $email, string $senha): ?string {
    $db = getDB();

    // Busca usuário pelo e-mail (prepared statement protege contra SQL Injection)
    $stmt = $db->prepare('SELECT id, nome, senha FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    if (!$usuario || !password_verify($senha, $usuario['senha'])) {
        return 'E-mail ou senha incorretos.';
    }

    // Regenera o ID da sessão para evitar session fixation
    session_regenerate_id(true);

    $_SESSION['usuario_id']   = $usuario['id'];
    $_SESSION['usuario_nome'] = $usuario['nome'];

    return null; // Sucesso
}

/**
 * Encerra a sessão do usuário e redireciona para o login.
 */
function fazerLogout(): void {
    iniciarSessao();
    session_destroy();
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

/**
 * Retorna o nome do usuário logado.
 */
function nomeUsuario(): string {
    return $_SESSION['usuario_nome'] ?? 'Administrador';
}

/**
 * Sanitiza string para exibição segura no HTML.
 * Use em TODOS os dados vindos do banco ou do usuário.
 */
function e(mixed $valor): string {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Formata valor monetário em reais: 1500.00 → R$ 1.500,00
 */
function formatarDinheiro(mixed $valor): string {
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

/**
 * Formata data do banco (Y-m-d) para exibição (d/m/Y)
 */
function formatarData(?string $data): string {
    if (!$data) return '—';
    return date('d/m/Y', strtotime($data));
}

/**
 * Gera token CSRF e armazena na sessão.
 * Use em formulários para evitar ataques CSRF.
 */
function csrfToken(): string {
    iniciarSessao();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida o token CSRF enviado via POST.
 * Encerra execução se inválido.
 */
function validarCsrf(): void {
    iniciarSessao();
    $tokenEnviado = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $tokenEnviado)) {
        http_response_code(403);
        die('Token de segurança inválido. Recarregue a página e tente novamente.');
    }
}

/**
 * Redireciona para uma URL relativa ao BASE_URL
 */
function redirecionar(string $caminho): void {
    header('Location: ' . BASE_URL . '/' . ltrim($caminho, '/'));
    exit;
}

/**
 * Armazena mensagem flash na sessão para exibir na próxima página.
 */
function flash(string $tipo, string $mensagem): void {
    iniciarSessao();
    $_SESSION['flash'] = ['tipo' => $tipo, 'mensagem' => $mensagem];
}

/**
 * Recupera e limpa mensagem flash.
 * Retorna array ['tipo'=>..., 'mensagem'=>...] ou null.
 */
function getFlash(): ?array {
    iniciarSessao();
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
