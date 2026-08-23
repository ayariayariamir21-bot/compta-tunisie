<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\CurrentFiscalYear;
use App\Services\Reporting\PdfReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * PDF export endpoints for the accounting reports.
 *
 * Every request is revalidated server-side: filters are normalized here,
 * company context always comes from session state (never from browser input)
 * and report data is produced by the existing report services.
 */
final class PdfReportController extends Controller
{
    public function __construct(private PdfReportService $pdfReportService) {}

    public function generalLedger(Request $request): Response
    {
        return $this->export(function (User $user) use ($request): array {
            $validated = $this->validateRangeFilters($request);

            return $this->pdfReportService->exportGeneralLedger(
                $user,
                $this->optionalId($request, 'account_id'),
                $this->optionalId($request, 'journal_id'),
                $validated['from_date'],
                $validated['to_date'],
                (string) $request->query('search', ''),
            );
        }, $request);
    }

    public function trialBalance(Request $request): Response
    {
        return $this->export(function (User $user) use ($request): array {
            $validated = $this->validateRangeFilters($request);

            return $this->pdfReportService->exportTrialBalance(
                $user,
                $validated['from_date'],
                $validated['to_date'],
                (string) $request->query('account_type', ''),
                $this->optionalId($request, 'account_id'),
                (string) $request->query('include_zero_balance', '0'),
            );
        }, $request);
    }

    public function balanceSheet(Request $request): Response
    {
        return $this->export(function (User $user) use ($request): array {
            $bounds = $this->fiscalYearBounds();

            /** @var array{as_of_date?: string} $validated */
            $validated = $request->validate([
                'as_of_date' => ['nullable', 'date_format:Y-m-d'],
                'include_zero_balance' => ['nullable', 'in:0,1'],
            ]);

            return $this->pdfReportService->exportBalanceSheet(
                $user,
                $validated['as_of_date'] ?? $bounds[1],
                (string) $request->query('include_zero_balance', '0'),
            );
        }, $request);
    }

    public function incomeStatement(Request $request): Response
    {
        return $this->export(function (User $user) use ($request): array {
            $validated = $this->validateRangeFilters($request);

            return $this->pdfReportService->exportIncomeStatement(
                $user,
                $validated['from_date'],
                $validated['to_date'],
                (string) $request->query('include_zero_balance', '0'),
            );
        }, $request);
    }

    public function vat(Request $request): Response
    {
        return $this->export(function (User $user) use ($request): array {
            $validated = $this->validateRangeFilters($request);

            return $this->pdfReportService->exportVatReport(
                $user,
                $validated['from_date'],
                $validated['to_date'],
            );
        }, $request);
    }

    public function customerStatement(Request $request, int $customerId): Response
    {
        return $this->export(function (User $user) use ($request, $customerId): array {
            $validated = $this->validateOptionalRangeFilters($request);

            return $this->pdfReportService->exportCustomerStatement(
                $user,
                $customerId,
                $validated['from_date'] ?? null,
                $validated['to_date'] ?? null,
            );
        }, $request);
    }

    public function supplierStatement(Request $request, int $supplierId): Response
    {
        return $this->export(function (User $user) use ($request, $supplierId): array {
            $validated = $this->validateOptionalRangeFilters($request);

            return $this->pdfReportService->exportSupplierStatement(
                $user,
                $supplierId,
                $validated['from_date'] ?? null,
                $validated['to_date'] ?? null,
            );
        }, $request);
    }

    // ---------- Internals ----------

    /**
     * Run an export and map invalid contexts/entities to the standard
     * 404 behavior without leaking internal details.
     *
     * @param  callable(User): array{content: string, filename: string}  $factory
     */
    private function export(callable $factory, Request $request): Response
    {
        try {
            return $this->pdfResponse($factory($this->user($request)));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    /**
     * Authenticated, verified user behind the route middleware.
     */
    private function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(404);
        }

        return $user;
    }

    /**
     * Validate mandatory range filters with fiscal-year defaults.
     *
     * @return array{from_date: string, to_date: string}
     */
    private function validateRangeFilters(Request $request): array
    {
        [$defaultFrom, $defaultTo] = $this->fiscalYearBounds();

        $validated = $this->validateOptionalRangeFilters($request);

        return [
            'from_date' => $validated['from_date'] ?? $defaultFrom,
            'to_date' => $validated['to_date'] ?? $defaultTo,
        ];
    }

    /**
     * Validate optional range filters (format + ordering).
     *
     * @return array{from_date?: string, to_date?: string}
     */
    private function validateOptionalRangeFilters(Request $request): array
    {
        /** @var array{from_date?: string, to_date?: string} $validated */
        $validated = $request->validate([
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ], [
            'from_date.date_format' => 'La date de début est invalide.',
            'to_date.date_format' => 'La date de fin est invalide.',
            'to_date.after_or_equal' => 'La date de début doit être antérieure ou égale à la date de fin.',
        ]);

        return $validated;
    }

    /**
     * Current fiscal year bounds used as default reporting period.
     *
     * @return array{0: string, 1: string}
     */
    private function fiscalYearBounds(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(404);
        }

        $fiscalYear = app(CurrentFiscalYear::class)->get($user);

        if (! $fiscalYear instanceof FiscalYear) {
            abort(404);
        }

        return [
            Carbon::parse($fiscalYear->start_date)->format('Y-m-d'),
            Carbon::parse($fiscalYear->end_date)->format('Y-m-d'),
        ];
    }

    /**
     * Optional positive-integer query identifier.
     */
    private function optionalId(Request $request, string $key): ?int
    {
        $value = $request->query($key);

        if ($value === null || $value === '') {
            return null;
        }

        /** @var array<string, mixed> $validated */
        $validated = $request->validate([$key => ['integer', 'min:1']], [
            "$key.integer" => 'Le paramètre sélectionné est invalide.',
            "$key.min" => 'Le paramètre sélectionné est invalide.',
        ]);

        return isset($validated[$key]) && is_numeric($validated[$key]) ? (int) $validated[$key] : null;
    }

    /**
     * Inline PDF response for browser preview.
     *
     * @param  array{content: string, filename: string}  $export
     */
    private function pdfResponse(array $export): Response
    {
        return response($export['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$export['filename'].'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
