<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use DOMDocument;
use DOMXPath;

class ChatLinkPreviewController extends Controller
{
    public function show(Request $request)
    {
        $validated = $request->validate([
            'url' => 'required|url|max:2048',
        ]);

        $url = $validated['url'];
        $parts = parse_url($url);

        if (
            !is_array($parts)
            || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return response()->json(['status' => 'error'], 422);
        }

        $host = strtolower($parts['host']);
        $port = (int) ($parts['port'] ?? (($parts['scheme'] ?? '') === 'https' ? 443 : 80));

        if (!in_array($port, [80, 443], true) || !$this->hostResolvesPublicly($host)) {
            return response()->json(['status' => 'error'], 422);
        }

        $cacheKey = 'chat_link_preview:' . hash('sha256', $url);

        $preview = Cache::remember($cacheKey, now()->addDay(), function () use ($url, $host) {
            try {
                $client = new Client([
                    'timeout' => 6,
                    'connect_timeout' => 3,
                    'allow_redirects' => false,
                    'http_errors' => false,
                    'verify' => true,
                ]);

                $response = $client->get($url, [
                    'headers' => [
                        'Accept' => 'text/html,application/xhtml+xml',
                        'User-Agent' => 'DAODES-LinkPreview/1.0',
                    ],
                ]);

                if ($response->getStatusCode() !== 200) {
                    return null;
                }

                $contentType = strtolower($response->getHeaderLine('Content-Type'));
                if (!str_contains($contentType, 'text/html')) {
                    return null;
                }

                $html = $response->getBody()->read(524288);
                if ($html === '') {
                    return null;
                }

                $previous = libxml_use_internal_errors(true);
                $document = new DOMDocument();
                $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
                libxml_clear_errors();
                libxml_use_internal_errors($previous);

                $xpath = new DOMXPath($document);
                $meta = [];
                foreach ($xpath->query('//meta[@property or @name]') as $node) {
                    $key = strtolower($node->getAttribute('property') ?: $node->getAttribute('name'));
                    if ($key !== '') {
                        $meta[$key] = trim($node->getAttribute('content'));
                    }
                }

                $titleNode = $xpath->query('//title')->item(0);
                $title = $meta['og:title'] ?? ($titleNode?->textContent ?? $host);
                $description = $meta['og:description'] ?? $meta['description'] ?? '';
                $image = $meta['og:image'] ?? null;

                if ($image) {
                    $image = $this->resolveImageUrl($image, $url, $host);
                }

                return [
                    'url' => $url,
                    'domain' => $host,
                    'title' => Str::limit(trim(strip_tags($title)), 180),
                    'description' => Str::limit(trim(strip_tags($description)), 320),
                    'image' => $image,
                ];
            } catch (\Throwable $exception) {
                return null;
            }
        });

        if (!$preview) {
            return response()->json(['status' => 'unavailable'], 404);
        }

        return response()->json([
            'status' => 'success',
            'preview' => $preview,
        ]);
    }

    private function hostResolvesPublicly(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) !== false;
        }

        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        if (!$records) {
            return false;
        }

        $addresses = [];
        foreach ($records as $record) {
            if (!empty($record['ip'])) {
                $addresses[] = $record['ip'];
            }
            if (!empty($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        if (!$addresses) {
            return false;
        }

        foreach ($addresses as $address) {
            if (!filter_var(
                $address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            )) {
                return false;
            }
        }

        return true;
    }

    private function resolveImageUrl(string $image, string $pageUrl, string $pageHost): ?string
    {
        if (str_starts_with($image, '//')) {
            $scheme = parse_url($pageUrl, PHP_URL_SCHEME) ?: 'https';
            $image = $scheme . ':' . $image;
        } elseif (str_starts_with($image, '/')) {
            $parts = parse_url($pageUrl);
            $image = ($parts['scheme'] ?? 'https') . '://' . $pageHost . $image;
        } elseif (!preg_match('/^https?:\/\//i', $image)) {
            return null;
        }

        $parts = parse_url($image);
        if (
            !is_array($parts)
            || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || strtolower($parts['host'] ?? '') !== $pageHost
        ) {
            return null;
        }

        return $image;
    }
}
