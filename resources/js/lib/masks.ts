// Helpers de máscara e validação reutilizáveis (pt-BR).

/** Mantém apenas dígitos. */
export function onlyDigits(value: string): string {
    return (value ?? '').replace(/\D/g, '');
}

/** Máscara de telefone BR: (00) 0000-0000 ou (00) 00000-0000. */
export function maskPhone(value: string): string {
    const d = onlyDigits(value).slice(0, 11);
    if (d.length <= 2) return d.replace(/^(\d{0,2})/, '($1');
    if (d.length <= 6) return d.replace(/^(\d{2})(\d{0,4})/, '($1) $2');
    if (d.length <= 10) return d.replace(/^(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
    return d.replace(/^(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
}

/** Máscara de CPF: 000.000.000-00. */
export function maskCpf(value: string): string {
    const d = onlyDigits(value).slice(0, 11);
    return d
        .replace(/^(\d{3})(\d)/, '$1.$2')
        .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/\.(\d{3})(\d)/, '.$1-$2');
}

/** Máscara de CNPJ: 00.000.000/0000-00. */
export function maskCnpj(value: string): string {
    const d = onlyDigits(value).slice(0, 14);
    return d
        .replace(/^(\d{2})(\d)/, '$1.$2')
        .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/\.(\d{3})(\d)/, '.$1/$2')
        .replace(/(\d{4})(\d)/, '$1-$2');
}

/** Máscara dinâmica CPF (<=11 dígitos) ou CNPJ. Síndico pessoa física usa só CPF. */
export function maskCpfCnpj(value: string): string {
    const d = onlyDigits(value);
    return d.length <= 11 ? maskCpf(d) : maskCnpj(d);
}

/** Validação real de CPF (dígitos verificadores). */
export function isValidCpf(value: string): boolean {
    const cpf = onlyDigits(value);
    if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;
    let sum = 0;
    for (let i = 0; i < 9; i++) sum += parseInt(cpf[i]) * (10 - i);
    let check = (sum * 10) % 11 % 10;
    if (check !== parseInt(cpf[9])) return false;
    sum = 0;
    for (let i = 0; i < 10; i++) sum += parseInt(cpf[i]) * (11 - i);
    check = (sum * 10) % 11 % 10;
    return check === parseInt(cpf[10]);
}

/** Validação real de CNPJ (dígitos verificadores). */
export function isValidCnpj(value: string): boolean {
    const cnpj = onlyDigits(value);
    if (cnpj.length !== 14 || /^(\d)\1{13}$/.test(cnpj)) return false;
    const calc = (len: number) => {
        const weights = len === 12
            ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
            : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        let sum = 0;
        for (let i = 0; i < len; i++) sum += parseInt(cnpj[i]) * weights[i];
        const rest = sum % 11;
        return rest < 2 ? 0 : 11 - rest;
    };
    return calc(12) === parseInt(cnpj[12]) && calc(13) === parseInt(cnpj[13]);
}

/** Aceita CPF (11) ou CNPJ (14). Vazio é considerado válido (campo opcional). */
export function isValidCpfCnpj(value: string): boolean {
    const d = onlyDigits(value);
    if (d.length === 0) return true;
    if (d.length === 11) return isValidCpf(d);
    if (d.length === 14) return isValidCnpj(d);
    return false;
}

/** Validação simples de e-mail (precisa ter @ e domínio). */
export function isValidEmail(value: string): boolean {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test((value ?? '').trim());
}
