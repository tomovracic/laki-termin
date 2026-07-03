import type { TerrainUsageRule } from '@/lib/terrain-usage-rule-icons';

export type SharedNavProps = {
    token_count: number;
    terrain_usage_rules: TerrainUsageRule[];
    must_acknowledge_terrain_usage_rules: boolean;
};
