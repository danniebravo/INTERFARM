<?php

namespace App\Services;

use App\Models\SubscriptionInvoice;

class InvoicePdfService
{
    protected array $parts = [];

    public function render(SubscriptionInvoice $invoice): string
    {
        $invoice->loadMissing(['user', 'plan']);
        $this->parts = [];

        $client = $invoice->user?->full_name ?: $invoice->user?->email ?: 'Cliente';
        $email = $invoice->user?->email ?: '-';
        $plan = $invoice->plan?->name ?: 'Suscripcion InterFarm';
        $total = $invoice->currency . ' $' . number_format((float) $invoice->amount, 0, ',', '.');
        $remaining = $invoice->status === 'paid'
            ? $invoice->currency . ' $0'
            : $total;

        $this->rect(0, 0, 612, 792, '0.97 0.99 0.98');
        $this->rect(54, 54, 504, 684, '1 1 1');
        $this->rect(54, 704, 504, 34, '0.09 0.40 0.20');

        $this->rect(82, 650, 42, 42, '0.09 0.40 0.20');
        $this->text('IF', 94, 666, 15, '1 1 1');
        $this->text('InterFarm', 136, 674, 22, '0.09 0.40 0.20');
        $this->text('Gestion ganadera SaaS', 136, 656, 9, '0.39 0.45 0.55');
        $this->text('Factura #' . $invoice->invoice_number, 82, 620, 18, '0.06 0.09 0.16');
        $this->pill($this->statusLabel($invoice->status), 82, 596);

        $this->text('Facturado a', 82, 546, 9, '0.39 0.45 0.55');
        $this->text($client, 82, 528, 13, '0.06 0.09 0.16');
        $this->text($email, 82, 511, 10, '0.39 0.45 0.55');

        $this->text('Facturado por', 392, 546, 9, '0.39 0.45 0.55');
        $this->text('InterFarm', 392, 528, 13, '0.06 0.09 0.16');
        $this->text('Plataforma SaaS', 392, 511, 10, '0.39 0.45 0.55');

        $this->rect(82, 462, 448, 50, '0.94 0.98 0.95');
        $this->text('Total', 104, 490, 9, '0.39 0.45 0.55');
        $this->text($total, 104, 472, 15, '0.09 0.40 0.20');
        $this->text('Vence', 260, 490, 9, '0.39 0.45 0.55');
        $this->text(optional($invoice->due_date)->format('d/m/Y') ?: '-', 260, 472, 13, '0.06 0.09 0.16');
        $this->text('Emitida', 398, 490, 9, '0.39 0.45 0.55');
        $this->text(optional($invoice->issue_date)->format('d/m/Y') ?: '-', 398, 472, 13, '0.06 0.09 0.16');

        $this->text('Detalle', 82, 418, 14, '0.06 0.09 0.16');
        $this->line(82, 398, 530, 398, '0.86 0.89 0.93');
        $this->text('Articulo', 82, 380, 9, '0.39 0.45 0.55');
        $this->text('Ctd.', 348, 380, 9, '0.39 0.45 0.55');
        $this->text('Precio', 410, 380, 9, '0.39 0.45 0.55');
        $this->text('Importe', 486, 380, 9, '0.39 0.45 0.55');
        $this->line(82, 366, 530, 366, '0.86 0.89 0.93');

        $this->text($plan, 82, 340, 12, '0.06 0.09 0.16');
        $period = 'Periodo ' . (optional($invoice->period_start)->format('d/m/Y') ?: '-') . ' - ' . (optional($invoice->period_end)->format('d/m/Y') ?: '-');
        $this->text($period, 82, 322, 9, '0.39 0.45 0.55');
        $this->text('1', 354, 340, 11, '0.06 0.09 0.16');
        $this->text($total, 400, 340, 11, '0.06 0.09 0.16');
        $this->text($total, 480, 340, 11, '0.06 0.09 0.16');
        $this->line(82, 298, 530, 298, '0.86 0.89 0.93');

        $this->text('Subtotal', 370, 250, 10, '0.39 0.45 0.55');
        $this->text($total, 472, 250, 10, '0.06 0.09 0.16');
        $this->text('Total', 370, 226, 13, '0.06 0.09 0.16');
        $this->text($total, 472, 226, 13, '0.06 0.09 0.16');
        $this->text('Pendiente', 370, 200, 13, '0.09 0.40 0.20');
        $this->text($remaining, 472, 200, 13, '0.09 0.40 0.20');

        $this->line(82, 152, 530, 152, '0.86 0.89 0.93');
        $this->text('Gracias por usar InterFarm.', 82, 128, 10, '0.39 0.45 0.55');
        $this->text('Esta factura fue generada automaticamente desde la plataforma.', 82, 112, 9, '0.39 0.45 0.55');

        return $this->pdf(implode("\n", $this->parts));
    }

    protected function text(string $text, int $x, int $y, int $size = 10, string $color = '0 0 0'): void
    {
        $this->parts[] = "BT\n{$color} rg\n/F1 {$size} Tf\n{$x} {$y} Td\n(" . $this->escape($text) . ") Tj\nET";
    }

    protected function rect(int $x, int $y, int $w, int $h, string $color): void
    {
        $this->parts[] = "{$color} rg\n{$x} {$y} {$w} {$h} re\nf";
    }

    protected function line(int $x1, int $y1, int $x2, int $y2, string $color): void
    {
        $this->parts[] = "{$color} RG\n0.8 w\n{$x1} {$y1} m\n{$x2} {$y2} l\nS";
    }

    protected function pill(string $text, int $x, int $y): void
    {
        $this->rect($x, $y - 9, 86, 20, '0.92 0.98 0.94');
        $this->text($text, $x + 10, $y - 3, 9, '0.09 0.40 0.20');
    }

    protected function pdf(string $stream): string
    {
        $objects = [];
        $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>";
        $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objects[] = "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $number = $index + 1;
            $pdf .= "{$number} 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    protected function escape(string $text): string
    {
        if (function_exists('iconv')) {
            $text = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text) ?: $text;
        }

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    protected function statusLabel(string $status): string
    {
        return [
            'pending' => 'Sin pagar',
            'paid' => 'Pagada',
            'overdue' => 'Vencida',
            'cancelled' => 'Cancelada',
        ][$status] ?? ucfirst($status);
    }
}
