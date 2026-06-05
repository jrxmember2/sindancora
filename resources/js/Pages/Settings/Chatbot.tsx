import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import { Bot, MessageCircle, Building2 } from 'lucide-react';

interface Connection {
    id: string;
    name: string;
    bot_enabled: boolean;
    condominium_menu_header: string | null;
    serves_multiple: boolean;
    condominiums: string[];
}
interface CondSetting {
    condominium_id: string;
    condominium: string;
    is_enabled: boolean;
    greeting_message: string | null;
    sector_menu_header: string | null;
    invalid_option_message: string | null;
}
interface Props {
    connections: Connection[];
    settings: CondSetting[];
    defaults: { greeting: string; sector_menu_header: string; invalid: string };
}

export default function Chatbot({ connections, settings, defaults }: Props) {
    return (
        <AppLayout>
            <Head title="Chatbot do WhatsApp" />

            <div className="mx-auto max-w-3xl space-y-8">
                <div>
                    <h1 className="flex items-center gap-2 text-2xl font-bold text-gray-900">
                        <Bot className="h-6 w-6 text-emerald-600" /> Chatbot do WhatsApp
                    </h1>
                    <p className="mt-1 text-sm text-gray-500">
                        O chatbot recebe o contato, identifica o condomínio (quando a conexão atende mais de um) e o setor desejado, e encaminha a conversa para a equipe certa.
                    </p>
                </div>

                {/* Conexões */}
                <section className="space-y-3">
                    <h2 className="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-500">
                        <MessageCircle className="h-4 w-4" /> Por conexão
                    </h2>
                    {connections.length === 0 ? (
                        <p className="rounded-xl border border-dashed border-gray-300 bg-white py-8 text-center text-sm text-gray-400">Nenhuma conexão de WhatsApp.</p>
                    ) : (
                        connections.map((c) => <ConnectionCard key={c.id} connection={c} />)
                    )}
                </section>

                {/* Condomínios */}
                <section className="space-y-3">
                    <h2 className="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-500">
                        <Building2 className="h-4 w-4" /> Mensagens por condomínio
                    </h2>
                    {settings.length === 0 ? (
                        <p className="rounded-xl border border-dashed border-gray-300 bg-white py-8 text-center text-sm text-gray-400">Nenhum condomínio cadastrado.</p>
                    ) : (
                        settings.map((s) => <CondominiumCard key={s.condominium_id} setting={s} defaults={defaults} />)
                    )}
                </section>
            </div>
        </AppLayout>
    );
}

function ConnectionCard({ connection }: { connection: Connection }) {
    const form = useForm({
        bot_enabled: connection.bot_enabled,
        condominium_menu_header: connection.condominium_menu_header ?? '',
    });

    const save = () => form.put(route('chatbot.connection', connection.id), { preserveScroll: true });

    return (
        <div className="rounded-xl border border-gray-200 bg-white p-4">
            <div className="flex items-center justify-between gap-3">
                <div className="min-w-0">
                    <p className="font-semibold text-gray-900">{connection.name}</p>
                    <p className="truncate text-xs text-gray-500">{connection.condominiums.length > 0 ? connection.condominiums.join(', ') : 'Nenhum condomínio alocado'}</p>
                </div>
                <label className="flex flex-shrink-0 items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" checked={form.data.bot_enabled} onChange={(e) => form.setData('bot_enabled', e.target.checked)} className="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" />
                    Chatbot ativo
                </label>
            </div>

            {connection.serves_multiple && (
                <div className="mt-3">
                    <label className="mb-1 block text-sm font-medium text-gray-700">Mensagem do menu de condomínio</label>
                    <textarea
                        value={form.data.condominium_menu_header}
                        onChange={(e) => form.setData('condominium_menu_header', e.target.value)}
                        rows={2}
                        placeholder="Olá! 👋 Para qual condomínio é o seu atendimento? Responda com o número:"
                        className="w-full rounded-lg border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                    />
                    <p className="mt-1 text-xs text-gray-400">As opções (1, 2, 3…) com os nomes dos condomínios são adicionadas automaticamente.</p>
                </div>
            )}

            <div className="mt-3 flex justify-end">
                <button onClick={save} disabled={form.processing} className="rounded-lg bg-emerald-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">Salvar</button>
            </div>
        </div>
    );
}

function CondominiumCard({ setting, defaults }: { setting: CondSetting; defaults: Props['defaults'] }) {
    const form = useForm({
        is_enabled: setting.is_enabled,
        greeting_message: setting.greeting_message ?? '',
        sector_menu_header: setting.sector_menu_header ?? '',
        invalid_option_message: setting.invalid_option_message ?? '',
    });

    const save = () => form.put(route('chatbot.condominium', setting.condominium_id), { preserveScroll: true });

    return (
        <div className="rounded-xl border border-gray-200 bg-white p-4">
            <div className="flex items-center justify-between gap-3">
                <p className="font-semibold text-gray-900">{setting.condominium}</p>
                <label className="flex flex-shrink-0 items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" checked={form.data.is_enabled} onChange={(e) => form.setData('is_enabled', e.target.checked)} className="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" />
                    Menu de setores ativo
                </label>
            </div>

            <div className="mt-3 space-y-3">
                <Field label="Saudação" value={form.data.greeting_message} placeholder={defaults.greeting} onChange={(v) => form.setData('greeting_message', v)} />
                <Field label="Cabeçalho do menu de setores" value={form.data.sector_menu_header} placeholder={defaults.sector_menu_header} onChange={(v) => form.setData('sector_menu_header', v)} />
                <Field label="Mensagem de opção inválida" value={form.data.invalid_option_message} placeholder={defaults.invalid} onChange={(v) => form.setData('invalid_option_message', v)} />
            </div>

            <div className="mt-3 flex justify-end">
                <button onClick={save} disabled={form.processing} className="rounded-lg bg-emerald-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">Salvar</button>
            </div>
        </div>
    );
}

function Field({ label, value, placeholder, onChange }: { label: string; value: string; placeholder: string; onChange: (v: string) => void }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">{label}</label>
            <textarea value={value} onChange={(e) => onChange(e.target.value)} rows={2} placeholder={placeholder} className="w-full rounded-lg border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" />
        </div>
    );
}
