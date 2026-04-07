<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\LoginHistory;
use App\Mail\SecurityAlertNewDevice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ProcessLoginHistory implements ShouldQueue
{
    use Queueable;

    protected $user;
    protected $ipAddress;
    protected $userAgent;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, $ipAddress, $userAgent)
    {
        $this->user = $user;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $country = 'Inconnu';
            $city = 'Inconnu';
            $isp = 'Inconnu';

            // Fetch IP info if not localhost
            if ($this->ipAddress && !in_array($this->ipAddress, ['127.0.0.1', '::1'])) {
                $response = Http::timeout(5)->get("https://monip.lws.fr/api/{$this->ipAddress}");
                if ($response->successful()) {
                    $data = $response->json();
                    $country = $data['country'] ?? 'Inconnu';
                    $city = $data['city'] ?? 'Inconnu';
                    $isp = $data['isp'] ?? 'Inconnu';
                }
            }

            // Parse User Agent for a more stable fingerprint
            $agentInfo = $this->parseUserAgent($this->userAgent);
            $browser = $agentInfo['browser'];
            $os = $agentInfo['os'];

            // Create device fingerprint based on Country, City, Browser and OS
            // Inclusion of City as requested for higher sensitivity
            $fingerprint = md5($country . '|' . $city . '|' . $browser . '|' . $os);

            // Check if this fingerprint was seen before for this user
            $isNewDevice = !LoginHistory::where('user_id', $this->user->id)
                ->where('device_fingerprint', $fingerprint)
                ->exists();

            // Save login history
            $loginHistory = LoginHistory::create([
                'user_id' => $this->user->id,
                'ip_address' => $this->ipAddress,
                'user_agent' => $this->userAgent,
                'country' => $country,
                'city' => $city,
                'isp' => $isp,
                'device_fingerprint' => $fingerprint,
            ]);

            // Notify user if new device
            if ($isNewDevice) {
                Mail::to($this->user->email)->send(new SecurityAlertNewDevice($this->user, $loginHistory));
            }
        } catch (\Exception $e) {
            Log::error('Failed to process login history: ' . $e->getMessage());
        }
    }

    /**
     * Simple User Agent parser
     */
    private function parseUserAgent($ua): array
    {
        $browser = 'Navigateur Inconnu';
        $os = 'Système Inconnu';

        // OS Detection
        if (str_contains($ua, 'Windows')) $os = 'Windows';
        elseif (str_contains($ua, 'Macintosh')) $os = 'macOS';
        elseif (str_contains($ua, 'Android')) $os = 'Android';
        elseif (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) $os = 'iOS';
        elseif (str_contains($ua, 'Linux')) $os = 'Linux';

        // Browser Detection
        if (str_contains($ua, 'Edge') || str_contains($ua, 'Edg')) $browser = 'Edge';
        elseif (str_contains($ua, 'Firefox')) $browser = 'Firefox';
        elseif (str_contains($ua, 'Chrome')) $browser = 'Chrome';
        elseif (str_contains($ua, 'Safari')) $browser = 'Safari';
        elseif (str_contains($ua, 'Opera') || str_contains($ua, 'OPR')) $browser = 'Opera';

        return ['browser' => $browser, 'os' => $os];
    }
}
