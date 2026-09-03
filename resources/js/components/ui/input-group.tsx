import { cva, type VariantProps } from 'class-variance-authority';
import * as React from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

function InputGroup({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="input-group"
            role="group"
            className={cn(
                'group/input-group relative flex h-[42px] min-w-0 w-full items-center rounded-md border border-input bg-background transition-[border-color,box-shadow] outline-none',
                'has-[[data-slot=input-group-control]:focus-visible]:border-ring has-[[data-slot=input-group-control]:focus-visible]:ring-[3px] has-[[data-slot=input-group-control]:focus-visible]:ring-ring/15',
                'has-[[data-slot][aria-invalid=true]]:border-destructive has-[[data-slot][aria-invalid=true]]:ring-destructive/15',
                className,
            )}
            {...props}
        />
    );
}

const inputGroupAddonVariants = cva(
    'flex h-auto cursor-text items-center justify-center gap-2 py-1.5 text-sm font-medium text-muted-foreground select-none [&>svg:not([class*=\'size-\'])]:size-4',
    {
        variants: {
            align: {
                'inline-start': 'order-first pl-3',
                'inline-end': 'order-last pr-1.5',
            },
        },
        defaultVariants: {
            align: 'inline-start',
        },
    },
);

function InputGroupAddon({
    className,
    align = 'inline-start',
    ...props
}: React.ComponentProps<'div'> & VariantProps<typeof inputGroupAddonVariants>) {
    return (
        <div
            role="group"
            data-slot="input-group-addon"
            data-align={align}
            className={cn(inputGroupAddonVariants({ align }), className)}
            onClick={(event) => {
                if ((event.target as HTMLElement).closest('button')) {
                    return;
                }

                event.currentTarget.parentElement
                    ?.querySelector('input')
                    ?.focus();
            }}
            {...props}
        />
    );
}

function InputGroupButton({
    className,
    type = 'button',
    ...props
}: React.ComponentProps<typeof Button>) {
    return (
        <Button
            type={type}
            variant="ghost"
            size="icon-sm"
            className={cn('shadow-none', className)}
            {...props}
        />
    );
}

function InputGroupInput({
    className,
    ...props
}: React.ComponentProps<'input'>) {
    return (
        <Input
            data-slot="input-group-control"
            className={cn(
                'flex-1 rounded-none border-0 bg-transparent shadow-none focus-visible:ring-0',
                className,
            )}
            {...props}
        />
    );
}

export {
    InputGroup,
    InputGroupAddon,
    InputGroupButton,
    InputGroupInput,
};
