<?php
// Archivo: qr/index.php

// Cargar la base de datos JSON
$dbFile = __DIR__ . "/redirects.json";
if (!file_exists($dbFile)) {
    die("Config no encontrada");
}
$db = json_decode(file_get_contents($dbFile), true);

// Obtener el slug de la URL: /qr/?s=promoqr
$slug = isset($_GET['s']) ? $_GET['s'] : null;

if (!$slug || !isset($db[$slug])) {
    http_response_code(404);
    echo "QR no configurado.";
    exit;
}

// Redirigir al destino
$target = $db[$slug];
header("Location: " . $target, true, 302);
exit;
