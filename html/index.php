<?php

$dbDir = dirname(__DIR__) . '/db';

$url = urldecode($_GET['url']);

if (parse_url($url)) {
    $width = $_GET['w'] ?? 100;
    $char = $_GET['c'] ?? '0';
    $trimLeft = $_GET['tl'] ?? 0;
    $trimRight = $_GET['tr'] ?? 0;
    $trimTop = ($_GET['tt'] ?? 0) + 1;
    $trimBottom = ($_GET['tb'] ?? 0);

    if ($width > 200) {
        $width = 200;
    }

    $sha256Url = hash('sha256', "{$url}#{$width}");

    header('Content-Type: text/plain;charset=UTF-8');
    $shell = "(
        cat ${dbDir}/${sha256Url} \
          || curl  -A 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:59.0) Gecko/20100101 Firefox/59.0' -sS '${url}' | ansize /dev/stdin ${dbDir}/${sha256Url} ${width} \
      ) \
        | sed 's/m1/m0/g' \
        | sed 's/m0/m${char}/g' \
        | sed 's/^\(\e[^\e]*\)\{${trimLeft}\}//; s/\(\e[^\e]*\)\{${trimRight}\}$//; ' \
        | tail -n +${trimTop} \
        | head -n -${trimBottom} \
        ;
    ";
    exec($shell, $output, $returnValue);

    foreach ($output as $row) {
        echo $row, PHP_EOL;
    }
}
