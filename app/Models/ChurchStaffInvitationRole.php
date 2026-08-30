<?php

namespace App\Models;

use App\Enums\ChurchRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChurchStaffInvitationRole extends Model
{
    protected $fillable = ['role'];

    protected function casts(): array
    {
        return ['role' => ChurchRole::class];
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(ChurchStaffInvitation::class, 'invitation_id');
    }
}
