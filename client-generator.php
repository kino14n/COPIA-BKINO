<?php
declare(strict_types=1);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function slugify(string $value): string
{
    $value = mb_strtolower(trim($value), 'UTF-8');
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    $value = preg_replace('/[^a-z0-9-]+/i', '-', $value) ?? '';
    $value = preg_replace('/--+/', '-', $value) ?? '';
    return trim($value, '-') ?: '';
}

function darkenColor(string $hex, float $factor = 0.2): string
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
        return '#000000';
    }
    $rgb = [];
    for ($i = 0; $i < 6; $i += 2) {
        $component = hexdec(substr($hex, $i, 2));
        $adjusted = max(0, min(255, (int)round($component * (1 - $factor))));
        $rgb[] = str_pad(dechex($adjusted), 2, '0', STR_PAD_LEFT);
    }
    return '#' . implode('', $rgb);
}

$clientName = trim((string)($_POST['client_name'] ?? ''));
$clientSlug = trim((string)($_POST['client_slug'] ?? ''));
$adminUser  = trim((string)($_POST['admin_user'] ?? ''));
$adminPass  = (string)($_POST['admin_pass'] ?? '');
$dbHost     = trim((string)($_POST['db_host'] ?? ''));
$dbName     = trim((string)($_POST['db_name'] ?? ''));
$dbUser     = trim((string)($_POST['db_user'] ?? ''));
$dbPass     = (string)($_POST['db_pass'] ?? '');
$dbPort     = (int)($_POST['db_port'] ?? 3306);
$primaryColor = trim((string)($_POST['primary_color'] ?? '#F87171'));
$primaryHover = trim((string)($_POST['primary_hover'] ?? ''));
$highlighter  = trim((string)($_POST['pdf_highlighter_url'] ?? ''));

if ($clientName === '') {
    respond(['success' => false, 'error' => 'El nombre del cliente es obligatorio'], 422);
}

if ($clientSlug === '') {
    $clientSlug = slugify($clientName);
}
$clientSlug = slugify($clientSlug);
if ($clientSlug === '') {
    respond(['success' => false, 'error' => 'El ID del cliente no es válido'], 422);
}

if ($adminUser === '' || $adminPass === '') {
    respond(['success' => false, 'error' => 'Las credenciales del administrador son obligatorias'], 422);
}

if ($dbHost === '' || $dbName === '' || $dbUser === '' || $dbPass === '') {
    respond(['success' => false, 'error' => 'Todos los campos de la base de datos son obligatorios'], 422);
}

if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
    respond(['success' => false, 'error' => 'El logo es obligatorio y debe subirse correctamente'], 422);
}

$allowedExtensions = ['png', 'svg', 'jpg', 'jpeg', 'webp'];
$logoInfo = $_FILES['logo'];
$logoExtension = strtolower(pathinfo($logoInfo['name'], PATHINFO_EXTENSION));
if (!in_array($logoExtension, $allowedExtensions, true)) {
    respond(['success' => false, 'error' => 'Formato de logo no soportado'], 422);
}

if ($primaryHover === '') {
    $primaryHover = darkenColor($primaryColor);
}

$rootPath = __DIR__;
$clientsDir = $rootPath . '/clientes';
$clientDir  = $clientsDir . '/' . $clientSlug;
$uploadsDir = $rootPath . '/uploads/' . $clientSlug;
$adminDir   = $rootPath . '/admin/' . $clientSlug;
$bcDir      = $rootPath . '/bc/' . $clientSlug;
$templatesAdmin = $rootPath . '/templates/admin-base';
$templatesBc    = $rootPath . '/templates/bc-base';

if (!is_dir($templatesAdmin) || !is_dir($templatesBc)) {
    respond(['success' => false, 'error' => 'No se encontraron las plantillas base requeridas'], 500);
}

if (is_dir($clientDir) || is_dir($adminDir) || is_dir($bcDir)) {
    respond(['success' => false, 'error' => 'Ya existe una instancia para este cliente'], 409);
}

foreach ([$clientsDir, $rootPath . '/uploads', $rootPath . '/admin', $rootPath . '/bc'] as $dir) {
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        respond(['success' => false, 'error' => 'No se pudo preparar la estructura base'], 500);
    }
}

$structureCreated = @mkdir($clientDir, 0775, true) && @mkdir($uploadsDir, 0775, true) && @mkdir($adminDir, 0775, true) && @mkdir($bcDir, 0775, true);
if (!$structureCreated) {
    respond(['success' => false, 'error' => 'No se pudo crear la estructura de carpetas del cliente'], 500);
}

$logoFilename = 'logo.' . $logoExtension;
$logoPath = $clientDir . '/' . $logoFilename;
$logoSaved = move_uploaded_file($logoInfo['tmp_name'], $logoPath);
if (!$logoSaved) {
    respond(['success' => false, 'error' => 'No se pudo guardar el logo del cliente'], 500);
}

function copyTemplateDirectory(string $source, string $destination, array $replacements): bool
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($source));
        $targetPath = $destination . $relative;
        if ($item->isDir()) {
            if (!is_dir($targetPath) && !mkdir($targetPath, 0775, true) && !is_dir($targetPath)) {
                return false;
            }
        } else {
            $contents = file_get_contents($item->getPathname());
            if ($contents === false) {
                return false;
            }
            $contents = strtr($contents, $replacements);
            if (file_put_contents($targetPath, $contents) === false) {
                return false;
            }
        }
    }
    return true;
}

$replacements = ['__CLIENT_SLUG__' => $clientSlug];

if (!copyTemplateDirectory($templatesAdmin, $adminDir, $replacements)) {
    respond(['success' => false, 'error' => 'No se pudieron copiar las plantillas del panel administrador'], 500);
}

if (!copyTemplateDirectory($templatesBc, $bcDir, $replacements)) {
    respond(['success' => false, 'error' => 'No se pudieron copiar las plantillas del portal público'], 500);
}

$configData = [
    'db' => [
        'host'    => $dbHost,
        'port'    => $dbPort ?: 3306,
        'dbname'  => $dbName,
        'user'    => $dbUser,
        'pass'    => $dbPass,
        'charset' => 'utf8mb4',
    ],
    'branding' => [
        'client_name' => $clientName,
        'logo_path'   => 'clientes/' . $clientSlug . '/' . $logoFilename,
        'colors'      => [
            'primary'       => $primaryColor,
            'primary_hover' => $primaryHover,
        ],
    ],
    'admin' => [
        'user'      => $adminUser,
        'pass_hash' => password_hash($adminPass, PASSWORD_DEFAULT),
    ],
];
if ($highlighter !== '') {
    $configData['pdf_highlighter_url'] = $highlighter;
}

$configExport = "<?php\nreturn " . var_export($configData, true) . ";\n";
if (file_put_contents($clientDir . '/config.php', $configExport) === false) {
    respond(['success' => false, 'error' => 'No se pudo escribir el archivo de configuración'], 500);
}

$tablesCreated = false;
$dbError = null;
try {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbHost, $configData['db']['port'], $dbName);
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec('CREATE TABLE IF NOT EXISTS documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        date DATE NOT NULL,
        path VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_date (date),
        INDEX idx_path (path)
    ) ENGINE=InnoDB CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS codes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        document_id INT NOT NULL,
        code VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_document_id (document_id),
        INDEX idx_code (code),
        FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
    ) ENGINE=InnoDB CHARSET=utf8mb4');
    $tablesCreated = true;
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = rtrim($protocol . '://' . $host, '/');
$adminUrl = $baseUrl . '/admin/' . $clientSlug . '/';
$publicUrl = $baseUrl . '/bc/' . $clientSlug . '/';

respond([
    'success' => true,
    'client' => [
        'id'         => $clientSlug,
        'name'       => $clientName,
        'admin_url'  => $adminUrl,
        'public_url' => $publicUrl,
    ],
    'admin' => [
        'url'            => $adminUrl,
        'user'           => $adminUser,
        'password_hint'  => '(la que ingresaste)',
    ],
    'database' => [
        'host' => $dbHost,
        'port' => $configData['db']['port'],
        'name' => $dbName,
        'user' => $dbUser,
    ],
    'branding' => [
        'logo_saved' => $logoSaved,
    ],
    'status' => [
        'structure' => $structureCreated,
        'tables'    => $tablesCreated,
        'db_error'  => $dbError,
    ],
]);
