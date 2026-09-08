<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeploymentRun extends Model
{
    use HasFactory;

    public const ACTIVE_STATUSES = ['preparing', 'ready', 'queued', 'running'];

    protected $fillable = [
        'user_id',
        'store_id',
        'status',
        'phase',
        'commit_message',
        'prepared_fingerprint',
        'prepared_branch',
        'prepared_head_sha',
        'prepared_remote_sha',
        'commit_sha',
        'previous_sha',
        'output',
        'error',
        'exit_code',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
