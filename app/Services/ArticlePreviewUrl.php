<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\URL;

/** Builds the storefront link for an article: public URL when live, a short-lived signed preview link otherwise. */
class ArticlePreviewUrl
{
    public const TTL_MINUTES = 60;

    public function for(Article $article): string
    {
        $base = rtrim((string) config('shop.frontend_url'), '/').'/editorial/'.rawurlencode($article->slug);

        if ($article->isLive()) {
            return $base;
        }

        $signed = URL::temporarySignedRoute(
            'articles.preview',
            now()->addMinutes(self::TTL_MINUTES),
            ['slug' => $article->slug],
            absolute: false,
        );

        parse_str((string) parse_url($signed, PHP_URL_QUERY), $query);

        return $base.'?'.http_build_query(['preview' => 1, 'expires' => $query['expires'], 'signature' => $query['signature']]);
    }
}
