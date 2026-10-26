<?php

namespace Cabuz\SafeMode\Tests\Fixtures;

use Cabuz\SafeMode\Masking\MasksAttributes;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use MasksAttributes;

    protected $guarded = [];

    public $timestamps = true;

    protected array $maskedAttributes = [
        'email' => 'email',
    ];
}
