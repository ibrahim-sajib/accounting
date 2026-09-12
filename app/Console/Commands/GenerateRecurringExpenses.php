<?php

namespace App\Console\Commands;

use App\Domain\Expense\Services\ExpenseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateRecurringExpenses extends Command
{
    protected $signature = 'expenses:generate-recurring';

    protected $description = 'Generate the next draft copy of every posted recurring expense whose next run date has arrived.';

    public function handle(ExpenseService $service): int
    {
        $result = $service->generateRecurringDrafts();

        Log::channel('stack')->info('Recurring expenses generated.', $result);

        $this->info("Recurring expense drafts generated: {$result['created']}");

        return self::SUCCESS;
    }
}