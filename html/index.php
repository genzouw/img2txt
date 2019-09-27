<?php

$url = $_GET['url'];

if (parse_url($url)) {
    $trimLeft = $_GET['tl'] ?: 0;
    $trimRight = $_GET['tr'] ?: 0;
    $trimTop = ($_GET['tt'] ?: 0) + 1;
    $trimBottom = ($_GET['tb'] ?: 0) + 1;

    $sha256Url = hash_file('sha256', $url);

    header('Content-Type: text/plain;charset=UTF-8');
    $shell = "(cat /var/www/db/${sha256Url} || curl -sS '${url}' | /usr/lib/x86_64-linux-gnu/go/bin/ansize /dev/stdin /var/www/db/${sha256Url}) \
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
