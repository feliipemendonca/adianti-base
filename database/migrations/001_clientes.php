<?php

return [
    'up' => function () {
        $pdo = TTransaction::get();

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS clientes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100),
                email VARCHAR(150)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },

    'down' => function () {
        $pdo = TTransaction::get();

        $pdo->exec("DROP TABLE IF EXISTS clientes");
    },
];
