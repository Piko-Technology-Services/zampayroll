<?php

namespace App\Services\Compliance;

/**
 * Reads an uploaded Word (.docx) template into simple HTML (headings, paragraphs, lists, bold/italic/underline, tables).
 * Layout details such as fonts, columns and images are not carried over; the letterhead is applied separately.
 */
class DocxImport
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    public static function toHtml(string $path): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (! $xml || strlen($xml) > 30_000_000) {
            return '';
        }
        $dom = new \DOMDocument();
        if (! @$dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
            return '';
        }
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('w', self::W);
        $body = $xp->query('//w:body')->item(0);
        if (! $body) {
            return '';
        }

        $html = '';
        $list = null;
        foreach ($body->childNodes as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }
            if ($node->localName === 'tbl') {
                if ($list) {
                    $html .= "</{$list}>";
                    $list = null;
                }
                $html .= '<table>';
                foreach ($xp->query('w:tr', $node) as $tr) {
                    $html .= '<tr>';
                    foreach ($xp->query('w:tc', $tr) as $tc) {
                        $cell = [];
                        foreach ($xp->query('w:p', $tc) as $p) {
                            $cell[] = self::runs($xp, $p);
                        }
                        $html .= '<td>' . implode('<br>', array_filter($cell)) . '</td>';
                    }
                    $html .= '</tr>';
                }
                $html .= '</table>';
                continue;
            }
            if ($node->localName !== 'p') {
                continue;
            }
            $style = (string) $xp->evaluate('string(w:pPr/w:pStyle/@w:val)', $node);
            $isList = $xp->query('w:pPr/w:numPr', $node)->length > 0 || stripos($style, 'List') !== false;
            $text = self::runs($xp, $node);
            if ($isList) {
                $tag = 'ul';
                if ($list !== $tag) {
                    $html .= ($list ? "</{$list}>" : '') . "<{$tag}>";
                    $list = $tag;
                }
                $html .= "<li>{$text}</li>";
                continue;
            }
            if ($list) {
                $html .= "</{$list}>";
                $list = null;
            }
            if (trim(strip_tags($text)) === '') {
                continue;
            }
            if (preg_match('/^(Heading|Titre|Title)\s*([1-4])?/i', $style, $m)) {
                $level = stripos($style, 'Title') === 0 ? 1 : max(1, min(4, (int) ($m[2] ?? 2)));
                $html .= "<h{$level}>" . strip_tags($text, '<strong><em><u>') . "</h{$level}>";
            } else {
                $html .= "<p>{$text}</p>";
            }
        }
        if ($list) {
            $html .= "</{$list}>";
        }

        return HtmlSanitizer::clean($html);
    }

    private static function runs(\DOMXPath $xp, \DOMElement $p): string
    {
        $out = '';
        foreach ($xp->query('.//w:r', $p) as $r) {
            $t = '';
            foreach ($r->childNodes as $c) {
                if ($c->localName === 't') {
                    $t .= e($c->textContent);
                } elseif ($c->localName === 'tab') {
                    $t .= ' ';
                } elseif ($c->localName === 'br') {
                    $t .= '<br>';
                }
            }
            if ($t === '') {
                continue;
            }
            $b = $xp->query('w:rPr/w:b[not(@w:val="0") and not(@w:val="false")]', $r)->length > 0;
            $i = $xp->query('w:rPr/w:i[not(@w:val="0") and not(@w:val="false")]', $r)->length > 0;
            $u = $xp->query('w:rPr/w:u[not(@w:val="none")]', $r)->length > 0;
            $out .= ($b ? '<strong>' : '') . ($i ? '<em>' : '') . ($u ? '<u>' : '') . $t . ($u ? '</u>' : '') . ($i ? '</em>' : '') . ($b ? '</strong>' : '');
        }

        return $out;
    }
}
