<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class LibreriaModel extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
