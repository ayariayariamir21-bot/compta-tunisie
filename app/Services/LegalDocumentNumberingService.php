<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Numbering of legal sales documents (invoices, credit notes).
 *
 * Tunisian rules require an unbroken, chronological numbering series for
 * issued invoices. Drafts therefore only carry a provisional number; the
 * definitive number is assigned when the document is posted, inside the
 * posting transaction and under a lock on the company row, so concurrent
 * postings can never produce gaps or duplicates.
 */
class LegalDocumentNumberingService
{
    public const DRAFT_PREFIX = 'BR-';

    public function provisionalNumber(): string
    {
        return self::DRAFT_PREFIX.Str::upper((string) Str::ulid());
    }

    public function isProvisional(?string $number): bool
    {
        return $number === null || str_starts_with($number, self::DRAFT_PREFIX);
    }

    /**
     * Assign the next definitive number. Must be called inside a DB transaction.
     *
     * @param  class-string<Model>  $modelClass
     */
    public function assignDefinitiveNumber(
        string $modelClass,
        string $numberColumn,
        string $dateColumn,
        int $companyId,
        string $basePrefix,
        CarbonInterface $documentDate,
        int $minimumNextNumber = 1,
    ): string {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('La numérotation définitive doit être attribuée dans une transaction.');
        }

        Company::query()->whereKey($companyId)->lockForUpdate()->firstOrFail();

        $year = $documentDate->year;
        $prefix = "{$basePrefix}-{$year}-";

        $issued = $modelClass::query()
            ->where('company_id', $companyId)
            ->where($numberColumn, 'like', $prefix.'%');

        $lastDate = (clone $issued)->max($dateColumn);
        if ($lastDate !== null && $documentDate->lt(Carbon::parse((string) $lastDate)->startOfDay())) {
            throw new \InvalidArgumentException(sprintf(
                'Numérotation chronologique : un document daté du %s a déjà été émis. Impossible d\'émettre un document daté du %s.',
                Carbon::parse((string) $lastDate)->format('d/m/Y'),
                $documentDate->format('d/m/Y'),
            ));
        }

        $lastSequence = (clone $issued)
            ->pluck($numberColumn)
            ->map(fn (mixed $number): int => (int) substr((string) $number, strlen($prefix)))
            ->max() ?? 0;

        $next = max($lastSequence + 1, $minimumNextNumber);

        return $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
