<?php

namespace App\Models;

use Voyager\Database\Instrument\Model;

/**
 * One DONKI record as fetched (DonkiRecords::encode()), under its type and key.
 */
class DonkiRecord extends Model
{
    protected $fillable = ['type', 'record_key', 'event_time', 'record'];
}
