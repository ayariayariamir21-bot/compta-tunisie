<?php

namespace App\Services\Accounting;

use App\Enums\JournalType;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Journal;
use Illuminate\Support\Facades\DB;

class ChartOfJournalsSeeder
{
    /**
     * Seed the standard Tunisian accounting journals for a given company and fiscal year.
     *
     * Returns the number of journals created. Safe to call multiple times —
     * skips codes that already exist.
     */
    public function seed(Company $company, FiscalYear $fiscalYear): int
    {
        if ($fiscalYear->company_id !== $company->id) {
            throw new \InvalidArgumentException('L\'exercice n\'appartient pas à cette société.');
        }

        $journals = $this->defaultJournals();
        $count = 0;

        DB::transaction(function () use ($journals, $company, $fiscalYear, &$count) {
            foreach ($journals as $entry) {
                $exists = Journal::where('company_id', $company->id)
                    ->where('fiscal_year_id', $fiscalYear->id)
                    ->where('code', $entry['code'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                Journal::create([
                    'company_id' => $company->id,
                    'fiscal_year_id' => $fiscalYear->id,
                    'code' => $entry['code'],
                    'name' => $entry['name'],
                    'type' => $entry['type'],
                    'is_active' => true,
                ]);

                $count++;
            }
        });

        return $count;
    }

    /**
     * Default Tunisian accounting journals.
     */
    private function defaultJournals(): array
    {
        return [
            ['code' => 'AC', 'name' => 'Journal des achats', 'type' => JournalType::ACHATS],
            ['code' => 'VE', 'name' => 'Journal des ventes', 'type' => JournalType::VENTES],
            ['code' => 'BQ', 'name' => 'Journal de banque', 'type' => JournalType::BANQUE],
            ['code' => 'CA', 'name' => 'Journal de caisse', 'type' => JournalType::CAISSE],
            ['code' => 'OD', 'name' => 'Journal des opérations diverses', 'type' => JournalType::OPERATIONS_DIVERSES],
        ];
    }
}
