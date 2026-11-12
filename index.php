<?php
session_start();
require_once 'conexao.php';

$erro = '';
$sucessoCat = '';
$avisoTransacao = '';

// Garantir tabelas no banco SQLite de forma segura
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome TEXT,
        apelido TEXT UNIQUE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS transacoes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        usuario_apelido TEXT,
        tipo TEXT,
        valor REAL,
        descricao TEXT,
        categoria TEXT,
        data TEXT
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS categorias (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        usuario_apelido TEXT,
        nome TEXT,
        icone TEXT,
        tipo TEXT DEFAULT 'despesa'
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS compromissos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        usuario_apelido TEXT,
        titulo TEXT,
        data_vencimento TEXT,
        valor_estimado REAL
    )");
} catch (Exception $e) {}

// Função para popular categorias padrão
function criarCategoriasPadrao($pdo, $apelido) {
    try {
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM categorias WHERE usuario_apelido = ?");
        $stmtCheck->execute([$apelido]);
        if ($stmtCheck->fetchColumn() == 0) {
            $padroes = [
                ['Alimentação', '🍔', 'despesa'],
                ['Transporte', '🚗', 'despesa'],
                ['Moradia', '🏠', 'despesa'],
                ['Lazer', '🎉', 'despesa'],
                ['Saúde', '💊', 'despesa'],
                ['Educação', '📚', 'despesa'],
                ['Outros', '📦', 'despesa'],
                ['Salário', '💰', 'receita'],
                ['Investimentos', '📈', 'receita'],
                ['Extras/Freelance', '🛠️', 'receita']
            ];
            $stmtInsCat = $pdo->prepare("INSERT INTO categorias (usuario_apelido, nome, icone, tipo) VALUES (?, ?, ?, ?)");
            foreach ($padroes as $p) {
                $stmtInsCat->execute([$apelido, $p[0], $p[1], $p[2]]);
            }
        }
    } catch (Exception $e) {}
}

// Ações via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['acao_cadastrar'])) {
        $nome = trim($_POST['nome'] ?? '');
        $apelido = strtolower(trim($_POST['apelido'] ?? ''));
        $apelido = preg_replace('/[^a-z0-9_]/', '', $apelido);

        if (!empty($nome) && !empty($apelido)) {
            try {
                $stmtCheck = $pdo->prepare("SELECT * FROM usuarios WHERE apelido = ?");
                $stmtCheck->execute([$apelido]);
                
                if ($stmtCheck->fetch(PDO::FETCH_ASSOC)) {
                    $erro = "Este apelido já está em uso por outra pessoa. Escolha outro!";
                } else {
                    $stmtIns = $pdo->prepare("INSERT INTO usuarios (nome, apelido) VALUES (?, ?)");
                    $stmtIns->execute([$nome, $apelido]);
                    criarCategoriasPadrao($pdo, $apelido);

                    $_SESSION['usuario_apelido'] = $apelido;
                    $_SESSION['usuario_nome'] = $nome;
                    header("Location: index.php");
                    exit;
                }
            } catch (Exception $e) {
                $erro = "Erro ao cadastrar usuário.";
            }
        } else {
            $erro = "Por favor, preencha o nome e um apelido válido.";
        }
    }

    if (isset($_POST['acao_entrar'])) {
        $apelido = strtolower(trim($_POST['apelido_login'] ?? ''));
        
        if (!empty($apelido)) {
            try {
                $stmtCheck = $pdo->prepare("SELECT * FROM usuarios WHERE apelido = ?");
                $stmtCheck->execute([$apelido]);
                $usuarioCadastrado = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if ($usuarioCadastrado) {
                    $_SESSION['usuario_apelido'] = $usuarioCadastrado['apelido'];
                    $_SESSION['usuario_nome'] = $usuarioCadastrado['nome'];
                    criarCategoriasPadrao($pdo, $usuarioCadastrado['apelido']);

                    header("Location: index.php");
                    exit;
                } else {
                    $erro = "Apelido não encontrado. Faça seu cadastro primeiro!";
                }
            } catch (Exception $e) {
                $erro = "Erro ao realizar login.";
            }
        } else {
            $erro = "Informe seu apelido para entrar.";
        }
    }

    if (isset($_POST['acao_sair'])) {
        // Limpar totalmente a sessão ao sair
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        header("Location: index.php");
        exit;
    }

    // Cadastro de Nova Categoria
    if (isset($_POST['acao']) && $_POST['acao'] === 'cadastrar_categoria' && isset($_SESSION['usuario_apelido'])) {
        $novaCat = trim($_POST['nova_categoria_nome'] ?? '');
        $novoIcone = trim($_POST['nova_categoria_icone'] ?? '📁');
        $tipoCat = trim($_POST['nova_categoria_tipo'] ?? 'despesa');
        $apelidoAtual = $_SESSION['usuario_apelido'];

        if (!empty($novaCat)) {
            try {
                $stmtCatCheck = $pdo->prepare("SELECT COUNT(*) FROM categorias WHERE usuario_apelido = ? AND nome = ?");
                $stmtCatCheck->execute([$apelidoAtual, $novaCat]);
                if ($stmtCatCheck->fetchColumn() == 0) {
                    $stmtInsCat = $pdo->prepare("INSERT INTO categorias (usuario_apelido, nome, icone, tipo) VALUES (?, ?, ?, ?)");
                    $stmtInsCat->execute([$apelidoAtual, $novaCat, $novoIcone, $tipoCat]);
                    $sucessoCat = "Categoria '{$novaCat}' cadastrada com sucesso!";
                }
            } catch (Exception $e) {}
        }
    }

    // Cadastro de Lembrete de Pagamento
    if (isset($_POST['acao']) && $_POST['acao'] === 'cadastrar_compromisso' && isset($_SESSION['usuario_apelido'])) {
        $tituloComp = trim($_POST['titulo_comp'] ?? '');
        $dataVenc = $_POST['data_vencimento'] ?? date('Y-m-d');
        $valorComp = floatval(str_replace(',', '.', $_POST['valor_comp'] ?? 0));
        $apelidoAtual = $_SESSION['usuario_apelido'];

        if (!empty($tituloComp) && !empty($dataVenc)) {
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS compromissos (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    usuario_apelido TEXT,
                    titulo TEXT,
                    data_vencimento TEXT,
                    valor_estimado REAL
                )");

                $stmtComp = $pdo->prepare("INSERT INTO compromissos (usuario_apelido, titulo, data_vencimento, valor_estimado) VALUES (?, ?, ?, ?)");
                $stmtComp->execute([$apelidoAtual, $tituloComp, $dataVenc, $valorComp]);
            } catch (Exception $e) {}
        }
    }

    // Processamento de Transação (Salvar) com Mensagens Divertidas
    if (isset($_POST['acao']) && $_POST['acao'] === 'salvar' && isset($_SESSION['usuario_apelido'])) {
        $tipo = $_POST['tipo'] ?? 'despesa';
        $valor = floatval(str_replace(',', '.', $_POST['valor'] ?? 0));
        $categoria = trim($_POST['categoria'] ?? 'Geral');
        $descricao = trim($_POST['descricao'] ?? '');
        
        if (empty($descricao)) {
            $descricao = $categoria;
        }

        $data = $_POST['data'] ?? date('Y-m-d');
        $apelidoAtual = $_SESSION['usuario_apelido'];

        if ($valor > 0) {
            try {
                $stmtInsert = $pdo->prepare("INSERT INTO transacoes (usuario_apelido, tipo, valor, descricao, categoria, data) VALUES (?, ?, ?, ?, ?, ?)");
                $stmtInsert->execute([$apelidoAtual, $tipo, $valor, $descricao, $categoria, $data]);
                
                if ($tipo === 'receita') {
                    $frasesReceita = [
                        "💰 Oba! Mais cascalho no cofrinho! Devagar se vai longe rumo ao topo!",
                        "🚀 Receita confirmada! Seu império financeiro acaba de ganhar mais um tijolinho!",
                        "🌟 Dinheiro entrando! O trabalho deu fruto e a conta agradece com alegria!",
                        "🎉 Uia! Salário/Receita computada com sucesso. Respire aliviado e continue firme!"
                    ];
                    $_SESSION['aviso_divertido'] = $frasesReceita[array_rand($frasesReceita)];
                } else {
                    $frasesDespesa = [
                        "☕ Dinheiro trocado por experiência (ou café)! Uma conta a menos, foco no equilíbrio.",
                        "💸 Mais um compromisso pago ou gasto efetuado! Menos um peso na consciência.",
                        "🛒 Saída registrada na ponta do lápis. Cada centavo monitorado é sabedoria pura!",
                        "🎯 Despesa anotada! O controle diário é o segredo de quem manda no próprio dinheiro."
                    ];
                    $_SESSION['aviso_divertido'] = $frasesDespesa[array_rand($frasesDespesa)];
                }
            } catch (Exception $e) {}

            header("Location: index.php");
            exit;
        }
    }
}

// Verificação rigorosa do login: Se a sessão não tiver apelido definido, obrigatoriamente força o estado deslogado
$usuarioLogado = isset($_SESSION['usuario_apelido']) && !empty($_SESSION['usuario_apelido']);
if ($usuarioLogado && isset($_SESSION['aviso_divertido'])) {
    $avisoTransacao = $_SESSION['aviso_divertido'];
    unset($_SESSION['aviso_divertido']);
}

$totalReceitasGeral = 0;
$totalDespesasGeral = 0;
$labelsRoscaFixo = [];
$valoresRoscaFixo = [];

$labelsLongoPrazo = [];
$valoresLongoPrazo = [];

$listaCategorias = [];
$listaCompromissos = [];

$filtroPeriodo = $_GET['periodo'] ?? '30dias';
$qtdDiasPersonalizada = intval($_GET['dias'] ?? 0);
$condicaoDataSql = "";
$hoje = date('Y-m-d');

if ($qtdDiasPersonalizada > 0) {
    $dataLimite = date('Y-m-d', strtotime("-{$qtdDiasPersonalizada} days"));
    $condicaoDataSql = " AND data >= '$dataLimite'";
    $filtroPeriodo = 'personalizado';
} else {
    switch ($filtroPeriodo) {
        case 'hoje':
            $condicaoDataSql = " AND data = '$hoje'";
            break;
        case 'semana':
            $inicioSemana = date('Y-m-d', strtotime('sunday this week -1 week'));
            $condicaoDataSql = " AND data >= '$inicioSemana'";
            break;
        case '7dias':
            $seteDiasAtras = date('Y-m-d', strtotime('-7 days'));
            $condicaoDataSql = " AND data >= '$seteDiasAtras'";
            break;
        case '30dias':
        default:
            $trintaDiasAtras = date('Y-m-d', strtotime('-30 days'));
            $condicaoDataSql = " AND data >= '$trintaDiasAtras'";
            break;
        case 'tudo':
            $condicaoDataSql = "";
            break;
    }
}

$transacoes = [];

if ($usuarioLogado) {
    try {
        // Validação adicional: garante que o usuário realmente existe no banco SQLite
        $apelidoAtual = $_SESSION['usuario_apelido'];
        $stmtUserCheck = $pdo->prepare("SELECT nome FROM usuarios WHERE apelido = ?");
        $stmtUserCheck->execute([$apelidoAtual]);
        $userData = $stmtUserCheck->fetch(PDO::FETCH_ASSOC);

        if (!$userData) {
            // Se o usuário foi apagado do banco mas a sessão continuava presa, desloga
            session_destroy();
            header("Location: index.php");
            exit;
        }
        $_SESSION['usuario_nome'] = $userData['nome'];

        criarCategoriasPadrao($pdo, $apelidoAtual);

        $stmtCats = $pdo->prepare("SELECT * FROM categorias WHERE usuario_apelido = ? ORDER BY nome ASC");
        $stmtCats->execute([$apelidoAtual]);
        $listaCategorias = $stmtCats->fetchAll(PDO::FETCH_ASSOC);

        try {
            $stmtComps = $pdo->prepare("SELECT * FROM compromissos WHERE usuario_apelido = ? ORDER BY data_vencimento ASC");
            $stmtComps->execute([$apelidoAtual]);
            $listaCompromissos = $stmtComps->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $ex) {
            $listaCompromissos = [];
        }

        $stmtRGeral = $pdo->prepare("SELECT SUM(valor) as total FROM transacoes WHERE usuario_apelido = ? AND tipo = 'receita'");
        $stmtRGeral->execute([$apelidoAtual]);
        $totalReceitasGeral = $stmtRGeral->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

        $stmtDGeral = $pdo->prepare("SELECT SUM(valor) as total FROM transacoes WHERE usuario_apelido = ? AND tipo = 'despesa'");
        $stmtDGeral->execute([$apelidoAtual]);
        $totalDespesasGeral = $stmtDGeral->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

        // Gráfico de Rosca Fixo (Esquerda)
        $stmtRoscaFixo = $pdo->prepare("SELECT categoria, SUM(valor) as total FROM transacoes WHERE usuario_apelido = ? AND tipo = 'despesa' GROUP BY categoria ORDER BY total DESC");
        $stmtRoscaFixo->execute([$apelidoAtual]);
        while ($rf = $stmtRoscaFixo->fetch(PDO::FETCH_ASSOC)) {
            $labelsRoscaFixo[] = $rf['categoria'];
            $valoresRoscaFixo[] = $rf['total'];
        }

        // Gráfico de Médio/Longo Prazo por Período Escolhido (Direita)
        $stmtLongo = $pdo->prepare("SELECT data, SUM(valor) as total FROM transacoes WHERE usuario_apelido = ? AND tipo = 'despesa' $condicaoDataSql GROUP BY data ORDER BY data ASC");
        $stmtLongo->execute([$apelidoAtual]);
        while ($lp = $stmtLongo->fetch(PDO::FETCH_ASSOC)) {
            $labelsLongoPrazo[] = date('d/m', strtotime($lp['data']));
            $valoresLongoPrazo[] = $lp['total'];
        }

        $stmtTrans = $pdo->prepare("SELECT * FROM transacoes WHERE usuario_apelido = ? ORDER BY id DESC LIMIT 10");
        $stmtTrans->execute([$apelidoAtual]);
        $transacoes = $stmtTrans->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

$saldoTotal = $totalReceitasGeral - $totalDespesasGeral;
$percentualGasto = ($totalReceitasGeral > 0) ? min(100, ($totalDespesasGeral / $totalReceitasGeral) * 100) : 0;
$percentualSobra = max(0, 100 - $percentualGasto);

$listaConselhos = [
    "⚓ <strong>Cuidado com os pequenos gastos:</strong> Um vazamento pequeno afunda um grande navio.",
    "⏳ <strong>Regra das 24 horas:</strong> Antes de comprar por impulso, espere um dia. Se ainda quiser, avalie.",
    "🏆 <strong>Pague a si mesmo primeiro:</strong> Reserve uma parte da sua receita para poupar antes de gastar.",
    "📝 <strong>O poder do lápis:</strong> O melhor investimento é o seu controle financeiro diário rigoroso.",
    "🛍️ <strong>Aviso de futilidade:</strong> Quem compra o que não precisa, logo terá que vender o que precisa.",
    "🎯 <strong>Qualidade de vida:</strong> Economizar não é deixar de viver, mas gastar com o que realmente importa.",
    "🔍 <strong>Ralos invisíveis:</strong> Monitore seus pequenos gastos diários; eles somam fortunas.",
    "🛡️ <strong>Tranquilidade noturna:</strong> Construir uma reserva de emergência é o melhor calmante para dormir.",
    "🧭 <strong>Dinheiro com bússola:</strong> Defina metas claras. Dinheiro sem rumo evapora sem deixar rastro.",
    "🏷️ <strong>Preço vs Valor:</strong> O preço é o que você paga; o valor é o que você leva para a vida."
];
$conselhoDoDia = $listaConselhos[array_rand($listaConselhos)];

function formatarBRL($valor) {
    return "R$ " . number_format($valor, 2, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Na Ponta do Lápis - Controle Financeiro</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg-body: #f4f7f6;
            --bg-card: #ffffff;
            --text-main: #2b3a4a;
            --text-muted: #718096;
            --accent-green: #2ecc71;
            --accent-red: #e74c3c;
            --accent-blue: #3498db;
            --border-color: #e2e8f0;
            --input-bg: #f8fafc;
            --tip-bg: #e8f4fd;
            --tip-border: #bee3f8;
        }

        [data-theme="dark"] {
            --bg-body: #0b132b;
            --bg-card: #1c2541;
            --text-main: #ffffff;
            --text-muted: #8d99ae;
            --accent-green: #2ec4b6;
            --accent-red: #e71d36;
            --accent-blue: #3a86ff;
            --border-color: #2b3a5a;
            --input-bg: #0b132b;
            --tip-bg: #162447;
            --tip-border: #1f4068;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; transition: background 0.3s, color 0.3s; }
        body { background-color: var(--bg-body); color: var(--text-main); padding: 12px; display: flex; justify-content: center; }
        .app-container { width: 100%; max-width: 900px; margin: 0 auto; padding-bottom: 40px; }

        .welcome-box { width: 100%; max-width: 440px; margin: 25px auto; background: var(--bg-card); padding: 25px; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: 0 8px 20px rgba(0,0,0,0.06); text-align: center; }
        .app-logo-icon {
            width: 65px;
            height: 65px;
            background: rgba(52, 152, 219, 0.12);
            color: var(--accent-blue);
            font-size: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin: 0 auto 12px auto;
            border: 2px solid var(--accent-blue);
        }
        .welcome-box h2 { margin-bottom: 6px; font-size: 1.4rem; color: var(--text-main); font-weight: 700; }
        .welcome-box p { margin-bottom: 20px; font-size: 0.85rem; color: var(--text-muted); line-height: 1.4; }
        
        .form-group { margin-bottom: 12px; text-align: left; }
        .form-group label { display: block; margin-bottom: 4px; font-size: 0.82rem; color: var(--text-muted); font-weight: 500; }
        .form-group input, .form-group select { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-main); font-size: 1rem; outline: none; }
        .alert-error { background: rgba(231,76,60,0.1); color: var(--accent-red); padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 0.82rem; font-weight: 600; }
        .alert-success { background: rgba(46,204,113,0.1); color: var(--accent-green); padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 0.82rem; font-weight: 600; }
        .alert-divertido { background: rgba(52, 152, 219, 0.12); color: var(--accent-blue); padding: 14px; border-radius: 10px; margin-bottom: 15px; font-size: 0.95rem; font-weight: 600; border-left: 5px solid var(--accent-blue); box-shadow: 0 4px 12px rgba(52, 152, 219, 0.1); text-align: center; }
        .btn-submit { width: 100%; padding: 12px; background: var(--accent-green); color: #fff; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 1rem; margin-top: 5px; }
        .btn-submit:hover { opacity: 0.9; }

        .divider { margin: 20px 0; border: none; border-top: 1px solid var(--border-color); }

        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid var(--border-color); flex-wrap: wrap; gap: 10px; }
        .header-title h2 { font-size: 0.85rem; color: var(--text-muted); }
        .header-title h1 { font-size: 1.3rem; font-weight: 700; }
        .header-actions { display: flex; align-items: center; gap: 10px; }
        .theme-toggle-btn, .logout-btn { background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-weight: 600; }
        .logout-btn { color: var(--accent-red); }

        .ai-tip-card {
            background: var(--tip-bg);
            border: 1px solid var(--tip-border);
            border-left: 4px solid var(--accent-blue);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.88rem;
            line-height: 1.5;
        }

        .report-drawer {
            display: none;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 12px;
            margin-top: 8px;
            margin-bottom: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .report-drawer.open { display: block; }
        .filter-bar {
            display: flex;
            gap: 6px;
            overflow-x: auto;
            margin-top: 6px;
            padding-bottom: 4px;
            -webkit-overflow-scrolling: touch;
        }
        .filter-btn {
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            padding: 6px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
            transition: 0.2s;
        }
        .filter-btn:hover, .filter-btn.active {
            background: var(--accent-blue);
            color: #ffffff;
            border-color: var(--accent-blue);
        }
        .custom-days-form {
            display: flex;
            gap: 6px;
            margin-top: 8px;
            align-items: center;
        }
        .custom-days-form input {
            padding: 6px 8px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background: var(--input-bg);
            color: var(--text-main);
            width: 85px;
            font-size: 0.85rem;
        }
        .custom-days-btn {
            background: var(--accent-blue);
            color: #fff;
            border: none;
            padding: 7px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
        }

        .dashboard-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; }
        @media (max-width: 768px) { .dashboard-grid { grid-template-columns: 1fr; } }
        .card { background: var(--bg-card); border-radius: 12px; padding: 16px; border: 1px solid var(--border-color); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .card h2 { font-size: 0.95rem; margin-bottom: 12px; color: var(--text-muted); font-weight: 600; }
        .saldo-valor { font-size: 1.7rem; font-weight: 700; margin-bottom: 12px; }
        .progress-bar-container { width: 100%; background: var(--border-color); height: 8px; border-radius: 4px; overflow: hidden; display: flex; }
        .progress-gasto { background: var(--accent-red); height: 100%; width: <?= $percentualGasto ?>%; }
        .progress-sobra { background: var(--accent-green); height: 100%; width: <?= $percentualSobra ?>%; }
        .saldo-legendas { display: flex; justify-content: space-between; margin-top: 8px; font-size: 0.78rem; color: var(--text-muted); flex-wrap: wrap; gap: 5px; }

        .tipo-selector { display: flex; gap: 10px; margin-bottom: 12px; }
        .tipo-btn {
            flex: 1; text-align: center; padding: 11px; border-radius: 8px; border: 1px solid var(--border-color);
            cursor: pointer; font-size: 0.9rem; font-weight: 600; background: var(--input-bg); color: var(--text-muted);
            transition: all 0.2s ease;
        }
        .tipo-btn.active-despesa {
            background: var(--accent-red); color: #ffffff; border-color: var(--accent-red);
            box-shadow: 0 4px 10px rgba(231, 76, 60, 0.3);
        }
        .tipo-btn.active-receita {
            background: var(--accent-green); color: #ffffff; border-color: var(--accent-green);
            box-shadow: 0 4px 10px rgba(46, 204, 113, 0.3);
        }

        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { width: 100%; border-collapse: collapse; margin-top: 5px; white-space: nowrap; }
        th, td { padding: 10px 8px; text-align: left; border-bottom: 1px solid var(--border-color); font-size: 0.85rem; }
        th { color: var(--text-muted); font-weight: 600; }
        .tipo-receita { color: var(--accent-green); font-weight: bold; }
        .tipo-despesa { color: var(--accent-red); font-weight: bold; }

        .box-nova-categoria {
            background: var(--input-bg);
            border: 1px dashed var(--accent-blue);
            padding: 12px;
            border-radius: 8px;
            margin-top: 8px;
            margin-bottom: 10px;
            display: none;
        }
        .agenda-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 9px 6px;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.84rem;
            gap: 10px;
        }
        .agenda-item:last-child { border-bottom: none; }
        
        .btn-alterar-periodo {
            background: var(--accent-blue);
            color: #fff;
            border: none;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }
        .btn-alterar-periodo:hover { opacity: 0.9; }

        .chart-type-selector-box {
            display: flex;
            gap: 4px;
            background: var(--input-bg);
            padding: 3px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            margin-bottom: 10px;
        }
        .chart-type-option {
            flex: 1;
            background: transparent;
            border: none;
            color: var(--text-muted);
            padding: 5px 2px;
            border-radius: 4px;
            font-size: 0.72rem;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            white-space: nowrap;
        }
        .chart-type-option.active {
            background: var(--accent-blue);
            color: #fff;
        }
    </style>
</head>
<body>

<div class="app-container">
    <div style="display: flex; justify-content: flex-end; margin-bottom: 10px;">
        <button class="theme-toggle-btn" onclick="toggleTheme()" id="themeBtn">🌙 Modo Escuro</button>
    </div>

    <?php if (!$usuarioLogado): ?>
        <div class="welcome-box">
            <div class="app-logo-icon">✏️</div>
            <h2>Na Ponta do Lápis</h2>
            <p>Seu controle financeiro pessoal, prático, inteligente e organizado no dia a dia.</p>
            
            <?php if($erro): ?><div class="alert-error"><?= $erro ?></div><?php endif; ?>

            <form action="index.php" method="POST">
                <input type="hidden" name="acao_cadastrar" value="1">
                <div style="font-size: 0.85rem; font-weight: 600; color: var(--accent-blue); margin-bottom: 10px; text-align: left;">👤 Primeiro Acesso (Criar Conta):</div>
                <div class="form-group">
                    <label>Seu Nome</label>
                    <input type="text" name="nome" placeholder="Ex: Maria" required>
                </div>
                <div class="form-group">
                    <label>Apelido Único (será seu ID usuário)</label>
                    <input type="text" name="apelido" placeholder="Ex: maria_silva" required>
                </div>
                <button type="submit" class="btn-submit">Criar Minha Conta</button>
            </form>

            <hr class="divider">

            <form action="index.php" method="POST">
                <input type="hidden" name="acao_entrar" value="1">
                <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 10px; text-align: left;">🔑 Já tem conta? Entre aqui:</div>
                <div class="form-group">
                    <label>Seu ID de Usuário (Apelido)</label>
                    <input type="text" name="apelido_login" placeholder="Ex: ana123" required>
                </div>
                <button type="submit" class="btn-submit" style="background: var(--accent-blue);">Entrar na Minha Conta</button>
            </form>
        </div>

    <?php else: ?>
        <div class="header">
            <div class="header-title">
                <h2>Gestão Financeira Pessoal</h2>
                <h1>Olá, <?= htmlspecialchars($_SESSION['usuario_nome']) ?>! ✏️</h1>
            </div>
            <div class="header-actions">
                <form action="index.php" method="POST">
                    <input type="hidden" name="acao_sair" value="1">
                    <button type="submit" class="logout-btn" title="Trocar de conta">Sair / Trocar</button>
                </form>
            </div>
        </div>

        <!-- ASSISTENTE FINANCEIRO NO TOPO -->
        <div class="ai-tip-card">
            <?= $conselhoDoDia ?>
        </div>

        <!-- SALDO FIXO DA CARTEIRA -->
        <div class="card" style="margin-bottom: 20px;">
            <h2>💰 Saldo Total da Carteira</h2>
            <div class="saldo-valor"><?= formatarBRL($saldoTotal) ?></div>
            <div class="progress-bar-container">
                <div class="progress-gasto"></div>
                <div class="progress-sobra"></div>
            </div>
            <div class="saldo-legendas">
                <span>Gastos: <?= formatarBRL($totalDespesasGeral) ?> (<?= number_format($percentualGasto, 1) ?>%)</span>
                <span>Sobra: <?= formatarBRL($saldoTotal) ?> (<?= number_format($percentualSobra, 1) ?>%)</span>
            </div>
        </div>

        <!-- PAINEL DUPLO: GRÁFICO DE ROSCA FIXO (ESQUERDA) + MÉDIO/LONGO PRAZO COM BOTÃO ALTERAR E SELETOR DE GRÁFICO (DIREITA) -->
        <div class="dashboard-grid">
            <!-- GRÁFICO DE ROSCA FIXO (LADO ESQUERDO) -->
            <div class="card">
                <h2>📊 Gráfico de Rosca Geral</h2>
                <div style="height: 180px; display: flex; justify-content: center; align-items: center;">
                    <?php if (empty($labelsRoscaFixo)): ?>
                        <p style="color: var(--text-muted); font-size: 0.8rem;">Nenhuma despesa registrada.</p>
                    <?php else: ?>
                        <canvas id="graficoRoscaFixo"></canvas>
                    <?php endif; ?>
                </div>
            </div>

            <!-- CAIXA DE MÉDIO/LONGO PRAZO COM BOTÃO ALTERAR (LADO DIREITO) -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <h2 style="margin-bottom: 0;">📈 Visão de Prazo</h2>
                    <button class="btn-alterar-periodo" onclick="toggleReportDrawer()">
                        <span>⚙️ Alterar</span>
                        <span id="drawerIcon">▼</span>
                    </button>
                </div>

                <!-- Gaveta retrátil de alteração de período -->
                <div class="report-drawer" id="reportDrawer">
                    <div style="font-size: 0.78rem; font-weight: 600; color: var(--text-muted); margin-bottom: 4px;">
                        Período:
                    </div>
                    <div class="filter-bar">
                        <a href="index.php?periodo=hoje" class="filter-btn <?= ($filtroPeriodo == 'hoje' && $qtdDiasPersonalizada == 0) ? 'active' : '' ?>">Hoje</a>
                        <a href="index.php?periodo=semana" class="filter-btn <?= ($filtroPeriodo == 'semana' && $qtdDiasPersonalizada == 0) ? 'active' : '' ?>">Semana</a>
                        <a href="index.php?periodo=7dias" class="filter-btn <?= ($filtroPeriodo == '7dias' && $qtdDiasPersonalizada == 0) ? 'active' : '' ?>">7 Dias</a>
                        <a href="index.php?periodo=30dias" class="filter-btn <?= ($filtroPeriodo == '30dias' && $qtdDiasPersonalizada == 0) ? 'active' : '' ?>">30 Dias</a>
                        <a href="index.php?periodo=tudo" class="filter-btn <?= ($filtroPeriodo == 'tudo' && $qtdDiasPersonalizada == 0) ? 'active' : '' ?>">Geral</a>
                    </div>
                    
                    <div style="font-size: 0.78rem; font-weight: 600; color: var(--text-muted); margin-top: 8px; margin-bottom: 4px;">
                        Dias Personalizados:
                    </div>
                    <form action="index.php" method="GET" class="custom-days-form">
                        <input type="number" name="dias" min="1" max="365" placeholder="Ex: 15" value="<?= $qtdDiasPersonalizada > 0 ? $qtdDiasPersonalizada : '' ?>" required>
                        <button type="submit" class="custom-days-btn">Ok</button>
                    </form>
                </div>

                <!-- Seletor do tipo de gráfico (Prédios, Linhas ou Rosca) para a direita -->
                <div class="chart-type-selector-box">
                    <button type="button" class="chart-type-option active" id="btnTipoBarra" onclick="mudarTipoLongoPrazo('bar')">🏙️ Prédios</button>
                    <button type="button" class="chart-type-option" id="btnTipoLinha" onclick="mudarTipoLongoPrazo('line')">📈 Linhas</button>
                    <button type="button" class="chart-type-option" id="btnTipoRosca" onclick="mudarTipoLongoPrazo('doughnut')">🍩 Rosca</button>
                </div>

                <!-- Exibição do Gráfico de Médio/Longo Prazo -->
                <div style="height: 125px; display: flex; justify-content: center; align-items: center;">
                    <?php if (empty($labelsLongoPrazo)): ?>
                        <p style="color: var(--text-muted); font-size: 0.8rem;">Sem dados para este período.</p>
                    <?php else: ?>
                        <canvas id="graficoLongoPrazo"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="card">
                <h2>Adicionar Nova Transação</h2>
                
                <form action="index.php?periodo=<?= $filtroPeriodo ?><?= $qtdDiasPersonalizada > 0 ? '&dias='.$qtdDiasPersonalizada : '' ?>" method="POST" id="formTransacao">
                    <input type="hidden" name="acao" value="salvar">
                    
                    <div class="tipo-selector">
                        <div id="btnDespesa" class="tipo-btn active-despesa" onclick="selecionarTipo('despesa')">Despesa</div>
                        <div id="btnReceita" class="tipo-btn" onclick="selecionarTipo('receita')">Receita</div>
                        <input type="hidden" name="tipo" id="tipoTransacao" value="despesa">
                    </div>

                    <div class="form-group"><input type="number" step="0.01" name="valor" placeholder="Valor (R$)" required></div>
                    <div class="form-group"><input type="date" name="data" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="form-group"><input type="text" name="descricao" placeholder="Descrição (Opcional - ex: Posto Shell)"></div>
                    
                    <div class="form-group">
                        <select name="categoria" id="selectCategoria" required onchange="checarNovaCategoria(this)">
                            <option value="">Selecione a Categoria</option>
                            <?php foreach ($listaCategorias as $catItem): ?>
                                <option value="<?= htmlspecialchars($catItem['nome']) ?>" data-tipo="<?= $catItem['tipo'] ?>">
                                    <?= $catItem['icone'] ?> <?= htmlspecialchars($catItem['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="CADASTRAR_NOVA" style="font-weight: bold; color: var(--accent-blue);">➕ Cadastrar Nova Categoria...</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-submit" style="background: var(--accent-green); color: #fff; margin-top:0;">Salvar Transação</button>
                </form>

                <div id="boxNovaCategoria" class="box-nova-categoria">
                    <form action="index.php?periodo=<?= $filtroPeriodo ?><?= $qtdDiasPersonalizada > 0 ? '&dias='.$qtdDiasPersonalizada : '' ?>" method="POST">
                        <input type="hidden" name="acao" value="cadastrar_categoria">
                        <input type="hidden" name="nova_categoria_tipo" id="inputTipoNovaCat" value="despesa">
                        <div style="font-size: 0.82rem; font-weight: 600; color: var(--accent-blue); margin-bottom: 6px;">✨ Criar Nova Categoria (<span id="txtTipoCatLabel">Despesa</span>):</div>
                        <div style="display: flex; gap: 8px;">
                            <div style="flex: 2;">
                                <input type="text" name="nova_categoria_nome" placeholder="Nome (Ex: Pet, Bônus...)" required style="width:100%; padding:10px; border-radius:6px; border:1px solid var(--border-color); background:var(--input-bg); color:var(--text-main); font-size:0.9rem;">
                            </div>
                            <div style="flex: 1;">
                                <select name="nova_categoria_icone" required style="width:100%; padding:10px; border-radius:6px; border:1px solid var(--border-color); background:var(--input-bg); color:var(--text-main); font-size:0.9rem;">
                                    <option value="🏷️">🏷️ Padrão</option>
                                    <option value="🍔">🍔 Lanche</option>
                                    <option value="🛒">🛒 Supermercado</option>
                                    <option value="🚗">🚗 Carro</option>
                                    <option value="🏠">🏠 Casa</option>
                                    <option value="💊">💊 Farmácia</option>
                                    <option value="📚">📚 Livros/Estudo</option>
                                    <option value="✈️">✈️ Viagem</option>
                                    <option value="💻">💻 Tecnologia</option>
                                    <option value="🏋️">🏋️ Academia</option>
                                    <option value="🐾">🐾 Pets</option>
                                    <option value="💡">💡 Contas/Luz</option>
                                    <option value="💰">💰 Dinheiro</option>
                                    <option value="📈">📈 Investimento</option>
                                    <option value="⭐">⭐ Estrela</option>
                                    <option value="🔥">🔥 Fogo</option>
                                    <option value="💎">💎 Diamante</option>
                                    <option value="🎁">🎁 Presente</option>
                                    <option value="⚡">⚡ Raio</option>
                                    <option value="🚀">🚀 Foguete</option>
                                </select>
                            </div>
                        </div>
                        <div style="display: flex; gap: 6px; margin-top: 8px;">
                            <button type="submit" style="background: var(--accent-blue); color: #fff; border: none; padding: 8px 12px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer;">Salvar Categoria</button>
                            <button type="button" onclick="cancelarNovaCategoria()" style="background: transparent; color: var(--text-muted); border: 1px solid var(--border-color); padding: 8px 10px; border-radius: 6px; font-size: 0.85rem; cursor: pointer;">Cancelar</button>
                        </div>
                    </form>
                </div>

            </div>

            <!-- MENSAGEM DIVERTIDA PÓS-TRANSAÇÃO ACIMA DOS LEMBRETES DE VENCIMENTOS (DIA, MÊS E ANO) -->
            <div class="card">
                <?php if($avisoTransacao): ?>
                    <div class="alert-divertido"><?= $avisoTransacao ?></div>
                <?php endif; ?>

                <h2>🔔 Lembretes de Vencimentos</h2>
                
                <form action="index.php?periodo=<?= $filtroPeriodo ?><?= $qtdDiasPersonalizada > 0 ? '&dias='.$qtdDiasPersonalizada : '' ?>" method="POST" style="margin-bottom: 15px;">
                    <input type="hidden" name="acao" value="cadastrar_compromisso">
                    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                        <input type="text" name="titulo_comp" placeholder="Conta (Ex: Luz / Aluguel)" required style="flex: 2; min-width: 130px; padding: 9px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-main); font-size: 0.85rem;">
                        <input type="date" name="data_vencimento" value="<?= date('Y-m-d') ?>" required style="flex: 1.5; min-width: 110px; padding: 9px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-main); font-size: 0.85rem;">
                        <input type="number" step="0.01" name="valor_comp" placeholder="R$ Est." style="flex: 1; min-width: 80px; padding: 9px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-main); font-size: 0.85rem;">
                        <button type="submit" style="background: var(--accent-blue); color: #fff; border: none; padding: 9px 12px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer; width: 100%; margin-top: 4px;">+ Adicionar Lembrete</button>
                    </div>
                </form>

                <div style="max-height: 160px; overflow-y: auto;">
                    <?php if (empty($listaCompromissos)): ?>
                        <p style="color: var(--text-muted); font-size: 0.8rem; text-align: center; padding: 10px;">Nenhum lembrete de pagamento cadastrado.</p>
                    <?php else: ?>
                        <?php foreach ($listaCompromissos as $comp): ?>
                            <div class="agenda-item">
                                <span>⏰ <strong><?= htmlspecialchars($comp['titulo']) ?></strong> (<?= !empty($comp['data_vencimento']) ? date('d/m/Y', strtotime($comp['data_vencimento'])) : 'Data não def.' ?>)</span>
                                <span style="color: var(--accent-red); font-weight: 600;"><?= $comp['valor_estimado'] > 0 ? formatarBRL($comp['valor_estimado']) : '' ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <div class="card" style="margin-top: 20px;">
            <h2>Últimas Transações</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Data</th>
                            <th>Descrição</th>
                            <th>Categoria</th>
                            <th>Valor (R$)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transacoes)): ?>
                            <tr><td colspan="5" style="text-align: center; color: var(--text-muted);">Nenhuma transação encontrada.</td></tr>
                        <?php else: ?>
                            <?php foreach ($transacoes as $t): ?>
                                <tr>
                                    <td><span class="<?= $t['tipo'] === 'receita' ? 'tipo-receita' : 'tipo-despesa' ?>"><?= $t['tipo'] === 'receita' ? '[R]' : '[D]' ?></span></td>
                                    <td><?= date('d/m/Y', strtotime($t['data'])) ?></td>
                                    <td><?= htmlspecialchars($t['descricao']) ?></td>
                                    <td><?= htmlspecialchars($t['categoria']) ?></td>
                                    <td class="<?= $t['tipo'] === 'receita' ? 'tipo-receita' : 'tipo-despesa' ?>"><?= ($t['tipo'] === 'receita' ? '+ ' : '- ') . formatarBRL($t['valor']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    function toggleReportDrawer() {
        const drawer = document.getElementById('reportDrawer');
        const icon = document.getElementById('drawerIcon');
        if (drawer.classList.contains('open')) {
            drawer.classList.remove('open');
            icon.innerHTML = '▼';
        } else {
            drawer.classList.add('open');
            icon.innerHTML = '▲';
        }
    }

    <?php if ($qtdDiasPersonalizada > 0): ?>
    document.addEventListener("DOMContentLoaded", function() {
        document.getElementById('reportDrawer').classList.add('open');
        document.getElementById('drawerIcon').innerHTML = '▲';
    });
    <?php endif; ?>

    function filtrarCategoriasPorTipo(tipo) {
        const select = document.getElementById('selectCategoria');
        if(!select) return;
        const options = select.options;
        select.value = ""; 
        
        for (let i = 0; i < options.length; i++) {
            let opt = options[i];
            if (opt.value === "" || opt.value === "CADASTRAR_NOVA") {
                opt.style.display = "block";
                continue;
            }
            let catTipo = opt.getAttribute('data-tipo');
            if (catTipo === tipo) {
                opt.style.display = "block";
            } else {
                opt.style.display = "none";
            }
        }
    }

    function selecionarTipo(tipo) {
        const btnDespesa = document.getElementById('btnDespesa');
        const btnReceita = document.getElementById('btnReceita');
        const inputTipo = document.getElementById('tipoTransacao');
        const inputTipoNovaCat = document.getElementById('inputTipoNovaCat');
        const txtTipoCatLabel = document.getElementById('txtTipoCatLabel');

        if (tipo === 'despesa') {
            btnDespesa.className = 'tipo-btn active-despesa';
            btnReceita.className = 'tipo-btn';
            inputTipo.value = 'despesa';
            inputTipoNovaCat.value = 'despesa';
            txtTipoCatLabel.innerText = 'Despesa';
        } else {
            btnReceita.className = 'tipo-btn active-receita';
            btnDespesa.className = 'tipo-btn';
            inputTipo.value = 'receita';
            inputTipoNovaCat.value = 'receita';
            txtTipoCatLabel.innerText = 'Receita';
        }
        filtrarCategoriasPorTipo(tipo);
        cancelarNovaCategoria();
    }

    function checarNovaCategoria(select) {
        const box = document.getElementById('boxNovaCategoria');
        if (select.value === 'CADASTRAR_NOVA') {
            box.style.display = 'block';
            select.value = '';
        } else {
            box.style.display = 'none';
        }
    }

    function cancelarNovaCategoria() {
        document.getElementById('boxNovaCategoria').style.display = 'none';
        document.getElementById('selectCategoria').value = '';
    }

    function toggleTheme() {
        const html = document.documentElement;
        const btn = document.getElementById('themeBtn');
        if (html.getAttribute('data-theme') === 'dark') {
            html.removeAttribute('data-theme');
            btn.innerHTML = '🌙 Modo Escuro';
            localStorage.setItem('theme', 'light');
        } else {
            html.setAttribute('data-theme', 'dark');
            btn.innerHTML = '☀️ Modo Claro';
            localStorage.setItem('theme', 'dark');
        }
    }

    document.addEventListener("DOMContentLoaded", function() {
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            document.getElementById('themeBtn').innerHTML = '☀️ Modo Claro';
        } else {
            document.documentElement.removeAttribute('data-theme');
            document.getElementById('themeBtn').innerHTML = '🌙 Modo Escuro';
        }
        filtrarCategoriasPorTipo('despesa');
    });
</script>

<?php if ($usuarioLogado && !empty($labelsRoscaFixo)): ?>
<script>
    const ctxRosca = document.getElementById('graficoRoscaFixo').getContext('2d');
    new Chart(ctxRosca, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($labelsRoscaFixo) ?>,
            datasets: [{
                data: <?= json_encode($valoresRoscaFixo) ?>,
                backgroundColor: ['#3498db', '#2ecc71', '#e74c3c', '#f1c40f', '#9b59b6', '#1abc9c', '#e67e22'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'right', labels: { boxWidth: 8, font: { size: 9 } } } }
        }
    });
</script>
<?php endif; ?>

<?php if ($usuarioLogado && !empty($labelsLongoPrazo)): ?>
<script>
    const labelsLP = <?= json_encode($labelsLongoPrazo) ?>;
    const valoresLP = <?= json_encode($valoresLongoPrazo) ?>;
    let graficoLongoPrazoInstancia = null;

    function renderizarLongoPrazo(tipoGrafico) {
        const ctxLP = document.getElementById('graficoLongoPrazo').getContext('2d');
        if (graficoLongoPrazoInstancia) {
            graficoLongoPrazoInstancia.destroy();
        }

        let config = {};
        if (tipoGrafico === 'bar') {
            config = {
                type: 'bar',
                data: {
                    labels: labelsLP,
                    datasets: [{
                        label: 'Gastos por Período',
                        data: valoresLP,
                        backgroundColor: '#3498db',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { font: { size: 8 } } },
                        y: { ticks: { font: { size: 8 } } }
                    }
                }
            };
        } else if (tipoGrafico === 'line') {
            config = {
                type: 'line',
                data: {
                    labels: labelsLP,
                    datasets: [{
                        label: 'Tendência',
                        data: valoresLP,
                        borderColor: '#e74c3c',
                        backgroundColor: 'rgba(231, 76, 60, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { font: { size: 8 } } },
                        y: { ticks: { font: { size: 8 } } }
                    }
                }
            };
        } else if (tipoGrafico === 'doughnut') {
            config = {
                type: 'doughnut',
                data: {
                    labels: labelsLP,
                    datasets: [{
                        data: valoresLP,
                        backgroundColor: ['#3498db', '#2ecc71', '#e74c3c', '#f1c40f', '#9b59b6', '#1abc9c'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'right', labels: { boxWidth: 6, font: { size: 8 } } } }
                }
            };
        }

        graficoLongoPrazoInstancia = new Chart(ctxLP, config);
    }

    function mudarTipoLongoPrazo(tipo) {
        document.getElementById('btnTipoBarra').className = 'chart-type-option';
        document.getElementById('btnTipoLinha').className = 'chart-type-option';
        document.getElementById('btnTipoRosca').className = 'chart-type-option';

        if (tipo === 'bar') {
            document.getElementById('btnTipoBarra').className = 'chart-type-option active';
        } else if (tipo === 'line') {
            document.getElementById('btnTipoLinha').className = 'chart-type-option active';
        } else if (tipo === 'doughnut') {
            document.getElementById('btnTipoRosca').className = 'chart-type-option active';
        }

        renderizarLongoPrazo(tipo);
        localStorage.setItem('preferencia_grafico_direito', tipo);
    }

    document.addEventListener("DOMContentLoaded", function() {
        let salvo = localStorage.getItem('preferencia_grafico_direito') || 'bar';
        mudarTipoLongoPrazo(salvo);
    });
</script>
<?php endif; ?>
</body>
</html>