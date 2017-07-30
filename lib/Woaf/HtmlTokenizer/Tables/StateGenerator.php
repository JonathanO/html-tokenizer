<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 30/07/2017
 * Time: 15:32
 */

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