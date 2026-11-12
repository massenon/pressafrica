<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $guarded = [];

    protected $casts = ['consent_at' => 'datetime', 'replied_at' => 'datetime'];

    public function name(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
