/**
 * Campo honeypot: invisível para humanos, mas bots costumam preenchê-lo. O backend descarta
 * silenciosamente o envio quando vem preenchido. Não usar type="hidden" (bots ignoram).
 */
export default function HoneypotField({ value, onChange }: { value: string; onChange: (v: string) => void }) {
    return (
        <div aria-hidden="true" style={{ position: 'absolute', left: '-9999px', top: 'auto', width: 1, height: 1, overflow: 'hidden' }}>
            <label>
                Não preencha este campo
                <input
                    type="text"
                    tabIndex={-1}
                    autoComplete="off"
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                />
            </label>
        </div>
    );
}
