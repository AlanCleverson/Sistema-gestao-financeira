<?php
// Define o nome do arquivo do banco de dados SQLite local
$dbFile = __DIR__ . '/banco.db';

try {
    // Cria a conexão PDO com SQLite
    $pdo = new PDO('sqlite:' . $dbFile);
    
    // Configura o PDO para lançar exceções em caso de erros
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Cria a tabela 'transacoes' automaticamente se ela ainda não existir
    $pdo->exec("CREATE TABLE IF NOT EXISTS transacoes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        descricao TEXT NOT NULL,
        valor REAL NOT NULL,
        tipo TEXT NOT NULL,
        data TEXT NOT NULL
    )");

} catch (PDOException $e) {
    // Em caso de erro na conexão ou criação da tabela, exibe a mensagem
    die("Erro ao conectar com o banco de dados: " . $e->getMessage());
}
?>