<?php

namespace App\Services\Compliance;

/**
 * Keeps document HTML to a safe, printable subset (headings, paragraphs, lists, tables, basic emphasis).
 * Everything else is unwrapped or removed: no scripts, styles, event handlers, links or images in document bodies.
 */
class HtmlSanitizer
{
    private const ALLOWED = ['h1', 'h2', 'h3', 'h4', 'p', 'br', 'hr', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li',
        'table', 'thead', 'tbody', 'tr', 'th', 'td', 'blockquote', 'div', 'span'];

    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'form', 'input', 'button', 'select',
        'textarea', 'template', 'noscript', 'head', 'title', 'meta', 'link', 'img', 'video', 'audio'];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();

        $root = $doc->getElementById('__root') ?? $doc->documentElement;
        self::walk($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        // Collapse empty paragraphs the editor leaves behind.
        return trim(preg_replace('#<p>(\s|&nbsp;|<br>)*</p>#i', '', $out));
    }

    private static function walk(\DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMComment || $child instanceof \DOMProcessingInstruction) {
                $node->removeChild($child);
                continue;
            }
            if (! $child instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $node->removeChild($child);
                continue;
            }

            self::walk($child);

            if (! in_array($tag, self::ALLOWED, true)) {
                // Unknown wrapper: keep its text and children, drop the tag.
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            // Strip every attribute except a safe text-align on blocks and colspan on cells.
            $keep = [];
            if (in_array($tag, ['td', 'th'], true) && $child->hasAttribute('colspan')) {
                $keep['colspan'] = (string) max(1, min(12, (int) $child->getAttribute('colspan')));
            }
            $style = strtolower($child->getAttribute('style'));
            if (preg_match('/text-align\s*:\s*(left|right|center|justify)/', $style, $m) && in_array($tag, ['p', 'h1', 'h2', 'h3', 'h4', 'div', 'td', 'th'], true)) {
                $keep['style'] = 'text-align:' . $m[1];
            }
            foreach (iterator_to_array($child->attributes) as $attr) {
                $child->removeAttribute($attr->nodeName);
            }
            foreach ($keep as $k => $v) {
                $child->setAttribute($k, $v);
            }
        }
    }
}
