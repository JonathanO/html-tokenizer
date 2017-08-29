<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 29/08/2017
 * Time: 10:37
 */

namespace Woaf\HtmlTokenizer\Tables;


$mappings = [
    ['0x80', 'U+20AC', 'EURO SIGN (€)'],
    ['0x82', 'U+201A', 'SINGLE LOW-9 QUOTATION MARK (‚)'],
    ['0x83', 'U+0192', 'LATIN SMALL LETTER F WITH HOOK (ƒ)'],
    ['0x84', 'U+201E', 'DOUBLE LOW-9 QUOTATION MARK („)'],
    ['0x85', 'U+2026', 'HORIZONTAL ELLIPSIS (…)'],
    ['0x86', 'U+2020', 'DAGGER (†)'],
    ['0x87', 'U+2021', 'DOUBLE DAGGER (‡)'],
    ['0x88', 'U+02C6', 'MODIFIER LETTER CIRCUMFLEX ACCENT (ˆ)'],
    ['0x89', 'U+2030', 'PER MILLE SIGN (‰)'],
    ['0x8A', 'U+0160', 'LATIN CAPITAL LETTER S WITH CARON (Š)'],
    ['0x8B', 'U+2039', 'SINGLE LEFT-POINTING ANGLE QUOTATION MARK (‹)'],
    ['0x8C', 'U+0152', 'LATIN CAPITAL LIGATURE OE (Œ)'],
    ['0x8E', 'U+017D', 'LATIN CAPITAL LETTER Z WITH CARON (Ž)'],
    ['0x91', 'U+2018', 'LEFT SINGLE QUOTATION MARK (‘)'],
    ['0x92', 'U+2019', 'RIGHT SINGLE QUOTATION MARK (’)'],
    ['0x93', 'U+201C', 'LEFT DOUBLE QUOTATION MARK (“)'],
    ['0x94', 'U+201D', 'RIGHT DOUBLE QUOTATION MARK (”)'],
    ['0x95', 'U+2022', 'BULLET (•)'],
    ['0x96', 'U+2013', 'EN DASH (–)'],
    ['0x97', 'U+2014', 'EM DASH (—)'],
    ['0x98', 'U+02DC', 'SMALL TILDE (˜)'],
    ['0x99', 'U+2122', 'TRADE MARK SIGN (™)'],
    ['0x9A', 'U+0161', 'LATIN SMALL LETTER S WITH CARON (š)'],
    ['0x9B', 'U+203A', 'SINGLE RIGHT-POINTING ANGLE QUOTATION MARK (›)'],
    ['0x9C', 'U+0153', 'LATIN SMALL LIGATURE OE (œ)'],
    ['0x9E', 'U+017E', 'LATIN SMALL LETTER Z WITH CARON (ž)'],
    ['0x9F', 'U+0178', 'LATIN CAPITAL LETTER Y WITH DIAERESIS (Ÿ)'],
];

$lookup = [];
foreach ($mappings as $mapping) {
    $lookup[hexdec(substr($mapping[0], 2))] = hexdec(substr($mapping[1], 2));
}

$namespace = __NAMESPACE__;
$generated = <<<EOF
<?php
// Auto generated

namespace $namespace;

class CharRefRemapping {
    private static \$mappings =
EOF;
$generated .= var_export($lookup, true) . ";\n\n";
$generated .= <<<'EOF'

    public static function hasMapping($codepoint) {
        return isset(self::$mappings[$codepoint]);
    }
    
    public static function remapIfNeeded($codepoint) {
        if (!self::hasMapping($codepoint)) {
            return $codepoint;
        }
        return self::$mappings[$codepoint];
    }

}
EOF;

file_put_contents(__DIR__ . '/CharRefRemapping.php', $generated);