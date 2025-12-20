<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

libxml_use_internal_errors(true);

$html = file_get_contents(__DIR__ . '/debug_cfr.html');
if (!$html) {
    die('HTML non caricato');
}

$dom = new DOMDocument();
$dom->loadHTML($html);
$xp = new DOMXPath($dom);

$rows = $xp->query('//tr');

echo 'Numero <tr> trovate: ' . $rows->length;
