<?php 
// api.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// —————————————————————————————
// Cabecera y configuración multi-cliente
// —————————————————————————————
header('Content-Type: application/json');

function respondError(string $message, int $statusCode = 400): void {
    http_response_code($statusCode);
    echo json_encode(['error' => $message]);
    exit;
}

function sanitizeSlug(?string $slug): ?string {
    if ($slug === null) {
        return null;
    }
    $slug = strtolower(trim($slug));
    if ($slug === '') {
        return null;
    }
    $slug = preg_replace('/[^a-z0-9_-]/', '', $slug);
    return $slug ?: null;
}

function detectClientSlug(): string {
    $candidates = [];

    if (isset($_REQUEST['client'])) {
        $candidates[] = $_REQUEST['client'];
    }

    $headers = [
        $_SERVER['HTTP_X_CLIENT']      ?? null,
        $_SERVER['HTTP_X_CLIENT_SLUG'] ?? null,
    ];
    $candidates = array_merge($candidates, $headers);

    $hostHeader = $_SERVER['HTTP_HOST'] ?? '';
    if ($hostHeader !== '') {
        $hostWithoutPort = preg_replace('/:\\d+$/', '', strtolower($hostHeader));
        $parts = explode('.', $hostWithoutPort);
        $hasSubdomain = count($parts) > 2 || (count($parts) === 2 && $parts[1] === 'localhost');
        if ($hasSubdomain) {
            $firstPart = $parts[0] === 'www' ? ($parts[1] ?? null) : $parts[0];
            if ($firstPart) {
                $candidates[] = $firstPart;
            }
        }
    }

    foreach ($candidates as $candidate) {
        $slug = sanitizeSlug($candidate);
        if ($slug !== null) {
            return $slug;
        }
    }

    respondError('Cliente no especificado o slug inválido', 400);
}

$clientSlug = detectClientSlug();
$configPath = __DIR__ . '/clientes/' . $clientSlug . '/config.php';
if (!is_file($configPath)) {
    respondError('Configuración del cliente no encontrada', 404);
}

$config = require $configPath;
if (!is_array($config)) {
    respondError('Configuración del cliente inválida', 500);
}

$requiredKeys = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
foreach ($requiredKeys as $key) {
    if (!array_key_exists($key, $config) || $config[$key] === '') {
        respondError("Falta la clave obligatoria {$key} en la configuración del cliente", 500);
    }
}

$dbCharset = $config['DB_CHARSET'] ?? 'utf8mb4';
$dbPort    = (int)($config['DB_PORT'] ?? 3306);
$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $config['DB_HOST'],
    $dbPort,
    $config['DB_NAME'],
    $dbCharset
);

try {
    $db = new PDO($dsn, $config['DB_USER'], $config['DB_PASS'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    respondError('Error de conexión: ' . $e->getMessage(), 500);
}

$branding = [
    'name'   => $config['BRAND_NAME']   ?? null,
    'logo'   => $config['BRAND_LOGO']   ?? null,
    'colors' => $config['BRAND_COLORS'] ?? [],
];

$uploadsRootDir   = __DIR__ . '/uploads';
$clientUploadsDir = $uploadsRootDir . '/' . $clientSlug;
if (!is_dir($clientUploadsDir) && !mkdir($clientUploadsDir, 0775, true) && !is_dir($clientUploadsDir)) {
    respondError('No se pudo preparar la carpeta de archivos del cliente', 500);
}

function buildStoredUploadPath(string $filename, string $clientSlug): string
{
    return 'uploads/' . $clientSlug . '/' . ltrim($filename, '/');
}

function resolveUploadFullPath(?string $storedPath, string $clientSlug): ?string
{
    if (!$storedPath) {
        return null;
    }
    if (strpos($storedPath, 'uploads/') === 0) {
        return __DIR__ . '/' . $storedPath;
    }

    $candidate = __DIR__ . '/uploads/' . $clientSlug . '/' . ltrim($storedPath, '/');
    if (file_exists($candidate)) {
        return $candidate;
    }

    return __DIR__ . '/uploads/' . ltrim($storedPath, '/');
}

function normalizeDocumentPath(?string $storedPath, string $clientSlug): ?string
{
    if ($storedPath === null || $storedPath === '') {
        return $storedPath;
    }

    if (strpos($storedPath, 'uploads/') === 0) {
        return $storedPath;
    }

    return buildStoredUploadPath($storedPath, $clientSlug);
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {

  // —— AUTOCOMPLETE SUGGEST ——  
  case 'suggest':
    $term = trim($_GET['term'] ?? '');
    if ($term === '') {
      echo json_encode([]);
      exit;
    }
    $stmt = $db->prepare("
      SELECT DISTINCT code 
      FROM codes 
      WHERE code LIKE ? 
      ORDER BY code ASC 
      LIMIT 10
    ");
    $stmt->execute([$term . '%']);
    $codes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($codes);
    break;

  // —— SUBIR NUEVO DOCUMENTO ——  
  case 'upload':
    $name  = $_POST['name'];
    $date  = $_POST['date'];
    $codes = array_filter(array_map('trim', preg_split('/\r?\n/', $_POST['codes'] ?? '')));
    $file  = $_FILES['file'];
    $filename = time().'_'.basename($file['name']);
    $storedPath  = buildStoredUploadPath($filename, $clientSlug);
    $destination = __DIR__ . '/' . $storedPath;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
      respondError('No se pudo subir el PDF', 500);
    }
    $db->prepare('INSERT INTO documents (name,date,path) VALUES (?,?,?)')
       ->execute([$name,$date,$storedPath]);
    $docId = $db->lastInsertId();
    $ins = $db->prepare('INSERT INTO codes (document_id,code) VALUES (?,?)');
    foreach (array_unique($codes) as $c) {
      $ins->execute([$docId,$c]);
    }
    echo json_encode(['message'=>'Documento guardado']);
    break;

  // —— LISTAR CON PAGINACIÓN ——  
  case 'list':
    $page    = max(1,(int)($_GET['page'] ?? 1));
    $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 50;
    $total   = (int)$db->query("SELECT COUNT(*) FROM documents")->fetchColumn();

    if ($perPage === 0) {
      $stmt = $db->query("
        SELECT d.id,d.name,d.date,d.path,
               GROUP_CONCAT(c.code SEPARATOR '\n') AS codes
        FROM documents d
        LEFT JOIN codes c ON d.id=c.document_id
        GROUP BY d.id
        ORDER BY d.date DESC
      ");
      $rows = $stmt->fetchAll();
      $lastPage = 1;
      $page = 1;
    } else {
      $perPage = max(1, min(50, $perPage));
      $offset  = ($page - 1) * $perPage;
      $lastPage = (int)ceil($total / $perPage);

      $stmt = $db->prepare("
        SELECT d.id,d.name,d.date,d.path,
               GROUP_CONCAT(c.code SEPARATOR '\n') AS codes
        FROM documents d
        LEFT JOIN codes c ON d.id=c.document_id
        GROUP BY d.id
        ORDER BY d.date DESC
        LIMIT :l OFFSET :o
      ");
      $stmt->bindValue(':l',$perPage,PDO::PARAM_INT);
      $stmt->bindValue(':o',$offset ,PDO::PARAM_INT);
      $stmt->execute();
      $rows = $stmt->fetchAll();
    }

    $docs = array_map(function($r) use ($clientSlug){
      return [
        'id'    => (int)$r['id'],
        'name'  => $r['name'],
        'date'  => $r['date'],
        'path'  => normalizeDocumentPath($r['path'], $clientSlug),
        'codes' => $r['codes'] ? explode("\n",$r['codes']) : []
      ];
    }, $rows);

    echo json_encode([
      'total'     => $total,
      'page'      => $page,
      'per_page'  => $perPage,
      'last_page' => $lastPage,
      'data'      => $docs
    ]);
    break;

  // —— BÚSQUEDA INTELIGENTE VORAZ ——  
  case 'search':
    $codes = array_filter(array_map('trim', preg_split('/\r?\n/', $_POST['codes'] ?? '')));
    if (empty($codes)) {
      echo json_encode([]);
      exit;
    }

   // Usar UPPER para insensibilidad a mayúsculas/minúsculas
  $cond = implode(" OR ", array_fill(0, count($codes), "UPPER(c.code) = UPPER(?)"));
  $stmt = $db->prepare("
    SELECT d.id,d.name,d.date,d.path,c.code
    FROM documents d
    JOIN codes c ON d.id=c.document_id
    WHERE $cond
  ");
  $stmt->execute($codes);
  $rows = $stmt->fetchAll();

    $docs = [];
    foreach ($rows as $r) {
      $id = (int)$r['id'];
      if (!isset($docs[$id])) {
        $docs[$id] = [
          'id'    => $id,
          'name'  => $r['name'],
          'date'  => $r['date'],
          'path'  => normalizeDocumentPath($r['path'], $clientSlug),
          'codes' => []
        ];
      }
      if (!in_array($r['code'], $docs[$id]['codes'], true)) {
        $docs[$id]['codes'][] = $r['code'];
      }
    }

    $remaining = $codes;
    $selected  = [];
    while ($remaining) {
      $best      = null;
      $bestCover = [];
      foreach ($docs as $d) {
        $cover = array_intersect($d['codes'], $remaining);
        if (!$best
            || count($cover) > count($bestCover)
            || (count($cover) === count($bestCover) && $d['date'] > $best['date'])
        ) {
          $best      = $d;
          $bestCover = $cover;
        }
      }
      if (!$best || empty($bestCover)) break;
      $selected[] = $best;
      $remaining = array_diff($remaining, $bestCover);
      unset($docs[$best['id']]);
    }

    echo json_encode(array_values($selected));
    break;

  // —— ACCIÓN: DESCARGAR TODOS LOS PDFS EN ZIP ——  
  case 'download_pdfs':
    $uploadsDir = $clientUploadsDir;
    if (!is_dir($uploadsDir)) {
      respondError('Carpeta uploads del cliente no encontrada', 404);
    }

    // Crear ZIP en tmp
    $tmpFile = tempnam(sys_get_temp_dir(), 'zip');
    $zip = new ZipArchive();
    if ($zip->open($tmpFile, ZipArchive::CREATE) !== TRUE) {
      respondError('No se pudo crear el ZIP', 500);
    }

    // Agregar recursivamente todos los archivos de uploads
    $files = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($uploadsDir),
      RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($files as $file) {
      if (!$file->isDir()) {
        $filePath     = $file->getRealPath();
        $relativePath = substr($filePath, strlen($uploadsDir) + 1);
        $zip->addFile($filePath, $relativePath);
      }
    }
    $zip->close();

    // Cabeceras para descarga ZIP
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="uploads_'.$clientSlug.'_'.date('Ymd_His').'.zip"');

    // Enviar contenido
    readfile($tmpFile);
    unlink($tmpFile);
    exit;

  // —— EDITAR DOCUMENTO ——  
  case 'edit':
    $id   = (int)$_POST['id'];
    $name = $_POST['name'];
    $date = $_POST['date'];
    $codes= array_filter(array_map('trim', preg_split('/\r?\n/', $_POST['codes'] ?? '')));
    if (!empty($_FILES['file']['tmp_name'])) {
      $old = $db->prepare('SELECT path FROM documents WHERE id=?');
      $old->execute([$id]);
      $oldPath = $old->fetchColumn();
      if ($oldPath) {
        $fullOldPath = resolveUploadFullPath($oldPath, $clientSlug);
        if ($fullOldPath) {
          @unlink($fullOldPath);
        }
      }
      $fn = time().'_'.basename($_FILES['file']['name']);
      $storedPath = buildStoredUploadPath($fn, $clientSlug);
      $fullPath   = __DIR__ . '/' . $storedPath;
      if (!move_uploaded_file($_FILES['file']['tmp_name'], $fullPath)) {
        respondError('No se pudo subir el PDF actualizado', 500);
      }
      $db->prepare('UPDATE documents SET name=?,date=?,path=? WHERE id=?')
         ->execute([$name,$date,$storedPath,$id]);
    } else {
      $db->prepare('UPDATE documents SET name=?,date=? WHERE id=?')
         ->execute([$name,$date,$id]);
    }
    $db->prepare('DELETE FROM codes WHERE document_id=?')->execute([$id]);
    $ins = $db->prepare('INSERT INTO codes (document_id,code) VALUES (?,?)');
    foreach (array_unique($codes) as $c) {
      $ins->execute([$id,$c]);
    }
    echo json_encode(['message'=>'Documento actualizado']);
    break;

    if (!$id || !$name || !$date) {
  echo json_encode(['error' => 'Faltan campos obligatorios']);
  exit;
}

  // —— ELIMINAR DOCUMENTO ——  
  case 'delete':
    $id = (int)($_GET['id'] ?? 0);
    $old = $db->prepare('SELECT path FROM documents WHERE id=?');
    $old->execute([$id]);
    $oldPath = $old->fetchColumn();
    if ($oldPath) {
      $fullPath = resolveUploadFullPath($oldPath, $clientSlug);
      if ($fullPath) {
        @unlink($fullPath);
      }
    }
    $db->prepare('DELETE FROM codes WHERE document_id=?')->execute([$id]);
    $db->prepare('DELETE FROM documents WHERE id=?')->execute([$id]);
    echo json_encode(['message'=>'Documento eliminado']);
    break;

// —— BÚSQUEDA POR CÓDIGO ——  
case 'search_by_code':
  $code = trim($_POST['code'] ?? $_GET['code'] ?? '');
  if (!$code) {
    echo json_encode([]);
    exit;
  }

  // Trae todos los códigos asociados al documento donde existe el código buscado (insensible a mayúsculas)
  $stmt = $db->prepare("
    SELECT d.id, d.name, d.date, d.path, GROUP_CONCAT(c2.code SEPARATOR '\n') AS codes
    FROM documents d
    JOIN codes c1 ON d.id = c1.document_id
    LEFT JOIN codes c2 ON d.id = c2.document_id
    WHERE UPPER(c1.code) = UPPER(?)
    GROUP BY d.id
  ");
  $stmt->execute([$code]);
  $rows = $stmt->fetchAll();

  $docs = array_map(function($r) use ($clientSlug){
    return [
      'id'    => (int)$r['id'],
      'name'  => $r['name'],
      'date'  => $r['date'],
      'path'  => normalizeDocumentPath($r['path'], $clientSlug),
      'codes' => $r['codes'] ? explode("\n", $r['codes']) : []
    ];
  }, $rows);

  echo json_encode($docs);
  break;

  case 'branding':
    echo json_encode($branding);
    break;

  default:
    echo json_encode(['error'=>'Acción inválida']);
    break;
}
