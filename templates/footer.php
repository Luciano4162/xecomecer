<?php
// templates/footer.php

// Garante que a conexão exista ou a cria
if (!isset($pdo)) {
    require_once __DIR__ . '/../config/db.php';
}

// --- Busca Dados do Footer ---
$footer_data = [
    'telefone' => '(XX) XXXXX-XXXX',
    'email' => 'email@dominio.com',
    'horario' => 'Seg. a Sex. das Xh às Yh',
    'institucional_links' => [], // Será populado pela nova tabela
    'pagamento_prazo_icons' => [],
    'pagamento_vista_icons' => [],
    'seguranca_icons' => [],
    'social_icons' => [],
    'credits' => 'Nome da Loja, CNPJ..., Endereço..., CEP...'
];

// --- Busca Configs de Borda ---
$footer_configs_padrao = [
    'footer_borda_superior_ativa' => 'true',
    'footer_credits_borda_ativa' => 'true',
    'cor_borda_media' => '#ccc' // Fallback da cor da borda
];
$footer_configs = [];


try {
    // Busca configs gerais
    $stmt_configs = $pdo->query("SELECT chave, valor FROM config_site
        WHERE chave IN (
            'telefone_contato', 'email_contato', 'horario_atendimento', 'footer_credits',
            'footer_borda_superior_ativa', 'footer_credits_borda_ativa', 'cor_borda_media'
        )");

    $configs = $stmt_configs->fetchAll(PDO::FETCH_KEY_PAIR);

    // Mescla padrões com o DB
    $footer_configs = array_merge($footer_configs_padrao, $configs);

    // Popula os dados do footer
    $footer_data['telefone'] = $configs['telefone_contato'] ?? $footer_data['telefone'];
    $footer_data['email']    = $configs['email_contato'] ?? $footer_data['email'];
    $footer_data['horario']  = $configs['horario_atendimento'] ?? $footer_data['horario'];
    $footer_data['credits']  = $configs['footer_credits'] ?? $footer_data['credits'];

    // ==========================================================
    // ▼▼▼ MODIFICADO (Consulta agora usa 'footer_links') ▼▼▼
    // ==========================================================
    $stmt_links = $pdo->query("
        SELECT texto, url
        FROM footer_links
        WHERE coluna = 'institucional' AND ativo = true
        ORDER BY ordem ASC
    ");

    // Busca todos os links (texto, url) de uma vez
    $footer_data['institucional_links'] = $stmt_links->fetchAll(PDO::FETCH_ASSOC);
    // ==========================================================
    // ▲▲▲ FIM DA MODIFICAÇÃO ▲▲▲
    // ==========================================================


    // Busca ícones de pagamento e segurança ativos
    $stmt_icons = $pdo->query("SELECT imagem_url, alt_text, link_url, coluna FROM footer_icons WHERE ativo = true ORDER BY coluna, ordem ASC");
    while ($icon = $stmt_icons->fetch()) {
        if (isset($footer_data[$icon['coluna'] . '_icons'])) {
            $footer_data[$icon['coluna'] . '_icons'][] = $icon;
        }
    }

    // Busca Ícones Sociais
    $stmt_social = $pdo->query("SELECT link_url, svg_code FROM social_icons WHERE ativo = true ORDER BY ordem ASC");
    $footer_data['social_icons'] = $stmt_social->fetchAll();

} catch (PDOException $e) {
    error_log("Erro ao buscar dados do footer em footer.php: " . $e->getMessage());
    $footer_configs = $footer_configs_padrao;
}

// --- Processar as Bordas Dinâmicas ---
$cor_borda_db = htmlspecialchars($footer_configs['cor_borda_media']);

// Borda do Footer Principal
$footer_border_style = 'none';
if (filter_var($footer_configs['footer_borda_superior_ativa'], FILTER_VALIDATE_BOOLEAN)) {
    $footer_border_style = '1px solid ' . $cor_borda_db;
}

// Borda dos Créditos
$credits_border_style = 'none';
if (filter_var($footer_configs['footer_credits_borda_ativa'], FILTER_VALIDATE_BOOLEAN)) {
    $credits_border_style = '1px solid ' . $cor_borda_db;
}
?>

<style>
    /* ... (Todo o seu CSS do footer permanece o mesmo) ... */

    /* --- Estilos do Footer --- */
    .newsletter-section {
        background-color: var(--header-footer-bg);
        padding: 40px 0;
        text-align: center;
        flex-shrink: 0;
    }
    .newsletter-section h3 {
        margin-top: 0;
        margin-bottom: 10px;
        font-size: 1.2em;
        color: var(--text-color-dark);
        font-weight: bold;
        text-transform: uppercase;
    }
    .newsletter-section p {
        font-size: 0.9em;
        color: var(--text-color-medium);
        margin-bottom: 20px;
    }
    .newsletter-form {
        max-width: 450px;
        margin: 0 auto;
        position: relative;
    }
    .newsletter-form input[type="email"] {
        width: 100%;
        padding: 12px 50px 12px 20px;
        border: 1px solid var(--border-color-medium);
        border-radius: 25px;
        box-sizing: border-box;
        font-size: 0.9em;
    }
    .newsletter-form button {
        position: absolute;
        right: 5px;
        top: 50%;
        transform: translateY(-50%);
        background-color: var(--green-accent);
        color: white;
        border: none;
        border-radius: 20px;
        padding: 8px 15px;
        font-size: 0.9em;
        font-weight: bold;
    }

    .main-footer {
        background-color: var(--header-footer-bg);
        padding: 50px 0 30px 0;
        color: var(--text-color-medium);
        font-size: 0.85em;
        flex-shrink: 0;
        border-top: <?php echo $footer_border_style; ?>;
    }
    .footer-columns {
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 30px;
        margin-bottom: 40px;
    }
    .footer-column { flex: 1; min-width: 180px; }
    .footer-column h4 {
        font-size: 0.9em;
        color: var(--text-color-dark);
        margin-top: 0;
        margin-bottom: 15px;
        text-transform: uppercase;
        font-weight: bold;
    }
    .footer-column ul { list-style: none; padding: 0; margin: 0; }
    .footer-column ul li { margin-bottom: 8px; }
    .footer-column ul li a { color: var(--text-color-medium); font-size: 0.9em; }
    .footer-column ul li a:hover { color: var(--green-accent); }
    .footer-column p {
        font-size: 0.9em;
        line-height: 1.5;
        margin: 5px 0;
        display: flex;
        align-items: center;
        color: var(--text-color-medium);
    }
    .footer-column p i svg { fill: var(--text-color-medium); }
    .footer-column p a { color: var(--text-color-medium); }
    .footer-column p a:hover { color: var(--green-accent); }
    .footer-icons img {
        max-height: 34px;
        margin-right: 8px;
        margin-bottom: 8px;
        vertical-align: middle;
    }
    .footer-column-seguranca .footer-icons { display: flex; flex-direction: column; align-items: flex-start; gap: 10px; }
    .footer-column-seguranca .footer-icons a { display: inline-block; }
    .footer-column-seguranca .footer-icons img { max-height: 35px; max-width: 120px; margin-right: 0; margin-bottom: 0; }

    .social-icons { text-align: left; padding: 20px 0; }
    .social-icons a {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 10px;
        color: #555;
        width: 32px;
        height: 32px;
        background-color: transparent;
        border-radius: 0;
        transition: color 0.2s ease;
    }
    .social-icons a:hover { color: var(--green-accent); }
    .social-icons a svg { width: 100%; height: 100%; fill: currentColor; }
    .social-icons img { display: none; }

    .footer-credits {
        border-top: <?php echo $credits_border_style; ?>;
        padding-top: 20px;
        font-size: 0.75em;
        color: #888;
        text-align: center;
    }
    .footer-credits p strong { color: var(--text-color-medium); }

    /* CSS Responsivo do Footer */
    @media (max-width: 768px) {
        .footer-columns { flex-direction: column; align-items: stretch; gap: 0; margin-bottom: 0; }
        .footer-column { min-width: 100%; align-items: stretch; text-align: left; margin-bottom: 0; border-bottom: 1px solid var(--border-color-medium); }
        .footer-column h4 {
            font-size: 0.9em;
            color: var(--text-color-dark);
            margin: 0;
            padding: 18px 15px;
            text-transform: uppercase;
            font-weight: normal;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .footer-column h4::after {
            content: '';
            display: inline-block;
            width: 8px;
            height: 8px;
            border-right: 2px solid #888;
            border-bottom: 2px solid #888;
            transform: rotate(45deg);
            transition: transform 0.10s ease;
        }
        .footer-column.mobile-open h4::after { transform: rotate(225deg); }
        .footer-accordion-content {
            background-color: var(--header-footer-bg);
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.2s ease-in-out;
            padding-left: 15px;
            padding-right: 15px;
        }
        .footer-column.mobile-open .footer-accordion-content {
            max-height: 600px;
            padding-top: 10px;
            padding-bottom: 15px;
        }
        .footer-accordion-content p, .footer-accordion-content ul { text-align: left; margin-left: 0; margin-right: 0; }
        .footer-accordion-content ul { list-style: none; padding: 0; margin: 0; }
        .footer-accordion-content ul li { margin-bottom: 8px; }
        .footer-accordion-content ul li:last-child { margin-bottom: 0; }
        .footer-accordion-content ul li a { padding: 3px 0; font-size: 0.9em; }
        .footer-accordion-content .footer-icons { text-align: left; }
        .footer-accordion-content .footer-column-seguranca .footer-icons { align-items: flex-start; }

        .social-icons {
            display: flex !important;
            justify-content: center;
            align-items: center;
            gap: 15px;
            padding: 20px 0;
            width: 100%;
        }
        .social-icons a {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background-color: #8a8a8a;
            color: white;
            margin-right: 0;
        }
        .social-icons a svg { width: 24px; height: 24px; fill: white; }
        .footer-credits {
            text-align: center;
            width: 100%;
            padding-top: 20px;
            margin-top: 0;
        }
    }
</style>

<section class="newsletter-section">
    <div class="container">
        <h3>Cadastre-se e receba ofertas com preços exclusivos</h3>
        <p>Seja sempre o primeiro a receber nossas novidades, cadastre-se, é grátis!</p>
        <form action="#" method="post" class="newsletter-form">
            <input type="email" name="email" placeholder="Digite seu e-mail" required>
            <button type="submit">OK ></button>
        </form>
    </div>
</section>

<footer class="main-footer">
    <div class="container">
        <div class="footer-columns">
            <div class="footer-column">
                <h4>Institucional</h4>
                <ul>
                    <?php foreach ($footer_data['institucional_links'] as $link): ?>
                    <li><a href="<?php echo htmlspecialchars($link['url']); ?>"><?php echo htmlspecialchars($link['texto']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="footer-column">
                <h4>Fale Conosco</h4>
                <p>
                    <i class="app__icon app__footer__contact__phone-icon" style="width: 14px; height: 14px; fill: #666; vertical-align: middle; margin-right: 5px;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"> <path d="M164.9 24.6c-7.7-18.6-28-28.5-47.4-23.2l-88 24C12.1 30.2 0 46 0 64C0 311.4 200.6 512 448 512c18 0 33.8-12.1 38.6-29.5l24-88c5.3-19.4-4.6-39.7-23.2-47.4l-96-40c-16.3-6.8-35.2-2.1-46.3 11.6L304.7 368C234.3 334.7 177.3 277.7 144 207.3L193.3 167c13.7-11.2 18.4-30 11.6-46.3l-40-96z"/> </svg>
                    </i>
                    <?php echo htmlspecialchars($footer_data['telefone']); ?>
                </p>
                <p>
                    <a class="app__footer__contact__email" href="mailto:<?php echo htmlspecialchars($footer_data['email']); ?>" style="display: inline-flex; align-items: center;">
                        <i class="app__icon" style="width: 14px; height: 14px; fill: #666; vertical-align: middle; margin-right: 5px;"> <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"> <path d="M48 64C21.5 64 0 85.5 0 112c0 15.1 7.1 29.3 19.2 38.4L236.8 313.6c11.4 8.5 27 8.5 38.4 0L492.8 150.4c12.1-9.1 19.2-23.3 19.2-38.4c0-26.5-21.5-48-48-48H48zM0 176V384c0 35.3 28.7 64 64 64H448c35.3 0 64-28.7 64-64V176L294.4 339.2c-22.8 17.1-54 17.1-76.8 0L0 176z"/> </svg>
                        </i>
                        <?php echo htmlspecialchars($footer_data['email']); ?>
                    </a>
                </p>
                <p><?php echo htmlspecialchars($footer_data['horario']); ?></p>
            </div>

            <div class="footer-column">
                <h4>Pagamento</h4>
                <p><strong>Pagamento à prazo</strong></p>
                <div class="footer-icons">
                     <?php foreach ($footer_data['pagamento_prazo_icons'] as $icon): ?>
                         <img src="<?php echo htmlspecialchars($icon['imagem_url']); ?>"
                              alt="<?php echo htmlspecialchars($icon['alt_text']); ?>"
                              title="<?php echo htmlspecialchars($icon['alt_text']); ?>">
                     <?php endforeach; ?>
                </div>
                 <p><strong>Pagamento à vista</strong></p>
                 <div class="footer-icons">
                     <?php foreach ($footer_data['pagamento_vista_icons'] as $icon): ?>
                         <img src="<?php echo htmlspecialchars($icon['imagem_url']); ?>"
                              alt="<?php echo htmlspecialchars($icon['alt_text']); ?>"
                              title="<?php echo htmlspecialchars($icon['alt_text']); ?>">
                     <?php endforeach; ?>
                 </div>
            </div>

             <div class="footer-column footer-column-seguranca">
                  <h4>Compra Segura</h4>
                   <div class="footer-icons">
                       <?php foreach ($footer_data['seguranca_icons'] as $icon): ?>
                           <a href="<?php echo htmlspecialchars($icon['link_url']); ?>" target="_blank" title="<?php echo htmlspecialchars($icon['alt_text']); ?>">
                               <img src="<?php echo htmlspecialchars($icon['imagem_url']); ?>" alt="<?php echo htmlspecialchars($icon['alt_text']); ?>">
                           </a>
                       <?php endforeach; ?>
                   </div>
             </div>
        </div>

        <div class="social-icons">
            <?php foreach ($footer_data['social_icons'] as $social): ?>
                <a href="<?php echo htmlspecialchars($social['link_url']); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo $social['svg_code']; // Renderiza o SVG_CODE diretamente ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="footer-credits">
             <p><strong><?php echo nl2br(htmlspecialchars($footer_data['credits'])); ?></strong></p>
        </div>
    </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- LÓGICA FOOTER ACCORDION MOBILE ---
    const footerColumns = document.querySelectorAll('.footer-columns .footer-column');
    footerColumns.forEach(column => {
        // Verifica se já não foi processado (caso o script rode 2x)
        if (column.querySelector('.footer-accordion-content')) { return; }

        const title = column.querySelector('h4');
        if (!title) return;

        const contentWrapper = document.createElement('div');
        contentWrapper.className = 'footer-accordion-content';

        // Pega todos os filhos que NÃO são o h4
        const childrenToWrap = Array.from(column.children).filter(child => child !== title);

        if (childrenToWrap.length > 0) {
            childrenToWrap.forEach(child => contentWrapper.appendChild(child));
            column.appendChild(contentWrapper);

            // Adiciona o clique apenas se houver conteúdo
            title.addEventListener('click', () => {
                if (window.innerWidth <= 768) { // Só ativa em mobile
                    column.classList.toggle('mobile-open');

                    // Ajusta o max-height para a animação
                    if (column.classList.contains('mobile-open')) {
                        contentWrapper.style.maxHeight = contentWrapper.scrollHeight + "px";
                    } else {
                        contentWrapper.style.maxHeight = null;
                    }
                }
            });
        } else {
            // Se não tem conteúdo (ex: só o H4), não faz nada
            title.style.cursor = 'default';
        }
    });
});
</script>