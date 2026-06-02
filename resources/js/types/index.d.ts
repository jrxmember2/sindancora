export interface AuthUser {
    id: string;
    name: string;
    email: string;
    is_super_admin: boolean;
    permissions: string[];
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

export interface NotificationData {
    title: string;
    message: string;
    url?: string;
    icon?: string;
}

export interface AppNotification {
    id: string;
    data: NotificationData;
    read_at: string | null;
    created_at: string | null;
}

export interface SharedNotifications {
    unread_count: number;
    recent: AppNotification[];
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: AuthUser | null;
    };
    tenant: TenantData | null;
    flash: FlashMessages;
    notifications: SharedNotifications | null;
};
