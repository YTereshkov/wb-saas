<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'terms' => ['accepted'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $email = $this->input('email');
        $this->merge(array_filter([
            'name' => is_string($name) ? trim($name) : null,
            'email' => is_string($email) ? Str::lower(trim($email)) : null,
        ], static fn (?string $value): bool => $value !== null));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Введите имя.',
            'email.required' => 'Введите электронную почту.',
            'email.email' => 'Введите корректный адрес электронной почты.',
            'email.unique' => 'Аккаунт с такой электронной почтой уже существует.',
            'password.required' => 'Введите пароль.',
            'password.confirmed' => 'Пароли не совпадают.',
            'password.min' => 'Пароль должен содержать не менее 8 символов.',
            'password.letters' => 'Пароль должен содержать хотя бы одну букву.',
            'password.numbers' => 'Пароль должен содержать хотя бы одну цифру.',
            'terms.accepted' => 'Примите условия использования и политику конфиденциальности.',
        ];
    }
}
