import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { Mail, Send, Inbox } from 'lucide-react';

interface Settings {
    enabled: boolean;
    host: string; port: number; encryption: string; username: string; has_password: boolean;
    from_address: string; from_name: string;
    save_to_sent: boolean;
    imap_host: string; imap_port: number; imap_encryption: string; imap_username: string; has_imap_password: boolean;
    sent_folder: string;
}
interface Props { settings: Settings }

export default function MailSettings({ settings }: Props) {
    const form = useForm({
        enabled: settings.enabled,
        host: settings.host, port: settings.port, encryption: settings.encryption,
        username: settings.username, password: '',
        from_address: settings.from_address, from_name: settings.from_name,
        save_to_sent: settings.save_to_sent,
        imap_host: settings.imap_host, imap_port: settings.imap_port, imap_encryption: settings.imap_encryption,
        imap_username: settings.imap_username, imap_password: '',
        sent_folder: settings.sent_folder,
    });

    const [testEmail, setTestEmail] = useState('');
    const save = (e: React.FormEvent) => { e.preventDefault(); form.put(route('settings.email.update'), { preserveScroll: true }); };
    const sendTest = () => {
        if (!testEmail.trim()) return;
        router.post(route('settings.email.test'), { test_email: testEmail }, { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title="E-mail (SMTP)" />

            <form onSubmit={save} className="mx-auto max-w-2xl space-y-6">
                <h1 className="flex items-center gap-2 text-2xl font-bold text-gray-900"><Mail className="h-6 w-6 text-blue-600" /> E-mail (SMTP)</h1>
                <p className="text-sm text-gray-500">Use seu próprio servidor de e-mail para os envios (recuperação de senha, convites, comunicados e cobranças). Deixe as senhas em branco para manter as atuais.</p>

                {/* SMTP */}
                <section className="rounded-xl border border-gray-200 bg-white p-5 space-y-4">
                    <div className="flex items-center justify-between">
                        <h2 className="flex items-center gap-2 text-sm font-semibold text-gray-700"><Send className="h-4 w-4" /> Servidor de envio (SMTP)</h2>
                        <label className="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" checked={form.data.enabled} onChange={(e) => form.setData('enabled', e.target.checked)} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                            Ativo
                        </label>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Host" error={form.errors.host}><Input value={form.data.host} onChange={(v) => form.setData('host', v)} placeholder="smtp.seudominio.com" /></Field>
                        <div className="grid grid-cols-2 gap-3">
                            <Field label="Porta" error={form.errors.port}><Input type="number" value={String(form.data.port)} onChange={(v) => form.setData('port', Number(v))} /></Field>
                            <Field label="Criptografia"><Select value={form.data.encryption} onChange={(v) => form.setData('encryption', v)} options={[['tls', 'TLS'], ['ssl', 'SSL']]} /></Field>
                        </div>
                        <Field label="Usuário" error={form.errors.username}><Input value={form.data.username} onChange={(v) => form.setData('username', v)} placeholder="usuario@seudominio.com" /></Field>
                        <Field label="Senha" error={form.errors.password}><Input type="password" value={form.data.password} onChange={(v) => form.setData('password', v)} placeholder={settings.has_password ? '•••••• (mantém a atual)' : ''} /></Field>
                        <Field label="Remetente (e-mail)" error={form.errors.from_address}><Input value={form.data.from_address} onChange={(v) => form.setData('from_address', v)} placeholder="contato@seudominio.com" /></Field>
                        <Field label="Remetente (nome)" error={form.errors.from_name}><Input value={form.data.from_name} onChange={(v) => form.setData('from_name', v)} /></Field>
                    </div>
                </section>

                {/* IMAP / Sent */}
                <section className="rounded-xl border border-gray-200 bg-white p-5 space-y-4">
                    <div className="flex items-center justify-between">
                        <h2 className="flex items-center gap-2 text-sm font-semibold text-gray-700"><Inbox className="h-4 w-4" /> Salvar cópia em Enviados (IMAP)</h2>
                        <label className="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" checked={form.data.save_to_sent} onChange={(e) => form.setData('save_to_sent', e.target.checked)} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                            Salvar no Sent
                        </label>
                    </div>
                    {form.data.save_to_sent && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Host IMAP"><Input value={form.data.imap_host} onChange={(v) => form.setData('imap_host', v)} placeholder="imap.seudominio.com" /></Field>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label="Porta"><Input type="number" value={String(form.data.imap_port)} onChange={(v) => form.setData('imap_port', Number(v))} /></Field>
                                <Field label="Criptografia"><Select value={form.data.imap_encryption} onChange={(v) => form.setData('imap_encryption', v)} options={[['ssl', 'SSL'], ['tls', 'TLS']]} /></Field>
                            </div>
                            <Field label="Usuário IMAP"><Input value={form.data.imap_username} onChange={(v) => form.setData('imap_username', v)} /></Field>
                            <Field label="Senha IMAP"><Input type="password" value={form.data.imap_password} onChange={(v) => form.setData('imap_password', v)} placeholder={settings.has_imap_password ? '•••••• (mantém a atual)' : ''} /></Field>
                            <Field label="Pasta Enviados"><Input value={form.data.sent_folder} onChange={(v) => form.setData('sent_folder', v)} placeholder="Sent" /></Field>
                        </div>
                    )}
                </section>

                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <input type="email" value={testEmail} onChange={(e) => setTestEmail(e.target.value)} placeholder="testar enviando para…" className="rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500" />
                        <button type="button" onClick={sendTest} className="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Enviar teste</button>
                    </div>
                    <button type="submit" disabled={form.processing} className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Salvar</button>
                </div>
            </form>
        </AppLayout>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">{label}</label>
            {children}
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}
function Input({ value, onChange, type, placeholder }: { value: string; onChange: (v: string) => void; type?: string; placeholder?: string }) {
    return <input type={type ?? 'text'} value={value} placeholder={placeholder} onChange={(e) => onChange(e.target.value)} className="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500" />;
}
function Select({ value, onChange, options }: { value: string; onChange: (v: string) => void; options: [string, string][] }) {
    return (
        <select value={value} onChange={(e) => onChange(e.target.value)} className="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
            {options.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
        </select>
    );
}
