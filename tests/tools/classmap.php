<?php

// Usage: php classmap.php <dir>  -- prints a PHP class map (class name => files) of every class/interface/trait in <dir>.
$root = $argv[1];
$map = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->getExtension() !== 'php') {
        continue;
    }
    $toks = token_get_all(file_get_contents($f->getPathname()));
    $ns = '';
    $n = count($toks);
    for ($i = 0;$i < $n;$i++) {
        $t = $toks[$i];
        if (!is_array($t)) {
            continue;
        }
        if ($t[0] === T_NAMESPACE) {
            $ns = '';
            for ($j = $i + 1;$j < $n;$j++) {
                if ($toks[$j] === ';' || $toks[$j] === '{') {
                    break;
                } if (is_array($toks[$j]) && in_array($toks[$j][0], [T_STRING,T_NAME_QUALIFIED,T_NS_SEPARATOR])) {
                    $ns .= $toks[$j][1];
                }
            }
        }
        if (in_array($t[0], [T_CLASS,T_INTERFACE,T_TRAIT])) {
            if ($i > 0 && is_array($toks[$i - 1]) && $toks[$i - 1][0] === T_DOUBLE_COLON) {
                continue;
            }
            for ($j = $i + 1;$j < $n;$j++) {
                if (is_array($toks[$j]) && $toks[$j][0] === T_STRING) {
                    $c = ($ns ? $ns.'\\' : '').$toks[$j][1];
                    $map[strtolower($c)][] = $f->getPathname();
                    break;
                } if ($toks[$j] === '{' || $toks[$j] === '(') {
                    break;
                }
            }
        }
    }
}
echo '<?php return ', var_export($map, true), ";\n";
