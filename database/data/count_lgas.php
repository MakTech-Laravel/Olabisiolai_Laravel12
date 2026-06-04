<?php

$d = json_decode(file_get_contents(__DIR__ . '/nigeria_lgas_source.json'), true);
$c = 0;
foreach ($d as $lgas) {
    $c += count($lgas);
}
echo 'states:' . count($d) . ' lgas:' . $c . PHP_EOL;
