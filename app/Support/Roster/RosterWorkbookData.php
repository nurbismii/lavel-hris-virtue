<?php

namespace App\Support\Roster;

use Illuminate\Support\Collection;

final class RosterWorkbookData
{
    public function __construct(
        public readonly string $sheetName,
        public readonly array $columns,
        public readonly Collection $rows
    ) {
    }
}
