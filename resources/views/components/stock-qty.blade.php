{{-- Jumlah stok dalam kemasan + satuan terkecil, mis. "1 Box + 2 Strip (120 tablet)". --}}
@props(['qty', 'drug'])
<span {{ $attributes->merge(['class' => 'nx-num']) }}>{{ app(\App\Modules\Inventory\Services\PackagingConverter::class)->format((int) $qty, $drug->packagingRows(), $drug->base_unit) }}</span>
