<?php

namespace App\Services\Compliance;

use App\Models\Company;
use App\Models\Compliance\Letterhead;

/**
 * Writes an editable Word (.docx) file from a document body, with the letterhead in the page header and footer.
 * Built with PHP's ZipArchive so no extra package is needed. Supports headings, paragraphs, bold/italic/underline,
 * bulleted and numbered lists, tables and horizontal rules.
 */
class DocxWriter
{
    private array $media = [];          // ['rId' => ..., 'name' => ..., 'bytes' => ..., 'part' => header|document]

    private int $numCounter = 0;

    private array $nums = [];           // numId => abstractNumId

    private string $font = 'Calibri';

    private string $accent = '0B3D2E';

    private int $size = 21;             // half-points

    public static function make(string $bodyHtml, ?Letterhead $lh, Company $company, string $title = 'Document', ?string $footerNote = null): string
    {
        return (new self())->build($bodyHtml, $lh, $company, $title, $footerNote);
    }

    public function build(string $bodyHtml, ?Letterhead $lh, Company $company, string $title, ?string $footerNote): string
    {
        $s = $lh ? $lh->allSettings() : Letterhead::DEFAULTS;
        $this->accent = ltrim(preg_match('/^#[0-9a-fA-F]{6}$/', (string) $s['accent']) ? $s['accent'] : '#0B3D2E', '#');
        $this->font = str_contains((string) $s['font'], 'Serif') || $s['font'] === 'Times' ? 'Times New Roman' : 'Calibri';
        $this->size = (int) round(max(8, min(14, (float) $s['font_size'])) * 2);

        $body = $this->convertBody($bodyHtml);
        $header = $this->headerXml($lh, $company, $s);
        $footer = $this->footerXml($lh, $company, $s, $footerNote);

        $tw = fn ($mm) => (int) round($mm * 56.7);
        $sect = '<w:sectPr><w:headerReference w:type="default" r:id="rIdHeader"/><w:footerReference w:type="default" r:id="rIdFooter"/>'
            . '<w:pgSz w:w="11906" w:h="16838"/>'
            . '<w:pgMar w:top="' . $tw($s['header_height'] + $s['margin_top']) . '" w:right="' . $tw($s['margin_right']) . '" w:bottom="' . $tw($s['footer_height'] + $s['margin_bottom'])
            . '" w:left="' . $tw($s['margin_left']) . '" w:header="' . $tw(6) . '" w:footer="' . $tw(6) . '" w:gutter="0"/></w:sectPr>';

        $doc = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document ' . self::NS . '><w:body>' . $body . $sect . '</w:body></w:document>';

        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>');
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>' . $this->x($title) . '</dc:title><dc:creator>' . $this->x($company->name) . '</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">' . now()->toIso8601ZuluString() . '</dcterms:created></cp:coreProperties>');
        $zip->addFromString('word/document.xml', $doc);
        $zip->addFromString('word/styles.xml', $this->styles());
        $zip->addFromString('word/numbering.xml', $this->numbering());
        $zip->addFromString('word/header1.xml', $header);
        $zip->addFromString('word/footer1.xml', $footer);
        $zip->addFromString('word/_rels/document.xml.rels', $this->rels('document'));
        $zip->addFromString('word/_rels/header1.xml.rels', $this->rels('header'));
        $zip->addFromString('word/_rels/footer1.xml.rels', $this->rels('footer'));
        foreach ($this->media as $m) {
            $zip->addFromString('word/media/' . $m['name'], $m['bytes']);
        }
        $zip->close();
        $bytes = file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }

    private const NS = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"';

    private function x(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /* ------------------------------ header / footer ------------------------------ */

    private function headerXml(?Letterhead $lh, Company $company, array $s): string
    {
        $content = '';
        if ($lh && $lh->mode === 'custom' && $lh->header_path && ($bytes = Vault::get($lh->header_path))) {
            $content = '<w:p><w:pPr><w:spacing w:after="0"/><w:ind w:left="-' . (int) round($s['margin_left'] * 56.7) . '" w:right="-' . (int) round($s['margin_right'] * 56.7) . '"/></w:pPr>'
                . $this->imageRun($bytes, 'header', 210 - 0.5, (float) $s['header_height']) . '</w:p>';
        } else {
            $logo = Letterheads::logoBytes($company);
            $align = ['left' => 'left', 'center' => 'center', 'right' => 'right'][$s['logo_position']] ?? 'left';
            if ($logo) {
                $content .= '<w:p><w:pPr><w:jc w:val="' . $align . '"/><w:spacing w:after="40"/></w:pPr>' . $this->imageRun($logo, 'header', 40, max(10, $s['header_height'] - 14)) . '</w:p>';
            }
            $content .= '<w:p><w:pPr><w:jc w:val="' . $align . '"/><w:spacing w:after="0"/></w:pPr><w:r><w:rPr><w:b/><w:color w:val="' . $this->accent . '"/><w:sz w:val="30"/></w:rPr><w:t>' . $this->x($company->name) . '</w:t></w:r></w:p>';
            foreach ([Letterheads::addressLine($company), Letterheads::contactLine($company)] as $line) {
                if ($line !== '') {
                    $content .= '<w:p><w:pPr><w:jc w:val="' . $align . '"/><w:spacing w:after="0"/></w:pPr><w:r><w:rPr><w:color w:val="45524A"/><w:sz w:val="16"/></w:rPr><w:t xml:space="preserve">' . $this->x($line) . '</w:t></w:r></w:p>';
                }
            }
            $content .= '<w:p><w:pPr><w:pBdr><w:bottom w:val="single" w:sz="12" w:space="1" w:color="' . $this->accent . '"/></w:pBdr><w:spacing w:after="0"/></w:pPr></w:p>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:hdr ' . self::NS . '>' . $content . '</w:hdr>';
    }

    private function footerXml(?Letterhead $lh, Company $company, array $s, ?string $note): string
    {
        $content = '';
        if ($lh && $lh->mode === 'custom' && $lh->footer_path && ($bytes = Vault::get($lh->footer_path))) {
            $content .= '<w:p><w:pPr><w:spacing w:after="0"/><w:ind w:left="-' . (int) round($s['margin_left'] * 56.7) . '" w:right="-' . (int) round($s['margin_right'] * 56.7) . '"/></w:pPr>'
                . $this->imageRun($bytes, 'footer', 209.5, (float) $s['footer_height']) . '</w:p>';
        }
        $left = collect([(! $lh || $lh->mode !== 'custom') && $s['show_registration'] ? Letterheads::registrationLine($company) : null, $note])->filter()->implode('  ·  ');
        $run = fn ($t) => '<w:r><w:rPr><w:color w:val="55615A"/><w:sz w:val="15"/></w:rPr><w:t xml:space="preserve">' . $this->x($t) . '</w:t></w:r>';
        $field = fn ($f) => '<w:r><w:rPr><w:sz w:val="15"/></w:rPr><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:rPr><w:sz w:val="15"/></w:rPr><w:instrText xml:space="preserve"> ' . $f . ' </w:instrText></w:r><w:r><w:rPr><w:sz w:val="15"/></w:rPr><w:fldChar w:fldCharType="separate"/></w:r><w:r><w:rPr><w:sz w:val="15"/></w:rPr><w:t>1</w:t></w:r><w:r><w:rPr><w:sz w:val="15"/></w:rPr><w:fldChar w:fldCharType="end"/></w:r>';
        $content .= '<w:p><w:pPr><w:pBdr><w:top w:val="single" w:sz="4" w:space="4" w:color="C9D3CC"/></w:pBdr><w:tabs><w:tab w:val="right" w:pos="9638"/></w:tabs><w:spacing w:after="0"/></w:pPr>'
            . ($left !== '' ? $run($left) : '')
            . ($s['page_numbers'] ? '<w:r><w:tab/></w:r>' . $run('Page ') . $field('PAGE') . $run(' of ') . $field('NUMPAGES') : '') . '</w:p>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:ftr ' . self::NS . '>' . $content . '</w:ftr>';
    }

    private function imageRun(string $bytes, string $part, float $maxWmm, float $maxHmm): string
    {
        $info = @getimagesizefromstring($bytes);
        if (! $info) {
            return '';
        }
        $ext = $info[2] === IMAGETYPE_JPEG ? 'jpeg' : 'png';
        if (! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            $img = @imagecreatefromstring($bytes);
            if (! $img) {
                return '';
            }
            ob_start();
            imagepng($img);
            $bytes = ob_get_clean();
            $ext = 'png';
        }
        $n = count($this->media) + 1;
        $rId = 'rIdImg' . $n;
        $this->media[] = ['rId' => $rId, 'name' => "image{$n}.{$ext}", 'bytes' => $bytes, 'part' => $part];

        [$w, $h] = [$info[0], $info[1]];
        $ratio = min($maxWmm / ($w * 0.2646), $maxHmm / ($h * 0.2646), 1000);
        $cx = (int) round($w * 0.2646 * $ratio * 36000);
        $cy = (int) round($h * 0.2646 * $ratio * 36000);

        return '<w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0"><wp:extent cx="' . $cx . '" cy="' . $cy . '"/><wp:docPr id="' . $n . '" name="Picture ' . $n . '"/>'
            . '<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic><pic:nvPicPr><pic:cNvPr id="' . $n . '" name="image' . $n . '"/><pic:cNvPicPr/></pic:nvPicPr>'
            . '<pic:blipFill><a:blip r:embed="' . $rId . '"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill><pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic>'
            . '</a:graphicData></a:graphic></wp:inline></w:drawing></w:r>';
    }

    /* ------------------------------ body ------------------------------ */

    private function convertBody(string $html): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="__r">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        $root = $dom->getElementById('__r') ?? $dom->documentElement;

        $out = $this->blocks($root, 0);

        return $out === '' ? '<w:p/>' : $out;
    }

    private function blocks(\DOMNode $parent, int $depth, array $fmt = []): string
    {
        $out = '';
        $inline = [];
        $flush = function () use (&$inline, &$out) {
            $runs = implode('', $inline);
            if (trim(strip_tags($runs)) !== '' || str_contains($runs, '<w:br/>')) {
                $out .= '<w:p>' . $runs . '</w:p>';
            }
            $inline = [];
        };

        foreach ($parent->childNodes as $node) {
            $tag = $node instanceof \DOMElement ? strtolower($node->tagName) : null;
            if (in_array($tag, ['h1', 'h2', 'h3', 'h4', 'p', 'div', 'blockquote', 'ul', 'ol', 'table', 'hr'], true)) {
                $flush();
                $out .= $this->block($node, $tag, $depth, $fmt);
            } else {
                $inline[] = $this->runs($node, $fmt);
            }
        }
        $flush();

        return $out;
    }

    private function block(\DOMElement $el, string $tag, int $depth, array $fmt = []): string
    {
        $align = '';
        if (preg_match('/text-align:(left|right|center|justify)/', $el->getAttribute('style'), $m)) {
            $align = '<w:jc w:val="' . ($m[1] === 'justify' ? 'both' : $m[1]) . '"/>';
        }

        switch ($tag) {
            case 'h1': case 'h2': case 'h3': case 'h4':
                return '<w:p><w:pPr><w:pStyle w:val="Heading' . substr($tag, 1) . '"/>' . $align . '</w:pPr>' . $this->runs($el, []) . '</w:p>';
            case 'hr':
                return '<w:p><w:pPr><w:pBdr><w:bottom w:val="single" w:sz="6" w:space="1" w:color="C9D3CC"/></w:pBdr></w:pPr></w:p>';
            case 'blockquote':
                return '<w:p><w:pPr><w:ind w:left="567"/>' . $align . '</w:pPr>' . $this->runs($el, ['i' => true]) . '</w:p>';
            case 'ul': case 'ol':
                return $this->listXml($el, $tag === 'ol', $depth);
            case 'table':
                return $this->tableXml($el);
            case 'div':
                // A div may hold blocks; treat it like a container.
                foreach ($el->childNodes as $c) {
                    if ($c instanceof \DOMElement && in_array(strtolower($c->tagName), ['p', 'h1', 'h2', 'h3', 'h4', 'ul', 'ol', 'table', 'div'], true)) {
                        return $this->blocks($el, $depth, $fmt);
                    }
                }

                return '<w:p>' . ($align ? '<w:pPr>' . $align . '</w:pPr>' : '') . $this->runs($el, $fmt) . '</w:p>';
            default:
                return '<w:p>' . ($align ? '<w:pPr>' . $align . '</w:pPr>' : '') . $this->runs($el, $fmt) . '</w:p>';
        }
    }

    private function listXml(\DOMElement $list, bool $ordered, int $depth): string
    {
        $numId = ++$this->numCounter;
        $this->nums[$numId] = $ordered ? 2 : 1;
        $out = '';
        foreach ($list->childNodes as $li) {
            if (! $li instanceof \DOMElement || strtolower($li->tagName) !== 'li') {
                continue;
            }
            $runs = '';
            $nested = '';
            foreach ($li->childNodes as $c) {
                if ($c instanceof \DOMElement && in_array(strtolower($c->tagName), ['ul', 'ol'], true)) {
                    $nested .= $this->listXml($c, strtolower($c->tagName) === 'ol', min(4, $depth + 1));
                } elseif ($c instanceof \DOMElement && strtolower($c->tagName) === 'p') {
                    $runs .= ($runs ? '<w:r><w:br/></w:r>' : '') . $this->runs($c, []);
                } else {
                    $runs .= $this->runs($c, []);
                }
            }
            $out .= '<w:p><w:pPr><w:pStyle w:val="ListParagraph"/><w:numPr><w:ilvl w:val="' . $depth . '"/><w:numId w:val="' . $numId . '"/></w:numPr><w:spacing w:after="60"/></w:pPr>' . $runs . '</w:p>' . $nested;
        }

        return $out;
    }

    private function tableXml(\DOMElement $table): string
    {
        $rows = [];
        $xp = new \DOMXPath($table->ownerDocument);
        foreach ($xp->query('.//tr', $table) as $tr) {
            $rows[] = $tr;
        }
        if (! $rows) {
            return '';
        }
        $cols = 1;
        foreach ($rows as $tr) {
            $n = 0;
            foreach ($tr->childNodes as $c) {
                if ($c instanceof \DOMElement && in_array(strtolower($c->tagName), ['td', 'th'], true)) {
                    $n += max(1, (int) $c->getAttribute('colspan'));
                }
            }
            $cols = max($cols, $n);
        }
        $width = (int) floor(9638 / $cols);
        $out = '<w:tbl><w:tblPr><w:tblStyle w:val="TableGrid"/><w:tblW w:w="5000" w:type="pct"/><w:tblBorders>'
            . '<w:top w:val="single" w:sz="4" w:color="C9D3CC"/><w:left w:val="single" w:sz="4" w:color="C9D3CC"/><w:bottom w:val="single" w:sz="4" w:color="C9D3CC"/><w:right w:val="single" w:sz="4" w:color="C9D3CC"/><w:insideH w:val="single" w:sz="4" w:color="C9D3CC"/><w:insideV w:val="single" w:sz="4" w:color="C9D3CC"/>'
            . '</w:tblBorders><w:tblCellMar><w:left w:w="100" w:type="dxa"/><w:right w:w="100" w:type="dxa"/></w:tblCellMar></w:tblPr><w:tblGrid>' . str_repeat('<w:gridCol w:w="' . $width . '"/>', $cols) . '</w:tblGrid>';
        foreach ($rows as $tr) {
            $out .= '<w:tr>';
            foreach ($tr->childNodes as $c) {
                if (! $c instanceof \DOMElement || ! in_array(strtolower($c->tagName), ['td', 'th'], true)) {
                    continue;
                }
                $span = max(1, (int) $c->getAttribute('colspan'));
                $isTh = strtolower($c->tagName) === 'th';
                $inner = $this->blocks($c, 0, $isTh ? ['b' => true] : []);
                $out .= '<w:tc><w:tcPr><w:tcW w:w="' . ($width * $span) . '" w:type="dxa"/>' . ($span > 1 ? '<w:gridSpan w:val="' . $span . '"/>' : '')
                    . ($isTh ? '<w:shd w:val="clear" w:color="auto" w:fill="EEF3EF"/>' : '') . '</w:tcPr>' . ($inner ?: '<w:p/>') . '</w:tc>';
            }
            $out .= '</w:tr>';
        }

        return $out . '</w:tbl><w:p><w:pPr><w:spacing w:after="0"/></w:pPr></w:p>';
    }

    /** Inline content to runs, carrying bold / italic / underline / highlight. */
    private function runs(\DOMNode $node, array $fmt): string
    {
        if ($node instanceof \DOMText) {
            $text = preg_replace('/\s+/u', ' ', $node->nodeValue);
            if ($text === '') {
                return '';
            }
            $rpr = ($fmt['b'] ?? false ? '<w:b/>' : '') . ($fmt['i'] ?? false ? '<w:i/>' : '') . ($fmt['u'] ?? false ? '<w:u w:val="single"/>' : '')
                . ($fmt['hl'] ?? false ? '<w:highlight w:val="yellow"/>' : '');

            return '<w:r>' . ($rpr ? '<w:rPr>' . $rpr . '</w:rPr>' : '') . '<w:t xml:space="preserve">' . $this->x($text) . '</w:t></w:r>';
        }
        if (! $node instanceof \DOMElement) {
            return '';
        }
        $tag = strtolower($node->tagName);
        if ($tag === 'br') {
            return '<w:r><w:br/></w:r>';
        }
        if (in_array($tag, ['strong', 'b'], true)) {
            $fmt['b'] = true;
        } elseif (in_array($tag, ['em', 'i'], true)) {
            $fmt['i'] = true;
        } elseif ($tag === 'u') {
            $fmt['u'] = true;
        } elseif ($tag === 'span' && str_contains($node->getAttribute('class'), 'ph-missing')) {
            $fmt['hl'] = true;
        }
        $out = '';
        foreach ($node->childNodes as $c) {
            $out .= $this->runs($c, $fmt);
        }

        return $out;
    }

    /* ------------------------------ package parts ------------------------------ */

    private function rels(string $part): string
    {
        $r = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        if ($part === 'document') {
            $r .= '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                . '<Relationship Id="rIdNumbering" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/>'
                . '<Relationship Id="rIdHeader" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/>'
                . '<Relationship Id="rIdFooter" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>';
        }
        foreach ($this->media as $m) {
            if ($m['part'] === $part) {
                $r .= '<Relationship Id="' . $m['rId'] . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/' . $m['name'] . '"/>';
            }
        }

        return $r . '</Relationships>';
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            . '<Default Extension="png" ContentType="image/png"/><Default Extension="jpeg" ContentType="image/jpeg"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            . '<Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>'
            . '<Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>'
            . '<Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>';
    }

    private function styles(): string
    {
        $h = fn ($n, $size, $before) => '<w:style w:type="paragraph" w:styleId="Heading' . $n . '"><w:name w:val="heading ' . $n . '"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/>'
            . '<w:pPr><w:keepNext/><w:spacing w:before="' . $before . '" w:after="100"/><w:outlineLvl w:val="' . ($n - 1) . '"/></w:pPr><w:rPr><w:b/><w:color w:val="' . $this->accent . '"/><w:sz w:val="' . $size . '"/></w:rPr></w:style>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="' . $this->font . '" w:hAnsi="' . $this->font . '" w:cs="' . $this->font . '"/><w:sz w:val="' . $this->size . '"/><w:szCs w:val="' . $this->size . '"/><w:lang w:val="en-GB"/></w:rPr></w:rPrDefault>'
            . '<w:pPrDefault><w:pPr><w:spacing w:after="120" w:line="276" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
            . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style>'
            . $h(1, $this->size + 10, 0) . $h(2, $this->size + 3, 240) . $h(3, $this->size + 1, 180) . $h(4, $this->size, 160)
            . '<w:style w:type="paragraph" w:styleId="ListParagraph"><w:name w:val="List Paragraph"/><w:basedOn w:val="Normal"/><w:pPr><w:ind w:left="720"/></w:pPr></w:style>'
            . '<w:style w:type="table" w:styleId="TableGrid"><w:name w:val="Table Grid"/><w:tblPr><w:tblBorders><w:top w:val="single" w:sz="4" w:color="auto"/><w:left w:val="single" w:sz="4" w:color="auto"/><w:bottom w:val="single" w:sz="4" w:color="auto"/><w:right w:val="single" w:sz="4" w:color="auto"/><w:insideH w:val="single" w:sz="4" w:color="auto"/><w:insideV w:val="single" w:sz="4" w:color="auto"/></w:tblBorders></w:tblPr></w:style>'
            . '</w:styles>';
    }

    private function numbering(): string
    {
        $lvls = function (bool $ordered) {
            $x = '';
            for ($i = 0; $i < 5; $i++) {
                $fmt = $ordered ? ['decimal', 'lowerLetter', 'lowerRoman', 'decimal', 'lowerLetter'][$i] : 'bullet';
                $text = $ordered ? '%' . ($i + 1) . '.' : ['•', '◦', '▪', '•', '◦'][$i];
                $x .= '<w:lvl w:ilvl="' . $i . '"><w:start w:val="1"/><w:numFmt w:val="' . $fmt . '"/><w:lvlText w:val="' . $text . '"/><w:lvlJc w:val="left"/>'
                    . '<w:pPr><w:ind w:left="' . (720 + 360 * $i) . '" w:hanging="360"/></w:pPr>' . ($ordered ? '' : '<w:rPr><w:rFonts w:ascii="Symbol" w:hAnsi="Symbol" w:hint="default"/></w:rPr>') . '</w:lvl>';
            }

            return $x;
        };
        // Bullets in a plain font so they render everywhere.
        $bullet = str_replace('<w:rPr><w:rFonts w:ascii="Symbol" w:hAnsi="Symbol" w:hint="default"/></w:rPr>', '', $lvls(false));
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:abstractNum w:abstractNumId="1"><w:multiLevelType w:val="hybridMultilevel"/>' . $bullet . '</w:abstractNum>'
            . '<w:abstractNum w:abstractNumId="2"><w:multiLevelType w:val="hybridMultilevel"/>' . $lvls(true) . '</w:abstractNum>';
        foreach ($this->nums as $numId => $abs) {
            $x .= '<w:num w:numId="' . $numId . '"><w:abstractNumId w:val="' . $abs . '"/>' . ($abs === 2 ? '<w:lvlOverride w:ilvl="0"><w:startOverride w:val="1"/></w:lvlOverride>' : '') . '</w:num>';
        }

        return $x . '</w:numbering>';
    }
}
