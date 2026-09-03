import { CircleCheck, CircleX, Info } from 'lucide-react';

import { Alert, AlertDescription } from '@/components/ui/alert';

type AuthStatusProps = {
    children: React.ReactNode;
    variant?: 'success' | 'info' | 'destructive';
};

export function AuthStatus({ children, variant = 'info' }: AuthStatusProps) {
    const Icon =
        variant === 'success'
            ? CircleCheck
            : variant === 'destructive'
              ? CircleX
              : Info;

    return (
        <Alert variant={variant}>
            <Icon />
            <AlertDescription>{children}</AlertDescription>
        </Alert>
    );
}
