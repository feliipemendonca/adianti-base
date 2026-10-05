<?php

return [
    'run' => function () {
        $pdo = TTransaction::get();

        $clientes = [
            ['name' => 'Cliente Demo', 'email' => 'demo@example.com'],
            ['name' => 'Ana Silva', 'email' => 'ana.silva@example.com'],
            ['name' => 'Bruno Costa', 'email' => 'bruno.costa@example.com'],
        ];

        $check = $pdo->prepare("SELECT COUNT(*) FROM clientes WHERE email = ?");
        $insert = $pdo->prepare("INSERT INTO clientes (name, email) VALUES (?, ?)");

        foreach ($clientes as $cliente) {
            $check->execute([$cliente['email']]);

            if ((int) $check->fetchColumn() === 0) {
                $insert->execute([$cliente['name'], $cliente['email']]);
            }
        }
    },
];
