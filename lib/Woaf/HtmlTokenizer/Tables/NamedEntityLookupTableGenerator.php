<?php
/**
 * Created by IntelliJ IDEA.
 * User: jonat
 * Date: 05/08/2017
 * Time: 08:42
 */

namespace Woaf\HtmlTokenizer\Tables;


class Node {
    public $key;
    public $value;

    public $parent;
    public $children;

}


$entities = json_decode(file_get_contents(__DIR__ . "/entities.json"), true);

$namedEntityLookup = [[], null];
foreach ($entities as $name => $data) {
    $name = ltrim($name, "&");
    $exploded = str_split($name);
    $cur = &$namedEntityLookup;
    foreach ($exploded as $char) {
        if (!isset($cur[0][$char])) {
            $cur[0][$char] = [[], null];
        }
        $cur = &$cur[0][$char];
    }
    $cur[1] = $data["characters"];
}


$namespace = __NAMESPACE__;
$generated = <<<EOF
<?php
// Auto generated named entity lookup table

namespace $namespace;

class NamedEntity {

EOF;

$lookup = [];

$generated .= "\t" . 'public static $TABLE = ' . var_export($namedEntityLookup, true) . ';';

$generated .= <<<'EOF'

}
EOF;

file_put_contents(__DIR__ . "/NamedEntity.php", $generated);

