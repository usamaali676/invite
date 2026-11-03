<?php

namespace App\Http\Middleware;

use App\Enums\ActorType;
use App\Enums\ClientStatus;
use App\Models\GbpOnboardingLink;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Looks the link up by its SHA-256 hash. Unknown, expired and revoked links all get
 * the very same response so a visitor cannot learn which tokens exist.
 */
class ResolveOnboardingLink
{
    public function handle(Request $request, Closure $next): Response
    {
        $link = GbpOnboardingLink::findByToken((string) $request->route('token'));

        if ($link === null || ! $link->isUsable()) {
            if ($link?->isExpired() && ! $link->isRevoked()) {
                $this->markExpired($link);
            }

            return $this->hardened(response()->view('onboarding.invalid', [], 404));
        }

        $request->attributes->set('onboarding_link', $link);

        return $this->hardened($next($request));
    }

    private function markExpired(GbpOnboardingLink $link): void
    {
        $client = $link->client;

        if ($client->activeLink()?->is($link) && $client->tryTransitionTo(ClientStatus::Expired)) {
            $client->logEvent('link.expired', ActorType::System, 'The onboarding link expired before it was used.');
        }
    }

    private function hardened(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
