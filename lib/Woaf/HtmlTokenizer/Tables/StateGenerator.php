<?php


namespace Woaf\HtmlTokenizer\Tables;

$rawStates = [
    "DATA",
    "RCDATA",
    "RAWTEXT",
    "SCRIPT_DATA",
    "PLAINTEXT",
    "RCDATA_LT_SIGN",
    "SCRIPT_DATA_LT_SIGN",
    "MARKUP_DECLARATION_OPEN",
    "END_TAG_OPEN",
    "BOGUS_COMMENT",
    "TAG_NAME",
    "BEFORE_ATTRIBUTE_NAME",
    "SELF_CLOSING_START_TAG",
    "RCDATA_END_TAG_OPEN",
    "TAG_OPEN",
    "RCDATA_END_TAG_NAME",
    "RAWTEXT_LT_SIGN",
    "RAWTEXT_END_TAG_OPEN",
    "RAWTEXT_END_TAG_NAME",
    "SCRIPT_DATA_END_TAG_OPEN",
    "SCRIPT_DATA_ESCAPE_START",
    "SCRIPT_DATA_END_TAG_NAME",
    "SCRIPT_DATA_ESCAPED_DASH_DASH",
    "SCRIPT_DATA_ESCAPED",
    "SCRIPT_DATA_ESCAPE_START_DASH",
    "SCRIPT_DATA_ESCAPED_DASH",
    "SCRIPT_DATA_ESCAPED_LT_SIGN",
    "SCRIPT_DATA_ESCAPED_END_TAG_OPEN",
    "SCRIPT_DATA_DOUBLE_ESCAPE_START",
    "SCRIPT_DATA_ESCAPED_END_TAG_NAME",
    "SCRIPT_DATA_DOUBLE_ESCAPED",
    "SCRIPT_DATA_DOUBLE_ESCAPED_DASH",
    "SCRIPT_DATA_DOUBLE_ESCAPED_LT_SIGN",
    "SCRIPT_DATA_DOUBLE_ESCAPED_DASH_DASH",
    "SCRIPT_DATA_DOUBLE_ESCAPE_END",
    "ATTRIBUTE_NAME",
    "AFTER_ATTRIBUTE_NAME",
    "BEFORE_ATTRIBUTE_VALUE",
    "ATTRIBUTE_VALUE_UNQUOTED",
    "ATTRIBUTE_VALUE_SINGLE_QUOTED",
    "ATTRIBUTE_VALUE_DOUBLE_QUOTED",
    "AFTER_ATTRIBUTE_VALUE_QUOTED",
    "COMMENT_START",
    "COMMENT_START_DASH",
    "COMMENT",
    "COMMENT_END",
    "COMMENT_END_DASH",
    "COMMENT_END_BANG",
    "DOCTYPE",
    "CDATA_SECTION",
    "BEFORE_DOCTYPE_NAME",
    "DOCTYPE_NAME",
    "AFTER_DOCTYPE_NAME",
    "AFTER_DOCTYPE_SYSTEM_KEYWORD",
    "AFTER_DOCTYPE_PUBLIC_KEYWORD",
    "BOGUS_DOCTYPE",
    "BEFORE_DOCTYPE_PUBLIC_IDENTIFIER",
    "DOCTYPE_PUBLIC_IDENTIFIER_DOUBLE_QUOTED",
    "DOCTYPE_PUBLIC_IDENTIFIER_SINGLE_QUOTED",
    "AFTER_DOCTYPE_PUBLIC_IDENTIFIER",
    "BETWEEN_DOCTYPE_PUBLIC_AND_SYSTEM_IDENTIFIERS",
    "DOCTYPE_SYSTEM_IDENTIFIER_DOUBLE_QUOTED",
    "DOCTYPE_SYSTEM_IDENTIFIER_SINGLE_QUOTED",
    "BEFORE_DOCTYPE_SYSTEM_IDENTIFIER",
    "AFTER_DOCTYPE_SYSTEM_IDENTIFIER",
    "COMMENT_LT_SIGN",
    "COMMENT_LT_SIGN_BANG",
    "COMMENT_LT_SIGN_BANG_DASH",
    "COMMENT_LT_SIGN_BANG_DASH_DASH",
    "CDATA_SECTION_BRACKET",
    "CDATA_SECTION_END",
    "CHARACTER_REFERENCE",
    "NAMED_CHARACTER_REFERENCE",
    "AMBIGUOUS_AMPERSAND",
    "NUMERIC_CHARACTER_REFERENCE",
    "HEXADECIMAL_CHARACTER_REFERENCE_START",
    "DECIMAL_CHARACTER_REFERENCE_START",
    "HEXADECIMAL_CHARACTER_REFERENCE",
    "DECIMAL_CHARACTER_REFERENCE",
    "NUMERIC_CHARACTER_REFERENCE_END",
];

$namespace = __NAMESPACE__;
$generated = <<<EOF
<?php
// Auto generated state table

namespace $namespace;

class State {

EOF;

$lookup = [];

$i = 1;
foreach ($rawStates as $state) {
    $name = "STATE_" . $state;
    $generated .= "\tpublic static $" . $name . " = " . $i . ";\n";
    $lookup[$i] = $name;
    $i++;
}

$generated .= "\t" . 'private static $lookup = ' . var_export($lookup, true) . ';';

$generated .= <<<'EOF'

    public static function toName($state) {
        return self::$lookup[$state];
    }

}
EOF;

file_put_contents(__DIR__ . "/State.php", $generated);