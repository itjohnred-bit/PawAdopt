<?php
$req = $_SERVER["REQUEST_URI"];


if (preg_match('/^\/PawAdopt\/(assets|uploads|api)\/(.*)$/', $req, $matches)) {
    $localFile = __DIR__ . '/public/' . $matches[1] . '/' . $matches[2];
    if (file_exists($localFile) && !is_dir($localFile)) {
        if (pathinfo($localFile, PATHINFO_EXTENSION) === 'css') header("Content-Type: text/css");
        if (pathinfo($localFile, PATHINFO_EXTENSION) === 'js') header("Content-Type: application/javascript");
        readfile($localFile);
        exit;
    }
}


return false;