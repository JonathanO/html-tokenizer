<?php


namespace Woaf\HtmlTokenizer\Tables;

$errors = [
    "abrupt-closing-of-empty-comment",
    "abrupt-doctype-public-identifier",
    "abrupt-doctype-system-identifier",
    "absence-of-digits-in-numeric-character-reference",
    "cdata-in-html-content",
    "character-reference-outside-unicode-range",
    "control-character-in-input-stream",
    "control-character-reference",
    "end-tag-with-attributes",
    "duplicate-attribute",
    "end-tag-with-trailing-solidus",
    "eof-before-tag-name",
    "eof-in-cdata",
    "eof-in-comment",
    "eof-in-doctype",
    "eof-in-script-html-comment-like-text",
    "eof-in-tag",
    "incorrectly-closed-comment",
    "incorrectly-opened-comment",
    "invalid-character-sequence-after-doctype-name",
    "invalid-first-character-of-tag-name",
    "missing-attribute-value",
    "missing-doctype-name",
    "missing-doctype-public-identifier",
    "missing-doctype-system-identifier",
    "missing-end-tag-name",
    "missing-quote-before-doctype-public-identifier",
    "missing-quote-before-doctype-system-identifier",
    "missing-semicolon-after-character-reference",
    "missing-whitespace-after-doctype-public-keyword",
    "missing-whitespace-after-doctype-system-keyword",
    "missing-whitespace-before-doctype-name",
    "missing-whitespace-between-attributes",
    "missing-whitespace-between-doctype-public-and-system-identifiers",
    "nested-comment",
    "noncharacter-character-reference",
    "noncharacter-in-input-stream",
    "non-void-html-element-start-tag-with-trailing-solidus",
    "null-character-reference",
    "surrogate-character-reference",
    "surrogate-in-input-stream",
    "unexpected-character-after-doctype-system-identifier",
    "unexpected-character-in-attribute-name",
    "unexpected-character-in-unquoted-attribute-value",
    "unexpected-equals-sign-before-attribute-name",
    "unexpected-null-character",
    "unexpected-question-mark-instead-of-tag-name",
    "unexpected-solidus-in-tag",
    "unknown-named-character-reference"
];

$functions = "";
$errorMap = [];
$inits = "";
foreach ($errors as $error) {
    $message = str_replace('-', ' ', $error);
    $itemName = array_reduce(explode('-', $error), function($carry, $item) { return $carry . ucfirst($item); }, "");
    $funcName = "get" . $itemName;
    $staticName = lcfirst($itemName);
    $errorMap[$error] = $funcName;

    $functions .= <<<EOF
        public static function $funcName(\$line, \$col = null) {
            if (is_array(\$line)) {
                list(\$line, \$col) = \$line;
            } elseif (\$col === null) {
                throw new \Exception("No col value provided");
            }
            return new HtmlParseError("$error", "$message", \$line, \$col);
        }
        
        public \$$staticName;

EOF;

    $inits .= <<<EOF
    
            \$this->$staticName = function(\$line, \$col = null) {
                return self::$funcName(\$line, \$col);
            };
EOF;


}

$generated = <<<'EOF'
<?php

namespace Woaf\HtmlTokenizer\Tables;

use Woaf\HtmlTokenizer\HtmlParseError;

class ParseErrors {

    private static $instance = null;
    
    /**
     * @return ParseErrors
     */
    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new ParseErrors();
        }
        return self::$instance;
    }
    
    private function __construct() {
EOF;
$generated .= $inits;
$generated .= <<<'EOF'
    }

    private static $errors = 
EOF;
$generated .= var_export($errorMap, true) . ";\n\n";
$generated .= $functions;
$generated .= <<<'EOF'

    /**
     * @return HtmlParseError
     */
    public static function forCode($code, $line, $col) {
        $method = self::$errors[$code];
        return self::$method($line, $col);
    }


}
EOF;

file_put_contents(__DIR__ . '/ParseErrors.php', $generated);