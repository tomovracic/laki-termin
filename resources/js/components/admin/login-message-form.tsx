import { RefreshCcw } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useI18n } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type LoginMessageFormProps = {
    value: string;
    isSaving: boolean;
    error?: string;
    onChange: (value: string) => void;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
};

export function LoginMessageForm({
    value,
    isSaving,
    error,
    onChange,
    onSubmit,
}: LoginMessageFormProps) {
    const { t } = useI18n();

    return (
        <form className="space-y-3 rounded-xl border p-4" onSubmit={onSubmit}>
            <div className="space-y-1">
                <h2 className="text-lg font-semibold">{t('login_message_settings_title')}</h2>
                <p className="text-sm text-muted-foreground">
                    {t('login_message_settings_description')}
                </p>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="login-message">{t('login_message_label')}</Label>
                <textarea
                    id="login-message"
                    rows={4}
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    placeholder={t('login_message_placeholder')}
                    className={cn(
                        'border-input placeholder:text-muted-foreground flex min-h-24 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none md:text-sm',
                        'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                    )}
                />
                <p className="text-sm text-muted-foreground">{t('login_message_help')}</p>
            </div>

            {error !== undefined && <p className="text-sm text-red-500">{error}</p>}

            <Button type="submit" disabled={isSaving}>
                {isSaving ? (
                    <>
                        <RefreshCcw className="size-4 animate-spin" />
                        {t('saving')}
                    </>
                ) : (
                    t('save_login_message')
                )}
            </Button>
        </form>
    );
}
