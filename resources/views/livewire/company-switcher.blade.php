<div>
    <flux:select
        wire:model.live="companyId"
        wire:change="switchCompany"
        class="w-full"
    >
        @foreach ($companies as $company)
            <flux:select.option value="{{ $company->id }}">
                {{ $company->name }}
            </flux:select.option>
        @endforeach
    </flux:select>

    @error('companyId')
        <flux:text class="mt-1 text-red-600">
            {{ $message }}
        </flux:text>
    @enderror
</div>
