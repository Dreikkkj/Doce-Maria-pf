<?php

function loyalty_ensure_redemptions_table(PDO $pdo): void
{
    static $tableCreated = false;
    if ($tableCreated) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS fidelidade_resgates (
            id_resgate INT AUTO_INCREMENT PRIMARY KEY,
            id_cliente INT NOT NULL,
            id_produto INT NULL,
            nome_produto VARCHAR(100) NOT NULL,
            pontos_gastos INT NOT NULL,
            status ENUM('Pendente', 'Entregue') NOT NULL DEFAULT 'Pendente',
            data_resgate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_resgates_cliente (id_cliente),
            CONSTRAINT fk_resgate_cliente FOREIGN KEY (id_cliente) REFERENCES usuarios (id_user),
            CONSTRAINT fk_resgate_produto FOREIGN KEY (id_produto) REFERENCES produtos (id_produto) ON DELETE SET NULL
        ) ENGINE=InnoDB"
    );
    $tableCreated = true;
}

function loyalty_points_for_user(PDO $pdo, int $userId): int
{
    loyalty_ensure_redemptions_table($pdo);
    $statement = $pdo->prepare(
        "SELECT
            COALESCE((
                SELECT ROUND(SUM(subtotal) * 10)
                FROM pedidos
                WHERE id_cliente = :id_cliente_orders
                  AND status_pedido <> 'Cancelado'
            ), 0) -
            COALESCE((
                SELECT SUM(pontos_gastos)
                FROM fidelidade_resgates
                WHERE id_cliente = :id_cliente_redemptions
            ), 0)"
    );
    $statement->execute([
        ':id_cliente_orders' => $userId,
        ':id_cliente_redemptions' => $userId,
    ]);

    return max(0, (int) $statement->fetchColumn());
}
