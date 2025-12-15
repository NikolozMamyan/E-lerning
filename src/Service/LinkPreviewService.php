<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class LinkPreviewService
{
    public function __construct(
        private HttpClientInterface $client
    ) {}

   public function fetch(string $url): array
{
    $parsed = parse_url($url);

    $scheme = $parsed['scheme'] ?? 'https';
    $originalHost = $parsed['host'] ?? '';

    // Domaine racine pour le fetch
    $rootHost = preg_replace('/^(www\.|m\.|mobile\.)/', '', $originalHost);
    $fetchUrl = $scheme . '://' . $rootHost;

    $response = $this->client->request('GET', $fetchUrl, [
        'timeout' => 5,
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (LinkPreviewBot)'
        ]
    ]);

    $html = $response->getContent();

    libxml_use_internal_errors(true);
    $dom = new \DOMDocument();
    $dom->loadHTML($html);
    libxml_clear_errors();

    $xpath = new \DOMXPath($dom);

    $get = fn ($prop) =>
        trim($xpath->evaluate(
            "string(//meta[@property='$prop']/@content)"
        ));

    return [
        'title'       => $get('og:title'),
        'description' => $get('og:description'),
        'image'       => $get('og:image'),
        'url'         => $url,        // 🔗 URL ORIGINALE → clic
        'site'        => $rootHost,   // affichage propre
        'fetchUrl'    => $fetchUrl,   // debug / cache
    ];
}
}
