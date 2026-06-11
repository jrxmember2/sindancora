// Mapa de ícones usados pelos widgets (resolvers devolvem o nome em string).
import {
    Building2, DoorClosed, Users, TrendingUp, TrendingDown, Wallet,
    CircleDollarSign, AlertTriangle, Scale, Receipt, AlertCircle,
    CalendarRange, Timer, FileText, Hammer, Megaphone, HardDrive,
    Activity, type LucideIcon,
} from 'lucide-react';

export const ICONS: Record<string, LucideIcon> = {
    Building2, DoorClosed, Users, TrendingUp, TrendingDown, Wallet,
    CircleDollarSign, AlertTriangle, Scale, Receipt, AlertCircle,
    CalendarRange, Timer, FileText, Hammer, Megaphone, HardDrive, Activity,
};

export function icon(name?: string): LucideIcon {
    return (name && ICONS[name]) || Activity;
}
