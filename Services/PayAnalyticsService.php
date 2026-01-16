<?php

declare(strict_types=1);

/**
 * Anchor Framework - Pay Package
 *
 * Pay Analytics Service
 *
 * Provides reporting and analytics for payment transactions
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Pay\Services;

use Database\DB;
use Money\Money;
use Pay\Enums\Status;

class PayAnalyticsService
{
    protected ?string $statusFilter = null;

    protected ?string $driverFilter = null;

    protected ?string $fromFilter = null;

    protected ?string $toFilter = null;

    /**
     * Filter by successful transactions
     */
    public function successful(): self
    {
        $clone = clone $this;
        $clone->statusFilter = Status::SUCCESS->value;

        return $clone;
    }

    public function failed(): self
    {
        $clone = clone $this;
        $clone->statusFilter = Status::FAILED->value;

        return $clone;
    }

    /**
     * Filter by pending transactions
     */
    public function pending(): self
    {
        $clone = clone $this;
        $clone->statusFilter = Status::PENDING->value;

        return $clone;
    }

    public function driver(string $driver): self
    {
        $clone = clone $this;
        $clone->driverFilter = $driver;

        return $clone;
    }

    /**
     * Filter by date range
     */
    public function between(string $from, string $to): self
    {
        $clone = clone $this;
        $clone->fromFilter = $from;
        $clone->toFilter = $to;

        return $clone;
    }

    public function count(): int
    {
        return $this->getTransactionCount(
            $this->statusFilter,
            $this->driverFilter,
            $this->fromFilter,
            $this->toFilter
        );
    }

    public function getTotalRevenue(?string $from = null, ?string $to = null, string $currency = 'NGN'): Money
    {
        $query = DB::table('payment_transaction')
            ->where('status', Status::SUCCESS->value)
            ->where('currency', $currency);

        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        $total = (int) $query->sum('amount');

        return Money::make($total, $currency);
    }

    public function getTransactionCount(?string $status = null, ?string $driver = null, ?string $from = null, ?string $to = null): int
    {
        $query = DB::table('payment_transaction');

        if ($status) {
            $query->where('status', $status);
        }
        if ($driver) {
            $query->where('driver', $driver);
        }
        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return (int) $query->count();
    }

    public function getDailyVolume(string $from, string $to, string $currency = 'NGN'): array
    {
        $query = DB::table('payment_transaction')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed"),
                DB::raw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending"),
                DB::raw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled"),
                DB::raw("SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END) as revenue")
            )
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->where('currency', $currency)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date', 'ASC');

        $results = $query->get();

        return array_map(function ($row) use ($currency) {
            return [
                'date' => $row->date,
                'count' => (int) $row->count,
                'successful' => (int) $row->successful,
                'failed' => (int) $row->failed,
                'pending' => (int) $row->pending,
                'cancelled' => (int) $row->cancelled,
                'revenue' => Money::make((int) $row->revenue, $currency),
            ];
        }, $results);
    }

    public function getMonthlyVolume(string $from, string $to, string $currency = 'NGN'): array
    {
        $query = DB::table('payment_transaction')
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                DB::raw('COUNT(*) as count'),
                DB::raw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed"),
                DB::raw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending"),
                DB::raw("SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END) as revenue")
            )
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->where('currency', $currency)
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"))
            ->orderBy('month', 'ASC');

        $results = $query->get();

        return array_map(function ($row) use ($currency) {
            return [
                'month' => $row->month,
                'count' => (int) $row->count,
                'successful' => (int) $row->successful,
                'failed' => (int) $row->failed,
                'pending' => (int) $row->pending,
                'revenue' => Money::make((int) $row->revenue, $currency),
            ];
        }, $results);
    }

    public function getRevenueByDriver(?string $from = null, ?string $to = null, string $currency = 'NGN'): array
    {
        $query = DB::table('payment_transaction')
            ->select('driver', DB::raw('SUM(amount) as revenue'), DB::raw('COUNT(*) as count'))
            ->where('status', Status::SUCCESS->value)
            ->where('currency', $currency)
            ->groupBy('driver')
            ->orderBy('revenue', 'DESC');

        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        $results = $query->get();

        // Convert revenue to Money objects
        return array_map(function ($row) use ($currency) {
            return [
                'driver' => $row->driver,
                'revenue' => Money::make((int) $row->revenue, $currency),
                'count' => (int) $row->count,
            ];
        }, $results);
    }

    public function getTopCustomers(int $limit = 10, ?string $from = null, ?string $to = null, string $currency = 'NGN'): array
    {
        $query = DB::table('payment_transaction')
            ->select('email', DB::raw('SUM(amount) as total_paid'), DB::raw('COUNT(*) as payment_count'))
            ->where('status', Status::SUCCESS->value)
            ->where('currency', $currency)
            ->groupBy('email')
            ->orderBy('total_paid', 'DESC')
            ->limit($limit);

        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        $results = $query->get();

        return array_map(function ($row) use ($currency) {
            return [
                'email' => $row->email,
                'total_paid' => Money::make((int) $row->total_paid, $currency),
                'payment_count' => (int) $row->payment_count,
            ];
        }, $results);
    }

    /**
     * Get conversion rate - optimized to single query
     */
    public function getConversionRate(?string $from = null, ?string $to = null): array
    {
        $query = DB::table('payment_transaction')
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed"),
                DB::raw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending"),
                DB::raw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled")
            );

        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        $result = $query->first();
        $total = (int) ($result->total ?? 0);
        $successful = (int) ($result->successful ?? 0);

        return [
            'total_attempts' => $total,
            'successful' => $successful,
            'failed' => (int) ($result->failed ?? 0),
            'pending' => (int) ($result->pending ?? 0),
            'cancelled' => (int) ($result->cancelled ?? 0),
            'conversion_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
        ];
    }

    public function getSummary(?string $from = null, ?string $to = null, ?string $currency = null): array
    {
        return [
            'total_revenue' => $this->getTotalRevenue($from, $to, $currency),
            'transaction_count' => $this->getTransactionCount(null, null, $from, $to),
            'successful_count' => $this->getTransactionCount(Status::SUCCESS->value, null, $from, $to),
            'failed_count' => $this->getTransactionCount(Status::FAILED->value, null, $from, $to),
            'pending_count' => $this->getTransactionCount(Status::PENDING->value, null, $from, $to),
            'conversion_rate' => $this->getConversionRate($from, $to)['conversion_rate'],
            'revenue_by_driver' => $this->getRevenueByDriver($from, $to),
            'period' => ['from' => $from, 'to' => $to],
        ];
    }

    public function getAverageTransactionValue(?string $from = null, ?string $to = null): float
    {
        $query = DB::table('payment_transaction')
            ->where('status', Status::SUCCESS->value);

        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return (float) $query->avg('amount') ?? 0;
    }
}
