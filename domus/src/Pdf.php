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
        return self::build(implode("\n", $c));
    }

    /**
     * Preventivo / proposta di parcella: intestazione dello studio, destinatario, voci con importi allineati, totale.
     * @param list<array{0:string,1:string,2:float}> $rows [voce, scadenza, importo]
     * @param list<string> $meta righe sotto il titolo (cliente, progetto, luogo…)
     */
    public static function quote(string $studio, string $title, array $meta, array $rows, string $notes): string
    {
        $c = [];
        $c[] = '0.06 0.06 0.06 rg 0 772 595 70 re f';
        $c[] = 'BT /F2 11 Tf 1 1 1 rg 40 812 Td (' . self::esc(mb_strtoupper($studio)) . ') Tj ET';
        $c[] = 'BT /F1 8 Tf 0.65 0.65 0.65 rg 40 796 Td (DOMUS  -  INTELLIGENT BUILDING DESIGN) Tj ET';
        $c[] = 'BT /F1 9 Tf 0.85 0.85 0.85 rg 430 812 Td (' . self::esc('Firenze, ' . date('d/m/Y')) . ') Tj ET';
        $c[] = 'BT /F2 22 Tf 0.07 0.07 0.07 rg 40 722 Td (' . self::esc($title) . ') Tj ET';
        $y = 698;
        foreach ($meta as $line) {
            $c[] = 'BT /F1 10.5 Tf 0.3 0.3 0.3 rg 40 ' . $y . ' Td (' . self::esc($line) . ') Tj ET';
            $y -= 16;
        }
        $y -= 14;
        $c[] = "0.07 0.07 0.07 RG 0.8 w 40 $y m 555 $y l S";
        $c[] = 'BT /F2 8 Tf 0.45 0.45 0.45 rg 40 ' . ($y + 8) . ' Td (VOCE) Tj ET';
        $c[] = 'BT /F2 8 Tf 0.45 0.45 0.45 rg 360 ' . ($y + 8) . ' Td (PREVISTA) Tj ET';
        $c[] = 'BT /F2 8 Tf 0.45 0.45 0.45 rg 515 ' . ($y + 8) . ' Td (IMPORTO) Tj ET';
        $y -= 22;
        $total = 0.0;
        foreach ($rows as [$desc, $due, $amount]) {
            $total += $amount;
            $money = self::money($amount);
            $c[] = 'BT /F1 11 Tf 0.1 0.1 0.1 rg 40 ' . $y . ' Td (' . self::esc($desc) . ') Tj ET';
            $c[] = 'BT /F1 10 Tf 0.4 0.4 0.4 rg 360 ' . $y . ' Td (' . self::esc($due) . ') Tj ET';
            $c[] = 'BT /F1 11 Tf 0.1 0.1 0.1 rg ' . self::right(555, $money, 11) . " $y Td (" . self::esc($money) . ') Tj ET';
            $c[] = '0.85 0.85 0.85 RG 0.4 w 40 ' . ($y - 9) . ' m 555 ' . ($y - 9) . ' l S';
            $y -= 26;
        }
        $y -= 6;
        $tot = self::money($total);
        $c[] = 'BT /F1 9 Tf 0.45 0.45 0.45 rg 250 ' . $y . ' Td (TOTALE, oltre IVA e cassa) Tj ET';
        $c[] = 'BT /F2 15 Tf 0.07 0.07 0.07 rg ' . self::right(555, $tot, 15, true) . " $y Td (" . self::esc($tot) . ') Tj ET';
        $y -= 44;
        foreach (explode("\n", wordwrap($notes, 95, "\n", true)) as $line) {
            $c[] = 'BT /F1 9.5 Tf 0.35 0.35 0.35 rg 40 ' . $y . ' Td (' . self::esc($line) . ') Tj ET';
            $y -= 14;
        }
        $c[] = '0.8 0.8 0.8 RG 0.6 w 40 90 m 250 90 l S';
        $c[] = 'BT /F1 8.5 Tf 0.45 0.45 0.45 rg 40 76 Td (Per accettazione - data e firma del committente) Tj ET';
        $c[] = 'BT /F1 8 Tf 0.55 0.55 0.55 rg 40 40 Td (' . self::esc($studio . ' - documento generato da DOMUS') . ') Tj ET';
        return self::build(implode("\n", $c));
    }

    private static function money(float $v): string
    {
        return number_format($v, 2, ',', '.') . ' EUR';
    }

    // Posizione x per allineare a destra (larghezze medie dei caratteri Helvetica).
    private static function right(float $edge, string $text, float $size, bool $bold = false): float
    {
        $w = 0.0;
        foreach (str_split($text) as $ch) {
            $w += ctype_digit($ch) ? 0.556 : ($ch === '.' || $ch === ',' || $ch === ' ' ? 0.278 : ($bold ? 0.70 : 0.65));
        }
        return round($edge - $w * $size, 2);
    }

    private static function build(string $stream): string
    {
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
