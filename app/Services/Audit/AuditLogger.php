<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Records security-sensitive administrative actions (MASTER_SPEC section 17).
 *
 * Metadata is written by callers and must never contain passwords, tokens, or
 * unnecessary personal data.
 */
class AuditLogger
{
    public function __construct(private readonly Request $request) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(string $action, ?Model $subject = null, array $metadata = [], ?User $actor = null): AuditLog
    {
        $log = new AuditLog([
            'user_id' => ($actor ?? $this->request->user())?->getKey(),
            'action' => $action,
            'metadata' => $metadata === [] ? null : $metadata,
            'ip_address' => $this->request->ip(),
            'user_agent' => substr((string) $this->request->userAgent(), 0, 512),
        ]);

        if ($subject) {
            $log->auditable()->associate($subject);
        }

        $log->save();

        return $log;
    }
}
