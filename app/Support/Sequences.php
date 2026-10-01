<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Gapless counters, e.g. admission and (later) receipt numbers.
 *
 * The counter row is locked FOR UPDATE inside the caller's transaction, so
 * concurrent callers queue up and a rolled-back transaction gives its
 * number back. Auto-increment ids cannot guarantee that.
 */
class Sequences
{
    public function next(string $key): int
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Sequences::next() must run inside the transaction that uses the number.');
        }

        DB::table('sequences')->insertOrIgnore(['key' => $key, 'next_value' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $row = DB::table('sequences')->where('key', $key)->lockForUpdate()->first();

        DB::table('sequences')->where('id', $row->id)->update(['next_value' => $row->next_value + 1, 'updated_at' => now()]);

        return (int) $row->next_value;
    }
}
