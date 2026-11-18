<?php

namespace App\Models\Concerns;

use App\Models\Portfolio;
use App\Support\CurrentPortfolio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rows are only visible within the current portfolio, and new rows are stamped with it.
 * HTTP requests with no current portfolio see nothing (fail closed); console/tests see everything.
 */
trait BelongsToPortfolio
{
    public static function bootBelongsToPortfolio(): void
    {
        static::addGlobalScope('portfolio', function (Builder $query) {
            $id = CurrentPortfolio::id();
            if ($id) {
                $query->where($query->getModel()->qualifyColumn('portfolio_id'), $id);
            } elseif (! app()->runningInConsole()) {
                $query->whereRaw('0 = 1');
            }
        });

        static::creating(function (Model $model) {
            if ($model->portfolio_id === null) {
                $model->portfolio_id = CurrentPortfolio::id()
                    ?? throw new \LogicException('No current portfolio while creating '.static::class.'. Wrap the code in CurrentPortfolio::using().');
            }
        });
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }
}
