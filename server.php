<?php

$publicPath = getcwd();

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

// This file allows us to emulate Apache's "mod_rewrite" functionality from the
// built-in PHP web server. This provides a convenient way to test a Laravel
// application without having installed a "real" web server software here.
//
// Static files are served by the built-in server WITHOUT going through
// Laravel's HandleCors middleware, so emit the CORS headers here and serve the
// file ourselves (mirrors the Apache .htaccess rules used in production).
if ($uri !== '/' && file_exists($publicPath.$uri)) {
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        header('Access-Control-Allow-Origin: *');
    }
    $mime = mimeType($publicPath.$uri);
    header('Content-Type: '.($mime ?: 'application/octet-stream'));
    readfile($publicPath.$uri);
    return true;
}

/**
 * Mime type de un archivo estático por extensión.
 *
 * mime_content_type() depende del magic file del SO y en algunos S.O.
 * (Homebrew/macOS) detecta .css como text/plain, lo que hace que el
 * navegador bloquee las hojas de estilo. Aquí resolvemos por extensión
 * primero y caemos en mime_content_type() como respaldo.
 */
function mimeType(string $path): string
{
    static $map = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'mjs' => 'application/javascript',
        'json' => 'application/json',
        'html' => 'text/html',
        'htm' => 'text/html',
        'txt' => 'text/plain',
        'xml' => 'application/xml',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'bmp' => 'image/bmp',
        'ico' => 'image/x-icon',
        'avif' => 'image/avif',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'eot' => 'application/vnd.ms-fontobject',
        'pdf' => 'application/pdf',
        'zip' => 'application/zip',
        'mp3' => 'audio/mpeg',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
    ];

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    return $map[$ext] ?? (mime_content_type($path) ?: 'application/octet-stream');
}

require_once $publicPath.'/index.php';