<?php

namespace Tests\Unit;

use App\Modules\Inventory\Services\PackagingConverter;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PackagingConverterTest extends TestCase
{
    private PackagingConverter $converter;

    private const TABLET = [
        ['unit_name' => 'Tablet', 'quantity_in_base' => 1],
        ['unit_name' => 'Strip', 'quantity_in_base' => 10],
        ['unit_name' => 'Box', 'quantity_in_base' => 100],
    ];

    protected function setUp(): void
    {
        $this->converter = new PackagingConverter;
    }

    public function test_to_base_multiplies(): void
    {
        $this->assertSame(20, $this->converter->toBase(2, 10));
        $this->assertSame(0, $this->converter->toBase(0, 100));
        $this->assertSame(7, $this->converter->toBase(7, 1));
    }

    public function test_to_base_rejects_negative_quantity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->converter->toBase(-1, 10);
    }

    public function test_to_base_rejects_zero_package_size(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->converter->toBase(1, 0);
    }

    public function test_to_base_rejects_overflow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->converter->toBase(PHP_INT_MAX, 2);
    }

    public function test_to_base_by_unit_is_case_insensitive(): void
    {
        $this->assertSame(300, $this->converter->toBaseByUnit(self::TABLET, 'box', 3));
        $this->assertSame(10, $this->converter->toBaseByUnit(self::TABLET, 'STRIP', 1));
    }

    public function test_to_base_by_unknown_unit_fails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->converter->toBaseByUnit(self::TABLET, 'Botol', 1);
    }

    public static function breakdowns(): array
    {
        return [
            'campuran' => [125, [['unit_name' => 'Box', 'quantity' => 1], ['unit_name' => 'Strip', 'quantity' => 2], ['unit_name' => 'Tablet', 'quantity' => 5]]],
            'pas box' => [200, [['unit_name' => 'Box', 'quantity' => 2]]],
            'hanya tablet' => [9, [['unit_name' => 'Tablet', 'quantity' => 9]]],
            'nol' => [0, []],
        ];
    }

    #[DataProvider('breakdowns')]
    public function test_breakdown(int $base, array $expected): void
    {
        $this->assertSame($expected, $this->converter->breakdown($base, self::TABLET));
    }

    public function test_breakdown_is_order_independent(): void
    {
        $this->assertSame(
            $this->converter->breakdown(125, self::TABLET),
            $this->converter->breakdown(125, array_reverse(self::TABLET)),
        );
    }

    public function test_breakdown_with_non_divisible_levels(): void
    {
        // 1 Box = 12 Botol @60 ml (720 ml); 1 Botol = 60 ml.
        $syrup = [['unit_name' => 'ml', 'quantity_in_base' => 1], ['unit_name' => 'Botol', 'quantity_in_base' => 60], ['unit_name' => 'Box', 'quantity_in_base' => 720]];

        $this->assertSame(
            [['unit_name' => 'Box', 'quantity' => 1], ['unit_name' => 'Botol', 'quantity' => 1], ['unit_name' => 'ml', 'quantity' => 5]],
            $this->converter->breakdown(785, $syrup),
        );
    }

    public function test_breakdown_requires_base_unit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->converter->breakdown(15, [['unit_name' => 'Strip', 'quantity_in_base' => 10]]);
    }

    public function test_breakdown_rejects_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->converter->breakdown(-5, self::TABLET);
    }

    public function test_format(): void
    {
        $this->assertSame('1 Box + 2 Strip + 5 Tablet (125 tablet)', $this->converter->format(125, self::TABLET, 'tablet'));
        $this->assertSame('3 Tablet', $this->converter->format(3, self::TABLET, 'tablet'));
        $this->assertSame('1 Strip (10 tablet)', $this->converter->format(10, self::TABLET, 'tablet'));
        $this->assertSame('0 tablet', $this->converter->format(0, self::TABLET, 'tablet'));
    }

    public function test_validate_accepts_valid_structure(): void
    {
        $this->assertSame([], $this->converter->validate(self::TABLET));
    }

    public function test_validate_requires_base_unit(): void
    {
        $errors = $this->converter->validate([['unit_name' => 'Strip', 'quantity_in_base' => 10]]);
        $this->assertContains('Wajib ada kemasan satuan terkecil dengan isi 1.', $errors);
    }

    public function test_validate_rejects_empty(): void
    {
        $this->assertNotEmpty($this->converter->validate([]));
    }

    public function test_validate_rejects_duplicates_and_bad_values(): void
    {
        $errors = $this->converter->validate([
            ['unit_name' => 'Tablet', 'quantity_in_base' => 1],
            ['unit_name' => 'tablet', 'quantity_in_base' => 5],
            ['unit_name' => 'Strip', 'quantity_in_base' => 5],
            ['unit_name' => '', 'quantity_in_base' => 2],
            ['unit_name' => 'Box', 'quantity_in_base' => 2.5],
            ['unit_name' => 'Dus', 'quantity_in_base' => 0],
        ]);

        $this->assertCount(5, $errors);
    }
}
