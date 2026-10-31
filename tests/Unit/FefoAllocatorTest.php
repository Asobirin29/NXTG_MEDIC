<?php

namespace Tests\Unit;

use App\Modules\Inventory\Exceptions\InsufficientStock;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Services\FefoAllocator;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

/** Aturan FEFO murni (tanpa database): urutan ED, lintas batch, dan batch yang diblokir. */
class FefoAllocatorTest extends TestCase
{
    private FefoAllocator $fefo;

    private Carbon $today;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fefo = new FefoAllocator;
        $this->today = Carbon::parse('2026-10-30');
    }

    private function batch(int $id, string $expiry, int $qty, string $status = Batch::STATUS_AVAILABLE): Batch
    {
        $batch = new Batch(['batch_number' => "B{$id}", 'expiry_date' => $expiry, 'qty_on_hand' => $qty, 'status' => $status]);
        $batch->id = $id;

        return $batch;
    }

    /** @return array<string, int> batch_number => jumlah */
    private function summary(array $allocation): array
    {
        return collect($allocation)->mapWithKeys(fn ($r) => [$r['batch']->batch_number => $r['quantity']])->all();
    }

    public function test_takes_earliest_expiry_first_regardless_of_input_order(): void
    {
        $batches = [$this->batch(1, '2027-06-01', 50), $this->batch(2, '2026-12-01', 50), $this->batch(3, '2027-01-01', 50)];

        $this->assertSame(['B2' => 30], $this->summary($this->fefo->plan($batches, 30, 'X', $this->today)));
    }

    public function test_spans_batches_in_fefo_order(): void
    {
        $batches = [$this->batch(1, '2027-06-01', 100), $this->batch(2, '2026-12-01', 15), $this->batch(3, '2027-01-01', 20)];

        $this->assertSame(['B2' => 15, 'B3' => 20, 'B1' => 5], $this->summary($this->fefo->plan($batches, 40, 'X', $this->today)));
    }

    public function test_same_expiry_uses_older_batch_first(): void
    {
        $batches = [$this->batch(9, '2027-01-01', 10), $this->batch(4, '2027-01-01', 10)];

        $this->assertSame(['B4' => 10, 'B9' => 2], $this->summary($this->fefo->plan($batches, 12, 'X', $this->today)));
    }

    public function test_skips_expired_quarantined_and_empty_batches(): void
    {
        $batches = [
            $this->batch(1, '2026-10-01', 100),                          // sudah lewat ED
            $this->batch(2, '2026-10-30', 100),                          // ED hari ini → diblokir
            $this->batch(3, '2026-11-15', 100, Batch::STATUS_QUARANTINE), // karantina
            $this->batch(4, '2026-11-20', 0),                            // kosong
            $this->batch(5, '2026-12-31', 8),
        ];

        $this->assertSame(['B5' => 8], $this->summary($this->fefo->plan($batches, 8, 'X', $this->today)));
    }

    public function test_insufficient_stock_ignores_expired_quantity(): void
    {
        $batches = [$this->batch(1, '2026-10-01', 500), $this->batch(2, '2027-01-01', 5)];

        $this->expectException(InsufficientStock::class);
        $this->expectExceptionMessage('diminta 6, tersedia 5');
        $this->fefo->plan($batches, 6, 'Paracetamol', $this->today);
    }

    public function test_exact_total_is_allocated_and_quantity_must_be_positive(): void
    {
        $batches = [$this->batch(1, '2027-01-01', 3), $this->batch(2, '2027-02-01', 4)];
        $this->assertSame(7, array_sum(array_column($this->fefo->plan($batches, 7, 'X', $this->today), 'quantity')));

        $this->expectException(InvalidArgumentException::class);
        $this->fefo->plan($batches, 0, 'X', $this->today);
    }
}
