import { Form, Head, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import session from '@/routes/staff/session';
import signInCodes from '@/routes/staff/sign-in-codes';

interface LoginProps {
    restaurant: { name: string; slug: string } | null;
    codeLength: number;
    expiresInMinutes: number;
}

/**
 * Passwordless sign-in for staff, in two steps on one screen.
 *
 * The address is entered, a code is emailed, and the same screen then asks for
 * it — the same shape as the panels' login, which staff may also have seen.
 * Everything is sized for a thumb, because this is done standing up.
 */
export default function Login({
    restaurant,
    codeLength,
    expiresInMinutes,
}: LoginProps) {
    const [email, setEmail] = useState('');
    const [codeSent, setCodeSent] = useState(false);
    const { props } = usePage<{ flash?: { status?: string } }>();

    return (
        <>
            <Head title="Sign in" />

            <main className="flex flex-1 flex-col justify-center px-6 pt-[max(2rem,env(safe-area-inset-top))] pb-[max(2rem,env(safe-area-inset-bottom))]">
                <div className="mb-8">
                    <p className="text-muted-foreground text-sm font-medium">
                        {restaurant?.name}
                    </p>
                    <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                        {codeSent ? 'Enter your code' : 'Staff sign in'}
                    </h1>
                    <p className="text-muted-foreground mt-2 text-sm">
                        {codeSent
                            ? `We emailed you a ${codeLength}-digit code. It expires in ${expiresInMinutes} minutes.`
                            : 'We will email you a code. There is no password.'}
                    </p>
                </div>

                {!codeSent ? (
                    <Form
                        action={signInCodes.store(restaurant?.slug ?? '')}
                        onSuccess={() => setCodeSent(true)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="space-y-2">
                                    <Label htmlFor="email">Email address</Label>
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        inputMode="email"
                                        autoComplete="username"
                                        autoFocus
                                        required
                                        value={email}
                                        onChange={(event) =>
                                            setEmail(event.target.value)
                                        }
                                        placeholder="you@example.com"
                                        className="h-12 text-base"
                                    />
                                    {errors.email && (
                                        <p className="text-destructive text-sm">
                                            {errors.email}
                                        </p>
                                    )}
                                </div>

                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="h-12 w-full text-base"
                                >
                                    {processing
                                        ? 'Sending…'
                                        : 'Email me a code'}
                                </Button>
                            </>
                        )}
                    </Form>
                ) : (
                    <Form
                        action={session.store(restaurant?.slug ?? '')}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="email"
                                    value={email}
                                />

                                <div className="space-y-2">
                                    <Label htmlFor="code">
                                        {codeLength}-digit code
                                    </Label>
                                    <Input
                                        id="code"
                                        name="code"
                                        inputMode="numeric"
                                        autoComplete="one-time-code"
                                        autoFocus
                                        required
                                        maxLength={codeLength}
                                        placeholder={'0'.repeat(codeLength)}
                                        className="h-14 text-center text-2xl font-semibold tracking-[0.4em]"
                                    />
                                    {errors.code && (
                                        <p className="text-destructive text-sm">
                                            {errors.code}
                                        </p>
                                    )}
                                    {props.flash?.status && !errors.code && (
                                        <p className="text-muted-foreground text-sm">
                                            {props.flash.status}
                                        </p>
                                    )}
                                </div>

                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="h-12 w-full text-base"
                                >
                                    {processing ? 'Checking…' : 'Sign in'}
                                </Button>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="h-12 w-full"
                                    onClick={() => setCodeSent(false)}
                                >
                                    Use a different email
                                </Button>
                            </>
                        )}
                    </Form>
                )}
            </main>
        </>
    );
}
