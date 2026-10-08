<?php

function product_image_url(?string $storedImage): ?string
{
    $filename = basename(str_replace('\\', '/', trim((string) $storedImage)));
    if ($filename === '' || $filename === '.') {
        return null;
    }

    $aliases = [
        'bolo_morango.png' => 'bmorango.png',
        'brownie.png' => 'brownienorm.png',
        'cupcake_red.png' => 'velvet.png',
    ];
    $candidateNames = [$filename];
    if (isset($aliases[strtolower($filename)])) {
        $candidateNames[] = $aliases[strtolower($filename)];
    }

    $stem = $filename;
    while (preg_match('/\.(png|jpe?g|webp)$/i', $stem) === 1) {
        $stem = preg_replace('/\.(png|jpe?g|webp)$/i', '', $stem);
        foreach (['png', 'jpg', 'jpeg', 'webp'] as $extension) {
            $candidateNames[] = $stem . '.' . $extension;
        }
    }

    foreach (array_unique($candidateNames) as $candidate) {
        foreach (['uploads', 'imagens'] as $directory) {
            $path = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR . $candidate;
            if (is_file($path)) {
                return './' . $directory . '/' . rawurlencode($candidate);
            }
        }
    }

    return null;
}
