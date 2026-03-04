import type { Auth } from '@/types/auth';
import type { LocaleCode, LocaleOption } from '@/types/i18n';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            locale: LocaleCode;
            availableLocales: LocaleOption[];
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
