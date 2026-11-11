import { Briefcase, Building2, Car, Cog, Droplets, Hammer, Leaf, Snowflake, Sparkles, Sun, Zap, type LucideIcon } from 'lucide-react';

/** Keyed by the segment name stored in the database (see SegmentSeeder). */
const segmentIcons: Record<string, LucideIcon> = {
    Climatização: Snowflake,
    Eletricistas: Zap,
    Encanadores: Droplets,
    Oficinas: Car,
    'Assistência técnica': Cog,
    Jardinagem: Leaf,
    Limpeza: Sparkles,
    'Serviços gerais': Hammer,
    'Energia solar': Sun,
    'Serviços profissionais': Briefcase,
};

/** Segments created after this file was written still get a coherent icon. */
const fallbackIcon: LucideIcon = Building2;

export function segmentIconFor(segmentName: string): LucideIcon {
    return segmentIcons[segmentName] ?? fallbackIcon;
}
