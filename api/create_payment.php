<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db.php';

header('Content-Type: application/json');

// Verifica login
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    echo json_encode(['error' => 'Usuário não autenticado.']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Verifica carrinho
if (empty($_SESSION['cart'])) {
    echo json_encode(['error' => 'Carrinho vazio.']);
    exit;
}

// Função para converter vírgula -> ponto
function toDecimal($value) {
    return floatval(str_replace(',', '.', $value));
}

// ---------------------------
// 1. Pega items do carrinho
// ---------------------------
$cart_product_ids = [];
foreach (array_keys($_SESSION['cart']) as $key) {
    $parts = explode('_', $key);
    $cart_product_ids[] = (int)$parts[0];
}

$placeholders = implode(',', array_fill(0, count($cart_product_ids), '?'));
$stmt = $pdo->prepare("SELECT id, nome, preco, estoque FROM produtos WHERE id IN ($placeholders)");
$stmt->execute($cart_product_ids);
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$subtotal = 0;
$itens_para_inserir = [];

// ---------------------------
// 2. Monta cálculo seguro
// ---------------------------
foreach ($_SESSION['cart'] as $key => $qtd) {

    $parts = explode('_', $key);
    $id_limpo = (int)$parts[0];
    $tamanho = $parts[1] ?? null;

    foreach ($produtos as $p) {
        if ($p['id'] == $id_limpo) {

            // Converte preço de "12,90" → 12.90
            $precoConvertido = toDecimal($p['preco']);

            $line_total = $precoConvertido * $qtd;
            $subtotal += $line_total;

            $itens_para_inserir[] = [
                'produto_id' => $p['id'],
                'quantidade' => $qtd,
                'preco_unitario' => $precoConvertido,
                'tamanho' => $tamanho
            ];
        }
    }
}

$frete_valor = 0;
$total = $subtotal + $frete_valor;

// ---------------------------
// 3. Cria pedido com status PENDENTE
// ---------------------------
$stmt = $pdo->prepare("
    INSERT INTO pedidos (usuario_id, valor_total, valor_subtotal, valor_frete, status, criado_em)
    VALUES (:u, :t, :s, :f, 'PENDENTE', NOW())
    RETURNING id
");

$stmt->execute([
    'u' => $user_id,
    't' => $total,
    's' => $subtotal,
    'f' => $frete_valor
]);

$pedido_id = $stmt->fetchColumn();

// ---------------------------
// 4. Insere itens do pedido
// ---------------------------
$stmtItem = $pdo->prepare("
    INSERT INTO pedidos_itens (pedido_id, produto_id, quantidade, preco_unitario)
    VALUES (:p, :prod, :qtd, :preco)
");

foreach ($itens_para_inserir as $it) {
    $stmtItem->execute([
        'p' => $pedido_id,
        'prod' => $it['produto_id'],
        'qtd' => $it['quantidade'],
        'preco' => $it['preco_unitario']
    ]);
}

// ---------------------------
// 5. CHAMADA AO PIXUP
// ---------------------------
$headers = [
    "Content-Type: application/json",
    "Accept: application/json",
    "X-API-Key: SUA_CHAVE_AQUI"
];

$payload = [
    "amount" => $total,
    "description" => "Pedido #$pedido_id"
];

$ch = curl_init("https://api.pixup.com/v1/create-payment");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$result = curl_exec($ch);
curl_close($ch);

$response = json_decode($result, true);

if (!$response || !isset($response['transactionId'])) {
    echo json_encode(['error' => 'Erro ao gerar PIX.']);
    exit;
}

// ---------------------------
// 6. CORRIGE transactionId (caso venha como texto)
// ---------------------------
$txid_original = $response['transactionId'];

// Remove tudo que não for número (Postgres NÃO aceita texto em campo integer)
$txid_limpo = preg_replace('/\D/', '', $txid_original);
if ($txid_limpo === '') {
    $txid_limpo = null; // Evita SQLSTATE 22P02
}

// PIX copia e cola
$pix_code = $response['pixCode'] ?? null;

// ---------------------------
// 7. Salva dados PIX no pedido
// ---------------------------
$stmtPix = $pdo->prepare("
    UPDATE pedidos
    SET pix_code = :pix,
        pix_txid = :txid
    WHERE id = :id
");

$stmtPix->execute([
    'pix' => $pix_code,
    'txid' => $txid_limpo,
    'id' => $pedido_id
]);

// ---------------------------
// 8. Retorna tudo para o front
// ---------------------------
echo json_encode([
    'success' => true,
    'pedido_id' => $pedido_id,
    'pix_code' => $pix_code,
    'txid' => $txid_limpo
]);
exit;

?>