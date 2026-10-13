<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Enums\Notification\NotificationType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    protected $errorBag = 'notifications';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * One yes or no for the email of each kind of notification. A box left
     * unticked is not sent at all, so an absent key means no.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return collect(NotificationType::cases())
            ->mapWithKeys(fn (NotificationType $type) => ['email.'.$type->value => ['sometimes', 'boolean']])
            ->all();
    }
}
