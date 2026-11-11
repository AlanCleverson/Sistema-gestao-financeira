<?php
require_once 'conexao.php';

// Processa o formulário de cadastro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'adicionar') {
    $descricao = trim($_POST['descricao']);
    $valor = floatval(str_replace(',', '.', $_POST['valor']));
    $tipo = $_POST['tipo'];
    $data = date('Y-m-d');

    if (!empty($descricao) && $valor > 0) {
        $stmt = $pdo->prepare("INSERT INTO transacoes (descricao, valor, tipo, data) VALUES (?, ?, ?, ?)");
        $stmt->execute([$descricao, $valor, $tipo, $data]);
    }
    header('Location: index.php');
    exit;
}

// Processa a exclusão
if (isset($_GET['excluir'])) {
    $id = intval($_GET['excluir']);
    $stmt = $pdo->prepare("DELETE FROM transacoes WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: index.php');
    exit;
}

// Busca os dados (Corrigido o SELECT aqui)
$stmt = $pdo->query("SELECT * FROM transacoes ORDER BY id DESC");
$transacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalReceitas = 0;
$totalDespesas = 0;
foreach ($transacoes as $t) {
    if ($t['tipo'] === 'receita') {
        $totalReceitas += $t['valor'];
    } else {
        $totalDespesas += $t['valor'];
    }
}
$saldoFinal = $totalReceitas - $totalDespesas;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão Financeira Pessoal</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-main: #121214;
            --card-bg: #202024;
            --text-color: #E1E1E6;
            --text-muted: #82828E;
            --primary: #00B37E;
            --danger: #F75A68;
            --border: #29292E;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Montserrat', sans-serif; }
        body { background-color: var(--bg-main); color: var(--text-color); padding: 20px; display: flex; justify-content: center; min-height: 100vh; }
        .container { width: 100%; max-width: 600px; }
        header h1 { font-size: 24px; font-weight: 700; text-align: center; margin-bottom: 24px; }
        .summary-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 24px; }
        .card { background-color: var(--card-bg); padding: 16px; border-radius: 8px; border: 1px solid var(--border); text-align: center; }
        .card span { font-size: 12px; color: var(--text-muted); display: block; margin-bottom: 6px; }
        .card h2 { font-size: 18px; font-weight: 600; }
        .card.green h2 { color: var(--primary); }
        .card.red h2 { color: var(--danger); }
        .form-box { background-color: var(--card-bg); padding: 20px; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 24px; display: flex; flex-direction: column; gap: 12px; }
        .form-control { width: 100%; padding: 12px; background-color: var(--bg-main); border: 1px solid var(--border); color: var(--text-color); border-radius: 6px; font-size: 14px; outline: none; }
        .form-row { display: flex; gap: 10px; }
        .btn-submit { background-color: var(--primary); color: white; border: none; padding: 12px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 14px; }
        .transactions-list { display: flex; flex-direction: column; gap: 10px; }
        .transaction-item { background-color: var(--card-bg); padding: 14px 16px; border-radius: 8px; border: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .transaction-info span { font-size: 12px; color: var(--text-muted); display: block; }
        .transaction-info strong { font-size: 15px; }
        .transaction-value { font-size: 15px; font-weight: 600; display: flex; align-items: center; gap: 12px; }
        .transaction-value.receita { color: var(--primary); }
        .transaction-value.despesa { color: var(--danger); }
        .btn-delete { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 16px; }
        .btn-delete:hover { color: var(--danger); }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h1>Controle Financeiro</h1>
    </header>

    <div class="summary-cards">
        <div class="card">
            <span>Entradas</span>
            <h2 class="green">R$ <?php echo number_format($totalReceitas, 2, ',', '.'); ?></h2>
        </div>
        <div class="card">
            <span>Saídas</span>
            <h2 class="red">R$ <?php echo number_format($totalDespesas, 2, ',', '.'); ?></h2>
        </div>
        <div class="card">
            <span>Saldo</span>
            <h2 style="color: <?php echo $saldoFinal >= 0 ? 'var(--primary)' : 'var(--danger)'; ?>">
                R$ <?php echo number_format($saldoFinal, 2, ',', '.'); ?>
            </h2>
        </div>
    </div>

    <form class="form-box" method="POST">
        <input type="hidden" name="acao" value="adicionar">
        <input type="text" name="descricao" class="form-control" placeholder="Descrição (ex: Salário, Aluguel...)" required>
        <div class="form-row">
            <input type="text" name="valor" class="form-control" placeholder="Valor (ex: 150.00)" required>
            <select name="tipo" class="form-control" required>
                <option value="receita">Receita (Entrada)</option>
                <option value="despesa">Despesa (Saída)</option>
            </select>
        </div>
        <button type="submit" class="btn-submit">Adicionar Transação</button>
    </form>

    <div class="transactions-list">
        <?php if (empty($transacoes)): ?>
            <p style="text-align: center; color: var(--text-muted); font-size: 14px;">Nenhuma transação cadastrada ainda.</p>
        <?php else: ?>
            <?php foreach ($transacoes as $t): ?>
                <div class="transaction-item">
                    <div class="transaction-info">
                        <strong><?php echo htmlspecialchars($t['descricao']); ?></strong>
                        <span><?php echo date('d/m/Y', strtotime($t['data'])); ?></span>
                    </div>
                    <div class="transaction-value <?php echo $t['tipo']; ?>">
                        <?php echo ($t['tipo'] === 'receita' ? '+ ' : '- ') . 'R$ ' . number_format($t['valor'], 2, ',', '.'); ?>
                        <a href="index.php?excluir=<?php echo $t['id']; ?>" class="btn-delete" title="Excluir">✕</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html> 