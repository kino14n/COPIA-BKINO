<?php
declare(strict_types=1);

$rootPath = dirname(__DIR__, 2);
$config   = require $rootPath . '/clientes/__CLIENT_SLUG__/config.php';
$branding = $config['branding'] ?? [];
if (!is_array($branding)) {
    $branding = [];
}

$brandName = $branding['client_name'] ?? $branding['name'] ?? $config['BRAND_NAME'] ?? 'Panel Administrativo';
$logoPath  = $branding['logo_path'] ?? $branding['logo'] ?? $config['BRAND_LOGO'] ?? null;
$colors    = $branding['colors'] ?? $config['BRAND_COLORS'] ?? [];
if (!is_array($colors)) {
    $colors = [];
}

$primary       = $colors['primary'] ?? '#F87171';
$primaryHover  = $colors['primary_hover'] ?? '#DC2626';
$secondary     = $colors['secondary'] ?? '#D1D5DB';
$secondaryHover= $colors['secondary_hover'] ?? '#9CA3AF';
$dark          = $colors['dark'] ?? '#374151';
$darkHover     = $colors['dark_hover'] ?? '#1F2937';
$warning       = $colors['warning'] ?? '#8B5E5E';
$warningHover  = $colors['warning_hover'] ?? '#6B4542';
$onLight       = $colors['on_light'] ?? '#1F2937';
$onDark        = $colors['on_dark'] ?? '#FFFFFF';

$logoUrl = $logoPath ? '../../' . ltrim($logoPath, '/') : null;
?>
<!DOCTYPE html>
<html lang="es" data-client="__CLIENT_SLUG__">
<head>
  <meta charset="UTF-8" />
  <meta name="google" content="notranslate" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
  <meta http-equiv="Pragma" content="no-cache" />
  <meta http-equiv="Expires" content="0" />
  <script src="https://cdn.tailwindcss.com"></script>
  <title><?php echo htmlspecialchars($brandName); ?> – Panel Administrativo</title>
  <style>
    .overlay { position: fixed; top:0; left:0; width:100vw; height:100vh; background: rgba(0,0,0,0.6); display:flex; align-items:center; justify-content:center; z-index:9999; }
    .overlay.hidden { display:none; }
    .modal { background:white; border-radius:0.5rem; max-width:360px; width:90%; padding:2rem; box-shadow:0 2px 10px rgba(0,0,0,0.3); text-align:center; }
    .modal input, .modal button { font-size:1rem; }
    .modal label { display:block; text-align:left; font-weight:600; margin-top:0.75rem; }
    .modal input { width:100%; padding:0.6rem; margin-top:0.3rem; border:1px solid #d1d5db; border-radius:0.375rem; }
    .modal button { margin-top:1rem; width:100%; }
    #toast-container { position:fixed; top:1rem; left:50%; transform:translateX(-50%); display:flex; flex-direction:column; gap:0.5rem; z-index:10000; }
    .toast { min-width:240px; max-width:480px; background-color:var(--color-primary); color:var(--color-on-dark); padding:1rem 1.5rem; border-radius:0.375rem; box-shadow:0 2px 6px rgba(0,0,0,0.2); display:flex; align-items:center; justify-content:space-between; font-size:1.125rem; }
    .toast button { background:transparent; border:none; color:inherit; font-weight:bold; margin-left:1rem; cursor:pointer; font-size:1.125rem; }
    #confirmOverlay, #deleteOverlay { position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.6); display:flex; align-items:center; justify-content:center; z-index:10001; }
    #confirmOverlay.hidden, #deleteOverlay.hidden { display:none; }
    .modal.confirm, .modal.deleteKey { background:white; border-radius:0.5rem; padding:1.5rem; max-width:320px; width:90%; box-shadow:0 2px 10px rgba(0,0,0,0.3); text-align:center; }
    .modal.confirm button, .modal.deleteKey button { margin:0.5rem; padding:0.5rem 1rem; font-size:1rem; border-radius:0.25rem; }
    .tab.active { border-bottom:2px solid var(--color-primary); color:var(--color-primary); }
    .button-group { display: flex; flex-direction: column; align-items: stretch; gap: 5px; }
    :root {
      --btn-padding: 0.5rem 1rem;
      --btn-radius: 0.375rem;
      --btn-font-size: 1rem;
      --btn-transition: background-color .2s;
      --color-primary: <?php echo $primary; ?>;
      --color-primary-hover: <?php echo $primaryHover; ?>;
      --color-secondary: <?php echo $secondary; ?>;
      --color-secondary-hover: <?php echo $secondaryHover; ?>;
      --color-dark: <?php echo $dark; ?>;
      --color-dark-hover: <?php echo $darkHover; ?>;
      --color-warning: <?php echo $warning; ?>;
      --color-warning-hover: <?php echo $warningHover; ?>;
      --color-on-light: <?php echo $onLight; ?>;
      --color-on-dark: <?php echo $onDark; ?>;
    }
    .btn { padding: var(--btn-padding); border-radius: var(--btn-radius); font-size: var(--btn-font-size); transition: var(--btn-transition); border: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
    .btn--primary { background: var(--color-primary); color: var(--color-on-dark); }
    .btn--primary:hover { background: var(--color-primary-hover); }
    .btn--secondary { background: var(--color-secondary); color: var(--color-on-light); }
    .btn--secondary:hover { background: var(--color-secondary-hover); }
    .btn--dark { background: var(--color-dark); color: var(--color-on-dark); }
    .btn--dark:hover { background: var(--color-dark-hover); }
    .btn--warning { background: var(--color-warning); color: var(--color-on-dark); }
    .btn--warning:hover { background: var(--color-warning-hover); }
    .btn--full { width: 100%; }
    .btn--flex1 { flex: 1; }
    @media (max-width: 1024px) {
      .max-w-4xl { max-width: 90%; }
    }
    @media (max-width: 768px) {
      #tabs { flex-direction: column; }
      .flex.gap-4 { flex-direction: column; }
      .p-6 { padding: 1rem; }
    }
    @media (max-width: 480px) {
      h1.text-2xl { font-size: 1.5rem; }
      .modal { max-width: 95%; padding: 1.25rem; }
      .btn { padding: 0.5rem; font-size: 0.875rem; }
      textarea, input { font-size: 0.875rem; }
    }
  </style>
  <script>
    window.__CLIENT_BRANDING__ = <?php echo json_encode([
        'name'   => $brandName,
        'logo'   => $logoUrl,
        'colors' => [
            'primary'        => $primary,
            'primary_hover'  => $primaryHover,
            'secondary'      => $secondary,
            'secondary_hover'=> $secondaryHover,
            'dark'           => $dark,
            'dark_hover'     => $darkHover,
            'warning'        => $warning,
            'warning_hover'  => $warningHover,
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
  </script>
  <script defer src="script.js"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-start justify-center p-6">
  <div id="loginOverlay" class="overlay">
    <div class="modal">
      <?php if ($logoUrl): ?>
        <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Logo <?php echo htmlspecialchars($brandName); ?>" class="mx-auto h-16 mb-4" />
      <?php endif; ?>
      <h2 class="text-xl font-semibold mb-4">Acceso a <?php echo htmlspecialchars($brandName); ?></h2>
      <form id="loginForm" class="text-left">
        <label for="loginUser">Usuario</label>
        <input id="loginUser" name="user" type="text" autocomplete="username" required />
        <label for="loginPass">Contraseña</label>
        <input id="loginPass" name="pass" type="password" autocomplete="current-password" required />
        <button type="submit" class="btn btn--primary">Iniciar sesión</button>
      </form>
      <p id="loginError" class="mt-3 text-red-500 hidden">Credenciales inválidas. Inténtalo nuevamente.</p>
    </div>
  </div>

  <div id="confirmOverlay" class="overlay hidden">
    <div id="confirmBox" class="modal confirm">
      <p id="confirmMsg">¿Confirmar acción?</p>
      <button id="confirmOk" class="btn btn--primary">Aceptar</button>
      <button id="confirmCancel" class="btn btn--secondary">Cancelar</button>
    </div>
  </div>

  <div id="deleteOverlay" class="overlay hidden">
    <div class="modal deleteKey">
      <h2 class="text-xl font-semibold">Clave de Eliminación</h2>
      <p class="mt-2 text-gray-700">Ingrese la clave para eliminar:</p>
      <input id="deleteKeyInput" type="password" placeholder="Clave de borrado" class="mt-2 w-full border rounded px-3 py-2" />
      <p id="deleteKeyError" class="mt-2 text-red-500 hidden">Clave incorrecta.</p>
      <button id="deleteKeyOk" class="btn btn--primary btn--full">Enviar</button>
      <button id="deleteKeyCancel" class="btn btn--secondary btn--full">Cancelar</button>
    </div>
  </div>

  <div id="toast-container"></div>

  <div id="mainContent" class="w-full max-w-4xl bg-white rounded-2xl shadow-lg hidden">
    <div class="bg-white border-b flex items-center justify-between px-6 py-4">
      <div class="flex items-center gap-3">
        <?php if ($logoUrl): ?>
          <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Logo <?php echo htmlspecialchars($brandName); ?>" class="h-12 hidden sm:block" />
        <?php endif; ?>
        <h1 class="text-2xl font-bold" id="brandHeading"><?php echo htmlspecialchars($brandName); ?></h1>
      </div>
      <button id="logoutBtn" class="btn btn--secondary">Cerrar sesión</button>
    </div>
    <nav class="border-b bg-white shadow-sm">
      <ul id="tabs" class="flex">
        <li class="tab flex-1 text-center cursor-pointer px-6 py-4 active" data-tab="tab-search">Buscar</li>
        <li class="tab flex-1 text-center cursor-pointer px-6 py-4" data-tab="tab-upload">Subir</li>
        <li class="tab flex-1 text-center cursor-pointer px-6 py-4" data-tab="tab-list">Consultar</li>
        <li class="tab flex-1 text-center cursor-pointer px-6 py-4" data-tab="tab-code">Búsqueda por Código</li>
      </ul>
    </nav>
    <div class="p-6 space-y-6">
      <div id="tab-search" class="tab-content">
        <h2 class="text-xl font-semibold mb-4">Búsqueda Inteligente</h2>
        <textarea id="searchInput" rows="6" class="w-full border rounded px-3 py-2 text-lg mb-4" placeholder="Pega aquí tus códigos o bloque de texto…"></textarea>
        <div class="flex gap-4 mb-4">
          <button id="btnDoSearch" class="btn btn--primary btn--flex1 text-lg">Buscar</button>
          <button id="btnClearSearch" class="btn btn--secondary btn--flex1 text-lg">Limpiar</button>
        </div>
        <div id="search-alert" class="text-red-600 font-medium text-lg mb-4"></div>
        <div id="results-search" class="space-y-4"></div>
      </div>

      <div id="tab-upload" class="tab-content hidden">
        <h2 class="text-xl font-semibold mb-4">Subir / Editar Documento</h2>
        <form id="form-upload" enctype="multipart/form-data" class="space-y-4">
          <input id="docId" type="hidden" name="id" />
          <div>
            <label class="block mb-1 text-lg">Nombre</label>
            <input id="name" name="name" type="text" required class="w-full border rounded px-3 py-2 text-lg" />
          </div>
          <div>
            <label class="block mb-1 text-lg">Fecha</label>
            <input id="date" name="date" type="date" required class="w-full border rounded px-3 py-2 text-lg" />
          </div>
          <div>
            <label class="block mb-1 text-lg">PDF o Documento</label>
            <input id="file" name="file" type="file" accept="application/pdf,image/*" class="w-full text-lg" />
            <p id="uploadWarning" class="mt-1 text-red-600 text-sm hidden">El archivo excede los 10 MB. Por favor, sube uno menor.</p>
          </div>
          <div>
            <label class="block mb-1 text-lg">Códigos</label>
            <textarea id="codes" name="codes" rows="4" class="w-full border rounded px-3 py-2 text-lg"></textarea>
          </div>
          <button type="submit" class="btn btn--primary btn--full text-lg">Guardar</button>
        </form>
      </div>

      <div id="tab-list" class="tab-content hidden">
        <h2 class="text-xl font-semibold mb-4">Consultar Documentos</h2>
        <div class="flex flex-wrap gap-4 mb-4">
          <input id="consultFilterInput" type="text" class="flex-1 min-w-[200px] border rounded px-3 py-2 text-lg" placeholder="Filtrar por nombre o PDF" />
          <button id="btnClearConsult" class="btn btn--secondary text-lg">Limpiar</button>
          <button id="btnDownloadCsv" class="btn btn--primary text-lg">Descargar CSV</button>
          <button id="btnDownloadPdfs" class="btn btn--dark text-lg">Descargar PDFs</button>
        </div>
        <div id="results-list" class="space-y-4"></div>
      </div>

      <div id="tab-code" class="tab-content hidden">
        <h2 class="text-xl font-semibold mb-4">Búsqueda por Código</h2>
        <div class="relative mb-4">
          <input id="codeInput" type="text" class="w-full border rounded px-3 py-2 text-lg" placeholder="Código en MAYÚSCULAS (ej: ABC123)" autocomplete="off" />
          <div id="suggestions" class="absolute top-full left-0 right-0 bg-white border rounded-b px-2 shadow max-h-48 overflow-auto hidden z-20"></div>
        </div>
        <button id="btnSearchCode" class="btn btn--primary btn--full mb-4 text-lg">Buscar por Código</button>
        <div id="results-code" class="space-y-4"></div>
      </div>
    </div>
  </div>
</body>
</html>
