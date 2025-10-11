<?php
header('Content-Type: application/json; charset=UTF-8');
$code = isset($_GET['code']) ? trim($_GET['code']) : '';
if(!$code) {
    echo json_encode([]);
    exit;
}

$dir = __DIR__ . '/uploads';
$results = [];

foreach(scandir($dir) as $file) {
    if(!preg_match('/\.pdf$/i',$file)) continue;
    $path = escapeshellarg("$dir/$file");
    // Extrae texto con iluminación de saltos de página
    $txt = shell_exec("pdftotext -layout $path -");
    if(!$txt) continue;
    // Fragmenta por página: pdftotext coloca '\f' al final de cada página
    $pages = preg_split("/\f/", $txt);
    foreach($pages as $i => $content) {
        if(strpos($content, "Ref:") !== false) {
            // Busca "Ref: <código>"
            if(preg_match('/Ref:\s*([A-Za-z0-9\-]+)/', $content, $m)) {
                if(strtoupper($m[1]) === strtoupper($code)) {
                    $results[] = [
                        'file' => $file,
                        'page' => $i+1
                    ];
                }
            }
        }
    }
}

// Devuelve solo coincidencias
echo json_encode($results);
