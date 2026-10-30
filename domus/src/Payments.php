<?php
// Economia del progetto: rate dell'onorario (acconti, saldo), fatture e incassi.
// Visibile solo all'amministratore.
declare(strict_types=1);

namespace Domus;

final class Payments
{
    /** @return array<string,mixed> */
    public static function forProject(int $projectId): array
    {
        $rows = Db::all('SELECT * FROM payments WHERE project_id = ? ORDER BY due_date IS NULL, due_date, id', [$projectId]);
        $fee = (float) Db::value('SELECT COALESCE(fee, 0) FROM projects WHERE id = ?', [$projectId]);
        return ['items' => $rows, 'summary' => self::summary($rows, $fee)];
    }

    /** @param list<array<string,mixed>> $rows */
    private static function summary(array $rows, float $fee): array
    {
        $planned = $invoiced = $paid = 0.0;
        foreach ($rows as $r) {
            $planned += $r['amount'];
            if ($r['invoiced_at']) {
                $invoiced += $r['amount'];
            }
            if ($r['paid_at']) {
                $paid += $r['amount'];
            }
        }
        return [
            'fee' => $fee,
            'planned' => round($planned, 2),
            'invoiced' => round($invoiced, 2),
            'paid' => round($paid, 2),
            'to_collect' => round($invoiced - $paid, 2),
            'unplanned' => round(max(0, $fee - $planned), 2),
        ];
    }

    /** @return array<string,mixed> totali dello studio, per la panoramica */
    public static function studioSummary(): array
    {
        $year = date('Y');
        return [
            'to_collect' => (float) Db::value('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoiced_at IS NOT NULL AND paid_at IS NULL'),
            'to_invoice' => (float) Db::value('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoiced_at IS NULL AND due_date <= ?', [App::today()]),
            'paid_year' => (float) Db::value("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE paid_at LIKE ?", [$year . '-%']),
            'overdue' => Db::all(
                "SELECT pa.*, p.code AS project_code, p.title AS project_title FROM payments pa JOIN projects p ON p.id = pa.project_id
                 WHERE pa.invoiced_at IS NOT NULL AND pa.paid_at IS NULL ORDER BY pa.invoiced_at LIMIT 10"
            ),
        ];
    }

    // Preventivo in PDF con le rate del progetto.
    public static function quotePdf(int $projectId): never
    {
        $p = Db::one(
            'SELECT p.*, c.name AS client_name, c.address AS client_address, c.city AS client_city, c.tax_code, c.vat
             FROM projects p LEFT JOIN clients c ON c.id = p.client_id WHERE p.id = ?',
            [$projectId]
        );
        if (!$p) {
            throw new HttpError(404, 'Progetto non trovato');
        }
        $items = Db::all('SELECT * FROM payments WHERE project_id = ? ORDER BY due_date IS NULL, due_date, id', [$projectId]);
        $rows = $items
            ? array_map(fn ($r) => [$r['description'], $r['due_date'] ? date('d/m/Y', strtotime($r['due_date'])) : '-', (float) $r['amount']], $items)
            : [['Onorario professionale', '-', (float) $p['fee']]];
        $meta = array_values(array_filter([
            $p['client_name'] ? 'Spett.le ' . $p['client_name'] : null,
            trim(($p['client_address'] ?? '') . ' ' . ($p['client_city'] ?? '')) ?: null,
            $p['vat'] ? 'P.IVA ' . $p['vat'] : ($p['tax_code'] ? 'C.F. ' . $p['tax_code'] : null),
            '',
            'Oggetto: ' . $p['title'] . ' (' . $p['code'] . ')',
            trim(($p['intervention'] ?? '') . ' - ' . ($p['address'] ?? '') . ' ' . ($p['city'] ?? ''), ' -'),
        ], fn ($v) => $v !== null));
        $notes = 'Gli importi si intendono al netto di contributo integrativo e IVA di legge. Le rate sono fatturate al raggiungimento '
            . 'di ciascuna fase. Il presente preventivo ha validità 60 giorni dalla data di emissione.';
        $pdf = Pdf::quote(Settings::get('studio_name') ?: 'Studio di architettura', 'Proposta di parcella', $meta, $rows, $notes);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="preventivo-' . $p['code'] . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    public static function create(array $in): int
    {
        $data = self::clean($in);
        $data['project_id'] = Validate::id($in['project_id'] ?? null, 'projects', 'project_id', required: true);
        return Db::insert('payments', $data);
    }

    public static function update(int $id, array $in): void
    {
        $row = Db::one('SELECT * FROM payments WHERE id = ?', [$id]);
        if (!$row) {
            throw new HttpError(404, 'Voce non trovata');
        }
        Db::update('payments', $id, self::clean($in + $row));
    }

    public static function delete(int $id): void
    {
        if (!Db::run('DELETE FROM payments WHERE id = ?', [$id])) {
            throw new HttpError(404, 'Voce non trovata');
        }
    }

    /** @return array<string,mixed> */
    private static function clean(array $in): array
    {
        $amount = Validate::money($in['amount'] ?? null, 'amount');
        if ($amount === null || $amount <= 0) {
            throw new HttpError(422, 'Indica un importo', ['amount' => 'Obbligatorio']);
        }
        $data = [
            'description' => Validate::required($in['description'] ?? '', 'description', 160),
            'amount' => $amount,
            'due_date' => Validate::date($in['due_date'] ?? null, 'due_date'),
            'invoice_no' => Validate::text($in['invoice_no'] ?? null, 40),
            'invoiced_at' => Validate::date($in['invoiced_at'] ?? null, 'invoiced_at'),
            'paid_at' => Validate::date($in['paid_at'] ?? null, 'paid_at'),
            'notes' => Validate::text($in['notes'] ?? null, 2000),
        ];
        // Se è incassata è per forza anche fatturata.
        if ($data['paid_at'] && !$data['invoiced_at']) {
            $data['invoiced_at'] = $data['paid_at'];
        }
        return $data;
    }
}
