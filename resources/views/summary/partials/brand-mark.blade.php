{{--
  شعارُ الجهة في رأس الصفحة — T-90، بقرار مالك المنتج في ١١ أيلول ٢٠٢٦.

  **ويغيب مع اسم الجهة**: نمطُ نسبةٍ يُخفي الجهة (الملقي وحده، أو بلا نسبة)
  يُخفي شعارها — فالشعارُ نسبةٌ بالصورة كما الاسمُ نسبةٌ بالحرف.
  والصفحةُ بلا شعارٍ لا وسمَ فيها ولا فراغ.
--}}
@if($brand->logoDataUri && $venueMode->showsVenueBlock())
  <div class="brand-mark{{ $brand->logoTransparent ? ' no-plate' : '' }}"><img src="{{ $brand->logoDataUri }}" alt="{{ $brand->venueFull }}"></div>
@endif
