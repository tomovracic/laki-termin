import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { useI18n } from '@/lib/i18n';
import { login } from '@/routes';

type InvitationRegisterPageProps = {
    token: string;
    email: string | null;
    is_valid_invitation: boolean;
    status?: string;
};

export default function InvitationRegisterPage({
    token,
    email,
    is_valid_invitation,
    status,
}: InvitationRegisterPageProps) {
    const { locale } = useI18n();
    const isCroatian = locale === 'hr';
    const showExpiredMessage = !is_valid_invitation || status === 'invitation_expired';

    return (
        <AuthLayout
            title={isCroatian ? 'Dovrsite registraciju' : 'Complete registration'}
            description={
                isCroatian
                    ? 'Unesite svoje podatke za aktivaciju korisnickog racuna.'
                    : 'Enter your details to activate your user account.'
            }
        >
            <Head title={isCroatian ? 'Pozivnica' : 'Invitation'} />

            {showExpiredMessage ? (
                <div className="space-y-4 text-center">
                    <p className="text-sm text-muted-foreground">
                        {isCroatian
                            ? 'Pozivnica je istekla. Molimo obratite se administratoru za novu pozivnicu.'
                            : 'This invitation has expired. Please contact an administrator for a new invitation.'}
                    </p>
                    <TextLink href={login()}>
                        {isCroatian ? 'Povratak na prijavu' : 'Back to login'}
                    </TextLink>
                </div>
            ) : (
                <Form
                    action={`/invitation/${token}`}
                    method="post"
                    resetOnSuccess={['password', 'password_confirmation']}
                    disableWhileProcessing
                    className="flex flex-col gap-6"
                >
                    {({ processing, errors }) => (
                        <div className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {isCroatian ? 'Email adresa' : 'Email address'}
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value={email ?? ''}
                                    readOnly
                                    required
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="first_name">{isCroatian ? 'Ime' : 'First name'}</Label>
                                <Input
                                    id="first_name"
                                    type="text"
                                    name="first_name"
                                    required
                                    autoFocus
                                    autoComplete="given-name"
                                />
                                <InputError message={errors.first_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="last_name">
                                    {isCroatian ? 'Prezime' : 'Last name'}
                                </Label>
                                <Input
                                    id="last_name"
                                    type="text"
                                    name="last_name"
                                    required
                                    autoComplete="family-name"
                                />
                                <InputError message={errors.last_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="phone">
                                    {isCroatian ? 'Broj telefona' : 'Phone number'}
                                </Label>
                                <Input
                                    id="phone"
                                    type="tel"
                                    name="phone"
                                    required
                                    autoComplete="tel"
                                />
                                <InputError message={errors.phone} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">
                                    {isCroatian ? 'Lozinka' : 'Password'}
                                </Label>
                                <Input
                                    id="password"
                                    type="password"
                                    name="password"
                                    required
                                    autoComplete="new-password"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    {isCroatian ? 'Potvrdite lozinku' : 'Confirm password'}
                                </Label>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    name="password_confirmation"
                                    required
                                    autoComplete="new-password"
                                />
                                <InputError message={errors.password_confirmation} />
                            </div>

                            <Button type="submit" className="mt-2 w-full">
                                {processing && <Spinner />}
                                {isCroatian ? 'Dovrsi registraciju' : 'Complete registration'}
                            </Button>
                        </div>
                    )}
                </Form>
            )}
        </AuthLayout>
    );
}
