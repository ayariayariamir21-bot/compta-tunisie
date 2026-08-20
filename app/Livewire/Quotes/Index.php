<?php

namespace App\Livewire\Quotes;

use App\Enums\QuoteStatus;
use App\Models\Customer;
use App\Models\Quote;
use App\Services\CurrentCompany;
use App\Services\QuoteService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Index extends Component
{
    public string $search = '';

    public string $filterStatus = '';

    public ?int $filterCustomerId = null;

    public string $filterDateFrom = '';

    public string $filterDateTo = '';

    public int $perPage = 15;

    public function delete(Quote $quote, QuoteService $quoteService): void
    {
        if (Auth::user()->cannot('delete', $quote)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        if (! $currentCompany || $quote->company_id !== $currentCompany->id) {
            abort(403);
        }

        try {
            $quoteService->deleteDraft($quote);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
            $this->redirect(route('quotes.index'), navigate: true);

            return;
        }

        session()->flash('success', "Le devis « {$quote->quote_number} » a été supprimé.");
        $this->redirect(route('quotes.index'), navigate: true);
    }

    public function transitionTo(Quote $quote, string $action, QuoteService $quoteService): void
    {
        if (Auth::user()->cannot($action, $quote)) {
            abort(403);
        }

        $currentCompany = app(CurrentCompany::class)->get(Auth::user());
        if (! $currentCompany || $quote->company_id !== $currentCompany->id) {
            abort(403);
        }

        try {
            match ($action) {
                'send' => $quoteService->send($quote),
                'accept' => $quoteService->accept($quote),
                'reject' => $quoteService->reject($quote),
                'cancel' => $quoteService->cancel($quote),
                default => null,
            };
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
            $this->redirect(route('quotes.index'), navigate: true);

            return;
        }

        $statusLabel = match ($action) {
            'send' => 'envoyé',
            'accept' => 'accepté',
            'reject' => 'refusé',
            'cancel' => 'annulé',
            default => $action,
        };

        session()->flash('success', "Le devis « {$quote->quote_number} » a été {$statusLabel}.");
        $this->redirect(route('quotes.index'), navigate: true);
    }

    public function render(CurrentCompany $currentCompany): View
    {
        $company = $currentCompany->get(Auth::user());
        $quotes = collect();
        $customers = collect();

        if ($company) {
            $query = Quote::where('quotes.company_id', $company->id)
                ->with('customer');

            if ($this->search !== '') {
                $search = $this->search;
                $query->where(function ($q) use ($search) {
                    $q->where('quote_number', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            }

            if ($this->filterStatus !== '') {
                $query->where('status', $this->filterStatus);
            }

            if ($this->filterCustomerId !== null) {
                $query->where('customer_id', $this->filterCustomerId);
            }

            if ($this->filterDateFrom !== '') {
                $query->where('quote_date', '>=', $this->filterDateFrom);
            }

            if ($this->filterDateTo !== '') {
                $query->where('quote_date', '<=', $this->filterDateTo);
            }

            $quotes = $query->orderByDesc('quote_date')
                ->orderByDesc('id')
                ->paginate($this->perPage);

            $customers = Customer::where('company_id', $company->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        return view('livewire.quotes.index', [
            'quotes' => $quotes,
            'currentCompany' => $company,
            'quoteStatuses' => QuoteStatus::cases(),
            'customers' => $customers,
        ]);
    }
}
