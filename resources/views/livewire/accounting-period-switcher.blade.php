<div>
    @if ($periods->isNotEmpty())
        <flux:select
            wire:model.live="periodId"
            wire:change="switchPeriod"
            class="w-full"
        >
            <flux:select.option value="">
                Toutes les périodes
            </flux:select.option>

            @foreach ($periods as $period)
                <flux:select.option value="{{ $period->id }}">
                    {{ $period->name }} ({{ $period->start_date->format('d/m') }} — {{ $period->end_date->format('d/m') }})
                </flux:select.option>
            @endforeach
        </flux:select>

        @error('periodId')
            <flux:text class="mt-1 text-red-600">
                {{ $message }}
            </flux:text>
        @enderror
    @endif
</div>
