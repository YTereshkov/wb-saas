import { Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';

import {
    InputGroup,
    InputGroupAddon,
    InputGroupButton,
    InputGroupInput,
} from '@/components/ui/input-group';

type PasswordInputProps = Omit<React.ComponentProps<'input'>, 'type'>;

export function PasswordInput(props: PasswordInputProps) {
    const [visible, setVisible] = useState(false);

    return (
        <InputGroup>
            <InputGroupInput type={visible ? 'text' : 'password'} {...props} />
            <InputGroupAddon align="inline-end">
                <InputGroupButton
                    type="button"
                    onClick={() => setVisible((current) => !current)}
                    aria-label={visible ? 'Скрыть пароль' : 'Показать пароль'}
                    aria-pressed={visible}
                >
                    {visible ? <EyeOff /> : <Eye />}
                </InputGroupButton>
            </InputGroupAddon>
        </InputGroup>
    );
}
