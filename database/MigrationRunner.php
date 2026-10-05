<?php

require_once __DIR__ . '/../init.php';

class MigrationRunner
{
    private const DATABASE = 'database';

    private const TABLE = 'schema_migrations';

    public static function run()
    {
        $command = $GLOBALS['argv'][1] ?? 'migrate';

        switch ($command) {
            case 'migrate':
                self::migrate();
                break;

            case 'status':
                self::status();
                break;

            case 'rollback':
                self::rollback($GLOBALS['argv'][2] ?? 1);
                break;

            default:
                self::usage();
        }
    }

    private static function migrate()
    {
        // openFake: sem beginTransaction/commit — necessário porque DDL no MySQL
        // encerra a transaction (commit implícito).
        TTransaction::openFake(self::DATABASE);

        try {
            self::createMigrationTable();

            $executed = self::getExecutedMigrations();
            $files = self::getMigrationFiles();
            $ran = 0;

            foreach ($files as $file) {
                $version = self::getVersion($file);

                if (isset($executed[$version])) {
                    continue;
                }

                echo "Migrando " . basename($file) . "... ";

                $migration = self::loadMigration($file);
                $migration['up']();
                self::registerMigration($version, basename($file));

                echo "OK\n";
                $ran++;
            }

            if ($ran === 0) {
                echo "Nenhuma migration pendente.\n";
            }
            else {
                echo "\nBanco atualizado com sucesso.\n";
            }
        }
        catch (Exception $e) {
            echo "\nERRO: " . $e->getMessage() . "\n";
            exit(1);
        }
        finally {
            TTransaction::close();
        }
    }

    private static function rollback($steps = 1)
    {
        $steps = (int) $steps;

        if ($steps < 1) {
            echo "ERRO: informe um número maior que zero.\n";
            echo "Ex.: php database/MigrationRunner.php rollback 2\n";
            exit(1);
        }

        TTransaction::openFake(self::DATABASE);

        try {
            self::createMigrationTable();

            $toRollback = self::getLastExecutedMigrations($steps);

            if (!$toRollback) {
                echo "Nenhuma migration para reverter.\n";
                return;
            }

            $reverted = 0;

            foreach ($toRollback as $row) {
                $file = __DIR__ . '/migrations/' . $row['migration'];

                if (!is_file($file)) {
                    throw new Exception(
                        "Arquivo de migration não encontrado: {$row['migration']}"
                    );
                }

                echo "Revertendo {$row['migration']}... ";

                $migration = self::loadMigration($file);
                $migration['down']();
                self::unregisterMigration($row['version']);

                echo "OK\n";
                $reverted++;
            }

            if ($reverted < $steps) {
                echo "\nRevertidas {$reverted} de {$steps} solicitada(s).\n";
            }
            else {
                echo "\n{$reverted} migration(s) revertida(s).\n";
            }
        }
        catch (Exception $e) {
            echo "\nERRO: " . $e->getMessage() . "\n";
            exit(1);
        }
        finally {
            TTransaction::close();
        }
    }

    private static function status()
    {
        TTransaction::openFake(self::DATABASE);

        try {
            self::createMigrationTable();

            $executed = self::getExecutedMigrations();
            $files = self::getMigrationFiles();

            echo "\nMIGRATIONS\n";
            echo "===============================\n";

            foreach ($files as $file) {
                $version = self::getVersion($file);
                $name = basename($file);

                if (isset($executed[$version])) {
                    echo "[OK]      {$name}\n";
                }
                else {
                    echo "[PENDENTE] {$name}\n";
                }
            }
        }
        catch (Exception $e) {
            echo "ERRO: " . $e->getMessage() . "\n";
            exit(1);
        }
        finally {
            TTransaction::close();
        }
    }

    private static function loadMigration($file)
    {
        $migration = require $file;

        if (!is_array($migration)) {
            throw new Exception(
                "Migration {$file} deve retornar um array com 'up' e 'down'."
            );
        }

        if (empty($migration['up']) || !is_callable($migration['up'])) {
            throw new Exception("Migration {$file} sem Closure 'up'.");
        }

        if (empty($migration['down']) || !is_callable($migration['down'])) {
            throw new Exception("Migration {$file} sem Closure 'down'.");
        }

        return $migration;
    }

    private static function createMigrationTable()
    {
        $pdo = TTransaction::get();

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS " . self::TABLE . " (
                version VARCHAR(20) PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    private static function getExecutedMigrations()
    {
        $pdo = TTransaction::get();

        $stmt = $pdo->query("
            SELECT version
            FROM " . self::TABLE . "
        ");

        $result = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['version']] = true;
        }

        return $result;
    }

    private static function getLastExecutedMigrations($limit = 1)
    {
        $pdo = TTransaction::get();
        $limit = max(1, (int) $limit);

        $stmt = $pdo->query("
            SELECT version, migration
            FROM " . self::TABLE . "
            ORDER BY version DESC
            LIMIT {$limit}
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private static function getMigrationFiles()
    {
        $directory = __DIR__ . '/migrations';

        $files = glob($directory . '/*.php') ?: [];

        sort($files, SORT_NATURAL);

        return $files;
    }

    private static function getVersion($file)
    {
        $filename = basename($file);

        if (!preg_match('/^(\d+)_/', $filename, $matches)) {
            throw new Exception(
                "Nome de migration inválido: {$filename}"
            );
        }

        return $matches[1];
    }

    private static function registerMigration($version, $migration)
    {
        $pdo = TTransaction::get();

        $stmt = $pdo->prepare("
            INSERT INTO " . self::TABLE . "
                (version, migration, executed_at)
            VALUES
                (:version, :migration, CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            ':version'   => $version,
            ':migration' => $migration
        ]);
    }

    private static function unregisterMigration($version)
    {
        $pdo = TTransaction::get();

        $stmt = $pdo->prepare("
            DELETE FROM " . self::TABLE . "
            WHERE version = :version
        ");

        $stmt->execute([
            ':version' => $version
        ]);
    }

    private static function usage()
    {
        echo <<<TXT

Uso:

    php database/MigrationRunner.php migrate
    php database/MigrationRunner.php rollback [n]
    php database/MigrationRunner.php status

Exemplos:

    php database/MigrationRunner.php rollback      # reverte 1
    php database/MigrationRunner.php rollback 3    # reverte as 3 últimas

TXT;
    }
}

MigrationRunner::run();
