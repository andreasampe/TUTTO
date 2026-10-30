<?php
// Generatore PDF minimo (una pagina A4, Helvetica), senza librerie esterne.
// Usato per i documenti dimostrativi e per le schede stampabili generate dal server.
declare(strict_types=1);

namespace Domus;

final class Pdf
{
    /**
     * @param list<string> $lines righe di testo del corpo
     * @param bool $drawing aggiunge un riquadro con una pianta schematica (per le tavole)
     */
    public static function simple(string $studio, string $subtitle, array $lines, bool $drawing = false): string
    {
        $c = [];
        // intestazione con il marchio DOMUS stilizzato
        $c[] = '0.06 0.06 0.06 rg 0 792 595 50 re f';
        $c[] = 'BT /F2 9 Tf 1 1 1 rg 40 812 Td (' . self::esc(mb_strtoupper($studio)) . ') Tj ET';
        $c[] = 'BT /F1 8 Tf 0.7 0.7 0.7 rg 40 800 Td (DOMUS  -  DESIGN  ORGANIZE  MODEL  UNIFY  SIMPLIFY) Tj ET';
        $c[] = 'BT /F2 18 Tf 0.07 0.07 0.07 rg 40 745 Td (' . self::esc($subtitle) . ') Tj ET';
        $c[] = '0.8 0.8 0.8 RG 0.6 w 40 732 m 555 732 l S';
        $y = 705;
        foreach ($lines as $line) {
            $c[] = 'BT /F1 11 Tf 0.15 0.15 0.15 rg 40 ' . $y . ' Td (' . self::esc($line) . ') Tj ET';
            $y -= 18;
        }
        if ($drawing) {
            // pianta schematica: muri, porte, quote
            $c[] = '0.1 0.1 0.1 RG 3 w 90 200 m 505 200 l 505 520 l 90 520 l h S';
            $c[] = '1.5 w 300 200 m 300 380 l 90 380 m 230 380 l 300 430 m 300 520 l 400 380 m 505 380 l S';
            $c[] = '0.6 w 230 380 m 230 410 l S 0.5 0.5 0.5 RG 0.4 w 90 180 m 505 180 l 90 176 m 90 184 l 505 176 m 505 184 l S';
            $c[] = 'BT /F1 9 Tf 0.4 0.4 0.4 rg 280 168 Td (14,20 m) Tj ET';
            foreach ([['Soggiorno', 150, 300], ['Cucina', 380, 300], ['Camera', 150, 450], ['Bagno', 380, 450]] as [$t, $x, $yy]) {
                $c[] = "BT /F1 10 Tf 0.3 0.3 0.3 rg $x $yy Td (" . self::esc($t) . ') Tj ET';
            }
        }
        $c[] = '0.8 0.8 0.8 RG 0.6 w 40 60 m 555 60 l S';
        $c[] = 'BT /F1 8 Tf 0.5 0.5 0.5 rg 40 45 Td (Documento dimostrativo generato da DOMUS il ' . date('d/m/Y') . ') Tj ET';
        $stream = implode("\n", $c);

        $objs = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream",
        ];
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objs as $i => $o) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $o . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $off) {
            $pdf .= sprintf("%010d 00000 n \n", $off);
        }
        return $pdf . "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    }

    // Testo in WinAnsi con le parentesi e le barre protette.
    private static function esc(string $s): string
    {
        $s = strtr($s, ['—' => '-', '’' => "'", '·' => '-', '²' => '2']);
        $s = (string) @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }
}
