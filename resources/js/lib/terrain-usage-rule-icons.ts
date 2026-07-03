import {
    AlertTriangle,
    Ban,
    Calendar,
    CheckCircle,
    Clock,
    Coins,
    Info,
    MapPin,
    Ticket,
    Timer,
    Umbrella,
    Users,
    Wrench,
    type LucideIcon,
} from 'lucide-react';

export type TerrainUsageRuleIconName =
    | 'clock'
    | 'calendar'
    | 'ban'
    | 'info'
    | 'alert_triangle'
    | 'check_circle'
    | 'coins'
    | 'users'
    | 'map_pin'
    | 'umbrella'
    | 'wrench'
    | 'timer'
    | 'ticket';

export type TerrainUsageRule = {
    icon: TerrainUsageRuleIconName;
    text: string;
};

export const TERRAIN_USAGE_RULE_ICONS: TerrainUsageRuleIconName[] = [
    'clock',
    'calendar',
    'ban',
    'info',
    'alert_triangle',
    'check_circle',
    'coins',
    'users',
    'map_pin',
    'umbrella',
    'wrench',
    'timer',
    'ticket',
];

const ICON_MAP: Record<TerrainUsageRuleIconName, LucideIcon> = {
    clock: Clock,
    calendar: Calendar,
    ban: Ban,
    info: Info,
    alert_triangle: AlertTriangle,
    check_circle: CheckCircle,
    coins: Coins,
    users: Users,
    map_pin: MapPin,
    umbrella: Umbrella,
    wrench: Wrench,
    timer: Timer,
    ticket: Ticket,
};

export function terrainUsageRuleIconComponent(
    icon: TerrainUsageRuleIconName,
): LucideIcon {
    return ICON_MAP[icon];
}
