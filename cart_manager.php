<?php
// cart_manager.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db.php'; // Garante $pdo

// Inicializa o carrinho se não existir
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = []; 
}

$action = $_GET['action'] ?? '';
$response = ['status' => 'error', 'message' => 'Ação inválida'];

try {

    // ➤ AÇÃO: ADICIONAR ITEM AO CARRINHO
    if ($action == 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {

        $data = json_decode(file_get_contents('php://input'), true);

        $produto_id = (int)($data['produto_id'] ?? 0);
        $quantidade = (int)($data['quantidade'] ?? 1);
        $tamanho = trim($data['tamanho'] ?? '');

        if ($produto_id > 0 && $quantidade > 0) {

            // chave única respeitando tamanho
            $chaveItem = $produto_id . ($tamanho !== '' ? "_$tamanho" : '');

            if (isset($_SESSION['cart'][$chaveItem])) {
                $_SESSION['cart'][$chaveItem]['quantidade'] += $quantidade;
            } else {
                $_SESSION['cart'][$chaveItem] = [
                    'produto_id' => $produto_id,
                    'quantidade' => $quantidade,
                    'tamanho'   => $tamanho
                ];
            }

            $response = [
                'status' => 'success',
                'message' => 'Produto adicionado ao carrinho!'
            ];
        } else {
            $response['message'] = 'Dados do produto inválidos.';
        }
    }


    // ➤ AÇÃO: REMOVER UM ITEM
    if ($action == 'remove' && $_SERVER['REQUEST_METHOD'] === 'POST') {

        $data = json_decode(file_get_contents('php://input'), true);
        $key = $data['produto_key'] ?? '';

        if ($key !== '' && isset($_SESSION['cart'][$key])) {
            unset($_SESSION['cart'][$key]);
            $response = ['status' => 'success', 'message' => 'Item removido.'];
        } else {
            $response['message'] = 'Item não encontrado no carrinho.';
        }
    }


    // ➤ AÇÃO: LIMPAR SACOLA
    if ($action == 'clear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $_SESSION['cart'] = [];
        $response = ['status' => 'success', 'message' => 'Sacola esvaziada.'];
    }


    // ➤ AÇÃO: CONTAR ITENS DO CARRINHO
    if ($action == 'get_cart_count') {

        $total_itens = 0;

        foreach ($_SESSION['cart'] as $item) {
            $total_itens += $item['quantidade'];
        }

        $response = [
            'status' => 'success',
            'item_count' => $total_itens,
            'item_text' => $total_itens . ' ' . ($total_itens == 1 ? 'Item' : 'Itens')
        ];
    }


    // ➤ AÇÃO: GERAR HTML DO MODAL DA SACOLA
    if ($action == 'get_cart_html') {

        if (empty($_SESSION['cart'])) {
            $html = '
            <div class="cart-empty">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>
                <p>Sacola vazia</p>
            </div>';

            $response = ['status' => 'success', 'html' => $html];

        } else {

            // BUSCAR IDS REAIS
            $ids_limpos = [];

            foreach ($_SESSION['cart'] as $item) {
                $ids_limpos[] = $item['produto_id'];
            }

            $ids_limpos = array_unique($ids_limpos);

            // Monta SQL
            $placeholders = implode(',', array_fill(0, count($ids_limpos), '?'));
            $stmt = $pdo->prepare("
                SELECT id, nome, preco, imagem_url
                FROM produtos 
                WHERE id IN ($placeholders)
            ");

            $stmt->execute(array_values($ids_limpos));
            $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Mapa por ID
            $prod_por_id = [];
            foreach ($produtos as $p) {
                $prod_por_id[$p['id']] = $p;
            }

            // Construir HTML
            $html = '<div class="cart-items-list">';
            $subtotal = 0;

            foreach ($_SESSION['cart'] as $key => $item) {

                $produto_id = $item['produto_id'];
                if (!isset($prod_por_id[$produto_id])) continue;

                $prod = $prod_por_id[$produto_id];

                $total_item = $prod['preco'] * $item['quantidade'];
                $subtotal += $total_item;

                $tamanho_html = $item['tamanho'] ? "<p>Tamanho: {$item['tamanho']}</p>" : '';

                $html .= '
                <div class="cart-item">
                    <img src="'.htmlspecialchars($prod['imagem_url'] ?? 'uploads/placeholder.png').'" class="cart-item-img">

                    <div class="cart-item-info">
                        <p class="cart-item-name">'.htmlspecialchars($prod['nome']).'</p>
                        '.$tamanho_html.'
                        <p class="cart-item-qty">Qtd: '.$item['quantidade'].'</p>
                        <p class="cart-item-price">R$ '.number_format($total_item, 2, ',', '.').'</p>
                    </div>

                    <button class="cart-item-remove" data-key="'.$key.'" title="Remover item">&times;</button>
                </div>';
            }

            $html .= '</div>';

            // SUBTOTAL
            $html .= '
            <div class="cart-summary">
                <div class="cart-subtotal-display">
                    <strong>Subtotal:</strong>
                    <strong>R$ '.number_format($subtotal, 2, ',', '.').'</strong>
                </div>
                <a href="#" id="clear-cart-btn" class="cart-clear-btn">Esvaziar Sacola</a>
            </div>';

            $response = ['status' => 'success', 'html' => $html];
        }
    }

} catch (PDOException $e) {
    error_log("Cart Manager Error: " . $e->getMessage());
    $response = ['status' => 'error', 'message' => 'Erro de banco de dados.'];
}

header('Content-Type: application/json');
echo json_encode($response);
exit;