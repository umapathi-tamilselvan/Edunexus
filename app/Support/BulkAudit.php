<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Audit rows for changes made with bulk queries, which fire no model
 * events. They match what owen-it/laravel-auditing writes for a single
 * model: the user, old and new values, URL, IP address, user agent and
 * time, through the same resolvers.
 */
final class BulkAudit
{
    /**
     * @param  class-string<Model&Auditable>  $model
     * @param  list<array{id: int, event: 'created'|'updated'|'deleted', old: array<string, mixed>, new: array<string, mixed>}>  $changes
     */
    public static function record(string $model, array $changes): void
    {
        if ($changes === []) {
            return;
        }

        $auditable = new $model;
        $user = config('audit.user.resolver')::resolve();
        $prefix = config('audit.user.morph_prefix', 'user');
        $now = now();

        $shared = [
            "{$prefix}_type" => $user?->getMorphClass(),
            "{$prefix}_id" => $user?->getAuthIdentifier(),
            'auditable_type' => $auditable->getMorphClass(),
            'url' => config('audit.resolvers.url')::resolve($auditable),
            'ip_address' => config('audit.resolvers.ip_address')::resolve($auditable),
            'user_agent' => config('audit.resolvers.user_agent')::resolve($auditable),
            'tags' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        DB::table(config('audit.drivers.database.table', 'audits'))->insert(array_map(fn (array $change): array => [
            ...$shared,
            'event' => $change['event'],
            'auditable_id' => $change['id'],
            'old_values' => json_encode($change['old']),
            'new_values' => json_encode($change['new']),
        ], $changes));
    }
}
