<?php

namespace App\Livewire\Quotes;

use App\Models\Quote;
use App\Services\CurrentCompany;
use App\Services\QuoteService;
use App\Services\Reporting\SalesDocumentMailer;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Devis')]
class Show extends Component
{
    public int $quoteId;

    public function mount(int $quoteId): void
    {
        $this->quoteId = $quoteId;
        $this->quote();
    }

    public function transitionTo(string $action, QuoteService $quoteService): void
    {
        if (! in_array($action, ['send', 'accept', 'reject', 'cancel'], true)) {
            abort(404);
        }

        $quote = $this->quote();

        if (Auth::user()->cannot($action, $quote)) {
            abort(403);
        }

        try {
            match ($action) {
                'send' => $quoteService->send($quote),
                'accept' => $quoteService->accept($quote),
                'reject' => $quoteService->reject($quote),
                'cancel' => $quoteService->cancel($quote),
            };

            $statusLabel = match ($action) {
                'send' => 'envoyé',
                'accept' => 'accepté',
                'reject' => 'refusé',
                'cancel' => 'annulé',
            };

            session()->flash('success', "Le devis « {$quote->quote_number} » a été {$statusLabel}.");
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->redirect(route('quotes.show', $quote->id), navigate: true);
    }

    public function sendByEmail(SalesDocumentMailer $mailer): void
    {
        $quote = $this->quote();

        try {
            $recipient = $mailer->sendQuote($quote);
            session()->flash('success', "Le devis « {$quote->quote_number} » a été envoyé à {$recipient}.");
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->redirect(route('quotes.show', $quote->id), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.quotes.show', [
            'quote' => $this->quote(),
        ]);
    }

    private function quote(): Quote
    {
        $company = app(CurrentCompany::class)->get(Auth::user());

        if (! $company) {
            abort(403);
        }

        $quote = Quote::with([
            'lines.product',
            'lines.taxRate',
            'customer',
            'creator',
            'company',
        ])->where('company_id', $company->id)->findOrFail($this->quoteId);

        if (Auth::user()->cannot('view', $quote)) {
            abort(403);
        }

        return $quote;
    }
}
