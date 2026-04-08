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

export default function Register() {
    const { locale } = useI18n();
    const isCroatian = locale === 'hr';

    return (
        <AuthLayout
            title={isCroatian ? 'Kreirajte korisnicki racun' : 'Create an account'}
            description={
                isCroatian
                    ? 'Unesite podatke za kreiranje korisnickog racuna'
                    : 'Enter your details below to create your account'
            }
        >
            <Head title={isCroatian ? 'Registracija' : 'Register'} />
            <Form
                action="/register"
                method="post"
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="first_name">
                                    {isCroatian ? 'Ime' : 'First name'}
                                </Label>
                                <Input
                                    id="first_name"
                                    type="text"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="given-name"
                                    name="first_name"
                                    placeholder={isCroatian ? 'Ime' : 'First name'}
                                />
                                <InputError
                                    message={errors.first_name}
                                    className="mt-2"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="last_name">
                                    {isCroatian ? 'Prezime' : 'Last name'}
                                </Label>
                                <Input
                                    id="last_name"
                                    type="text"
                                    required
                                    tabIndex={2}
                                    autoComplete="family-name"
                                    name="last_name"
                                    placeholder={isCroatian ? 'Prezime' : 'Last name'}
                                />
                                <InputError
                                    message={errors.last_name}
                                    className="mt-2"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="phone">
                                    {isCroatian ? 'Broj telefona' : 'Phone number'}
                                </Label>
                                <Input
                                    id="phone"
                                    type="tel"
                                    required
                                    tabIndex={3}
                                    autoComplete="tel"
                                    name="phone"
                                    placeholder={
                                        isCroatian ? 'npr. 0911234567' : 'e.g. +385911234567'
                                    }
                                />
                                <InputError message={errors.phone} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {isCroatian ? 'Email adresa' : 'Email address'}
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    tabIndex={4}
                                    autoComplete="email"
                                    name="email"
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">
                                    {isCroatian ? 'Lozinka' : 'Password'}
                                </Label>
                                <Input
                                    id="password"
                                    type="password"
                                    required
                                    tabIndex={5}
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder={isCroatian ? 'Lozinka' : 'Password'}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    {isCroatian
                                        ? 'Potvrdite lozinku'
                                        : 'Confirm password'}
                                </Label>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    required
                                    tabIndex={6}
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder={
                                        isCroatian
                                            ? 'Potvrdite lozinku'
                                            : 'Confirm password'
                                    }
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                tabIndex={7}
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                {isCroatian
                                    ? 'Kreiraj korisnicki racun'
                                    : 'Create account'}
                            </Button>
                        </div>

                        <div className="text-center text-sm text-muted-foreground">
                            {isCroatian
                                ? 'Vec imate korisnicki racun? '
                                : 'Already have an account? '}
                            <TextLink href={login()} tabIndex={8}>
                                {isCroatian ? 'Prijava' : 'Log in'}
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
