<?php
try {
    $caminhoBanco = __DIR__ . '/database.sqlite';
    $pdo = new PDO('sqlite:' . $caminhoBanco);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Tabela de Transações vinculada ao apelido único do usuário
    $pdo->exec("CREATE TABLE IF NOT EXISTS transacoes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        usuario_apelido TEXT NOT NULL,
        tipo TEXT NOT NULL,
        valor REAL NOT NULL,
        descricao TEXT NOT NULL,
        categoria TEXT NOT NULL,
        data TEXT NOT NULL
    )");

    // Tabela de Usuários para garantir nomes de usuário únicos
    $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome TEXT NOT NULL,
        apelido TEXT UNIQUE NOT NULL
    )");

} catch (PDOException $e) {
    die("Erro ao conectar com o banco de dados: " . $e->getMessage());
}
?>