<?php

namespace App\Services\Security;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Stringable;

/**
 * Central append-only audit trail writer.
 *
 * Every entry records who did what, on which company and entity, with
 * sanitized before/after snapshots. The acting user is resolved from the
 * authenticated session unless explicitly provided; the company is derived
 * from the audited entity or an explicitly trusted model, never from
 * browser input.
 */
final class AuditLogService
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'recovery_codes',
        'passkey',
        'credential',
        'secret',
        'token',
        'api_token',
        'session',
        'cookie',
        'authorization',
        'private_key',
    ];

    private const SENSITIVE_KEY_SUFFIXES = ['_secret', '_token', '_password', '_key'];

    private const SENSITIVE_KEY_PREFIXES = ['x_', 'authorization_', 'cookie_'];

    private const MAX_KEYS = 60;

    private const MAX_STRING_LENGTH = 2000;

    /**
     * Write one sanitized, append-only audit entry.
     *
     * @param  array<array-key, mixed>|null  $before
     * @param  array<array-key, mixed>|null  $after
     * @param  array<array-key, mixed>  $metadata
     */
    public function log(
        AuditAction $action,
        ?Company $company = null,
        ?User $user = null,
        ?Model $entity = null,
        ?array $before = null,
        ?array $after = null,
        ?string $description = null,
        array $metadata = [],
    ): AuditLog {
        return AuditLog::query()->create([
            'company_id' => $this->resolveCompanyId($company, $entity),
            'user_id' => $this->resolveActorId($user),
            'action' => $action->value,
            'entity_type' => $entity !== null ? class_basename($entity) : null,
            'entity_id' => $entity?->getKey(),
            'description' => $description ?? $action->label(),
            'before_data' => $this->sanitizeData($before),
            'after_data' => $this->sanitizeData($after),
            'metadata' => $this->sanitizeMetadata($metadata),
            ...$this->requestContext(),
        ]);
    }

    /**
     * Convenience wrapper for actions without a dedicated model entity.
     *
     * @param  array<array-key, mixed>  $metadata
     */
    public function logAction(
        AuditAction $action,
        string $description,
        ?Company $company = null,
        ?Model $entity = null,
        array $metadata = [],
    ): AuditLog {
        return $this->log(
            action: $action,
            company: $company,
            entity: $entity,
            description: $description,
            metadata: $metadata,
        );
    }

    /**
     * Log the creation of a model with a sanitized attribute snapshot.
     *
     * @param  list<string>|null  $fields  whitelist of attributes to keep; defaults to all scalar columns
     * @param  array<array-key, mixed>  $metadata
     */
    public function logModelCreated(Model $entity, AuditAction $action, ?array $fields = null, array $metadata = []): AuditLog
    {
        return $this->log(
            action: $action,
            entity: $entity,
            after: $this->snapshot($entity, $fields),
            metadata: $metadata,
        );
    }

    /**
     * Log a business update of a model with explicit before/after snapshots.
     *
     * @param  array<array-key, mixed>  $before
     * @param  array<array-key, mixed>  $after
     * @param  array<array-key, mixed>  $metadata
     */
    public function logModelUpdated(Model $entity, AuditAction $action, array $before, array $after, ?string $description = null, array $metadata = []): AuditLog
    {
        return $this->log(
            action: $action,
            entity: $entity,
            before: $before,
            after: $after,
            description: $description,
            metadata: $metadata,
        );
    }

    /**
     * Keep model attributes as plain scalars; either only the given columns
     * or every attribute when no whitelist is provided.
     *
     * @param  list<string>|null  $fields
     * @return array<string, string|int|float|bool|null>
     */
    public function snapshot(Model $entity, ?array $fields = null): array
    {
        $fieldList = $fields ?? array_keys($entity->getAttributes());

        $snapshot = [];

        foreach ($fieldList as $field) {
            if (! array_key_exists($field, $entity->getAttributes()) && ! $entity->relationLoaded($field)) {
                continue;
            }

            $value = data_get($entity, $field);

            if ($value instanceof \BackedEnum) {
                $value = $value->value;
            } elseif ($value instanceof \UnitEnum) {
                $value = $value->name;
            } elseif ($value instanceof \DateTimeInterface) {
                $value = $value->format(DATE_ATOM);
            } elseif ($value instanceof Stringable) {
                $value = $value->__toString();
            }

            if (is_scalar($value) || $value === null) {
                $snapshot[$field] = $value;
            }
        }

        return $snapshot;
    }

    /**
     * Strip non-scalar and sensitive values from a snapshot payload.
     *
     * @param  array<array-key, mixed>|null  $data
     * @return array<string, string|int|float|bool|null>|null
     */
    public function sanitizeData(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $sanitized = [];

        foreach ($data as $key => $value) {
            if (count($sanitized) >= self::MAX_KEYS || ! is_string($key) || $this->isSensitiveKey($key)) {
                continue;
            }

            if ($value instanceof \BackedEnum) {
                $value = $value->value;
            } elseif ($value instanceof \UnitEnum) {
                $value = $value->name;
            } elseif ($value instanceof \DateTimeInterface) {
                $value = $value->format(DATE_ATOM);
            } elseif ($value instanceof Stringable) {
                $value = $value->__toString();
            }

            if (is_scalar($value) || $value === null) {
                $sanitized[$key] = is_string($value) && strlen($value) > self::MAX_STRING_LENGTH
                    ? Str::limit($value, self::MAX_STRING_LENGTH)
                    : $value;
            }
        }

        return $sanitized === [] ? null : $sanitized;
    }

    /**
     * Sanitize arbitrary metadata: one level deep, scalars only.
     *
     * @param  array<array-key, mixed>  $metadata
     * @return array<string, string|int|float|bool|null>|null
     */
    public function sanitizeMetadata(array $metadata): ?array
    {
        $sanitized = [];

        foreach ($metadata as $key => $value) {
            if (count($sanitized) >= self::MAX_KEYS || ! is_string($key) || $this->isSensitiveKey($key)) {
                continue;
            }

            $value = $value instanceof Stringable ? $value->__toString() : $value;

            if (is_scalar($value) || $value === null) {
                $sanitized[$key] = is_string($value) && strlen($value) > self::MAX_STRING_LENGTH
                    ? Str::limit($value, self::MAX_STRING_LENGTH)
                    : $value;
            }
        }

        return $sanitized === [] ? null : $sanitized;
    }

    private function resolveCompanyId(?Company $company, ?Model $entity): ?int
    {
        if ($company !== null) {
            return $company->id;
        }

        if ($entity !== null && array_key_exists('company_id', $entity->getAttributes())) {
            $companyId = $entity->getAttribute('company_id');

            if (is_numeric($companyId)) {
                return (int) $companyId;
            }
        }

        return null;
    }

    private function resolveActorId(?User $user): ?int
    {
        if ($user !== null) {
            return $user->id;
        }

        $current = Auth::user();

        return $current instanceof User ? $current->id : null;
    }

    /**
     * @return array{ip_address?: string|null, user_agent?: string|null, route?: string|null, method?: string|null}
     */
    private function requestContext(): array
    {
        $request = app()->bound('request') ? app('request') : null;

        if (! $request instanceof Request || $request->getMethod() === 'CLI') {
            return [];
        }

        return [
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), self::MAX_STRING_LENGTH),
            'route' => optional($request->route())->getName(),
            'method' => $request->getMethod(),
        ];
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(trim(str_replace(['-', ' '], '_', $key)));

        if (in_array($normalized, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        foreach (self::SENSITIVE_KEY_SUFFIXES as $suffix) {
            if (str_ends_with($normalized, $suffix)) {
                return true;
            }
        }

        foreach (self::SENSITIVE_KEY_PREFIXES as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
