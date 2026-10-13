<?php

declare(strict_types=1);

namespace App\Enums\Matter;

use Illuminate\Support\Str;

/** The part someone other than the client plays in a matter. */
enum PartyRole: string
{
    case Applicant = 'applicant';
    case Respondent = 'respondent';
    case Plaintiff = 'plaintiff';
    case Defendant = 'defendant';
    case Accused = 'accused';
    case Complainant = 'complainant';
    case Counsel = 'counsel';
    case Witness = 'witness';
    case ThirdParty = 'third_party';
    case Other = 'other';

    public function label(): string
    {
        return Str::headline($this->value);
    }
}
