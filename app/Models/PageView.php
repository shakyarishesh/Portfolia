<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class PageView extends Model
{
    use \App\Models\Concerns\BelongsToPortfolio;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = ['created_at' => 'datetime'];

    /** Record one hit. No IP or user agent is stored: the visitor id is a salted hash that rotates daily. */
    public static function record(Request $request, string $kind, ?string $subject = null): void
    {
        $ua = (string) $request->userAgent();
        if ($ua === '' || preg_match('/bot|crawl|spider|slurp|preview|headless|monitor/i', $ua) || $request->header('DNT') === '1') {
            return;
        }
        $referrer = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);

        static::create([
            'kind' => $kind,
            'subject' => $subject ? mb_substr($subject, 0, 160) : null,
            'visitor' => substr(hash_hmac('sha256', $request->ip().'|'.$ua.'|'.now()->toDateString(), (string) config('app.key')), 0, 16),
            'referrer_host' => $referrer && $referrer !== $request->getHost() ? mb_substr($referrer, 0, 120) : null,
        ]);
    }
}
