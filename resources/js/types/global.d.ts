import type { Auth } from '@/types/auth';
import type { LocaleCode, LocaleOption } from '@/types/i18n';
import type { SharedNavProps } from '@/types/nav';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            locale: LocaleCode;
            availableLocales: LocaleOption[];
            sidebarOpen: boolean;
            loginMessage?: string | null;
            nav?: SharedNavProps | null;
            [key: string]: unknown;
        };
    }
}
