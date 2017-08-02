<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 31/07/2017
 * Time: 20:28
 */

namespace Woaf\HtmlTokenizer\Tables;


$errors = [
        "eof-in-tag",
        "control-character-reference",
        "missing-semicolon-after-character-reference",
        "eof-in-script-html-comment-like-text",
        "abrupt-closing-of-empty-comment",
        "unexpected-question-mark-instead-of-tag-name",
        "unknown-named-character-reference",
        "unexpected-null-character",
        "noncharacter-character-reference",
        "eof-in-comment",
        "unexpected-character-in-attribute-name",
        "invalid-first-character-of-tag-name",
        "incorrectly-opened-comment",
        "control-character-in-input-stream",
        "unexpected-solidus-in-tag",
        "unexpected-character-in-unquoted-attribute-value",
        "noncharacter-in-input-stream",
        "null-character-reference",
        "absence-of-digits-in-numeric-character-reference",
        "character-reference-outside-unicode-range",
        "surrogate-character-reference",
        "missing-whitespace-between-attributes",
        "eof-before-tag-name",
        "eof-in-cdata",
        "eof-in-doctype",
        "missing-doctype-name",
        "missing-whitespace-before-doctype-name",
        "invalid-character-sequence-after-doctype-name",
        "missing-quote-before-doctype-public-identifier",
        "missing-whitespace-after-doctype-public-keyword",
        "missing-quote-before-doctype-system-identifier",
        "missing-whitespace-after-doctype-system-keyword",
        "duplicate-attribute",
        "abrupt-doctype-public-identifier",
        "abrupt-doctype-system-identifier",
        "incorrectly-closed-comment",
        "missing-doctype-public-identifier",
        "missing-doctype-system-identifier",
        "missing-attribute-value",
        "unexpected-character-after-doctype-system-identifier",
        "missing-whitespace-between-doctype-public-and-system-identifiers",
        "unexpected-equals-sign-before-attribute-name",
        "missing-end-tag-name",
        "nested-comment",
];

$functions = "";
$errorMap = [];
foreach ($errors as $error) {
    $message = str_replace('-', ' ', $error);
    $funcName = "get" . array_reduce(explode('-', $error), function($carry, $item) { return $carry . ucfirst($item); }, "");
    $errorMap[$error] = $funcName;

    $functions .= <<<EOF
        public static function $funcName(\$line = 0, \$col = 0) {
            return new ParseError("$error", "$message", \$line, \$col);
        }

EOF;

}

$generated = <<<'EOF'
<?php

namespace Woaf\HtmlTokenizer\Tables;

use Woaf\HtmlTokenizer\ParseError;

class ParseErrors {

    private static $errors = 
EOF;
$generated .= var_export($errorMap, true) . ";\n\n";
$generated .= $functions;
$generated .= <<<'EOF'

    /**
     * @return ParseError
     */
    public static function forCode($code, $col, $line) {
        $method = self::$errors[$code];
        return self::$method($line, $col);
    }

}
EOF;

file_put_contents(__DIR__ . '/ParseErrors.php', $generated);