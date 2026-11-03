<?php

namespace App\Jobs;

use App\Models\GbpAccessRequest;
use App\Services\Google\GoogleAccessVerificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class VerifyAccessRequestJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $accessRequestId) {}

    public function handle(GoogleAccessVerificationService $verification): void
    {
        $request = GbpAccessRequest::query()->with('client')->find($this->accessRequestId);

        if ($request === null || ! $request->status->isPending()) {
            return;
        }

        $verification->refresh($request);
    }
}
