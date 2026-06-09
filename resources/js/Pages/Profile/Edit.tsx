import AdminLayout from '@/Layouts/AdminLayout';
import AppLayout from '@/Layouts/AppLayout';
import PortalLayout from '@/Layouts/PortalLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { Bell, Camera, LockKeyhole, Save, Trash2, UserRound } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';

interface ProfileData {
    name: string;
    email: string;
    phone: string | null;
    avatar_url: string | null;
    area: 'admin' | 'panel' | 'portal';
}

interface NotificationChannelState {
    key: string;
    enabled: boolean;
}

interface NotificationEvent {
    key: string;
    group: string;
    label: string;
    description: string;
    channels: NotificationChannelState[];
}

interface Props {
    profile: ProfileData;
    notificationChannels: Record<string, string>;
    notificationEvents: NotificationEvent[];
}

type PreferenceState = Record<string, Record<string, boolean>>;

function PageShell({ area, children }: { area: ProfileData['area']; children: React.ReactNode }) {
    if (area === 'admin') {
        return <AdminLayout>{children}</AdminLayout>;
    }

    if (area === 'portal') {
        return <PortalLayout title="Meu perfil">{children}</PortalLayout>;
    }

    return <AppLayout>{children}</AppLayout>;
}

function Section({ title, icon: Icon, children }: { title: string; icon: typeof UserRound; children: React.ReactNode }) {
    return (
        <section className="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <div className="mb-4 flex items-center gap-2">
                <Icon className="h-5 w-5 text-blue-600" />
                <h2 className="text-sm font-semibold uppercase tracking-wide text-gray-700">{title}</h2>
            </div>
            {children}
        </section>
    );
}

const inputClass = 'mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';

export default function ProfileEdit({ profile, notificationChannels, notificationEvents }: Props) {
    const [avatarPreview, setAvatarPreview] = useState<string | null>(profile.avatar_url);

    const profileForm = useForm({
        name: profile.name,
        email: profile.email,
        phone: profile.phone ?? '',
    });

    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const avatarForm = useForm<{ avatar: File | null }>({
        avatar: null,
    });

    const initialPreferences = useMemo<PreferenceState>(() => {
        return notificationEvents.reduce<PreferenceState>((acc, event) => {
            acc[event.key] = event.channels.reduce<Record<string, boolean>>((channels, channel) => {
                channels[channel.key] = channel.enabled;
                return channels;
            }, {});

            return acc;
        }, {});
    }, [notificationEvents]);

    const preferencesForm = useForm<{ preferences: PreferenceState }>({
        preferences: initialPreferences,
    });

    const groupedEvents = useMemo(() => {
        return notificationEvents.reduce<Record<string, NotificationEvent[]>>((acc, event) => {
            (acc[event.group] ??= []).push(event);
            return acc;
        }, {});
    }, [notificationEvents]);

    const submitProfile = (event: FormEvent) => {
        event.preventDefault();
        profileForm.patch(route('profile.update'), { preserveScroll: true });
    };

    const submitPassword = (event: FormEvent) => {
        event.preventDefault();
        passwordForm.put(route('profile.password.update'), {
            preserveScroll: true,
            onSuccess: () => passwordForm.reset(),
        });
    };

    const submitAvatar = (event: FormEvent) => {
        event.preventDefault();
        avatarForm.post(route('profile.avatar.update'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => avatarForm.reset(),
        });
    };

    const selectAvatar = (file: File | null) => {
        avatarForm.setData('avatar', file);
        setAvatarPreview(file ? URL.createObjectURL(file) : profile.avatar_url);
    };

    const removeAvatar = () => {
        router.delete(route('profile.avatar.destroy'), {
            preserveScroll: true,
            onSuccess: () => {
                setAvatarPreview(null);
                avatarForm.reset();
            },
        });
    };

    const togglePreference = (eventKey: string, channelKey: string, enabled: boolean) => {
        preferencesForm.setData('preferences', {
            ...preferencesForm.data.preferences,
            [eventKey]: {
                ...(preferencesForm.data.preferences[eventKey] ?? {}),
                [channelKey]: enabled,
            },
        });
    };

    const submitPreferences = (event: FormEvent) => {
        event.preventDefault();
        preferencesForm.patch(route('profile.notifications.update'), { preserveScroll: true });
    };

    return (
        <PageShell area={profile.area}>
            <Head title="Meu perfil" />

            <div className="mx-auto max-w-5xl space-y-6">
                {profile.area !== 'portal' && (
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Meu perfil</h1>
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-[320px_minmax(0,1fr)]">
                    <div className="space-y-6">
                        <Section title="Foto" icon={Camera}>
                            <form onSubmit={submitAvatar} className="space-y-4">
                                <div className="flex items-center gap-4">
                                    {avatarPreview ? (
                                        <img src={avatarPreview} alt={profile.name} className="h-20 w-20 rounded-full object-cover" />
                                    ) : (
                                        <div className="flex h-20 w-20 items-center justify-center rounded-full bg-blue-100 text-2xl font-semibold text-blue-700">
                                            {profile.name.charAt(0).toUpperCase()}
                                        </div>
                                    )}
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium text-gray-900">{profile.name}</p>
                                        <p className="truncate text-xs text-gray-500">{profile.email}</p>
                                    </div>
                                </div>

                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    onChange={(event) => selectAvatar(event.target.files?.[0] ?? null)}
                                    className="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100"
                                />
                                {avatarForm.errors.avatar && <p className="text-xs text-red-600">{avatarForm.errors.avatar}</p>}

                                <div className="flex flex-wrap gap-2">
                                    <button type="submit" disabled={!avatarForm.data.avatar || avatarForm.processing} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                                        <Save className="h-4 w-4" /> Salvar foto
                                    </button>
                                    {profile.avatar_url && (
                                        <button type="button" onClick={removeAvatar} className="inline-flex items-center gap-2 rounded-lg border border-red-100 px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                                            <Trash2 className="h-4 w-4" /> Remover
                                        </button>
                                    )}
                                </div>
                            </form>
                        </Section>

                        <Section title="Senha" icon={LockKeyhole}>
                            <form onSubmit={submitPassword} className="space-y-4">
                                <div>
                                    <label className="text-sm font-medium text-gray-700">Senha atual</label>
                                    <input type="password" value={passwordForm.data.current_password} onChange={(event) => passwordForm.setData('current_password', event.target.value)} autoComplete="current-password" className={inputClass} />
                                    {passwordForm.errors.current_password && <p className="mt-1 text-xs text-red-600">{passwordForm.errors.current_password}</p>}
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-700">Nova senha</label>
                                    <input type="password" value={passwordForm.data.password} onChange={(event) => passwordForm.setData('password', event.target.value)} autoComplete="new-password" className={inputClass} />
                                    {passwordForm.errors.password && <p className="mt-1 text-xs text-red-600">{passwordForm.errors.password}</p>}
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-700">Confirmar nova senha</label>
                                    <input type="password" value={passwordForm.data.password_confirmation} onChange={(event) => passwordForm.setData('password_confirmation', event.target.value)} autoComplete="new-password" className={inputClass} />
                                </div>
                                <button type="submit" disabled={passwordForm.processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                                    Atualizar senha
                                </button>
                            </form>
                        </Section>
                    </div>

                    <div className="space-y-6">
                        <Section title="Dados de contato" icon={UserRound}>
                            <form onSubmit={submitProfile} className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="text-sm font-medium text-gray-700">Nome</label>
                                    <input value={profileForm.data.name} onChange={(event) => profileForm.setData('name', event.target.value)} className={inputClass} />
                                    {profileForm.errors.name && <p className="mt-1 text-xs text-red-600">{profileForm.errors.name}</p>}
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-700">E-mail</label>
                                    <input type="email" value={profileForm.data.email} onChange={(event) => profileForm.setData('email', event.target.value)} className={inputClass} />
                                    {profileForm.errors.email && <p className="mt-1 text-xs text-red-600">{profileForm.errors.email}</p>}
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-700">Telefone</label>
                                    <input value={profileForm.data.phone} onChange={(event) => profileForm.setData('phone', event.target.value)} className={inputClass} />
                                    {profileForm.errors.phone && <p className="mt-1 text-xs text-red-600">{profileForm.errors.phone}</p>}
                                </div>
                                <div className="flex items-end">
                                    <button type="submit" disabled={profileForm.processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                                        Salvar dados
                                    </button>
                                </div>
                            </form>
                        </Section>

                        <Section title="Preferencias de notificacao" icon={Bell}>
                            <form onSubmit={submitPreferences} className="space-y-6">
                                {Object.entries(groupedEvents).map(([group, events]) => (
                                    <div key={group} className="overflow-hidden rounded-lg border border-gray-100">
                                        <div className="bg-gray-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{group}</div>
                                        <div className="divide-y divide-gray-100">
                                            {events.map((event) => (
                                                <div key={event.key} className="grid gap-3 px-4 py-3 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                                                    <div>
                                                        <p className="text-sm font-medium text-gray-900">{event.label}</p>
                                                        <p className="mt-0.5 text-xs text-gray-500">{event.description}</p>
                                                    </div>
                                                    <div className="flex flex-wrap gap-2">
                                                        {event.channels.map((channel) => (
                                                            <label key={`${event.key}-${channel.key}`} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700">
                                                                <input
                                                                    type="checkbox"
                                                                    checked={Boolean(preferencesForm.data.preferences[event.key]?.[channel.key])}
                                                                    onChange={(checkbox) => togglePreference(event.key, channel.key, checkbox.target.checked)}
                                                                    className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                                />
                                                                {notificationChannels[channel.key] ?? channel.key}
                                                            </label>
                                                        ))}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ))}

                                <button type="submit" disabled={preferencesForm.processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                                    Salvar preferencias
                                </button>
                            </form>
                        </Section>
                    </div>
                </div>
            </div>
        </PageShell>
    );
}
