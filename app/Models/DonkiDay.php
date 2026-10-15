<?php

namespace App\Models;

use Voyager\Database\Instrument\Model;

/**
 * A day of one DONKI type, and when it was last fetched.
 */
class DonkiDay extends Model
{
    protected $fillable = ['type', 'day', 'fetched_at'];
}
