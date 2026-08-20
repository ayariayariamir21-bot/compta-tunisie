<?php

namespace App\Livewire\Quotes;

use App\Models\Quote;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Show extends Component
{
    public function render(CurrentCompany $currentCompany): View
    {
        $company = $currentCompany->get(Auth::user());
        $quoteId = request()->route('quoteId');

        $quote = Quote::with([
            'lines.product',
            'lines.taxRate',
            'customer',
            'creator',
            'company',
        ])->findOrFail((int) $quoteId);

        if ($company && $quote->company_id !== $company->id) {
            abort(403);
        }

        return view('livewire.quotes.show', [
            'quote' => $quote,
        ]);
    }
}
