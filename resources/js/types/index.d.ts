export interface AuthUser {
    id: string;
    name: string;
    email: string;
    is_super_admin: boolean;
}

export interface TenantData {
    id: string;
    name: string;
    slug: string;
    brand_name: string;
    logo_url: string | null;
    primary_color: string;
}

export interface FlashMessages {
    success: string | null;
    error: string | null;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: AuthUser | null;
    };
    tenant: TenantData | null;
    flash: FlashMessages;
};
