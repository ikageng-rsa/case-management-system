<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Billing;

use App\Enums\Billing\FirmAccount;
use App\Models\Billing\Disbursement;
use App\Models\Matter\Matter;
use App\Models\User\User;
use App\Services\Billing\RecordDisbursement;
use App\Services\Billing\RemoveDisbursement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecordDisbursementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('media-library.disk_name'));
    }

    public function test_it_records_what_was_paid_from_which_account_with_the_receipt(): void
    {
        $user = User::factory()->create();

        $disbursement = (new RecordDisbursement)->record(Matter::factory()->create(), [
            'incurred_on' => '2026-11-10',
            'description' => '  Medical expert report  ',
            'amount_cents' => 2000000,
            'paid_from' => FirmAccount::Trust,
        ], $user, UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf'));

        $this->assertSame('Medical expert report', $disbursement->description);
        $this->assertSame(2000000, $disbursement->amount_cents);
        $this->assertSame(FirmAccount::Trust, $disbursement->paid_from);
        $this->assertTrue($disbursement->recordedBy->is($user));
        $this->assertSame('invoice.pdf', $disbursement->receipt()->file_name);
    }

    public function test_one_recorded_in_error_is_removed_with_its_receipt(): void
    {
        $disbursement = Disbursement::factory()->create();
        $disbursement->attachReceipt(UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf'));

        (new RemoveDisbursement)->remove($disbursement);

        $this->assertModelMissing($disbursement);
        $this->assertSame(0, $disbursement->media()->count());
    }
}
