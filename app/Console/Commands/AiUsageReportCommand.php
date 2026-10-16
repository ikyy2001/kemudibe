<?php

namespace App\Console\Commands;

use App\Models\AiUsageLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AiUsageReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ai:usage-report {--days=7 : Jumlah hari laporan penggunaan}';

    /**
     * The console command description.
     */
    protected $description = 'Menampilkan ringkasan metrik penggunaan AI Assistant, cache hit rate, token, dan latensi';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        if ($days <= 0) {
            $days = 7;
        }

        $startDate = Carbon::now('Asia/Jakarta')->subDays($days)->startOfDay();

        $query = AiUsageLog::where('created_at', '>=', $startDate);

        $totalRequests = (clone $query)->count();
        $cacheHits = (clone $query)->where('cache_hit', true)->count();
        $cacheHitRate = $totalRequests > 0 ? round(($cacheHits / $totalRequests) * 100, 2) : 0.0;
        $totalTokens = (int) (clone $query)->sum('total_tokens');
        $avgLatency = round((float) ((clone $query)->avg('latency_ms') ?? 0), 1);
        $rateLimitedCount = (clone $query)->where('status', 'rate_limited')->count();

        $this->info("=== LAPORAN PENGGUNAAN AI ASSISTANT ({$days} HARI TERAKHIR) ===");
        $this->line("Periode        : {$startDate->format('Y-m-d H:i')} s/d Sekarang (WIB)");
        $this->line("Total Request  : {$totalRequests}");
        $this->line("Cache Hits     : {$cacheHits} ({$cacheHitRate}%)");
        $this->line("Total Token    : " . number_format($totalTokens));
        $this->line("Rata-rata Lat. : {$avgLatency} ms");
        $this->line("Jumlah 429     : {$rateLimitedCount}");
        $this->newLine();

        $topQuestions = (clone $query)
            ->select('question_hash', DB::raw('count(*) as count'))
            ->groupBy('question_hash')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $this->info("=== TOP 10 PERTANYAAN (QUESTION HASH) ===");
        if ($topQuestions->isEmpty()) {
            $this->line("Belum ada data pertanyaan tercatat.");
        } else {
            $rows = [];
            foreach ($topQuestions as $index => $row) {
                $rows[] = [
                    'Rank' => $index + 1,
                    'Question Hash' => $row->question_hash,
                    'Frekuensi' => $row->count,
                ];
            }
            $this->table(['Rank', 'Question Hash', 'Frekuensi'], $rows);
        }

        return Command::SUCCESS;
    }
}
