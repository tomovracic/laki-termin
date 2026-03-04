import { router } from '@inertiajs/react';
import { Languages } from 'lucide-react';
import { useI18n } from '@/lib/i18n';
import type { LocaleCode } from '@/types';

export function LanguageSwitcher() {
    const { locale, availableLocales, t } = useI18n();

    const updateLocale = (nextLocale: LocaleCode): void => {
        if (nextLocale === locale) {
            return;
        }

        router.post(
            '/locale',
            { locale: nextLocale },
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    };

    return (
        <div className="flex items-center gap-1 rounded-md border px-2 py-1">
            <Languages className="size-4 text-muted-foreground" />
            <span className="sr-only">{t('language')}</span>
            <div className="flex items-center gap-1 text-xs">
                {availableLocales.map((option) => (
                    <button
                        key={option.code}
                        type="button"
                        className={
                            option.code === locale
                                ? 'rounded bg-muted px-2 py-1 font-medium'
                                : 'rounded px-2 py-1 text-muted-foreground hover:bg-muted'
                        }
                        onClick={() => updateLocale(option.code)}
                    >
                        {option.label}
                    </button>
                ))}
            </div>
        </div>
    );
}
