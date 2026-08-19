<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use Illuminate\Support\Facades\DB;

class ChartOfAccountsSeeder
{
    /**
     * Seed a basic Tunisian chart of accounts for a given company and fiscal year.
     *
     * Returns the number of accounts created. Safe to call multiple times —
     * skips codes that already exist.
     */
    public function seed(Company $company, FiscalYear $fiscalYear): int
    {
        if ($fiscalYear->company_id !== $company->id) {
            throw new \InvalidArgumentException('L\'exercice n\'appartient pas à cette société.');
        }

        $chart = $this->defaultChart();
        $count = 0;

        DB::transaction(function () use ($chart, $company, $fiscalYear, &$count) {
            $created = [];

            foreach ($chart as $entry) {
                $parentId = null;

                if (isset($entry['parent_code']) && isset($created[$entry['parent_code']])) {
                    $parentId = $created[$entry['parent_code']];
                }

                $exists = Account::where('company_id', $company->id)
                    ->where('fiscal_year_id', $fiscalYear->id)
                    ->where('code', $entry['code'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                $account = Account::create([
                    'company_id' => $company->id,
                    'fiscal_year_id' => $fiscalYear->id,
                    'parent_id' => $parentId,
                    'code' => $entry['code'],
                    'name' => $entry['name'],
                    'account_type' => $entry['type'],
                    'description' => $entry['description'] ?? null,
                    'is_active' => true,
                ]);

                $created[$entry['code']] = $account->id;
                $count++;
            }
        });

        return $count;
    }

    /**
     * Default Tunisian chart of accounts structure.
     *
     * Based on common Tunisian PCG (Plan Comptable Général) conventions.
     * This is a starter structure, not an exhaustive legally verified chart.
     *
     * @return list<array{code: string, name: string, type: string, description?: string, parent_code?: string}>
     */
    private function defaultChart(): array
    {
        return [
            // ── Classe 1 : Capitaux ──
            ['code' => '1', 'name' => 'Capitaux', 'type' => 'equity', 'description' => 'Classe 1 — Capitaux propres'],
            ['code' => '10', 'name' => 'Capital', 'type' => 'equity', 'parent_code' => '1'],
            ['code' => '11', 'name' => 'Réserves', 'type' => 'equity', 'parent_code' => '1'],
            ['code' => '12', 'name' => 'Report à nouveau', 'type' => 'equity', 'parent_code' => '1'],
            ['code' => '13', 'name' => 'Résultat de l\'exercice', 'type' => 'equity', 'parent_code' => '1'],
            ['code' => '15', 'name' => 'Provisions pour charges', 'type' => 'equity', 'parent_code' => '1'],
            ['code' => '16', 'name' => 'Provisions pour risques', 'type' => 'equity', 'parent_code' => '1'],

            // ── Classe 2 : Immobilisations ──
            ['code' => '2', 'name' => 'Immobilisations', 'type' => 'asset', 'description' => 'Classe 2 — Immobilisations'],
            ['code' => '21', 'name' => 'Immobilisations incorporelles', 'type' => 'asset', 'parent_code' => '2'],
            ['code' => '22', 'name' => 'Terrains', 'type' => 'asset', 'parent_code' => '2'],
            ['code' => '23', 'name' => 'Bâtiments', 'type' => 'asset', 'parent_code' => '2'],
            ['code' => '24', 'name' => 'Matériel', 'type' => 'asset', 'parent_code' => '2'],
            ['code' => '25', 'name' => 'Matériel de transport', 'type' => 'asset', 'parent_code' => '2'],
            ['code' => '26', 'name' => 'Titres et valeurs de placement', 'type' => 'asset', 'parent_code' => '2'],
            ['code' => '27', 'name' => 'Immobilisations en cours', 'type' => 'asset', 'parent_code' => '2'],
            ['code' => '28', 'name' => 'Amortissements', 'type' => 'asset', 'parent_code' => '2'],
            ['code' => '29', 'name' => 'Provisions pour dépréciations', 'type' => 'asset', 'parent_code' => '2'],

            // ── Classe 3 : Stocks ──
            ['code' => '3', 'name' => 'Stocks', 'type' => 'asset', 'description' => 'Classe 3 — Stocks'],
            ['code' => '31', 'name' => 'Stocks de marchandises', 'type' => 'asset', 'parent_code' => '3'],
            ['code' => '32', 'name' => 'Matières premières', 'type' => 'asset', 'parent_code' => '3'],
            ['code' => '33', 'name' => 'Produits en cours', 'type' => 'asset', 'parent_code' => '3'],
            ['code' => '34', 'name' => 'Produits finis', 'type' => 'asset', 'parent_code' => '3'],
            ['code' => '35', 'name' => 'Stocks à l\'extérieur', 'type' => 'asset', 'parent_code' => '3'],
            ['code' => '36', 'name' => 'Achats stockés', 'type' => 'asset', 'parent_code' => '3'],
            ['code' => '38', 'name' => 'Provisions pour dépréciations des stocks', 'type' => 'asset', 'parent_code' => '3'],

            // ── Classe 4 : Tiers ──
            ['code' => '4', 'name' => 'Tiers', 'type' => 'asset', 'description' => 'Classe 4 — Tiers'],
            ['code' => '40', 'name' => 'Fournisseurs', 'type' => 'liability', 'parent_code' => '4'],
            ['code' => '401', 'name' => 'Fournisseurs', 'type' => 'liability', 'parent_code' => '40'],
            ['code' => '404', 'name' => 'Fournisseurs d\'immobilisations', 'type' => 'liability', 'parent_code' => '40'],
            ['code' => '41', 'name' => 'Clients', 'type' => 'asset', 'parent_code' => '4'],
            ['code' => '411', 'name' => 'Clients', 'type' => 'asset', 'parent_code' => '41'],
            ['code' => '416', 'name' => 'Clients douteux', 'type' => 'asset', 'parent_code' => '41'],
            ['code' => '42', 'name' => 'Personnel', 'type' => 'liability', 'parent_code' => '4'],
            ['code' => '421', 'name' => 'Personnel — rémunérations dues', 'type' => 'liability', 'parent_code' => '42'],
            ['code' => '43', 'name' => 'Organismes sociaux', 'type' => 'liability', 'parent_code' => '4'],
            ['code' => '431', 'name' => 'CNSS', 'type' => 'liability', 'parent_code' => '43'],
            ['code' => '44', 'name' => 'État et collectivités publiques', 'type' => 'liability', 'parent_code' => '4'],
            ['code' => '441', 'name' => 'État — subventions à recevoir', 'type' => 'asset', 'parent_code' => '44'],
            ['code' => '442', 'name' => 'État — impôts et taxes recouvrables', 'type' => 'asset', 'parent_code' => '44'],
            ['code' => '443', 'name' => 'État — impôts et taxes à payer', 'type' => 'liability', 'parent_code' => '44'],
            ['code' => '444', 'name' => 'TVA déductible', 'type' => 'asset', 'parent_code' => '44'],
            ['code' => '445', 'name' => 'TVA collectée', 'type' => 'liability', 'parent_code' => '44'],
            ['code' => '447', 'name' => 'Autres impôts', 'type' => 'liability', 'parent_code' => '44'],
            ['code' => '45', 'name' => 'Groupe et associés', 'type' => 'asset', 'parent_code' => '4'],
            ['code' => '46', 'name' => 'Débiteurs divers', 'type' => 'asset', 'parent_code' => '4'],
            ['code' => '47', 'name' => 'Créances diverses', 'type' => 'asset', 'parent_code' => '4'],
            ['code' => '48', 'name' => 'Charges constatées d\'avance', 'type' => 'asset', 'parent_code' => '4'],
            ['code' => '49', 'name' => 'Provisions pour dépréciations des comptes de tiers', 'type' => 'asset', 'parent_code' => '4'],

            // ── Classe 5 : Financiers ──
            ['code' => '5', 'name' => 'Financiers', 'type' => 'asset', 'description' => 'Classe 5 — Trésorerie et instruments financiers'],
            ['code' => '51', 'name' => 'Banques et assimilés', 'type' => 'asset', 'parent_code' => '5'],
            ['code' => '512', 'name' => 'Banques locales', 'type' => 'asset', 'parent_code' => '51'],
            ['code' => '514', 'name' => 'Banques étrangères', 'type' => 'asset', 'parent_code' => '51'],
            ['code' => '52', 'name' => 'Instruments financiers', 'type' => 'asset', 'parent_code' => '5'],
            ['code' => '53', 'name' => 'Caisse', 'type' => 'asset', 'parent_code' => '5'],
            ['code' => '531', 'name' => 'Caisse principale', 'type' => 'asset', 'parent_code' => '53'],
            ['code' => '54', 'name' => 'Régies de recettes', 'type' => 'asset', 'parent_code' => '5'],

            // ── Classe 6 : Charges ──
            ['code' => '6', 'name' => 'Charges', 'type' => 'expense', 'description' => 'Classe 6 — Charges'],
            ['code' => '60', 'name' => 'Achats', 'type' => 'expense', 'parent_code' => '6'],
            ['code' => '601', 'name' => 'Achats de marchandises', 'type' => 'expense', 'parent_code' => '60'],
            ['code' => '602', 'name' => 'Achats de matières premières', 'type' => 'expense', 'parent_code' => '60'],
            ['code' => '605', 'name' => 'Achats de fournitures', 'type' => 'expense', 'parent_code' => '60'],
            ['code' => '61', 'name' => 'Services extérieurs', 'type' => 'expense', 'parent_code' => '6'],
            ['code' => '613', 'name' => 'Locations et charges locatives', 'type' => 'expense', 'parent_code' => '61'],
            ['code' => '62', 'name' => 'Autres services extérieurs', 'type' => 'expense', 'parent_code' => '6'],
            ['code' => '621', 'name' => 'Personnel extérieur', 'type' => 'expense', 'parent_code' => '62'],
            ['code' => '622', 'name' => 'Transports', 'type' => 'expense', 'parent_code' => '62'],
            ['code' => '623', 'name' => 'Déplacements et missions', 'type' => 'expense', 'parent_code' => '62'],
            ['code' => '624', 'name' => 'Publicité et publications', 'type' => 'expense', 'parent_code' => '62'],
            ['code' => '625', 'name' => 'Rémunérations d\'intermédiaires', 'type' => 'expense', 'parent_code' => '62'],
            ['code' => '63', 'name' => 'Charges de personnel', 'type' => 'expense', 'parent_code' => '6'],
            ['code' => '631', 'name' => 'Rémunérations du personnel', 'type' => 'expense', 'parent_code' => '63'],
            ['code' => '632', 'name' => 'Charges sociales', 'type' => 'expense', 'parent_code' => '63'],
            ['code' => '64', 'name' => 'Impôts et taxes', 'type' => 'expense', 'parent_code' => '6'],
            ['code' => '641', 'name' => 'Impôts et taxes directs', 'type' => 'expense', 'parent_code' => '64'],
            ['code' => '645', 'name' => 'Taxe sur la valeur ajoutée', 'type' => 'expense', 'parent_code' => '64'],
            ['code' => '65', 'name' => 'Autres charges de gestion', 'type' => 'expense', 'parent_code' => '6'],
            ['code' => '651', 'name' => 'Rémunération du personnel d\'administration', 'type' => 'expense', 'parent_code' => '65'],
            ['code' => '654', 'name' => 'Pertes sur créances irrécouvrables', 'type' => 'expense', 'parent_code' => '65'],
            ['code' => '658', 'name' => 'Charges diverses de gestion courante', 'type' => 'expense', 'parent_code' => '65'],
            ['code' => '66', 'name' => 'Charges financières', 'type' => 'expense', 'parent_code' => '6'],
            ['code' => '661', 'name' => 'Charges d\'intérêts', 'type' => 'expense', 'parent_code' => '66'],
            ['code' => '67', 'name' => 'Éléments extraordinaires — charges', 'type' => 'expense', 'parent_code' => '6'],
            ['code' => '68', 'name' => 'Dotations aux amortissements', 'type' => 'expense', 'parent_code' => '6'],
            ['code' => '681', 'name' => 'Dotations aux amortissements des immobilisations', 'type' => 'expense', 'parent_code' => '68'],
            ['code' => '69', 'name' => 'Dotations aux provisions', 'type' => 'expense', 'parent_code' => '6'],

            // ── Classe 7 : Produits ──
            ['code' => '7', 'name' => 'Produits', 'type' => 'revenue', 'description' => 'Classe 7 — Produits'],
            ['code' => '70', 'name' => 'Ventes de marchandises', 'type' => 'revenue', 'parent_code' => '7'],
            ['code' => '701', 'name' => 'Ventes de marchandises', 'type' => 'revenue', 'parent_code' => '70'],
            ['code' => '707', 'name' => 'Ventes de produits finis', 'type' => 'revenue', 'parent_code' => '70'],
            ['code' => '71', 'name' => 'Ventes de produits fabriqués', 'type' => 'revenue', 'parent_code' => '7'],
            ['code' => '72', 'name' => 'Ventes de services', 'type' => 'revenue', 'parent_code' => '7'],
            ['code' => '73', 'name' => 'Chiffre d\'affaires', 'type' => 'revenue', 'parent_code' => '7'],
            ['code' => '74', 'name' => 'Produits accessoires', 'type' => 'revenue', 'parent_code' => '7'],
            ['code' => '75', 'name' => 'Reprises sur pertes et provisions', 'type' => 'revenue', 'parent_code' => '7'],
            ['code' => '76', 'name' => 'Produits financiers', 'type' => 'revenue', 'parent_code' => '7'],
            ['code' => '761', 'name' => 'Produits des titres de participation', 'type' => 'revenue', 'parent_code' => '76'],
            ['code' => '77', 'name' => 'Éléments extraordinaires — produits', 'type' => 'revenue', 'parent_code' => '7'],
        ];
    }
}
