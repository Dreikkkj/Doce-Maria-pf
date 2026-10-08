<?php

function product_categories(PDO $pdo): array
{
    $column = $pdo->query("SHOW COLUMNS FROM produtos LIKE 'categoria'")->fetch(PDO::FETCH_ASSOC);
    if (!$column || preg_match("/^enum\\((.*)\\)$/i", (string) $column['Type'], $matches) !== 1) {
        throw new RuntimeException('A coluna categoria da tabela produtos não possui opções ENUM válidas.');
    }

    return str_getcsv($matches[1], ',', "'", '\\');
}
