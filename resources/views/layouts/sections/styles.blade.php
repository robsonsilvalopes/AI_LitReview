<!-- BEGIN: Theme CSS-->
<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&ampdisplay=swap" rel="stylesheet" />

<!-- Fonts Icons -->
@vite(['resources/assets/vendor/fonts/iconify/iconify.css'])

<!-- BEGIN: Vendor CSS-->
@vite(['resources/assets/vendor/libs/node-waves/node-waves.scss'])


<!-- Core CSS -->
@vite(['resources/assets/vendor/scss/core.scss', 'resources/assets/css/demo.css'])

<!-- Vendor Styles -->
@vite('resources/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.scss')
@yield('vendor-style')

<!-- Page Styles -->
@yield('page-style')

<!-- app CSS -->
@vite(['resources/css/app.css'])
<!-- END: app CSS-->

<style>
  .menu-sub.menu-sub-no-bullets > .menu-item > .menu-link::before {
    display: none;
  }
  .menu-inner > .menu-item.active > .d-flex > .menu-link:not(.menu-toggle) {
    background: linear-gradient(270deg, var(--bs-primary) 0%, color-mix(in sRGB, var(--bs-primary) 52%, var(--bs-white)) 100%);
    box-shadow: var(--bs-box-shadow-sm);
    color: var(--bs-menu-active-color);
  }
</style>