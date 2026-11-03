<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ClientStatus;
use App\Http\Controllers\Controller;
use App\Models\GbpClient;
use App\Services\Google\GoogleAgencyService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, GoogleAgencyService $agency): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = ClientStatus::tryFrom((string) $request->query('status', ''));

        $clients = GbpClient::query()
            ->with([
                'latestAccessRequest',
                'onboardingLinks' => fn ($query) => $query->whereNull('revoked_at')->latest('id'),
            ])
            ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $inner->where('business_name', 'like', $like)
                    ->orWhere('client_name', 'like', $like)
                    ->orWhere('client_email', 'like', $like);
            }))
            ->when($status, fn ($query) => $query->where('access_status', $status->value))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $counts = GbpClient::query()->selectRaw('access_status, count(*) as total')->groupBy('access_status')->pluck('total', 'access_status');

        return view('admin.dashboard', [
            'clients' => $clients,
            'search' => $search,
            'statusFilter' => $status,
            'stats' => [
                'total' => (int) $counts->sum(),
                'connected' => (int) ($counts[ClientStatus::Connected->value] ?? 0),
                'pending' => (int) (($counts[ClientStatus::RequestSubmitted->value] ?? 0) + ($counts[ClientStatus::PendingApproval->value] ?? 0)),
                'failed' => (int) (($counts[ClientStatus::Failed->value] ?? 0) + ($counts[ClientStatus::Rejected->value] ?? 0)),
            ],
            'automaticReady' => $agency->canRunAutomaticFlow(),
        ]);
    }
}
