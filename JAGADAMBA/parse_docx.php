<?php
// Helper script to parse abc.docx text via PHP ZipArchive

$zip = new ZipArchive();
if ($zip->open('abc.docx') === TRUE) {
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    $dom = new DOMDocument();
    // Suppress warning for namespace prefixes in loadXML
    @$dom->loadXML($xml);
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

    $paragraphs = $xpath->query('//w:p');
    $out = "";
    foreach ($paragraphs as $p) {
        $texts = $xpath->query('.//w:t', $p);
        $pText = "";
        foreach ($texts as $t) {
            $pText .= $t->nodeValue;
        }
        $out .= $pText . "\n";
    }

    file_put_contents('abc_extracted.txt', $out);
    echo "Extracted successfully!";
} else {
    echo "Failed to open abc.docx";
}
?>
