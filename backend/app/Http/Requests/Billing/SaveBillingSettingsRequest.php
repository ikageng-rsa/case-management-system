<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Enums\Billing\BillingTiming;
use App\Enums\Billing\DrawingFeeBase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBillingSettingsRequest extends FormRequest
{
    protected $errorBag = 'billing_settings';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'contingency_percent' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
            'estate_percent' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
            'drawing_fee_percent' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
            'drawing_fee_base' => ['required', Rule::enum(DrawingFeeBase::class)],
            'billing_timing' => ['required', Rule::enum(BillingTiming::class)],
        ];
    }

    /**
     * @return array{contingencyPercent: float, estatePercent: float, drawingFeePercent: float, drawingFeeBase: DrawingFeeBase, billingTiming: BillingTiming}
     */
    public function settings(): array
    {
        return [
            'contingencyPercent' => (float) $this->validated('contingency_percent'),
            'estatePercent' => (float) $this->validated('estate_percent'),
            'drawingFeePercent' => (float) $this->validated('drawing_fee_percent'),
            'drawingFeeBase' => DrawingFeeBase::from($this->validated('drawing_fee_base')),
            'billingTiming' => BillingTiming::from($this->validated('billing_timing')),
        ];
    }
}
