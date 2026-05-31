-- =============================================================
-- BRAVA - Sistema de Gestão de Kitnet
-- Schema do banco de dados
-- =============================================================

CREATE DATABASE IF NOT EXISTS brava_kitnet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE brava_kitnet;

-- -------------------------------------------------------------
-- Tabela de usuários administrativos do sistema
-- -------------------------------------------------------------
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,          -- Armazenado com password_hash()
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- -------------------------------------------------------------
-- Tipos de quarto: suíte, solteiro, casal, etc.
-- -------------------------------------------------------------
CREATE TABLE tipos_quarto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(80) NOT NULL,            -- Ex: "Suíte", "Solteiro", "Casal"
    descricao TEXT,
    valor_padrao DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- -------------------------------------------------------------
-- Quartos da kitnet
-- -------------------------------------------------------------
CREATE TABLE quartos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(10) NOT NULL UNIQUE,   -- Ex: "101", "A2"
    tipo_id INT NOT NULL,
    valor_aluguel DECIMAL(10,2) NOT NULL,
    descricao TEXT,
    status ENUM('disponivel', 'ocupado', 'manutencao') DEFAULT 'disponivel',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tipo_id) REFERENCES tipos_quarto(id)
);

-- -------------------------------------------------------------
-- Inquilinos cadastrados no sistema
-- -------------------------------------------------------------
CREATE TABLE inquilinos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    cpf VARCHAR(14) UNIQUE,               -- Formato: 000.000.000-00
    rg VARCHAR(20),
    telefone VARCHAR(20),                 -- WhatsApp preferencial
    email VARCHAR(150),
    endereco_anterior TEXT,
    observacoes TEXT,
    ativo TINYINT(1) DEFAULT 1,           -- 1 = ativo, 0 = inativo/saiu
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- -------------------------------------------------------------
-- Contratos: vínculo entre inquilino e quarto
-- -------------------------------------------------------------
CREATE TABLE contratos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inquilino_id INT NOT NULL,
    quarto_id INT NOT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE,                        -- NULL = contrato aberto/indefinido
    dia_vencimento TINYINT NOT NULL DEFAULT 10, -- Dia do mês do vencimento
    valor_aluguel DECIMAL(10,2) NOT NULL,
    deposito DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('ativo', 'encerrado') DEFAULT 'ativo',
    observacoes TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inquilino_id) REFERENCES inquilinos(id),
    FOREIGN KEY (quarto_id) REFERENCES quartos(id)
);

-- -------------------------------------------------------------
-- Aluguéis mensais — gerados a partir dos contratos
-- -------------------------------------------------------------
CREATE TABLE alugueis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contrato_id INT NOT NULL,
    competencia DATE NOT NULL,            -- Ex: 2026-05-01 (primeiro dia do mês)
    data_vencimento DATE NOT NULL,
    data_pagamento DATE,                  -- NULL = não pago ainda
    valor DECIMAL(10,2) NOT NULL,
    valor_pago DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('pendente', 'pago', 'atrasado', 'parcial') DEFAULT 'pendente',
    forma_pagamento VARCHAR(50),          -- Pix, dinheiro, transferência...
    observacoes TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contrato_id) REFERENCES contratos(id),
    UNIQUE KEY uniq_contrato_competencia (contrato_id, competencia)
);

-- -------------------------------------------------------------
-- Contas a pagar: manutenção, água, luz, etc.
-- -------------------------------------------------------------
CREATE TABLE contas_pagar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    categoria ENUM('manutencao', 'agua', 'luz', 'internet', 'imposto', 'seguro', 'outro') NOT NULL,
    descricao TEXT,
    valor DECIMAL(10,2) NOT NULL,
    data_vencimento DATE NOT NULL,
    data_pagamento DATE,
    status ENUM('pendente', 'pago', 'atrasado') DEFAULT 'pendente',
    quarto_id INT DEFAULT NULL,           -- NULL = despesa geral, não vinculada a quarto
    recorrente TINYINT(1) DEFAULT 0,      -- 1 = se repete mensalmente
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quarto_id) REFERENCES quartos(id) ON DELETE SET NULL
);

-- =============================================================
-- Dados iniciais: admin padrão e tipos de quarto
-- Senha padrão: admin123 (deve ser alterada no primeiro login)
-- =============================================================
INSERT INTO usuarios (nome, email, senha) VALUES
('Administrador', 'admin@brava.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
-- Senha acima é: password (trocar imediatamente!)

INSERT INTO tipos_quarto (nome, descricao, valor_padrao) VALUES
('Solteiro', 'Quarto individual com cama de solteiro', 800.00),
('Suíte', 'Quarto com banheiro privativo', 1200.00),
('Casal', 'Quarto com cama de casal', 1000.00),
('Kitnet Completa', 'Espaço completo com cozinha integrada', 1500.00);
