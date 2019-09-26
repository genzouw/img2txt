<?php

$url = $_GET['url'];

if (parse_url($url)) {
    $sha256Url = hash_file('sha256', $url);

    header('Content-Type: text/plain;charset=UTF-8');
    $shell = 'cat /var/www/db/' . $sha256Url . ' || curl -sS "' . $url . '" | /usr/lib/x86_64-linux-gnu/go/bin/ansize /dev/stdin /var/www/db/' . $sha256Url . ';';
    exec($shell, $output, $returnValue);

    foreach ($output as $row) {
        echo $row, PHP_EOL;
    }
}
