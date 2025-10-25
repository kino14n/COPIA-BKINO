<?php
declare(strict_types=1);

header('Content-Type: application/json');

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sanitizeSlug(string $slug): string
{
    $slug = strtolower(trim($slug));
    $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug) ?? '';
    $slug = preg_replace('/--+/', '-', $slug) ?? '';
    return trim($slug, '-');
}

function removeDirectory(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }
    @rmdir($path);
}

$action = $_REQUEST['action'] ?? 'list';
$rootPath = __DIR__;
$clientsDir = $rootPath . '/clientes';
$uploadsDir = $rootPath . '/uploads';
$adminDir   = $rootPath . '/admin';
$bcDir      = $rootPath . '/bc';
$logsDir    = $rootPath . '/logs';
$logFile    = $logsDir . '/client-manager.log';

if (!is_dir($logsDir)) {
    @mkdir($logsDir, 0775, true);
}

switch ($action) {
    case 'list':
        $clients = [];
        if (is_dir($clientsDir)) {
            $finder = glob($clientsDir . '/*/config.php');
            if ($finder !== false) {
                foreach ($finder as $configPath) {
                    $slug = basename(dirname($configPath));
                    $config = @include $configPath;
                    if (!is_array($config)) {
                        continue;
                    }
                    $brandingSource = [];
                    if (isset($config['branding']) && is_array($config['branding'])) {
                        $brandingSource = $config['branding'];
                    }
                    $branding = $brandingSource['client_name'] ?? $brandingSource['name'] ?? $config['BRAND_NAME'] ?? $slug;
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $baseUrl = rtrim($protocol . '://' . $host, '/');
                    $clients[] = [
                        'id'         => $slug,
                        'name'       => $branding,
                        'admin_url'  => $baseUrl . '/admin/' . $slug . '/',
                        'public_url' => $baseUrl . '/bc/' . $slug . '/',
                    ];
                }
            }
        }
        respond(['success' => true, 'clients' => $clients]);

    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(['success' => false, 'error' => 'Método no permitido'], 405);
        }
        $client = sanitizeSlug((string)($_POST['client'] ?? ''));
        if ($client === '') {
            respond(['success' => false, 'error' => 'Cliente inválido'], 422);
        }

        $paths = [
            $clientsDir . '/' . $client,
            $uploadsDir . '/' . $client,
            $adminDir . '/' . $client,
            $bcDir . '/' . $client,
        ];
        foreach ($paths as $path) {
            removeDirectory($path);
        }

        $logEntry = sprintf("%s\tDELETE\t%s\t%s\n", date('c'), $client, $_SERVER['REMOTE_ADDR'] ?? 'cli');
        @file_put_contents($logFile, $logEntry, FILE_APPEND);

        respond(['success' => true]);

    default:
        respond(['success' => false, 'error' => 'Acción no soportada'], 400);
}
