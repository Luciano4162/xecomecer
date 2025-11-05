<?php
// config/db.php

// --- VARIÁVEIS DE CONEXÃO ---
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$db   = getenv('DB_NAME') ?: 'e-commerce-teste';
$user = getenv('DB_USER') ?: 'postgres';
$pass = getenv('DB_PASS') ?: 'Lucas8536@';

$charset = 'utf8';
$ssl_mode = '';
$options_dsn = "options='-c search_path=public'";

// Se estiver em um ambiente Render/Heroku configurado via DATABASE_URL
if (getenv('DATABASE_URL')) {
    $database_url = getenv('DATABASE_URL');
    $url = parse_url($database_url);

    $host = $url['host'];
    $port = $url['port'] ?? '5432';
    $db = ltrim($url['path'], '/');
    $user = $url['user'];
    $pass = $url['pass'];

    $ssl_mode = 'sslmode=require';
    $options_dsn = '';
}

// -- STRING DE CONEXÃO PDO UNIFICADA --
$dsn = "pgsql:host=$host;port=$port;dbname=$db";
if (!empty($ssl_mode)) {
    $dsn .= ";$ssl_mode";
}
if (!empty($options_dsn)) {
    $dsn .= ";$options_dsn";
}

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    // 1. EXECUÇÃO DA MIGRAÇÃO (Criação de Tabelas e Inserção de Dados Iniciais)
    require_once 'migrations.php';
    // Basta chamar runMigrations, que agora é responsável por todo o setup.
    runMigrations($pdo);

} catch (\PDOException $e) {
    // --- LÓGICA DE FALLBACK SEM SSL (Para corrigir o erro 08006 local) ---
    // A lógica de fallback também deve chamar APENAS a migração.
    if (getenv('DATABASE_URL') === false && (strpos($e->getMessage(), 'SSL') !== false || strpos($e->getMessage(), 'require') !== false)) {
        try {
            $dsn_no_ssl = "pgsql:host=$host;port=$port;dbname=$db;user=$user;password=$pass";
            $pdo = new PDO($dsn_no_ssl, $user, $pass, $options);

            // Tenta a migração novamente
            require_once 'migrations.php';
            runMigrations($pdo);

        } catch (\PDOException $e_no_ssl) {
            // Erro fatal de conexão ou migração
            throw new \PDOException("Erro fatal ao conectar ou inicializar DB (Local): " . $e_no_ssl->getMessage(), (int)$e_no_ssl->getCode());
        }
    } else {
        // Erro fatal geral
        throw new \PDOException("Erro fatal ao conectar ou inicializar DB: " . $e->getMessage(), (int)$e->getCode());
    }
}