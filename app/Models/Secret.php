<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;

class Secret extends Model
{
  use Uuids;

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = [
    'content', 'expires'
  ];

  /**
   * The attributes excluded from the model's JSON form.
   *
   * @var array
   */
  protected $hidden = [];
}
