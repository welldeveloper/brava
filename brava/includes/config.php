<?php
// =============================================================
// Configuração da conexão com o banco de dados via PDO
// Altere as credenciais conforme seu ambiente
// =============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'brava_kitnet');
define('DB_USER', 'root');          // Troque pelo seu usuário
define('DB_PASS', '');              // Troque pela sua senha
define('DB_CHARSET', 'utf8mb4');

// URL base do sistema (sem barra no final)
define('BASE_URL', 'http://localhost/brava');

// Nome do sistema
define('SISTEMA_NOME', 'Brava');
define('SISTEMA_SUBTITULO', 'Sistema de Gestão de Kitnet');

// Dias de antecedência para alertar vencimento
define('ALERTA_DIAS_ANTES', 5);

/**
 * Retorna uma instância PDO com configurações seguras.
 * Lança exceção em caso de erro — nunca expõe credenciais ao usuário.
 */
function getDB(): PDO {
    static $pdo = null; // Reutiliza a mesma conexão na mesma requisição

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // Lança exceções
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // Retorna arrays associativos
            PDO::ATTR_EMULATE_PREPARES   => false,                    // Prepared statements reais
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Em produção: logar o erro, nunca exibir detalhes
            error_log('Erro de conexão: ' . $e->getMessage());
            die(json_encode(['erro' => 'Falha na conexão com o banco de dados.']));
        }
    }

    return $pdo;
}
