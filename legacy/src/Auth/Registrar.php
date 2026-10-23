<?php

declare(strict_types=1);

namespace Geoapi\Auth;

final class Registrar
{
    public function __construct(private UserRepository $users)
    {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok:bool, errors:array<string, string>, id?:int}
     */
    public function register(array $input): array
    {
        $type = (string) ($input['type'] ?? '');
        if (!in_array($type, ['individual', 'company'], true)) {
            return ['ok' => false, 'errors' => ['type' => 'აირჩიეთ ფიზიკური ან იურიდიული პირი']];
        }

        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        $phone = preg_replace('/[\s\-()]/', '', trim((string) ($input['phone'] ?? ''))) ?? '';
        $password = (string) ($input['password'] ?? '');
        $confirm = (string) ($input['password_confirmation'] ?? '');

        $errors = [];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'ელფოსტა არასწორია';
        } elseif ($this->users->emailTaken($email)) {
            $errors['email'] = 'ეს ელფოსტა უკვე რეგისტრირებულია';
        }

        if (!preg_match('/^\+?[0-9]{9,15}$/', $phone)) {
            $errors['phone'] = 'ტელეფონი შეიყვანეთ ციფრებით, მაგალითად 555123456';
        }

        if (mb_strlen($password) < 8) {
            $errors['password'] = 'პაროლი უნდა იყოს მინიმუმ 8 სიმბოლო';
        } elseif ($password !== $confirm) {
            $errors['password_confirmation'] = 'პაროლები არ ემთხვევა';
        }

        $fields = [
            'first_name' => null,
            'last_name' => null,
            'personal_number' => null,
            'company_name' => null,
            'identification_code' => null,
            'representative_name' => null,
        ];

        if ($type === 'individual') {
            $fields['first_name'] = self::clean((string) ($input['first_name'] ?? ''));
            $fields['last_name'] = self::clean((string) ($input['last_name'] ?? ''));
            $fields['personal_number'] = preg_replace('/\s+/', '', (string) ($input['personal_number'] ?? '')) ?? '';

            if (!self::isName($fields['first_name'])) {
                $errors['first_name'] = 'სახელი სავალდებულოა';
            }
            if (!self::isName($fields['last_name'])) {
                $errors['last_name'] = 'გვარი სავალდებულოა';
            }
            if (!preg_match('/^[0-9]{11}$/', $fields['personal_number'])) {
                $errors['personal_number'] = 'პირადი ნომერი უნდა იყოს 11 ციფრი';
            } elseif ($this->users->personalNumberTaken($fields['personal_number'])) {
                $errors['personal_number'] = 'ეს პირადი ნომერი უკვე რეგისტრირებულია';
            }
        } else {
            $fields['company_name'] = self::clean((string) ($input['company_name'] ?? ''));
            $fields['identification_code'] = preg_replace('/\s+/', '', (string) ($input['identification_code'] ?? '')) ?? '';
            $fields['representative_name'] = self::clean((string) ($input['representative_name'] ?? ''));

            if (!preg_match('/^[\p{L}\p{N}\s.\'"„“”«»()\-]{2,160}$/u', $fields['company_name'])) {
                $errors['company_name'] = 'კომპანიის სახელწოდება სავალდებულოა';
            }
            if (!preg_match('/^[0-9]{9}$/', $fields['identification_code'])) {
                $errors['identification_code'] = 'საიდენტიფიკაციო კოდი უნდა იყოს 9 ციფრი';
            } elseif ($this->users->identificationCodeTaken($fields['identification_code'])) {
                $errors['identification_code'] = 'ეს საიდენტიფიკაციო კოდი უკვე რეგისტრირებულია';
            }
            if (!self::isName($fields['representative_name'])) {
                $errors['representative_name'] = 'წარმომადგენლის სახელი და გვარი სავალდებულოა';
            }
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $id = $this->users->insert([
            'type' => $type,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'phone' => $phone,
            'first_name' => $fields['first_name'],
            'last_name' => $fields['last_name'],
            'personal_number' => $fields['personal_number'],
            'company_name' => $fields['company_name'],
            'identification_code' => $fields['identification_code'],
            'representative_name' => $fields['representative_name'],
        ]);

        return ['ok' => true, 'errors' => [], 'id' => $id];
    }

    private static function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    private static function isName(string $value): bool
    {
        return (bool) preg_match('/^[\p{L}\s\-\']{2,80}$/u', $value);
    }
}
