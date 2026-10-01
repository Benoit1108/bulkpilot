<?php

declare(strict_types=1);

/*
 * Prints the CSP sources of the inline scripts of the built PWA's pages (Nuxt writes its config
 * and import map inline, with a build id): `'sha256-…' 'sha256-…'`. Run at image build; the
 * entrypoint puts the result in the Content-Security-Policy (docs/DEPLOYMENT.md).
 */
$directory = $argv[1] ?? '/app/pwa';
$hashes = [];
foreach (glob($directory.'/*.html') ?: [] as $page) {
    $html = (string) file_get_contents($page);
    preg_match_all('#<script(?![^>]*\bsrc=)(?![^>]*type="application/json")[^>]*>(.*?)</script>#s', $html, $scripts);
    foreach ($scripts[1] as $script) {
        if ('' !== trim($script)) {
            $hashes["'sha256-".base64_encode(hash('sha256', $script, true))."'"] = true;
        }
    }
}
echo implode(' ', array_keys($hashes)), "\n";
