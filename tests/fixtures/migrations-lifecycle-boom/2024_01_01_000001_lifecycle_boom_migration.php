<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        throw new RuntimeException('migration exploded');
    }

    public function down(): void
    {
        // nothing
    }
};
