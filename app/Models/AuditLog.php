<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Append-only audit trail record.
 *
 * Rows are never updated or deleted through Eloquent; the database-level
 * foreign keys use SET NULL so history survives user or company deletion.
 *
 * @property int $id
 * @property int|null $company_id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $entity_type
 * @property int|null $entity_id
 * @property string|null $description
 * @property array<array-key, mixed>|null $before_data
 * @property array<array-key, mixed>|null $after_data
 * @property array<array-key, mixed>|null $metadata
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $route
 * @property string|null $method
 * @property CarbonImmutable|null $created_at
 * @property Company|null $company
 * @property User|null $user
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'company_id',
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'description',
        'before_data',
        'after_data',
        'metadata',
        'ip_address',
        'user_agent',
        'route',
        'method',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'user_id' => 'integer',
            'entity_id' => 'integer',
            'before_data' => 'array',
            'after_data' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('Les entrées du journal d\'audit sont en écriture seule.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('Les entrées du journal d\'audit sont en écriture seule.');
        });
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
