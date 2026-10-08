<?php
$host = "localhost";
$port = 3306;
$dbname = "db_docemaria";
$username = "root";
$password = "";

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_EMULATE_PREPARES => false]
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    function create($pdo, $table, array $data) {
        validarIdentificador($table);
        foreach (array_keys($data) as $column) {
            validarIdentificador($column);
        }

        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO `$table` (`" . implode('`, `', array_keys($data)) . "`) VALUES ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($data));
        return $pdo->lastInsertId();
    }

    function readAll($pdo, $table, $where = null, array $params = []) {
        validarIdentificador($table);
        $sql = "SELECT * FROM `$table`";
        if ($where) {
            $sql .= " WHERE $where";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    function read($pdo, $table, $where = null, array $params = []) {
        validarIdentificador($table);
        $sql = "SELECT * FROM `$table`";
        if ($where) {
            $sql .= " WHERE $where";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    function read_nome_via_ID($pdo, $table, $id) {
        validarIdentificador($table);
        $sql = "SELECT nome FROM `$table` WHERE id_user = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultado ? $resultado['nome'] : "Desconhecido";
    }

    function update($pdo, $table, array $data, $where, array $whereParams = []) {
        validarIdentificador($table);
        $set = [];
        foreach ($data as $column => $value) {
            validarIdentificador($column);
            $set[] = "`$column` = ?";
        }
        $set = implode(', ', $set);

        $sql = "UPDATE `$table` SET $set WHERE $where";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge(array_values($data), $whereParams));
        return $stmt->rowCount();
    }

    function delete($pdo, $table, $where, array $params = []) {
        validarIdentificador($table);
        $sql = "DELETE FROM `$table` WHERE $where";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }

    function validarIdentificador($identifier) {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier)) {
            throw new InvalidArgumentException('Identificador SQL inválido.');
        }
    }
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}