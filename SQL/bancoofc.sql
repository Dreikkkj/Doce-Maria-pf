CREATE DATABASE IF NOT EXISTS db_docemaria;
USE db_docemaria;

DROP TABLE IF EXISTS configuracoes_site;

CREATE TABLE IF NOT EXISTS usuarios (
    id_user INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    telefone VARCHAR(20),
    tipo_usuario ENUM('cliente', 'admin') DEFAULT 'cliente',
    pontos_fidelidade INT DEFAULT 0,
    data_cadastro DATE NOT NULL DEFAULT (CURRENT_DATE)
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS produtos (
    id_produto INT AUTO_INCREMENT PRIMARY KEY,
    img_produto VARCHAR(255),
    nome_produto VARCHAR(100) NOT NULL,
    descricao TEXT,
    categoria ENUM(
        'Cookies', 'Cupcakes', 'Brownies', 'Doces',
        'Bolos', 'Alfajor', 'Brigadeiro', 'Cannoli',
        'Donuts', 'Eclair', 'Macaron', 'Pirulitos'
    ) NOT NULL,
    peso_tamanho VARCHAR(50),
    preco DECIMAL(10,2) NOT NULL,
    estoque INT NOT NULL DEFAULT 0,
    status_estoque ENUM('Em estoque', 'Estoque baixo', 'Esgotado') DEFAULT 'Em estoque',
    estrelas DECIMAL(2,1) NULL CHECK (estrelas BETWEEN 0 AND 5),
    eh_pacote_buffet BOOLEAN DEFAULT FALSE
) ENGINE=InnoDB;

ALTER TABLE produtos MODIFY estrelas DECIMAL(2,1) NULL;

CREATE TABLE IF NOT EXISTS pedidos (
    id_pedido INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT NOT NULL,
    data_pedido DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status_pedido ENUM(
        'Pendente', 'Em preparo', 'Pronto', 'Em entrega', 'Entregue', 'Cancelado'
    ) DEFAULT 'Pendente',
    metodo_coleta ENUM('Retirada', 'Entrega') NOT NULL,
    nome_coletor VARCHAR(100),
    nota_pedido TEXT,
    cep VARCHAR(10),
    endereco_entrega TEXT,
    subtotal DECIMAL(10, 2) NOT NULL,
    valor_frete DECIMAL(10, 2) DEFAULT 0.00,
    gorjeta DECIMAL(10, 2) DEFAULT 0.00,
    valor_total DECIMAL(10, 2) NOT NULL,
    metodo_pagamento ENUM('Cartão', 'Pix', 'Dinheiro') NOT NULL,
    possui_brinde BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (id_cliente) REFERENCES usuarios (id_user)
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS itens_pedido (
    id_item INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_produto INT NULL,
    nome_produto VARCHAR(100) NULL,
    img_produto VARCHAR(255) NULL,
    quantidade INT NOT NULL,
    preco_unitario DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_pedido) REFERENCES pedidos (id_pedido) ON DELETE CASCADE,
    FOREIGN KEY (id_produto) REFERENCES produtos (id_produto) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fidelidade_resgates (
    id_resgate INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT NOT NULL,
    id_produto INT NULL,
    nome_produto VARCHAR(100) NOT NULL,
    pontos_gastos INT NOT NULL,
    status ENUM('Pendente', 'Entregue') NOT NULL DEFAULT 'Pendente',
    data_resgate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_resgates_cliente (id_cliente),
    FOREIGN KEY (id_cliente) REFERENCES usuarios (id_user),
    FOREIGN KEY (id_produto) REFERENCES produtos (id_produto) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS avaliacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NULL,
    nome VARCHAR(100) NOT NULL,
    imagem VARCHAR(255) NOT NULL DEFAULT 'usuario.png',
    nota INT NOT NULL CHECK (nota BETWEEN 0 AND 5),
    comentario TEXT NOT NULL,
    data_avaliacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produto_id) REFERENCES produtos (id_produto) ON DELETE SET NULL
) ENGINE=InnoDB;



INSERT INTO usuarios (nome, email, senha, telefone, tipo_usuario, pontos_fidelidade)
VALUES 
('Maria Silva', 'maria.silva@email.com', 'hash_senha_admin', '(11) 99999-9999', 'admin', 0),
('Ana Beatriz', 'ana@email.com', 'hash_senha_123', '(11) 98888-8888', 'cliente', 2350),
('Juliana Martins', 'juliana@email.com', 'hash_senha_123', '(11) 97777-7777', 'cliente', 150);


INSERT INTO produtos 
(img_produto, nome_produto, descricao, categoria, peso_tamanho, preco, estoque, status_estoque, estrelas)
VALUES 
('whitepeanut.png', 'Donut ChocoPeanut Branco', 'Cobertura de chocolate branco com riscos de chocolate ao leite e pedaços de amendoim torrado.', 'donuts', '85g / Médio', 12.50, 25, 'Em Estoque', 4.8),
('wconfe.png', 'Donut Confeito Divertido', 'Cobertura de chocolate branco decorada com confeitos coloridos.', 'donuts', '75g / Médio', 10.00, 40, 'Em Estoque', 4.7),
('socreamy.png', 'Donut Creamy Berry', 'Cobertura de mirtilo/amora com calda cremosa e açúcar de confeiteiro salpicado.', 'donuts', '90g / Médio', 13.00, 15, 'Em Estoque', 4.9),
('Reve.png', 'Donut Red Velvet Crunch', 'Cobertura vermelha especial estilo Red Velvet com riscos brancos e esferas crocantes.', 'donuts', '85g / Médio', 14.00, 10, 'Em Estoque', 5.0),
('purwhite.png', 'Donut Purple Glaze', 'Glacê roxo vibrante finalizado com linhas delicadas de chocolate branco.', 'donuts', '70g / Médio', 11.00, 30, 'Em Estoque', 4.6),
('purpleconfetti.png', 'Donut Lavender Sprinkles', 'Cobertura clara tom lavanda com granulados crocantes de amoreira/frutas vermelhas.', 'donuts', '80g / Médio', 11.50, 20, 'Em Estoque', 4.5),
('ppinkconfe.png', 'Donut Pink Rainbow', 'Cobertura rosa clássica com granulados coloridos estilo arco-íris.', 'donuts', '80g / Médio', 10.50, 50, 'Em Estoque', 4.8),
('pinkcofe.png', 'Donut Pink Sugar Sprinkles', 'Cobertura rosa suave com granulados brancos e rosa em bastão.', 'donuts', '75g / Médio', 10.50, 35, 'Em Estoque', 4.7),
('peanuts.png', 'Donut Caramelo & Amendoim', 'Cobertura de chocolate branco, calda de caramelo e amendoim crocante.', 'donuts', '85g / Médio', 12.50, 18, 'Em Estoque', 4.9),
('oreod.png', 'Donut Oreo & Cream', 'Cobertura cremosa de chocolate com pedaços e biscoito Oreo inteiro.', 'donuts', '95g / Grande', 15.00, 12, 'Em Estoque', 5.0),
('mermaidd.png', 'Donut Mermaid Dust', 'Cobertura azul-turquesa cintilante com acabamento em brilho comestível estilo sereia.', 'donuts', '80g / Médio', 12.00, 22, 'Em Estoque', 4.8),
('velvet.png', 'Cupcake Red Velvet Heart', 'Massa red velvet com cobertura cremosa de cream cheese e confeitos de corações vermelhos.', 'cupcakes', '90g / Médio', 14.00, 25, 'Em Estoque', 4.9),
('raspberry.png', 'Cupcake Fresh Raspberry', 'Massa leve com cobertura de buttercream de framboesa e framboesas frescas no topo.', 'cupcakes', '95g / Médio', 15.50, 18, 'Em Estoque', 4.8),
('PINKCHERRY.png', 'Cupcake Pink Cherry Bliss', 'Massa fofinha com cobertura espiral rosa pastel, pérolas comestíveis e uma cereja no topo.', 'cupcakes', '85g / Médio', 13.50, 30, 'Em Estoque', 4.7),
('cherryred.png', 'Cupcake Triple Cherry Red', 'Massa red velvet com cobertura suave de chantilly e triplo topo de cerejas com calda.', 'cupcakes', '100g / Médio', 16.00, 15, 'Em Estoque', 5.0),
('oreocup.png', 'Cupcake Oreo Cookies & Cream', 'Massa de chocolate amargo com cobertura de buttercream de Oreo e um biscoito Oreo inteiro.', 'cupcakes', '95g / Médio', 14.50, 22, 'Em Estoque', 4.9),
('incup.png', 'Cupcake Mint Choco Cherry', 'Massa de chocolate com cobertura refrescante de menta, pedaços de chocolate e cereja.', 'cupcakes', '90g / Médio', 13.00, 20, 'Em Estoque', 4.6),
('classipink.png', 'Cupcake Pink Pearl Elegance', 'Massa de baunilha com cobertura aveludada rosa pastel e pérolas com esferas douradas.', 'cupcakes', '85g / Médio', 12.50, 28, 'Em Estoque', 4.8),
('canelacup.png', 'Cupcake Cinnamon Spice', 'Massa aromatizada com canela, cobertura cremosa salpicada de cacau e pau de canela decorativo.', 'cupcakes', '85g / Médio', 12.00, 24, 'Em Estoque', 4.7),
('cafe.png', 'Cupcake Cappuccino Crunch', 'Massa de café com chantilly mesclado, calda de chocolate e granulados crocantes.', 'cupcakes', '85g / Médio', 13.50, 26, 'Em Estoque', 4.8),
('bluee.png', 'Cupcake Ocean Blue Sky', 'Massa suave com cobertura espiral azul-celeste e microesferas prateadas comestíveis.', 'cupcakes', '85g / Médio', 12.50, 30, 'Em Estoque', 4.7),
('bmorango.png', 'Fatia Bolo Strawberry Shortcake', 'Pão de ló fofinho intercalado com creme aveludado e morangos frescos no recheio e no topo.', 'bolos', '150g / Fatia', 18.00, 15, 'Em Estoque', 4.9),
('chocolate.png', 'Fatia Bolo Supreme Chocolate', 'Bolo de chocolate intenso com recheio cremoso e cobertura decorada com raspas de chocolate nobre.', 'bolos', '160g / Fatia', 19.50, 12, 'Em Estoque', 4.8),
('floresta_negra.png', 'Fatia Bolo Floresta Negra com Framboesa', 'Massa escura de cacau recheada com mousse de framboesa, pedaços de fruta e cobertura rosa pastel.', 'bolos', '155g / Fatia', 21.00, 10, 'Em Estoque', 4.9),
('kinder.png', 'Fatia Bolo Duyoo Chocolate Trufado', 'Camadas de bolo de chocolate com recheio duplo de creme de leite e ganache cremoso salpicado com granulado.', 'bolos', '165g / Fatia', 22.00, 8, 'Em Estoque', 5.0),
('redvelvet.png', 'Fatia Bolo Red Velvet Classic', 'Massa aveludada red velvet intercalada com recheio tradicional de cream cheese e migalhas decorativas.', 'bolos', '150g / Fatia', 20.00, 14, 'Em Estoque', 4.9),
('pistache.png', 'Fatia Bolo Pistache com Morango', 'Bolo macio de pistache na cor verde com recheio de creme claro, geleia de morango e topo com morango e hortelã.', 'bolos', '155g / Fatia', 23.50, 10, 'Em Estoque', 4.8),
('cookiechoco.png', 'Cookie Double Chocolate Chips', 'Cookie macio e crocante de massa de cacau intensa, recheado e coberto com gotas de chocolate.', 'cookies', '80g / Unidade', 12.00, 25, 'Em Estoque', 4.9),
('cookienorm.png', 'Cookie Classic Chocolate Chips', 'Cookie artesanal dourado e amanteigado, repleto de gotas de chocolate meio amargo derretidas.', 'cookies', '80g / Unidade', 11.00, 30, 'Em Estoque', 4.8),
('cookiepeda.png', 'Cookie Brownie Chunks', 'Cookie estilo brownie com textura fofinha por dentro e topo coberto por pedaços generosos de chocolate nobre.', 'cookies', '85g / Unidade', 13.50, 20, 'Em Estoque', 5.0),
('brownienorm.png', 'Brownie Tradicional Fudge', 'Brownie clássico de chocolate com casquinha crocante por fora e interior denso, húmido e aveludado.', 'brownies', '70g / Unidade', 9.00, 25, 'Em Estoque', 4.9),
('oreobrown.png', 'Brownie recheado com Oreo', 'Massa de brownie super macia e densa com camada generosa de recheio de biscoito Oreo e topo decorado.', 'brownies', '85g / Unidade', 12.50, 18, 'Em Estoque', 5.0),
('gotasbrown.png', 'Brownie com Gotas de Chocolate', 'Brownie intenso de cacau com gotas crocantes de chocolate meio amargo espalhadas na massa e no topo.', 'brownies', '75g / Unidade', 10.50, 22, 'Em Estoque', 4.8);


INSERT INTO pedidos 
(id_cliente, data_pedido, status_pedido, metodo_coleta, nome_coletor, nota_pedido, subtotal, valor_frete, gorjeta, valor_total, metodo_pagamento, possui_brinde)
VALUES 
(2, '2025-05-16 14:30:00', 'Em preparo', 'Retirada', 'Ana Beatriz', '', 84.90, 0.00, 5.00, 89.90, 'Cartão', FALSE),
(3, '2025-05-16 13:15:00', 'Pendente', 'Entrega', 'Juliana Martins', '', 54.90, 10.00, 0.00, 64.90, 'Pix', FALSE),
(2, CURRENT_TIMESTAMP, 'Pendente', 'Entrega', 'Ana Beatriz', 'Por favor, caprichar na embalagem para presente!', 110.80, 0.00, 5.00, 115.80, 'Pix', FALSE);

INSERT INTO itens_pedido (id_pedido, id_produto, quantidade, preco_unitario)
VALUES 
(1, 7, 2, 15.90),
(1, 12, 2, 18.00),
(2, 9, 1, 23.90),
(3, 1, 2, 12.90),
(3, 6, 1, 48.00),
(3, 12, 2, 18.00);