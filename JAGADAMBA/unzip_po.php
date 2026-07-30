<?php
// unzip_po.php
$excelFile = "c:/Users/DELL/OneDrive/Desktop/sahara electrical/SAHARA ELECTRICAL-A-1 & A-2 (1).XLSX";
$outputDir = "c:/Users/DELL/OneDrive/Desktop/sahara electrical";

$zip = new ZipArchive();
if ($zip->open($excelFile) !== TRUE) {
    file_put_contents("$outputDir/parsed_po.txt", "Failed to open Excel zip file: $excelFile");
    exit("Failed to open Excel zip file: $excelFile");
}

// 1. Get Shared Strings
$sharedStrings = [];
$stringsXmlStr = $zip->getFromName("xl/sharedStrings.xml");
if ($stringsXmlStr) {
    $xml = simplexml_load_string($stringsXmlStr);
    foreach ($xml->si as $si) {
        $sharedStrings[] = (string)($si->t ?? $si->r->t ?? "");
    }
}

// 2. Get Sheet 1 data
$sheetXmlStr = $zip->getFromName("xl/worksheets/sheet1.xml");
if (!$sheetXmlStr) {
    file_put_contents("$outputDir/parsed_po.txt", "Failed to get sheet1.xml");
    exit("Failed to get sheet1.xml");
}

$xml = simplexml_load_string($sheetXmlStr);
$rows = [];
foreach ($xml->sheetData->row as $rowNode) {
    $rowIndex = (int)$rowNode['r'];
    $rowCells = [];
    foreach ($rowNode->c as $cell) {
        $cellRef = (string)$cell['r'];
        // Get column letter
        preg_match('/^[A-Z]+/', $cellRef, $matches);
        $colLetter = $matches[0];

        $valueType = (string)$cell['t'];
        $val = (string)$cell->v;

        if ($valueType === 's') {
            $val = $sharedStrings[(int)$val] ?? '';
        }
        $rowCells[$colLetter] = $val;
    }
    $rows[$rowIndex] = $rowCells;
}

$output = "Total rows found in sheet1: " . count($rows) . PHP_EOL . PHP_EOL;
foreach ($rows as $rIdx => $cols) {
    $output .= "Row $rIdx: ";
    foreach ($cols as $colLetter => $val) {
        $output .= "[$colLetter]: \"$val\"  ";
    }
    $output .= PHP_EOL;
}

file_put_contents("$outputDir/parsed_po.txt", $output);
echo "Done unzipping!";
