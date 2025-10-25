<?php
declare(strict_types=1);

$rootPath = dirname(__DIR__, 2);
$config   = require $rootPath . '/clientes/__CLIENT_SLUG__/config.php';
$branding = $config['branding'] ?? [];
if (!is_array($branding)) {
    $branding = [];
}

$brandName = $branding['client_name'] ?? $branding['name'] ?? $config['BRAND_NAME'] ?? 'Portal de Documentos';
$logoPath  = $branding['logo_path'] ?? $branding['logo'] ?? $config['BRAND_LOGO'] ?? null;
$colors    = $branding['colors'] ?? $config['BRAND_COLORS'] ?? [];
if (!is_array($colors)) {
    $colors = [];
}

$primary       = $colors['primary'] ?? '#F87171';
$primaryHover  = $colors['primary_hover'] ?? '#DC2626';
$onDark        = $colors['on_dark'] ?? '#FFFFFF';
$onLight       = $colors['on_light'] ?? '#1F2937';

$logoUrl = $logoPath ? '../../' . ltrim($logoPath, '/') : null;
?>
<!DOCTYPE html>
<html lang="es" data-client="__CLIENT_SLUG__">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <script src="https://cdn.tailwindcss.com"></script>
  <title><?php echo htmlspecialchars($brandName); ?> – Portal Público</title>
  <style>
    :root {
      --color-primary: <?php echo $primary; ?>;
      --color-primary-hover: <?php echo $primaryHover; ?>;
      --color-on-dark: <?php echo $onDark; ?>;
      --color-on-light: <?php echo $onLight; ?>;
    }
    .relative { position: relative; }
    #suggestions {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: white;
      border: 1px solid #e5e7eb;
      border-top: none;
      border-radius: 0 0 .375rem .375rem;
      max-height: 12rem;
      overflow-y: auto;
      -webkit-overflow-scrolling: touch;
      display: none;
      z-index: 9999;
    }
    #suggestions div:hover { background-color: #f3f4f6; }
    .btn-primary {
      background: var(--color-primary);
      color: var(--color-on-dark);
      transition: background-color 0.2s ease;
    }
    .btn-primary:hover {
      background: var(--color-primary-hover);
    }
    .btn-secondary {
      background: #e5e7eb;
      color: var(--color-on-light);
      transition: background-color 0.2s ease;
    }
    .btn-secondary:hover {
      background: #d1d5db;
    }
  </style>
  <script>
    window.__CLIENT_BRANDING__ = <?php echo json_encode([
        'name' => $brandName,
        'logo' => $logoUrl,
        'colors' => [
            'primary'       => $primary,
            'primary_hover' => $primaryHover,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  </script>
  <script defer src="script.js"></script>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col items-center justify-start p-4 sm:p-6">
  <div class="w-full max-w-3xl bg-white rounded-2xl shadow-lg overflow-visible">
    <div class="px-6 pt-6 pb-4 text-center">
      <?php if ($logoUrl): ?>
        <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Logo <?php echo htmlspecialchars($brandName); ?>" class="mx-auto h-20 sm:h-24 mb-6" />
      <?php endif; ?>
      <h1 class="text-2xl sm:text-3xl font-bold mb-3" id="brandTitle"><?php echo htmlspecialchars($brandName); ?></h1>
      <p class="text-gray-700 text-sm sm:text-base mb-2 leading-relaxed">Consulta los documentos disponibles ingresando el código del producto.</p>
    </div>

    <div class="border-t">
      <div class="p-6 sm:p-8">
        <h2 class="text-2xl sm:text-3xl font-semibold mb-4">Búsqueda por Código</h2>
        <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 mb-4">
          <div class="relative flex-1">
            <input
              id="codeInput"
              type="text"
              placeholder="Código a buscar"
              class="w-full sm:flex-1 border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]"
            />
            <div id="suggestions"></div>
          </div>
          <button id="btnCodeSearch" class="btn-primary w-full sm:w-auto px-6 py-2 rounded-lg text-center font-semibold">Buscar</button>
          <button id="btnCodeClear" class="btn-secondary w-full sm:w-auto px-6 py-2 rounded-lg text-center font-semibold">Limpiar</button>
        </div>
        <div id="code-alert" class="mb-4 text-red-600 font-medium"></div>
        <div id="results-code" class="space-y-4"></div>
      </div>
    </div>
  </div>

  <footer class="w-full max-w-3xl mt-6 text-center text-gray-600 text-sm">
    <p class="mb-1">Portal administrado por <?php echo htmlspecialchars($brandName); ?>.</p>
    <div class="mt-4">
      <button id="btnLegal" class="text-[var(--color-primary)] hover:underline font-medium">Aviso Legal</button>
    </div>
  </footer>

  <div id="legalModal" class="fixed inset-0 z-50 bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white max-w-md w-full p-6 rounded-xl shadow-lg relative">
      <button id="btnCloseLegal" class="absolute top-2 right-3 text-gray-500 hover:text-[var(--color-primary)] text-xl">&times;</button>
      <h2 class="text-lg font-bold text-gray-800 mb-3">Aviso Legal</h2>
      <p class="text-sm text-gray-700 leading-relaxed">
        Los documentos disponibles en este portal son propiedad de <strong><?php echo htmlspecialchars($brandName); ?></strong> y están destinados exclusivamente a consulta. Cualquier uso no autorizado será sancionado conforme a la legislación vigente.
      </p>
    </div>
  </div>
</body>
</html>
