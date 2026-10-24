<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Enums\Client\ContactKind;
use App\Models\User\User;
use App\Support\Clients\NormaliseIdentifier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UpdateUserDetails
{
    /**
     * Change the staff record of a user. The model's own logging records what it
     * can; phone numbers are kept out of that log, so a change to one is recorded
     * as an event naming the field. A new email address ends the person's other
     * sessions, since whoever holds them was let in as someone else.
     *
     * @param  array<string, mixed>  $data  Already shape-validated (FormRequest)
     */
    public function update(User $user, array $data): User
    {
        $changedNumbers = [];

        DB::transaction(function () use ($user, $data, &$changedNumbers): void {
            $email = filled($data['email'] ?? null) ? mb_strtolower(trim($data['email'])) : $user->email;
            $emailChanged = $email !== $user->email;

            $user->fill([
                'name' => trim($data['name']),
                'email' => $email,
                'job_title' => filled($data['job_title'] ?? null) ? trim($data['job_title']) : null,
                'employee_number' => filled($data['employee_number'] ?? null) ? trim($data['employee_number']) : null,
            ]);

            foreach (['mobile', 'office_phone'] as $field) {
                $value = $data[$field] ?? null;

                if (filled($value) && NormaliseIdentifier::contact(ContactKind::Mobile, $value) !== $user->{$field}) {
                    $user->{$field} = $value;
                    $changedNumbers[] = $field;
                }
            }

            $user->save();

            // Someone editing their own address stays signed in; anyone else is signed out everywhere.
            if ($emailChanged && ! $user->is(Auth::user())) {
                DB::table('sessions')->where('user_id', $user->getKey())->delete();
                $user->forceFill(['remember_token' => null])->save();
            }
        });

        if ($changedNumbers !== []) {
            $user->recordEvent(
                'value_changed',
                sprintf('%s changed the %s of %s', Auth::user()?->name ?? 'System', str_replace('_', ' ', implode(' and ', $changedNumbers)), $user->name),
                ['field' => implode(',', $changedNumbers)],
            );
        }

        return $user;
    }
}
