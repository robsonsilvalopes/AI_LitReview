@php
$containerFooter = !empty($containerNav) ? $containerNav : 'container-fluid';
@endphp

<!-- Footer-->
<footer class="content-footer footer bg-footer-theme">
    <div class="{{ $containerFooter }}">
        <div class="footer-container d-flex align-items-center justify-content-between py-4 flex-md-row flex-column">
            <div class="text-body">
                &#169; {{ date('Y') }}, made by <a href="https://deizepe.com.br/" target="_blank" rel="noopener" class="footer-link">Anderson Deizepe</a>
                · contributor: <a href="https://github.com/robsonsilvalopes" target="_blank" rel="noopener" class="footer-link">Robson Lopes</a>
            </div>
            <div class="d-none d-lg-inline-block">
                <a href="#" class="footer-link me-4" target="_blank">Documentation</a>
                <a href="#" class="footer-link d-none d-sm-inline-block" target="_blank">Support</a>
            </div>
        </div>
    </div>
</footer>
<!--/ Footer-->
