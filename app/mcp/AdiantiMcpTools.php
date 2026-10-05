<?php

declare(strict_types=1);

use PhpMcp\Server\Attributes\McpTool;

class AdiantiMcpTools
{
    #[McpTool(name: 'ping', description: 'Verifica se o MCP PHP do projeto Adianti está respondendo')]
    public function ping(): array
    {
        return [
            'ok' => true,
            'project' => 'adianti',
            'php' => PHP_VERSION,
            'time' => date('c'),
        ];
    }

    #[McpTool(name: 'project_info', description: 'Retorna informações básicas do projeto Adianti')]
    public function projectInfo(): array
    {
        $configPath = dirname(__DIR__) . '/config/application.php';
        $config = is_file($configPath) ? require $configPath : [];

        return [
            'application' => $config['general']['application'] ?? null,
            'title' => $config['general']['title'] ?? null,
            'theme' => $config['general']['theme'] ?? null,
            'language' => $config['general']['language'] ?? null,
            'base_path' => dirname(__DIR__, 2),
        ];
    }
}
