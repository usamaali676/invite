<?php

namespace App\Console\Commands;

use App\Enums\ActorType;
use App\Enums\ClientStatus;
use App\Models\GbpClient;
use App\Models\GbpOnboardingLink;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('gbp:expire-links')]
#[Description('Mark clients as expired when their onboarding link ran out before they finished')]
class ExpireLinksCommand extends Command
{
    public function handle(): int
    {
        $expired = 0;

        GbpOnboardingLink::query()
            ->whereNull('revoked_at')
            ->whereNull('used_at')
            ->where('expires_at', '<', now())
            ->with('client')
            ->each(function (GbpOnboardingLink $link) use (&$expired) {
                /** @var GbpClient $client */
                $client = $link->client;

                if ($client->activeLink()?->is($link) && $client->tryTransitionTo(ClientStatus::Expired)) {
                    $client->logEvent('link.expired', ActorType::System, 'The onboarding link expired.');
                    $expired++;
                }
            });

        $this->info("Marked {$expired} client(s) as expired.");

        return self::SUCCESS;
    }
}
