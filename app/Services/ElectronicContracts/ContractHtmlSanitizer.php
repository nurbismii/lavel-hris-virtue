<?php

namespace App\Services\ElectronicContracts;

use DOMDocument;
use DOMElement;
use DOMNode;

class ContractHtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'a', 'b', 'blockquote', 'br', 'caption', 'div', 'em', 'h1', 'h2', 'h3', 'h4',
        'hr', 'i', 'img', 'li', 'ol', 'p', 'small', 'span', 'strong', 'sub', 'sup',
        'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'u', 'ul',
    ];

    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'target', 'rel', 'title'],
        'img' => ['src', 'alt', 'width', 'height', 'style'],
        '*' => ['style', 'class', 'colspan', 'rowspan'],
    ];

    private const ALLOWED_STYLE_PROPERTIES = [
        'background-color', 'border', 'border-bottom', 'border-collapse', 'border-left',
        'border-right', 'border-top', 'color', 'font-size', 'font-style', 'font-weight',
        'height', 'line-height', 'margin', 'margin-bottom', 'margin-left', 'margin-right',
        'margin-top', 'padding', 'padding-bottom', 'padding-left', 'padding-right',
        'padding-top', 'text-align', 'text-decoration', 'vertical-align', 'width',
    ];

    public function clean(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $document->loadHTML(
            '<!DOCTYPE html><html><body>' . mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8') . '</body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $body = $document->getElementsByTagName('body')->item(0);

        if (!$body) {
            return '';
        }

        $this->sanitizeNode($body);

        $clean = '';

        foreach ($body->childNodes as $child) {
            $clean .= $document->saveHTML($child);
        }

        return trim($clean);
    }

    private function sanitizeNode(DOMNode $node): void
    {
        if ($node instanceof DOMElement) {
            $tag = strtolower($node->tagName);

            if (!in_array($tag, self::ALLOWED_TAGS, true) && $tag !== 'body') {
                $this->unwrapNode($node);
                return;
            }

            $this->sanitizeAttributes($node, $tag);
        }

        $children = [];

        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            $this->sanitizeNode($child);
        }
    }

    private function sanitizeAttributes(DOMElement $node, string $tag): void
    {
        $allowed = array_merge(
            self::ALLOWED_ATTRIBUTES['*'],
            self::ALLOWED_ATTRIBUTES[$tag] ?? []
        );

        $attributes = [];

        foreach ($node->attributes as $attribute) {
            $attributes[] = $attribute->name;
        }

        foreach ($attributes as $attributeName) {
            $attribute = strtolower($attributeName);
            $value = trim((string) $node->getAttribute($attributeName));

            if (strpos($attribute, 'on') === 0 || !in_array($attribute, $allowed, true)) {
                $node->removeAttribute($attributeName);
                continue;
            }

            if ($attribute === 'style') {
                $cleanStyle = $this->sanitizeStyle($value);

                if ($cleanStyle === '') {
                    $node->removeAttribute($attributeName);
                } else {
                    $node->setAttribute('style', $cleanStyle);
                }
            }

            if (in_array($attribute, ['href', 'src'], true) && !$this->isSafeUrl($value)) {
                $node->removeAttribute($attributeName);
            }
        }

        if ($tag === 'a' && $node->hasAttribute('target')) {
            $node->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private function sanitizeStyle(string $style): string
    {
        $safeDeclarations = [];
        $declarations = explode(';', $style);

        foreach ($declarations as $declaration) {
            if (strpos($declaration, ':') === false) {
                continue;
            }

            [$property, $value] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);
            $value = preg_replace('/\s+/', ' ', $value);

            if (!in_array($property, self::ALLOWED_STYLE_PROPERTIES, true)) {
                continue;
            }

            if (preg_match('/(expression|javascript:|vbscript:|url\s*\()/i', $value)) {
                continue;
            }

            $safeDeclarations[] = "{$property}: {$value}";
        }

        return implode('; ', $safeDeclarations);
    }

    private function isSafeUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        if (preg_match('/^(javascript|vbscript|data):/i', $url)) {
            return false;
        }

        if (preg_match('/^https?:\/\//i', $url)) {
            $urlHost = parse_url($url, PHP_URL_HOST);
            $allowedHosts = array_filter([
                parse_url((string) config('app.url'), PHP_URL_HOST),
                request() ? request()->getHost() : null,
            ]);

            return $urlHost && in_array($urlHost, $allowedHosts, true);
        }

        return strpos($url, '/') === 0
            || strpos($url, '#') === 0
            || !preg_match('/^[a-z][a-z0-9+.-]*:/i', $url);
    }

    private function unwrapNode(DOMElement $node): void
    {
        $parent = $node->parentNode;

        if (!$parent) {
            return;
        }

        while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
        }

        $parent->removeChild($node);
    }
}
