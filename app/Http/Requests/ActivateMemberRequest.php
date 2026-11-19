<?php

namespace App\Http\Requests;

use App\Services\MemberActivationService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\Password;

class ActivateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! auth()->check() && app(MemberActivationService::class)->resolve((string) $this->route('token')) !== null;
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->view('auth.activation-unavailable', [], 410));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(10)->letters()->numbers(),
                function (string $attribute, mixed $value, Closure $fail) {
                    if (is_string($value) && (strlen($value) > 72 || str_contains($value, "\0"))) {
                        $fail('Password maksimal 72 byte dan tidak boleh mengandung karakter null.');
                    }
                }],
        ];
    }
}
