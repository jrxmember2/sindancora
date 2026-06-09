<?php

namespace App\Http\Controllers;

use App\Models\UserNotificationPreference;
use App\Support\NotificationPreferenceRegistry;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user()->load('notificationPreferences');

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar_url' => $user->avatar_url,
                'area' => $user->isSuperAdmin() ? 'admin' : ($user->canAccessPanel() ? 'panel' : 'portal'),
            ],
            'notificationChannels' => NotificationPreferenceRegistry::channels(),
            'notificationEvents' => $this->notificationEventsPayload($user),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->where(fn ($query) => $tenantId ? $query->where('tenant_id', $tenantId) : $query->whereNull('tenant_id'))
                    ->ignore($user->id),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $user->fill($data);

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $user->save();

        if ($person = $user->person) {
            $person->update(['phone' => $data['phone'] ?? $person->phone]);
        }

        return back()->with('success', 'Perfil atualizado.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($data['password']),
        ]);

        return back()->with('success', 'Senha atualizada.');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();
        $disk = config('filesystems.default');
        $file = $data['avatar'];
        $path = 'avatars/'.$user->id.'/'.Str::uuid().'.'.$file->getClientOriginalExtension();

        if ($user->avatar_path) {
            Storage::disk($disk)->delete($user->avatar_path);
        }

        Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path));

        $user->update([
            'avatar_path' => $path,
            'avatar_mime_type' => $file->getMimeType(),
            'avatar_original_filename' => $file->getClientOriginalName(),
        ]);

        return back()->with('success', 'Foto atualizada.');
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk(config('filesystems.default'))->delete($user->avatar_path);
        }

        $user->update([
            'avatar_path' => null,
            'avatar_mime_type' => null,
            'avatar_original_filename' => null,
        ]);

        return back()->with('success', 'Foto removida.');
    }

    public function avatar(Request $request): StreamedResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->avatar_path, 404);

        $disk = config('filesystems.default');
        abort_unless(Storage::disk($disk)->exists($user->avatar_path), 404);

        $config = config("filesystems.disks.{$disk}", []);
        if (($config['driver'] ?? null) === 'local') {
            return Storage::disk($disk)->response(
                $user->avatar_path,
                $user->avatar_original_filename,
                ['Content-Type' => $user->avatar_mime_type ?: 'image/jpeg'],
            );
        }

        return redirect()->away(Storage::disk($disk)->temporaryUrl($user->avatar_path, now()->addMinutes(10)));
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'preferences' => ['array'],
        ]);

        $user = $request->user();
        $input = $data['preferences'] ?? [];

        foreach (NotificationPreferenceRegistry::events() as $event => $config) {
            foreach ($config['channels'] as $channel) {
                UserNotificationPreference::updateOrCreate(
                    ['user_id' => $user->id, 'event' => $event, 'channel' => $channel],
                    ['enabled' => (bool) data_get($input, "{$event}.{$channel}", false)],
                );
            }
        }

        return back()->with('success', 'Preferencias de notificacao atualizadas.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to('/');
    }

    private function notificationEventsPayload($user): array
    {
        $preferences = $user->notificationPreferences
            ->groupBy('event')
            ->map(fn ($items) => $items->pluck('enabled', 'channel')->map(fn ($enabled) => (bool) $enabled)->all());
        $preferenceMap = $preferences->all();

        return collect(NotificationPreferenceRegistry::events())
            ->map(function (array $event, string $key) use ($preferenceMap) {
                return [
                    'key' => $key,
                    'group' => $event['group'],
                    'label' => $event['label'],
                    'description' => $event['description'],
                    'channels' => collect($event['channels'])->map(fn (string $channel) => [
                        'key' => $channel,
                        'enabled' => data_get($preferenceMap, "{$key}.{$channel}", true),
                    ])->values(),
                ];
            })
            ->values()
            ->all();
    }
}
