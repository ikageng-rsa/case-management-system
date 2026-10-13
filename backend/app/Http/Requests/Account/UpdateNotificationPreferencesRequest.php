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
     * A yes or no for each channel of each kind of notification. Both off is a
     * choice, and nothing else can be slipped in beside them.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [];

        foreach (NotificationType::cases() as $type) {
            foreach (NotificationType::channels() as $channel) {
                $rules["channels.{$type->value}.{$channel}"] = ['sometimes', 'boolean'];
            }
        }

        return $rules;
    }
}
