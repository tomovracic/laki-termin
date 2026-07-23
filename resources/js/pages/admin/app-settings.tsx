import { Head } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useCallback, useState } from 'react';
import { AdminSectionLayout } from '@/components/admin/admin-section-layout';
import { LoginMessageForm } from '@/components/admin/login-message-form';
import { StatusBanner } from '@/components/admin/status-banner';
import type { ApiErrorResponse } from '@/components/admin/types';
import { csrfHeaders } from '@/lib/csrf';
import { useI18n } from '@/lib/i18n';

type AdminAppSettingsPageProps = {
    login_message: string | null;
};

export default function AdminAppSettingsPage({
    login_message: initialLoginMessage,
}: AdminAppSettingsPageProps) {
    const { t } = useI18n();

    const [loginMessage, setLoginMessage] = useState(initialLoginMessage ?? '');
    const [loginMessageError, setLoginMessageError] = useState<string | undefined>();
    const [isSavingLoginMessage, setIsSavingLoginMessage] = useState(false);
    const [message, setMessage] = useState<string | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    const parseError = useCallback(async (response: Response): Promise<ApiErrorResponse> => {
        try {
            return (await response.json()) as ApiErrorResponse;
        } catch {
            return { message: t('unexpected_server_response') };
        }
    }, [t]);

    async function handleSaveLoginMessage(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        setIsSavingLoginMessage(true);
        setLoginMessageError(undefined);
        setMessage(null);
        setErrorMessage(null);

        const response = await fetch('/app-settings/login-message', {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeaders(),
            },
            body: JSON.stringify({
                login_message: loginMessage.trim() === '' ? null : loginMessage,
            }),
        });

        if (!response.ok) {
            const error = await parseError(response);
            setLoginMessageError(
                Object.values(error.errors ?? {})[0]?.[0] ?? error.message ?? t('unable_save_login_message'),
            );
            setIsSavingLoginMessage(false);
            return;
        }

        const payload = (await response.json()) as { data: { login_message: string | null } };
        setLoginMessage(payload.data.login_message ?? '');
        setMessage(t('login_message_saved'));
        setIsSavingLoginMessage(false);
    }

    return (
        <AdminSectionLayout
            title={t('app_settings_overview')}
            description={t('app_settings_overview_description')}
        >
            <Head title={t('app_settings_overview')} />

            <StatusBanner message={message} error={errorMessage} />

            <LoginMessageForm
                value={loginMessage}
                isSaving={isSavingLoginMessage}
                error={loginMessageError}
                onChange={setLoginMessage}
                onSubmit={(event) => void handleSaveLoginMessage(event)}
            />
        </AdminSectionLayout>
    );
}
