<?php

namespace App\Jobs;

use App\Models\PendingSignup;
use App\Models\User;
use App\Notifications\TenantProvisioningFailed;
use App\Services\Billing\ProvisioningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Provisiona o tenant após a 1ª cobrança compensada. Com retry automático; se esgotar as
 * tentativas, alerta os super admins (não pode falhar silenciosamente). Idempotente.
 */
class ProvisionTenantFromSignup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [30, 60, 120, 300];

    public function __construct(public string $signupId) {}

    public function handle(ProvisioningService $provisioning): void
    {
        $signup = PendingSignup::find($this->signupId);

        if (! $signup || $signup->isProvisioned()) {
            return;
        }

        $provisioning->provision($signup);
    }

    public function failed(\Throwable $e): void
    {
        $signup = PendingSignup::find($this->signupId);
        $signup?->update(['status' => 'failed', 'error' => $e->getMessage()]);

        Log::critical('Provisionamento de tenant falhou após retries', [
            'signup' => $this->signupId, 'error' => $e->getMessage(),
        ]);

        $admins = User::where('is_super_admin', true)->get();
        if ($admins->isNotEmpty() && $signup) {
            Notification::send($admins, new TenantProvisioningFailed($signup, $e->getMessage()));
        }
    }
}
