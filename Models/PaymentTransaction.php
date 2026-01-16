<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Payment Transaction
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Pay\Models;

use Database\BaseModel;
use Database\Query\Builder;
use Helpers\DateTimeHelper;
use Pay\Enums\Currency;
use Pay\Enums\Status;

/**
 * @property int             $id
 * @property string          $reference
 * @property string          $refid
 * @property string          $driver
 * @property Status          $status
 * @property int             $amount
 * @property Currency        $currency
 * @property string          $email
 * @property ?array          $metadata
 * @property int             $payable_id
 * @property string          $payable_type
 * @property ?DateTimeHelper $created_at
 * @property ?DateTimeHelper $updated_at
 * @property-read BaseModel $payable
 *
 * @method static Builder byReference(string $reference)
 * @method static Builder byEmail(string $email)
 * @method static Builder byPayable(object $payable)
 * @method static Builder successful()
 * @method static Builder failed()
 * @method static Builder pending()
 */
class PaymentTransaction extends BaseModel
{
    protected string $table = 'payment_transaction';

    protected array $fillable = [
        'reference',
        'refid',
        'driver',
        'status',
        'amount',
        'currency',
        'email',
        'metadata',
        'payable_id',
        'payable_type'
    ];

    /**
     * Get the parent payable model (User, Organization, etc.).
     */
    public function payable(): mixed
    {
        return $this->morphTo();
    }

    protected array $casts = [
        'status' => Status::class,
        'currency' => Currency::class,
        'refid' => 'string',
        'metadata' => 'json'
    ];

    public function scopeByReference(Builder $query, string $reference): Builder
    {
        return $query->where('reference', $reference);
    }

    public function scopeByEmail(Builder $query, string $email): Builder
    {
        return $query->where('email', $email);
    }

    public function scopeByPayable(Builder $query, object $payable): Builder
    {
        return $query->where('payable_id', $payable->id)
            ->where('payable_type', get_class($payable));
    }

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', Status::SUCCESS);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', Status::FAILED);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', Status::PENDING);
    }
}
