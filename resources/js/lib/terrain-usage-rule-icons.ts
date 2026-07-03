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

export type TerrainUsageRuleEmphasis = 'neutral' | 'alert' | 'warning';

export type TerrainUsageRule = {
    icon: TerrainUsageRuleIconName;
    text: string;
    emphasis?: TerrainUsageRuleEmphasis | null;
};

export const TERRAIN_USAGE_RULE_EMPHASIS_OPTIONS: TerrainUsageRuleEmphasis[] = [
    'neutral',
    'alert',
    'warning',
];

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

export function terrainUsageRuleEmphasisContainerClasses(
    emphasis?: TerrainUsageRuleEmphasis | null,
): string {
    switch (emphasis) {
        case 'neutral':
            return 'border-2 border-muted-foreground/30 bg-muted/80 shadow-sm';
        case 'alert':
            return 'border-2 border-destructive/50 bg-destructive/10 shadow-sm';
        case 'warning':
            return 'border-2 border-amber-500/50 bg-amber-500/10 shadow-sm';
        default:
            return 'border border-border/70 bg-muted/30';
    }
}

export function terrainUsageRuleEmphasisIconClasses(
    emphasis?: TerrainUsageRuleEmphasis | null,
): string {
    switch (emphasis) {
        case 'neutral':
            return 'text-muted-foreground';
        case 'alert':
            return 'text-destructive';
        case 'warning':
            return 'text-amber-600 dark:text-amber-400';
        default:
            return 'text-primary';
    }
}

export function terrainUsageRuleEmphasisPreviewClasses(
    emphasis: TerrainUsageRuleEmphasis,
): string {
    switch (emphasis) {
        case 'neutral':
            return 'bg-muted-foreground/40';
        case 'alert':
            return 'bg-destructive';
        case 'warning':
            return 'bg-amber-500';
    }
}
