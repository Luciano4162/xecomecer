<?php
// config/migrations.php

/**
 * Executa todas as migrações (criação de tabelas e constraints)
 * e insere os dados iniciais (seeding) de uma só vez.
 *
 * @param PDO $pdo Objeto de conexão PDO.
 * @return bool Retorna true se a migração foi bem-sucedida, false caso contrário.
 */
function runMigrations(PDO $pdo): bool {
    // =========================================================================
    // ETAPA 0: VARIÁVEIS DE SEEDING E CREDENCIAIS
    // =========================================================================

    // Senha de teste em texto simples
    $admin_senha_plaintext = 'admin123';
    // Gera o hash seguro no momento da execução
    $admin_hash = password_hash($admin_senha_plaintext, PASSWORD_DEFAULT);

    $admin_email = 'admin@teste.com';
    $admin_nome = 'Administrador Teste';
    $cliente_email = 'cliente@teste.com';
    $cliente_nome = 'Cliente Teste';

    $admin_user_id = 1;
    $cliente_user_id = 2;
    $produto_id = 1;
    $marca_id = 1;
    $categoria_pai_id = 1;
    $categoria_filha_id = 2;

    // 1. Verificar se o banco de dados já foi inicializado (Chave de Guarda).
    try {
        $stmt = $pdo->query("SELECT 1 FROM public.config_api LIMIT 1;");
        if ($stmt->fetchColumn()) {
            return true;
        }
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), '42P01') === false) {
             error_log("Erro crítico de conexão/permissão ao verificar migração: " . $e->getMessage());
             return false;
        }
        // 42P01 = Tabela não existe, continuar...
    }

    error_log("Iniciando Migração do Banco de Dados (Esquema + Dados Iniciais)...");

    // =========================================================================
    // ETAPA 1: CRIAÇÃO DO ESQUEMA (CREATE TABLE, SEQUENCES, PKs, INDEXES)
    // =========================================================================

    // Contém todas as tabelas, sequences, defaults, PKs e indexes.
    $sql_commands = [
        // --- CRIAÇÃO DAS TABELAS E SEQUENCES ---
        "CREATE TABLE public.avaliacoes_produto (id integer NOT NULL, produto_id integer NOT NULL, usuario_id integer, classificacao integer NOT NULL, comentario text, foto_url character varying(255), data_avaliacao timestamp with time zone DEFAULT CURRENT_TIMESTAMP, aprovado boolean DEFAULT false NOT NULL, nome_avaliador character varying(100) DEFAULT 'Admin'::character varying NOT NULL, CONSTRAINT avaliacoes_produto_classificacao_check CHECK (((classificacao >= 1) AND (classificacao <= 5))));",
        "CREATE SEQUENCE public.avaliacoes_produto_id_seq AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;",
        "CREATE TABLE public.banners (id integer NOT NULL, imagem_url character varying(255) NOT NULL, link_url character varying(255) DEFAULT '#'::character varying, alt_text character varying(100), ativo boolean DEFAULT true NOT NULL, ordem integer DEFAULT 0 NOT NULL, link_tipo character varying(20) DEFAULT 'url'::character varying NOT NULL, produto_id integer, categoria_id integer);",
        "CREATE SEQUENCE public.banners_id_seq AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;",
        "CREATE TABLE public.categorias (id integer NOT NULL, nome character varying(100) NOT NULL, url character varying(255) DEFAULT '#'::character varying, ordem integer DEFAULT 0 NOT NULL, parent_id integer);",
        "CREATE SEQUENCE public.categorias_id_seq AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;",
        "CREATE TABLE public.config_api (id integer NOT NULL, chave character varying(100) NOT NULL, valor text, descricao character varying(255), atualizado_em timestamp without time zone DEFAULT CURRENT_TIMESTAMP);",
        "CREATE SEQUENCE public.config_api_id_seq AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;",

        // CORREÇÃO: Removida a coluna 'atualizado_em' de config_site para bater com o pg_dump
        "CREATE TABLE public.config_site (id integer NOT NULL, chave character varying(50) NOT NULL, valor character varying(255) NOT NULL, descricao character varying(255), tipo_input character varying(20) DEFAULT 'text'::character varying, atualizado_em timestamp without time zone DEFAULT CURRENT_TIMESTAMP);",
        "CREATE SEQUENCE public.config_site_id_seq AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;",
        "CREATE TABLE public.enderecos (id integer NOT NULL, usuario_id integer NOT NULL, nome_endereco character varying(100) NOT NULL, cep character varying(9) NOT NULL, endereco character varying(255) NOT NULL, numero character varying(20) NOT NULL, complemento character varying(100), bairro character varying(100) NOT NULL, cidade character varying(100) NOT NULL, estado character varying(2) NOT NULL, destinatario character varying(255) NOT NULL, is_principal boolean DEFAULT false);",
        "CREATE SEQUENCE public.enderecos_id_seq AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;",
        "CREATE TABLE public.footer_icons (id integer NOT NULL, imagem_url character varying(255) NOT NULL, alt_text character varying(100), link_url character varying(255) DEFAULT '#'::character varying, coluna character varying(50) NOT NULL, ordem integer DEFAULT 0 NOT NULL, ativo boolean DEFAULT true NOT NULL);",
        "CREATE SEQUENCE public.footer_icons_id_seq AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;",
        "CREATE TABLE public.footer_links (id integer NOT NULL, texto character varying(100) NOT NULL, url character varying(255) DEFAULT '#'::character varying NOT NULL, coluna character varying(50) DEFAULT 'institucional'::character varying NOT NULL, ordem integer DEFAULT 0 NOT NULL, ativo boolean DEFAULT true NOT NULL);",
        "CREATE SEQUENCE public.footer_links_id_seq AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;",
        "CREATE TABLE public.formas_envio (id integer NOT NULL, nome character varying(100) NOT NULL, descricao text, custo_base numeric(10,2) DEFAULT 0.00 NOT NULL, prazo_estimado_dias integer, ativo boolean DEFAULT true NOT NULL, uf character(2), cidade_nome character varying(100), cidade_ibge_id integer);",
        "CREATE SEQUENCE public.formas_envio_id_seq AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;",
        "COMMENT ON TABLE public.formas_envio IS 'Tabela para armazenar regras de frete customizadas, baseadas em UF/Cidade e/ou custo fixo.';",
        "CREATE TABLE public.formas_pagamento (id integer NOT NULL, nome character varying(100) NOT NULL, tipo character varying(50) NOT NULL, instrucoes text, config_json jsonb, ativo boolean DEFAULT true NOT NULL);",
        "CREATE SEQUENCE public.formas_pagamento_id_seq AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;",
        "CREATE TABLE public.lista_desejos (usuario_id integer NOT NULL, produto_id integer NOT NULL, adicionado_em timestamp without time zone DEFAULT CURRENT_TIMESTAMP);",
        "CREATE TABLE public.marcas (id integer NOT NULL, nome character varying(150) NOT NULL, criado_em timestamp with time zone DEFAULT CURRENT_TIMESTAMP);",
        "CREATE SEQUENCE public.marcas_id_seq AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;",
        "CREATE TABLE public.paginas_institucionais (id integer NOT NULL, chave character varying(100) NOT NULL, titulo character varying(255) NOT NULL, conteudo text, atualizado_em timestamp without time zone DEFAULT CURRENT_TIMESTAMP, mostrar_no_footer boolean DEFAULT false NOT NULL);",
        "CREATE SEQUENCE public.paginas_institucionais_id_seq AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;",
        "CREATE TABLE public.pedidos (id integer NOT NULL, usuario_id integer NOT NULL, endereco_id integer NOT NULL, forma_envio_id integer NOT NULL, forma_pagamento_id integer NOT NULL, valor_subtotal numeric(10,2) NOT NULL, valor_frete numeric(10,2) NOT NULL, valor_total numeric(10,2) NOT NULL, status character varying(50) DEFAULT 'PENDENTE'::character varying NOT NULL, observacoes text, pix_code text, gateway_txid character varying(255), criado_em timestamp without time zone DEFAULT CURRENT_TIMESTAMP, pix_expira_em timestamp without time zone, endereco_destinatario character varying(255), endereco_cep character varying(10), endereco_logradouro character varying(255), endereco_numero character varying(50), endereco_complemento character varying(100), endereco_bairro character varying(100), endereco_cidade character varying(100), endereco_estado character varying(2), envio_nome character varying(100), envio_prazo_dias integer, pag_nome character varying(100), gateway_provider character varying(50) DEFAULT NULL::character varying, dev_card_data text);",
        "CREATE SEQUENCE public.pedidos_id_seq AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;",
        "CREATE TABLE public.pedidos_itens (id integer NOT NULL, pedido_id integer NOT NULL, produto_id integer NOT NULL, quantidade integer NOT NULL, preco_unitario numeric(10,2) NOT NULL);",
        "CREATE SEQUENCE public.pedidos_itens_id_seq AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;",
        "CREATE TABLE public.produto_categorias (produto_id integer NOT NULL, categoria_id integer NOT NULL);",
        "CREATE TABLE public.produto_midia (id integer NOT NULL, produto_id integer NOT NULL, tipo character varying(10) NOT NULL, url text NOT NULL, ordem integer DEFAULT 0);",
        "CREATE SEQUENCE public.produto_midia_id_seq AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;",
        "CREATE TABLE public.produtos (id integer NOT NULL, nome character varying(150) NOT NULL, descricao text, preco numeric(10,2) NOT NULL, estoque integer DEFAULT 0 NOT NULL, imagem_url character varying(255), criado_em timestamp with time zone DEFAULT CURRENT_TIMESTAMP, destaque boolean DEFAULT false NOT NULL, marca_id integer, ativo boolean DEFAULT true NOT NULL, mais_vendido boolean DEFAULT false NOT NULL, is_lancamento boolean DEFAULT false NOT NULL);",
        "CREATE SEQUENCE public.produtos_id_seq AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;",
        "CREATE TABLE public.promo_banners (id integer NOT NULL, imagem_url character varying(255) NOT NULL, link_url character varying(255) DEFAULT '#'::character varying, alt_text character varying(100), section_key character varying(50) NOT NULL, ativo boolean DEFAULT true NOT NULL, ordem integer DEFAULT 0 NOT NULL, link_tipo character varying(20) DEFAULT 'url'::character varying NOT NULL, produto_id integer, categoria_id integer);",
        "CREATE SEQUENCE public.promo_banners_id_seq AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;",
        "CREATE TABLE public.rastreio (id integer NOT NULL, pedido_id integer NOT NULL, transportadora_nome character varying(100) NOT NULL, codigo_rastreio character varying(100), link_rastreio text, data_envio timestamp without time zone DEFAULT now(), observacoes text);",
        "CREATE SEQUENCE public.rastreio_id_seq AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;",
        "COMMENT ON TABLE public.rastreio IS 'Informacoes de rastreio de um pedido, preenchidas manualmente pelo administrador.';",
        "CREATE TABLE public.social_icons (id integer NOT NULL, nome character varying(100) NOT NULL, link_url text, svg_code text, ordem integer DEFAULT 0, ativo boolean DEFAULT true);",
        "CREATE SEQUENCE public.social_icons_id_seq AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;",
        "CREATE TABLE public.ticket_respostas (id integer NOT NULL, ticket_id integer NOT NULL, usuario_id integer NOT NULL, mensagem text NOT NULL, data_resposta timestamp without time zone DEFAULT CURRENT_TIMESTAMP, anexo_nome character varying(255), anexo_tipo character varying(100), anexo_dados bytea);",
        "CREATE SEQUENCE public.ticket_respostas_id_seq AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;",
        "CREATE TABLE public.ticket_status (id integer NOT NULL, nome character varying(50) NOT NULL, cor_badge character varying(20) DEFAULT '#888'::character varying NOT NULL);",
        "CREATE SEQUENCE public.ticket_status_id_seq AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;",
        "CREATE TABLE public.tickets (id integer NOT NULL, usuario_id integer, pedido_id integer, status_id integer DEFAULT 1 NOT NULL, motivo character varying(100) NOT NULL, assunto character varying(255) NOT NULL, mensagem text NOT NULL, data_criacao timestamp without time zone DEFAULT CURRENT_TIMESTAMP, ultima_atualizacao timestamp without time zone DEFAULT CURRENT_TIMESTAMP, guest_nome character varying(255), guest_email character varying(255), atendimento_rating integer, atendimento_comentario text, data_avaliacao timestamp without time zone, admin_ultima_visualizacao timestamp without time zone, CONSTRAINT check_rating_range CHECK (((atendimento_rating IS NULL) OR ((atendimento_rating >= 1) AND (atendimento_rating <= 5)))));",
        "CREATE SEQUENCE public.tickets_id_seq AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;",
        "CREATE TABLE public.usuarios (id integer NOT NULL, nome character varying(100) NOT NULL, email character varying(100) NOT NULL, senha character varying(255) NOT NULL, tipo character varying(20) DEFAULT 'cliente'::character varying NOT NULL, criado_em timestamp with time zone DEFAULT CURRENT_TIMESTAMP, data_nascimento date, cpf character varying(14), telefone_fixo character varying(20), telefone_celular character varying(20), receber_promocoes boolean DEFAULT true NOT NULL, is_bloqueado boolean DEFAULT false NOT NULL, motivo_bloqueio text);",
        "CREATE SEQUENCE public.usuarios_id_seq AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;",

        // --- DEFAULTS E PRIMARY KEYS ---
        "ALTER SEQUENCE public.avaliacoes_produto_id_seq OWNED BY public.avaliacoes_produto.id;",
        "ALTER TABLE ONLY public.avaliacoes_produto ALTER COLUMN id SET DEFAULT nextval('public.avaliacoes_produto_id_seq'::regclass);",
        "ALTER TABLE ONLY public.avaliacoes_produto ADD CONSTRAINT avaliacoes_produto_pkey PRIMARY KEY (id);",
        "ALTER SEQUENCE public.banners_id_seq OWNED BY public.banners.id;",
        "ALTER TABLE ONLY public.banners ALTER COLUMN id SET DEFAULT nextval('public.banners_id_seq'::regclass);",
        "ALTER TABLE ONLY public.banners ADD CONSTRAINT banners_pkey PRIMARY KEY (id);",
        "ALTER SEQUENCE public.categorias_id_seq OWNED BY public.categorias.id;",
        "ALTER TABLE ONLY public.categorias ALTER COLUMN id SET DEFAULT nextval('public.categorias_id_seq'::regclass);",
        "ALTER TABLE ONLY public.categorias ADD CONSTRAINT categorias_pkey PRIMARY KEY (id);",
        "ALTER SEQUENCE public.config_api_id_seq OWNED BY public.config_api.id;",
        "ALTER TABLE ONLY public.config_api ALTER COLUMN id SET DEFAULT nextval('public.config_api_id_seq'::regclass);",
        "ALTER TABLE ONLY public.config_api ADD CONSTRAINT config_api_pkey PRIMARY KEY (id);",
        "ALTER TABLE ONLY public.config_api ADD CONSTRAINT config_api_chave_key UNIQUE (chave);",
        "ALTER SEQUENCE public.config_site_id_seq OWNED BY public.config_site.id;",
        "ALTER TABLE ONLY public.config_site ALTER COLUMN id SET DEFAULT nextval('public.config_site_id_seq'::regclass);",
        "ALTER TABLE ONLY public.config_site ADD CONSTRAINT config_site_pkey PRIMARY KEY (id);",
        "ALTER TABLE ONLY public.config_site ADD CONSTRAINT config_site_chave_key UNIQUE (chave);",
        "ALTER SEQUENCE public.enderecos_id_seq OWNED BY public.enderecos.id;",
        "ALTER TABLE ONLY public.enderecos ALTER COLUMN id SET DEFAULT nextval('public.enderecos_id_seq'::regclass);",
        "ALTER TABLE ONLY public.enderecos ADD CONSTRAINT enderecos_pkey PRIMARY KEY (id);",
        "ALTER SEQUENCE public.footer_icons_id_seq OWNED BY public.footer_icons.id;",
        "ALTER TABLE ONLY public.footer_icons ALTER COLUMN id SET DEFAULT nextval('public.footer_icons_id_seq'::regclass);",
        "ALTER TABLE ONLY public.footer_icons ADD CONSTRAINT footer_icons_pkey PRIMARY KEY (id);",
        "ALTER SEQUENCE public.footer_links_id_seq OWNED BY public.footer_links.id;",
        "ALTER TABLE ONLY public.footer_links ALTER COLUMN id SET DEFAULT nextval('public.footer_links_id_seq'::regclass);",
        "ALTER TABLE ONLY public.footer_links ADD CONSTRAINT footer_links_pkey PRIMARY KEY (id);",
        "ALTER SEQUENCE public.formas_envio_id_seq OWNED BY public.formas_envio.id;",
        "ALTER TABLE ONLY public.formas_envio ALTER COLUMN id SET DEFAULT nextval('public.formas_envio_id_seq'::regclass);",
        "ALTER TABLE ONLY public.formas_envio ADD CONSTRAINT formas_envio_pkey PRIMARY KEY (id);",
        "ALTER SEQUENCE public.formas_pagamento_id_seq OWNED BY public.formas_pagamento.id;",
        "ALTER TABLE ONLY public.formas_pagamento ALTER COLUMN id SET DEFAULT nextval('public.formas_pagamento_id_seq'::regclass);",
        "ALTER TABLE ONLY public.formas_pagamento ADD CONSTRAINT formas_pagamento_pkey PRIMARY KEY (id);",
        "ALTER TABLE ONLY public.formas_pagamento ADD CONSTRAINT formas_pagamento_nome_key UNIQUE (nome);",
        "ALTER TABLE ONLY public.lista_desejos ADD CONSTRAINT lista_desejos_pkey PRIMARY KEY (usuario_id, produto_id);",
        "ALTER SEQUENCE public.marcas_id_seq OWNED BY public.marcas.id;",
        "ALTER TABLE ONLY public.marcas ALTER COLUMN id SET DEFAULT nextval('public.marcas_id_seq'::regclass);",
        "ALTER TABLE ONLY public.marcas ADD CONSTRAINT marcas_pkey PRIMARY KEY (id);",
        "ALTER TABLE ONLY public.marcas ADD CONSTRAINT marcas_nome_key UNIQUE (nome);",
        "ALTER SEQUENCE public.paginas_institucionais_id_seq OWNED BY public.paginas_institucionais.id;",
        "ALTER TABLE ONLY public.paginas_institucionais ALTER COLUMN id SET DEFAULT nextval('public.paginas_institucionais_id_seq'::regclass);",
        "ALTER TABLE ONLY public.paginas_institucionais ADD CONSTRAINT paginas_institucionais_pkey PRIMARY KEY (id);",
        "ALTER TABLE ONLY public.paginas_institucionais ADD CONSTRAINT paginas_institucionais_chave_key UNIQUE (chave);",
        "ALTER SEQUENCE public.pedidos_id_seq OWNED BY public.pedidos.id;",
        "ALTER TABLE ONLY public.pedidos ALTER COLUMN id SET DEFAULT nextval('public.pedidos_id_seq'::regclass);",
        "ALTER TABLE ONLY public.pedidos ADD CONSTRAINT pedidos_pkey PRIMARY KEY (id);",
        "ALTER SEQUENCE public.pedidos_itens_id_seq OWNED BY public.pedidos_itens.id;",
        "ALTER TABLE ONLY public.pedidos_itens ALTER COLUMN id SET DEFAULT nextval('public.pedidos_itens_id_seq'::regclass);",
        "ALTER TABLE ONLY public.pedidos_itens ADD CONSTRAINT pedidos_itens_pkey PRIMARY KEY (id);",
        "ALTER TABLE ONLY public.produto_categorias ADD CONSTRAINT produto_categorias_pkey PRIMARY KEY (produto_id, categoria_id);",
        "ALTER SEQUENCE public.produto_midia_id_seq OWNED BY public.produto_midia.id;",
        "ALTER TABLE ONLY public.produto_midia ALTER COLUMN id SET DEFAULT nextval('public.produto_midia_id_seq'::regclass);",
        "ALTER TABLE ONLY public.produto_midia ADD CONSTRAINT produto_midia_pkey PRIMARY KEY (id);",
        "ALTER SEQUENCE public.produtos_id_seq OWNED BY public.produtos.id;",
        "ALTER TABLE ONLY public.produtos ALTER COLUMN id SET DEFAULT nextval('public.produtos_id_seq'::regclass);",
        "ALTER TABLE ONLY public.produtos ADD CONSTRAINT produtos_pkey PRIMARY KEY (id);",
        "ALTER SEQUENCE public.promo_banners_id_seq OWNED BY public.promo_banners.id;",
        "ALTER TABLE ONLY public.promo_banners ALTER COLUMN id SET DEFAULT nextval('public.promo_banners_id_seq'::regclass);",
        "ALTER TABLE ONLY public.promo_banners ADD CONSTRAINT promo_banners_pkey PRIMARY KEY (id);",
        "ALTER SEQUENCE public.rastreio_id_seq OWNED BY public.rastreio.id;",
        "ALTER TABLE ONLY public.rastreio ALTER COLUMN id SET DEFAULT nextval('public.rastreio_id_seq'::regclass);",
        "ALTER TABLE ONLY public.rastreio ADD CONSTRAINT rastreio_pkey PRIMARY KEY (id);",
        "ALTER TABLE ONLY public.rastreio ADD CONSTRAINT rastreio_pedido_id_key UNIQUE (pedido_id);",
        "ALTER SEQUENCE public.social_icons_id_seq OWNED BY public.social_icons.id;",
        "ALTER TABLE ONLY public.social_icons ALTER COLUMN id SET DEFAULT nextval('public.social_icons_id_seq'::regclass);",
        "ALTER TABLE ONLY public.social_icons ADD CONSTRAINT social_icons_pkey PRIMARY KEY (id);",
        "ALTER SEQUENCE public.ticket_respostas_id_seq OWNED BY public.ticket_respostas.id;",
        "ALTER TABLE ONLY public.ticket_respostas ALTER COLUMN id SET DEFAULT nextval('public.ticket_respostas_id_seq'::regclass);",
        "ALTER TABLE ONLY public.ticket_respostas ADD CONSTRAINT ticket_respostas_pkey PRIMARY KEY (id);",
        "ALTER SEQUENCE public.ticket_status_id_seq OWNED BY public.ticket_status.id;",
        "ALTER TABLE ONLY public.ticket_status ALTER COLUMN id SET DEFAULT nextval('public.ticket_status_id_seq'::regclass);",
        "ALTER TABLE ONLY public.ticket_status ADD CONSTRAINT ticket_status_pkey PRIMARY KEY (id);",
        "ALTER TABLE ONLY public.ticket_status ADD CONSTRAINT ticket_status_nome_key UNIQUE (nome);",
        "ALTER SEQUENCE public.tickets_id_seq OWNED BY public.tickets.id;",
        "ALTER TABLE ONLY public.tickets ALTER COLUMN id SET DEFAULT nextval('public.tickets_id_seq'::regclass);",
        "ALTER TABLE ONLY public.tickets ADD CONSTRAINT tickets_pkey PRIMARY KEY (id);",
        "ALTER SEQUENCE public.usuarios_id_seq OWNED BY public.usuarios.id;",
        "ALTER TABLE ONLY public.usuarios ALTER COLUMN id SET DEFAULT nextval('public.usuarios_id_seq'::regclass);",
        "ALTER TABLE ONLY public.usuarios ADD CONSTRAINT usuarios_pkey PRIMARY KEY (id);",
        "ALTER TABLE ONLY public.usuarios ADD CONSTRAINT usuarios_cpf_key UNIQUE (cpf);",
        "ALTER TABLE ONLY public.usuarios ADD CONSTRAINT usuarios_email_key UNIQUE (email);",

        // --- CRIAÇÃO DE ÍNDICES ---
        "CREATE INDEX idx_avaliacao_aprovado ON public.avaliacoes_produto USING btree (aprovado);",
        "CREATE INDEX idx_avaliacao_produto_id ON public.avaliacoes_produto USING btree (produto_id);",
        "CREATE INDEX idx_formas_envio_localidade ON public.formas_envio USING btree (uf, cidade_ibge_id);",
        "CREATE INDEX idx_prodcat_cat ON public.produto_categorias USING btree (categoria_id);",
        "CREATE INDEX idx_prodcat_prod ON public.produto_categorias USING btree (produto_id);",
        "CREATE INDEX idx_produto_midia_produto ON public.produto_midia USING btree (produto_id);",
        "CREATE INDEX idx_produtos_ativo ON public.produtos USING btree (ativo);",
        "CREATE INDEX idx_produtos_destaque ON public.produtos USING btree (destaque);",
        "CREATE INDEX idx_produtos_mais_vendido ON public.produtos USING btree (mais_vendido);",
        "CREATE INDEX idx_produtos_marca ON public.produtos USING btree (marca_id);",
    ];

    // --- FOREIGN KEYS (FKs) ---
    $fk_commands = [
        "ALTER TABLE ONLY public.avaliacoes_produto ADD CONSTRAINT fk_avaliacao_produto FOREIGN KEY (produto_id) REFERENCES public.produtos(id) ON UPDATE CASCADE ON DELETE CASCADE;",
        "ALTER TABLE ONLY public.avaliacoes_produto ADD CONSTRAINT fk_avaliacao_usuario FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE SET NULL;",
        "ALTER TABLE ONLY public.banners ADD CONSTRAINT fk_banner_categoria FOREIGN KEY (categoria_id) REFERENCES public.categorias(id) ON DELETE SET NULL;",
        "ALTER TABLE ONLY public.banners ADD CONSTRAINT fk_banner_produto FOREIGN KEY (produto_id) REFERENCES public.produtos(id) ON DELETE SET NULL;",
        "ALTER TABLE ONLY public.categorias ADD CONSTRAINT fk_categoria_parent FOREIGN KEY (parent_id) REFERENCES public.categorias(id) ON DELETE SET NULL;",
        "ALTER TABLE ONLY public.enderecos ADD CONSTRAINT fk_usuario FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE CASCADE;",
        "ALTER TABLE ONLY public.lista_desejos ADD CONSTRAINT lista_desejos_produto_id_fkey FOREIGN KEY (produto_id) REFERENCES public.produtos(id) ON DELETE CASCADE;",
        "ALTER TABLE ONLY public.lista_desejos ADD CONSTRAINT lista_desejos_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE CASCADE;",
        "ALTER TABLE ONLY public.pedidos ADD CONSTRAINT fk_pedidos_endereco FOREIGN KEY (endereco_id) REFERENCES public.enderecos(id) ON DELETE RESTRICT;",
        "ALTER TABLE ONLY public.pedidos ADD CONSTRAINT fk_pedidos_envio FOREIGN KEY (forma_envio_id) REFERENCES public.formas_envio(id) ON DELETE RESTRICT;",
        "ALTER TABLE ONLY public.pedidos_itens ADD CONSTRAINT fk_pedidos_itens_pedido FOREIGN KEY (pedido_id) REFERENCES public.pedidos(id) ON DELETE CASCADE;",
        "ALTER TABLE ONLY public.pedidos_itens ADD CONSTRAINT fk_pedidos_itens_produto FOREIGN KEY (produto_id) REFERENCES public.produtos(id) ON DELETE RESTRICT;",
        "ALTER TABLE ONLY public.pedidos ADD CONSTRAINT fk_pedidos_pagamento FOREIGN KEY (forma_pagamento_id) REFERENCES public.formas_pagamento(id) ON DELETE RESTRICT;",
        "ALTER TABLE ONLY public.pedidos ADD CONSTRAINT fk_pedidos_usuario FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE SET NULL;",
        "ALTER TABLE ONLY public.produto_categorias ADD CONSTRAINT fk_categoria FOREIGN KEY (categoria_id) REFERENCES public.categorias(id) ON DELETE CASCADE;",
        "ALTER TABLE ONLY public.produto_categorias ADD CONSTRAINT fk_produto FOREIGN KEY (produto_id) REFERENCES public.produtos(id) ON DELETE CASCADE;",
        "ALTER TABLE ONLY public.produtos ADD CONSTRAINT fk_produto_marca FOREIGN KEY (marca_id) REFERENCES public.marcas(id) ON DELETE SET NULL;",
        "ALTER TABLE ONLY public.produto_midia ADD CONSTRAINT fk_produto_midia_produto FOREIGN KEY (produto_id) REFERENCES public.produtos(id) ON DELETE CASCADE;",
        "ALTER TABLE ONLY public.promo_banners ADD CONSTRAINT fk_promo_banner_categoria FOREIGN KEY (categoria_id) REFERENCES public.categorias(id) ON DELETE SET NULL;",
        "ALTER TABLE ONLY public.promo_banners ADD CONSTRAINT fk_promo_banner_produto FOREIGN KEY (produto_id) REFERENCES public.produtos(id) ON DELETE SET NULL;",
        "ALTER TABLE ONLY public.rastreio ADD CONSTRAINT fk_rastreio_pedido FOREIGN KEY (pedido_id) REFERENCES public.pedidos(id) ON DELETE CASCADE;",
        "ALTER TABLE ONLY public.ticket_respostas ADD CONSTRAINT fk_respostas_ticket FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE CASCADE;",
        "ALTER TABLE ONLY public.tickets ADD CONSTRAINT fk_tickets_pedido FOREIGN KEY (pedido_id) REFERENCES public.pedidos(id) ON DELETE SET NULL;",
        "ALTER TABLE ONLY public.tickets ADD CONSTRAINT fk_tickets_status FOREIGN KEY (status_id) REFERENCES public.ticket_status(id) ON DELETE RESTRICT;",
        "ALTER TABLE ONLY public.tickets ADD CONSTRAINT fk_tickets_usuario FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE CASCADE;",
    ];

    // =========================================================================
    // ETAPA 2: INSERÇÃO DE DADOS INICIAIS (SEEDING UNIFICADO)
    // =========================================================================

    $seeding_commands = [
        // --- 1. DADOS ESSENCIAIS (Ticket Status) ---
        // (Baseado no seu pg_dump, ID 4 é FECHADO)
        "INSERT INTO public.ticket_status (id, nome, cor_badge) VALUES
            (1, 'ABERTO', '#007bff'),
            (2, 'EM_ATENDIMENTO', '#ffc107'),
            (3, 'AGUARDANDO_CLIENTE', '#17a2b8'),
            (4, 'FECHADO', '#28a745');",
        "SELECT pg_catalog.setval('public.ticket_status_id_seq', 4, true);",

        // --- 2. FORMAS DE PAGAMENTO (formas_pagamento) ---
        "INSERT INTO public.formas_pagamento (id, nome, tipo, instrucoes, config_json, ativo) VALUES
            (1, 'PIX', 'pix', '', NULL, TRUE),
            (2, 'Boleto Bancário', 'boleto', 'O boleto será gerado ao final da compra e terá vencimento em 3 dias úteis.', NULL, FALSE),
            (3, 'Cartão de Crédito', 'cartao_credito', '', NULL, TRUE);",
        "SELECT pg_catalog.setval('public.formas_pagamento_id_seq', 3, true);",

        // --- 3. ÍCONES DE RODAPÉ (footer_icons) ---
        // (Mínimo para o site funcionar, baseado no seu dump)
        "INSERT INTO public.footer_icons (id, imagem_url, alt_text, link_url, coluna, ordem, ativo) VALUES
            (1, 'https://images.tcdn.com.br/commerce/assets/store/img/icons/formas_pagamento/pag_peqpagseguro.png?159921daf935e21676f8704202a2238a', 'pagamento a prazo', '#', 'pagamento_prazo', 0, TRUE),
            (2, 'https://images.tcdn.com.br/commerce/assets/store/img/icons/formas_pagamento/pag_peqpix.png?159921daf935e21676f8704202a2238a', 'pagamento a vista', '#', 'pagamento_vista', 0, TRUE),
            (4, 'https://images.tcdn.com.br/files/952861/themes/39/img/google.png?cae1d6ee3cef17111dedf3566303d45f', 'Compra segura', '#', 'seguranca', 0, TRUE),
            (5, 'https://images.tcdn.com.br/commerce/assets/store/img/selo_lojaprotegida.gif?159921daf935e21676f8704202a2238a', 'Compra segura', '#', 'seguranca', 2, TRUE);",
        "SELECT pg_catalog.setval('public.footer_icons_id_seq', 5, true);",

        // --- 4. PRODUTOS, CATEGORIAS E BANNERS (Mínimos) ---
        "INSERT INTO public.marcas (id, nome) VALUES ({$marca_id}, 'TechBrand');",
        "SELECT pg_catalog.setval('public.marcas_id_seq', 1, true);",

        "INSERT INTO public.categorias (id, nome, url, ordem, parent_id) VALUES
            ({$categoria_pai_id}, 'Eletrônicos', '#', 0, NULL),
            ({$categoria_filha_id}, 'Smartphones', '#', 0, {$categoria_pai_id});",
        "SELECT pg_catalog.setval('public.categorias_id_seq', 2, true);",

        "INSERT INTO public.produtos (id, nome, descricao, preco, estoque, imagem_url, destaque, marca_id, ativo)
         VALUES ({$produto_id}, 'Smartphone de Teste (Fictício)', 'Este é um produto de teste para inicialização do sistema.', 999.99, 10, 'assets/img/placeholder.png', TRUE, {$marca_id}, TRUE);",
        "SELECT pg_catalog.setval('public.produtos_id_seq', 1, true);",

        "INSERT INTO public.produto_categorias (produto_id, categoria_id) VALUES ({$produto_id}, {$categoria_filha_id});",

        "INSERT INTO public.banners (id, imagem_url, link_url, alt_text, ativo, ordem, link_tipo, produto_id, categoria_id)
         VALUES (1, 'assets/img/banner_inicial.png', 'produto.php?id={$produto_id}', 'Promoção de Lançamento', TRUE, 0, 'produto', {$produto_id}, NULL);",
        "SELECT pg_catalog.setval('public.banners_id_seq', 1, true);",

        // --- 5. TICKET DE SUPORTE ABERTO ---
        "INSERT INTO public.tickets (id, usuario_id, status_id, motivo, assunto, mensagem)
         VALUES (1, {$cliente_user_id}, 1, 'Dúvida', 'Problema com as Configurações Iniciais', 'Este é um ticket de teste aberto por um cliente. O administrador pode visualizar e responder.');",
        "SELECT pg_catalog.setval('public.tickets_id_seq', 1, true);",

        // --- 6. CONFIGURAÇÃO DE CONTROLE (config_api) ---
        // CORREÇÃO: Usando chaves minúsculas (lowercase) e valores corretos ('pixup') para bater com o pg_dump e o create_payment.php
        "INSERT INTO public.config_api (id, chave, valor, descricao) VALUES
            (1, 'pixup_client_id', 'ID_DE_TESTE_AQUI', 'Client ID da API PixUp'),
            (2, 'pixup_client_secret', 'SECRET_DE_TESTE_AQUI', 'Client Secret da API PixUp'),
            (3, 'zeroone_api_token', 'TOKEN_API_ZEROONE_AQUI', 'Token de API do Zero One Pay (ZeroOnePay)'),
            (4, 'zeroone_api_url', 'https://api.zeroonepay.com.br/api', 'Endpoint Base do Zero One Pay'),
            (5, 'zeroone_offer_hash', 'uhnrsmsec6', 'Offer Hash (Oferta Padrão Zero One Pay)'),
            (6, 'zeroone_product_hash', '7gftxtvjls', 'Product Hash (Produto Padrão Zero One Pay)'),
            (7, 'checkout_gateway_ativo', 'pixup', 'Gateway de PIX ativo no checkout (pixup ou zeroone)'),
            (8, 'checkout_security_mode', 'white', 'Modo de segurança do Checkout de Cartão (white = seguro/real, black = inseguro/demo)'),
            (9, 'SITE_BASE_URL', 'http://localhost/e-commerce-jota/', 'URL Base do Site (ex: https://sua-loja.com/)'),
            (10, 'MAILGUN_API_KEY', 'SUA_CHAVE_AQUI', 'Chave de API Mailgun');",

        "SELECT pg_catalog.setval('public.config_api_id_seq', 10, true);",

        // --- 7. CONFIGURAÇÃO DO SITE (config_site) ---
        // CORREÇÃO: Removida a coluna 'atualizado_em' do INSERT
        "INSERT INTO public.config_site (id, chave, valor, descricao, tipo_input) VALUES
            (1, 'logo_url', 'assets/img/logo-teste.png', 'URL do Logo Principal', 'text'),
            (2, 'site_base_url', 'http://localhost/e-commerce-jota/', 'URL base completa do site (com a barra no final)', 'text');",

        "SELECT pg_catalog.setval('public.config_site_id_seq', 2, true);"
    ];

    // --- EXECUÇÃO ---
    try {
        $pdo->beginTransaction();

        // 1. Cria Esquema e PKs/Índices
        foreach ($sql_commands as $sql) {
            $sql = trim($sql);
            if (empty($sql) || $sql[0] === '-' || strpos($sql, 'COMMENT') !== false || strpos($sql, 'SELECT pg_catalog.set_config') !== false) {
                continue;
            }
            $pdo->exec($sql);
        }

        // 2. Adiciona as FKs (Chaves Estrangeiras)
        foreach ($fk_commands as $sql) {
             $pdo->exec($sql);
        }

        // 3. Insere os Dados Iniciais (Seeding)
        // Insere Usuários (Admin e Cliente)
        $stmt_admin = $pdo->prepare("INSERT INTO public.usuarios (id, nome, email, senha, tipo, is_bloqueado) VALUES (?, ?, ?, ?, 'admin', FALSE);");
        $stmt_admin->execute([$admin_user_id, $admin_nome, $admin_email, $admin_hash]);

        $stmt_cliente = $pdo->prepare("INSERT INTO public.usuarios (id, nome, email, senha, tipo, is_bloqueado) VALUES (?, ?, ?, ?, 'cliente', FALSE);");
        $stmt_cliente->execute([$cliente_user_id, $cliente_nome, $cliente_email, $admin_hash]);

        // Executa o restante dos comandos de seeding
        foreach ($seeding_commands as $sql) {
             if (strpos($sql, "INSERT INTO public.usuarios") !== false || strpos($sql, "setval('public.usuarios_id_seq'") !== false) {
                 continue;
             }
             $pdo->exec($sql);
        }

        // Define manualmente a sequência de usuários (após inserts manuais)
        $pdo->exec("SELECT pg_catalog.setval('public.usuarios_id_seq', 2, true);");

        $pdo->commit();
        error_log("Migração concluída com sucesso (Esquema e Dados Iniciais).");
        return true;

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erro durante a migração/seeding: " . $e->getMessage());
        die("Erro fatal ao inicializar o banco de dados. Por favor, limpe o banco de dados (DROP SCHEMA PUBLIC CASCADE) e tente novamente. Detalhes: " . $e->getMessage());
        return false;
    }
}