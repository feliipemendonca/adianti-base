<?php

require_once __DIR__ . '/../init.php';

class SeedRunner
{
    private const DATABASE = 'database';

    private const TABLE = 'schema_seeders';

    public static function run()
    {
        $command = $GLOBALS['argv'][1] ?? 'seed';

        switch ($command) {
            case 'seed':
                self::seed();
                break;

            case 'fresh':
                self::fresh();
                break;

            case 'status':
                self::status();
                break;

            default:
                self::usage();
        }
    }

    private static function seed()
    {
        TTransaction::openFake(self::DATABASE);

        try {
            self::createSeederTable();

            $executed = self::getExecutedSeeders();
            $files = self::getSeederFiles();
            $ran = 0;

            foreach ($files as $file) {
                $version = self::getVersion($file);

                if (isset($executed[$version])) {
                    continue;
                }

                echo "Seedando " . basename($file) . "... ";

                $seeder = self::loadSeeder($file);
                $seeder['run']();
                self::registerSeeder($version, basename($file));

                echo "OK\n";
                $ran++;
            }

            if ($ran === 0) {
                echo "Nenhum seeder pendente.\n";
            }
            else {
                echo "\n{$ran} seeder(s) executado(s) com sucesso.\n";
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

    private static function fresh()
    {
        TTransaction::openFake(self::DATABASE);

        try {
            self::createSeederTable();

            $pdo = TTransaction::get();
            $pdo->exec("DELETE FROM " . self::TABLE);

            echo "Controle de seeders limpo.\n\n";
        }
        catch (Exception $e) {
            echo "\nERRO: " . $e->getMessage() . "\n";
            exit(1);
        }
        finally {
            TTransaction::close();
        }

        self::seed();
    }

    private static function status()
    {
        TTransaction::openFake(self::DATABASE);

        try {
            self::createSeederTable();

            $executed = self::getExecutedSeeders();
            $files = self::getSeederFiles();

            echo "\nSEEDERS\n";
            echo "===============================\n";

            if (!$files) {
                echo "(nenhum seeder encontrado)\n";
                return;
            }

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

    private static function loadSeeder($file)
    {
        $seeder = require $file;

        if (!is_array($seeder)) {
            throw new Exception(
                "Seeder {$file} deve retornar um array com 'run'."
            );
        }

        if (empty($seeder['run']) || !is_callable($seeder['run'])) {
            throw new Exception("Seeder {$file} sem Closure 'run'.");
        }

        return $seeder;
    }

    private static function createSeederTable()
    {
        $pdo = TTransaction::get();

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS " . self::TABLE . " (
                version VARCHAR(20) PRIMARY KEY,
                seeder VARCHAR(255) NOT NULL,
                executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    private static function getExecutedSeeders()
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

    private static function getSeederFiles()
    {
        $directory = __DIR__ . '/seeders';

        if (!is_dir($directory)) {
            return [];
        }

        $files = glob($directory . '/*.php') ?: [];

        sort($files, SORT_NATURAL);

        return $files;
    }

    private static function getVersion($file)
    {
        $filename = basename($file);

        if (!preg_match('/^(\d+)_/', $filename, $matches)) {
            throw new Exception(
                "Nome de seeder inválido: {$filename}"
            );
        }

        return $matches[1];
    }

    private static function registerSeeder($version, $seeder)
    {
        $pdo = TTransaction::get();

        $stmt = $pdo->prepare("
            INSERT INTO " . self::TABLE . "
                (version, seeder, executed_at)
            VALUES
                (:version, :seeder, CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            ':version' => $version,
            ':seeder'  => $seeder
        ]);
    }

    private static function usage()
    {
        echo <<<TXT

Uso:

    php database/SeedRunner.php seed
    php database/SeedRunner.php fresh
    php database/SeedRunner.php status

Exemplos:

    php database/SeedRunner.php seed     # executa seeders pendentes
    php database/SeedRunner.php fresh    # limpa controle e executa todos de novo
    php database/SeedRunner.php status   # lista OK / PENDENTE

TXT;
    }
}

SeedRunner::run();
