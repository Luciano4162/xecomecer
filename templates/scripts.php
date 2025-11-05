<?php
// templates/scripts.php
// Este arquivo deve ser incluído no FINAL do <body>, APÓS o JQuery (se usado).
?>
<script>
    // ==========================================================
    // JS GLOBAL (HEADER, CARRINHO, MODAIS)
    // ==========================================================

    // --- FUNÇÕES GLOBAIS DO CARRINHO ---
    const cartModal = document.getElementById('cart-modal');
    const cartOverlay = document.getElementById('cart-overlay');
    const openCart = () => {
        if (cartModal) cartModal.classList.add('open');
        if (cartOverlay) cartOverlay.classList.add('open');
    };
    const closeCart = () => {
        if (cartModal) cartModal.classList.remove('open');
        if (cartOverlay) cartOverlay.classList.remove('open');
    };

    /**
     * Atualiza o HTML da sacola e a contagem de itens.
     */
    async function updateCart() {
        try {
            // 1. Busca o HTML do corpo da sacola
            const responseHtml = await fetch('cart_manager.php?action=get_cart_html');
            const dataHtml = await responseHtml.json();
            if (dataHtml.status === 'success') {
                const cartBody = document.getElementById('cart-body-content');
                if (cartBody) { cartBody.innerHTML = dataHtml.html; }
            }

            // 2. Busca a contagem de itens
            const responseCount = await fetch('cart_manager.php?action=get_cart_count');
            const dataCount = await responseCount.json();
            if (dataCount.status === 'success') {
                // Atualiza o contador no header
                const cartCountSpan = document.getElementById('cart-item-count-header');
                if(cartCountSpan) { cartCountSpan.textContent = dataCount.item_text; }
            }
        } catch (error) { console.error("Erro ao atualizar a sacola:", error); }
    }

    /**
     * Remove um item da sacola via AJAX.
     */
    async function removeItemFromCart(produtoId) {
        try {
            const response = await fetch('cart_manager.php?action=remove', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ produto_id: produtoId })
            });
            const data = await response.json();
            if (data.status === 'success') { await updateCart(); }
        } catch (error) { console.error('Erro ao remover item:', error); }
    }

    /**
     * Limpa todos os itens da sacola via AJAX.
     */
    async function clearCart() {
        if (!confirm('Tem certeza que deseja esvaziar sua sacola?')) { return; }
        try {
            const response = await fetch('cart_manager.php?action=clear', { method: 'POST' });
            const data = await response.json();
            if (data.status === 'success') { await updateCart(); }
        } catch (error) { console.error('Erro ao limpar sacola:', error); }
    }

    // --- Funções de Incrementar/Decrementar ---

    /**
     * Adiciona 1 item (chama a ação 'add' existente)
     */
    async function incrementCartItem(produtoId) {
        try {
            const response = await fetch('cart_manager.php?action=add', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ produto_id: produtoId, quantidade: 1 })
            });
            const data = await response.json();
            if (data.status === 'success') {
                await updateCart(); // Espera o carrinho atualizar
            } else {
                alert(data.message || 'Erro ao adicionar item.');
            }
        } catch (error) { console.error('Erro ao incrementar item:', error); }
    }

    /**
     * Remove 1 item (chama a nova ação 'decrease_one')
     */
    async function decrementCartItem(produtoId) {
          try {
            const response = await fetch('cart_manager.php?action=decrease_one', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ produto_id: produtoId })
            });
            const data = await response.json();
            if (data.status === 'success') {
                await updateCart(); // Espera o carrinho atualizar
            } else {
                alert(data.message || 'Erro ao diminuir item.');
            }
        } catch (error) { console.error('Erro ao decrementar item:', error); }
    }


    // --- SCRIPT CENTRAL DOMContentLoaded (Vanilla JS) ---
    document.addEventListener('DOMContentLoaded', function() {

        // --- Lógica do Header (Menu Mobile) ---
        const hamburgerBtn = document.getElementById('hamburger-btn');
        const closeBtn = document.getElementById('close-btn');
        const navMenu = document.getElementById('nav-menu');

        if (hamburgerBtn && navMenu) { hamburgerBtn.addEventListener('click', function() { navMenu.classList.add('nav-open'); }); }
        if (closeBtn && navMenu) { closeBtn.addEventListener('click', function() { navMenu.classList.remove('nav-open'); }); }
        if(navMenu){
             document.addEventListener('click', function(event) {
                 const isClickInsideMenu = navMenu.contains(event.target);
                 const isClickOnHamburger = hamburgerBtn ? hamburgerBtn.contains(event.target) : false;
                 if (navMenu.classList.contains('nav-open') && !isClickInsideMenu && !isClickOnHamburger) {
                     navMenu.classList.remove('nav-open');
                 }
             });

             const menuItemsWithChildrenMobile = navMenu.querySelectorAll('.has-children');
             menuItemsWithChildrenMobile.forEach(function(item) {
                 const mainLink = item.querySelector('a:first-child');
                 if(mainLink){
                     mainLink.addEventListener('click', function(event) {
                         if (window.innerWidth <= 768 && item.classList.contains('has-children')) {
                             event.preventDefault();
                         }
                         if (navMenu.classList.contains('nav-open')) {
                             item.classList.toggle('item-open');
                         }
                     });
                 }
             });
        }

        // --- Lógica do Header (Dropdown Minha Conta Desktop) ---
        const accountTrigger = document.getElementById('minha-conta');
        const accountDropdown = document.getElementById('account-dropdown-content');
        let hideAccountTimer;

        if (accountTrigger && accountDropdown) {
             const showDropdown = () => { clearTimeout(hideAccountTimer); accountDropdown.classList.add('visible'); accountTrigger.classList.add('visible'); };
             const startHideTimer = () => { clearTimeout(hideAccountTimer); hideAccountTimer = setTimeout(() => { accountDropdown.classList.remove('visible'); accountTrigger.classList.remove('visible'); }, 150); };
             accountTrigger.addEventListener('mouseenter', showDropdown);
             accountDropdown.addEventListener('mouseenter', showDropdown);
             accountTrigger.addEventListener('mouseleave', startHideTimer);
             accountDropdown.addEventListener('mouseleave', startHideTimer);
        }

        // --- Lógica do Header (Dropdown Categorias Desktop) ---
        const categoryNav = document.querySelector('.header-nav');
        if (categoryNav) {
             const categoryItemsWithChildren = categoryNav.querySelectorAll('ul > li.has-children');
             categoryItemsWithChildren.forEach(item => {
                 const subMenu = item.querySelector('.sub-menu');
                 let hideCategoryTimer;
                 if (subMenu) {
                     const showCategoryDropdown = () => { clearTimeout(hideCategoryTimer); item.classList.add('visible'); };
                     const startCategoryHideTimer = () => {
                         clearTimeout(hideCategoryTimer);
                         hideCategoryTimer = setTimeout(() => { item.classList.remove('visible'); }, 150);
                     };
                     item.addEventListener('mouseenter', showCategoryDropdown);
                     subMenu.addEventListener('mouseenter', showCategoryDropdown);
                     item.addEventListener('mouseleave', startCategoryHideTimer);
                     subMenu.addEventListener('mouseleave', startCategoryHideTimer);
                 }
             });
        }

        // --- Lógica do Header (Sticky/Hide on Scroll) ---
        const headerTop = document.querySelector('.header-top');
        let lastScrollTop = 0;
          if (headerTop) {
              window.addEventListener('scroll', function() {
                  let currentScrollTop = window.pageYOffset || document.documentElement.scrollTop;
                  if (currentScrollTop <= 5) { headerTop.classList.remove('header-top-hidden');
                  } else if (currentScrollTop > lastScrollTop) { headerTop.classList.add('header-top-hidden');
                  } else { headerTop.classList.add('header-top-hidden'); }
                  lastScrollTop = currentScrollTop <= 0 ? 0 : currentScrollTop;
              }, false);
          }

        // --- Lógica de Abertura/Fechamento de Modais ---

        // Modal de Login (Abertura pelo Botão "Minha Conta" no header)
        const showModalBtnHeader = document.getElementById('show-login-modal');
        const loginModalHeader = document.getElementById('login-modal');
        if(showModalBtnHeader && loginModalHeader) {
              showModalBtnHeader.addEventListener('click', function(e) { e.preventDefault(); loginModalHeader.classList.add('modal-open'); });
        }

        // Modal do Carrinho (Abertura pelo Ícone de Sacola)
        const cartTriggerBtn = document.getElementById('cart-trigger-btn');
        if (cartTriggerBtn) { cartTriggerBtn.addEventListener('click', openCart); }
        // Se o overlay for o do carrinho (ID: cart-overlay)
        if (cartOverlay) { cartOverlay.addEventListener('click', closeCart); }


        // ==========================================================
        // DELEGAÇÃO DE EVENTOS GLOBAL (Para cliques)
        // ==========================================================
          document.addEventListener('click', function(e) {

            // --- CORREÇÃO: Botão de fechar MODAL (Login, Info, Delete, Carrinho) ---
            // Procura por [data-dismiss="modal"] em qualquer lugar.
            const modalCloseButton = e.target.closest('[data-dismiss="modal"]');

            if (modalCloseButton) {
                e.preventDefault();

                // Se o botão está dentro do CART MODAL
                if (modalCloseButton.closest('#cart-modal')) {
                    closeCart();
                } else if (modalCloseButton.closest('.modal-overlay')) {
                    // Se o botão está dentro de outro modal overlay (Login, Info, Delete)
                    modalCloseButton.closest('.modal-overlay').classList.remove('modal-open');
                }
            }

            // Clicar FORA do modal-content (mas dentro do overlay)
            if (e.target.classList.contains('modal-overlay')) {
                // Se for o overlay do carrinho (já tratado pelo listener específico cartOverlay.addEventListener('click', closeCart);)
                // Se for outro modal overlay:
                if (e.target.id !== 'cart-overlay') {
                    e.target.classList.remove('modal-open');
                }
            }

            // --- Delegação de Eventos (Itens da Sacola) ---

            // Botão de remover item
             const removeButton = e.target.closest('.cart-item-remove');
             if (removeButton) {
                 e.preventDefault();
                 const id = removeButton.getAttribute('data-id');
                 removeItemFromCart(id);
             }

             // Botão de limpar sacola
             if (e.target.id === 'clear-cart-btn') {
                 e.preventDefault();
                 clearCart();
             }

             // Botão de Incrementar (+)
             const increaseButton = e.target.closest('.cart-qty-increase');
             if (increaseButton) {
                 e.preventDefault();
                 const id = increaseButton.getAttribute('data-id');
                 incrementCartItem(id);
             }

             // Botão de Decrementar (-)
             const decreaseButton = e.target.closest('.cart-qty-decrease');
             if (decreaseButton) {
                 e.preventDefault();
                 const id = decreaseButton.getAttribute('data-id');
                 decrementCartItem(id);
             }
          });

        // Carrega o carrinho quando a página abre
        updateCart();

    }); // Fim do DOMContentLoaded


    // --- LÓGICA JQUERY (Modal de Delete) ---
    $(document).ready(function(){

        // Modal de Informação/Erro (usado por erros de DB)
        // O fechamento já está coberto pela delegação de eventos [data-dismiss="modal"]

        // Modal de Confirmação de Exclusão (usado em enderecos.php)
        const deleteModal = $('#delete-confirm-modal');
        if (deleteModal.length) {
            let deleteUrlToConfirm = null;

            // 1. Clicar em "Excluir" (na página de endereços)
            $(document).on('click', '.address-actions a.delete', function(e) { // Delegado
                e.preventDefault();
                deleteUrlToConfirm = $(this).attr('href');
                deleteModal.addClass('modal-open');
            });

            // 2. Clicar em "Sim, Excluir"
            $('#btn-confirm-delete').on('click', function() {
                if (deleteUrlToConfirm) {
                    window.location.href = deleteUrlToConfirm;
                }
            });

            // 3. Fechar o modal de exclusão (delegado para o listener global [data-dismiss="modal"])
        }

    });
</script>