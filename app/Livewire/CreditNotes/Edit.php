<?php

namespace App\Livewire\CreditNotes;

use App\Enums\CreditNoteStatus;
use App\Models\CreditNote;
use App\Services\CreditNoteService;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read array{subtotal: numeric-string, discount_total: numeric-string, tax_total: numeric-string, total: numeric-string} $totals
 */
#[Layout('layouts.app')]
#[Title('Modifier l\'avoir')]
class Edit extends Component
{
    public CreditNote $creditNote;

    public string $credit_note_date = '';

    public ?string $reason = null;

    public ?string $notes = null;

    /** @var array<int, array{invoice_line_id: int, description: string, original_quantity: string, credited_quantity: string, available_quantity: string, quantity: string, unit_price: string}> */
    public array $lines = [];

    public function mount(int $creditNoteId): void
    {
        $currentCompany = app(CurrentCompany::class)->get(Auth::user());

        $this->creditNote = CreditNote::with(['lines.invoiceLine', 'invoice', 'customer'])
            ->findOrFail($creditNoteId);

        if (! $currentCompany || $this->creditNote->company_id !== $currentCompany->id) {
            abort(404);
        }

        if ($this->creditNote->status !== CreditNoteStatus::DRAFT) {
            session()->flash('error', 'Seul un avoir en brouillon peut être modifié.');
            $this->redirect(route('credit-notes.show', $this->creditNote->id), navigate: true);

            return;
        }

        $this->credit_note_date = $this->creditNote->credit_note_date->toDateString();
        $this->reason = $this->creditNote->reason;
        $this->notes = $this->creditNote->notes;

        $remainingByInvoiceLineId = [];
        if ($this->creditNote->invoice) {
            foreach (app(CreditNoteService::class)->determineRemainingAmounts($this->creditNote->invoice) as $entry) {
                $remainingByInvoiceLineId[(int) $entry['line']->id] = $entry;
            }
        }

        foreach ($this->creditNote->lines as $line) {
            $entry = $remainingByInvoiceLineId[(int) $line->invoice_line_id] ?? null;

            $this->lines[] = [
                'invoice_line_id' => (int) $line->invoice_line_id,
                'description' => $line->description,
                'original_quantity' => (string) ($entry['original_quantity'] ?? $line->quantity),
                'credited_quantity' => (string) ($entry['credited_quantity'] ?? '0'),
                // Availability for this draft excludes its own draft quantity.
                'available_quantity' => $entry !== null
                    ? bcadd($entry['remaining_quantity'], (string) $line->quantity, 3)
                    : (string) $line->quantity,
                'quantity' => (string) $line->quantity,
                'unit_price' => (string) $line->unit_price,
            ];
        }
    }

    /** @return array{subtotal: numeric-string, discount_total: numeric-string, tax_total: numeric-string, total: numeric-string} */
    public function getTotalsProperty(): array
    {
        bcscale(3);
        $service = app(CreditNoteService::class);

        $subtotal = '0';
        $discountTotal = '0';
        $taxTotal = '0';
        $total = '0';

        foreach ($this->lines as $inputLine) {
            $line = $this->creditNote->lines->firstWhere('invoice_line_id', $inputLine['invoice_line_id']);
            if (! $line || bccomp($service->toDecimal($inputLine['quantity']), '0', 3) <= 0) {
                continue;
            }

            $calculated = $service->calculateLine(
                $inputLine['quantity'],
                (string) $line->unit_price,
                (string) $line->discount_percent,
                (string) $line->tax_rate
            );

            $subtotal = bcadd($subtotal, $calculated['line_subtotal'], 3);
            $discountTotal = bcadd($discountTotal, $calculated['discount_amount'], 3);
            $taxTotal = bcadd($taxTotal, $calculated['tax_amount'], 3);
            $total = bcadd($total, $calculated['line_total'], 3);
        }

        return [
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'total' => $total,
        ];
    }

    public function update(CreditNoteService $creditNoteService): void
    {
        if (Auth::user()->cannot('update', $this->creditNote)) {
            abort(403);
        }

        $this->validate([
            'credit_note_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $linesData = [];
        foreach ($this->lines as $inputLine) {
            $linesData[] = [
                'invoice_line_id' => $inputLine['invoice_line_id'],
                'quantity' => $inputLine['quantity'],
            ];
        }

        try {
            $creditNoteService->updateDraft($this->creditNote, [
                'credit_note_date' => $this->credit_note_date,
                'reason' => $this->reason,
                'notes' => $this->notes,
                'lines' => $linesData,
            ]);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        session()->flash('success', "L'avoir « {$this->creditNote->credit_note_number} » a été mis à jour.");
        $this->redirect(route('credit-notes.show', $this->creditNote->id), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.credit-notes.edit', [
            'totals' => $this->totals,
        ]);
    }
}
