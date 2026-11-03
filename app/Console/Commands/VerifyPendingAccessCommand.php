<?php

namespace App\Console\Commands;

use App\Enums\AccessRequestStatus;
use App\Jobs\VerifyAccessRequestJob;
use App\Models\GbpAccessRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('gbp:verify-pending')]
#[Description('Re-check pending Google access requests that still have a valid authorization')]
class VerifyPendingAccessCommand extends Command
{
    public function handle(): int
    {
        $cutoff = now()->subDays((int) config('gbp.verification.stop_checking_after_days'));

        $requests = GbpAccessRequest::query()
            ->whereIn('status', [AccessRequestStatus::Submitted->value, AccessRequestStatus::PendingApproval->value])
            ->where('created_at', '>=', $cutoff)
            ->get();

        foreach ($requests as $request) {
            VerifyAccessRequestJob::dispatch($request->id);
        }

        $this->info("Queued {$requests->count()} verification check(s).");

        return self::SUCCESS;
    }
}
