<?php

namespace Src\Models;

class Department extends Model
{
    protected string $table = 'departments';

    protected array $fillable = [
        'name',
        'code',
        'manager_id',
    ];
}
