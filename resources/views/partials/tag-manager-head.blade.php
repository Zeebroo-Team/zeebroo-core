{{-- Google Tag Manager: as high in <head> as possible. Pair with partials.tag-manager-body. --}}
@php($gtmId = app(\App\Services\TagManagerService::class)->containerId())
@if($gtmId)
<!-- Google Tag Manager -->
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',@json($gtmId));
</script>
<!-- End Google Tag Manager -->
@endif
