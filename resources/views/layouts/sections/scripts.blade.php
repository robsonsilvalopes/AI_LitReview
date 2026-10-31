<!-- BEGIN: Vendor JS-->

@vite(['resources/assets/vendor/libs/jquery/jquery.js', 'resources/assets/vendor/libs/popper/popper.js', 'resources/assets/vendor/js/bootstrap.js', 'resources/assets/vendor/libs/node-waves/node-waves.js'])

@vite(['resources/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js', 'resources/assets/vendor/js/menu.js'])

@yield('vendor-script')
<!-- END: Page Vendor JS-->
<!-- BEGIN: Theme JS-->
@vite(['resources/assets/js/main.js'])

<!-- END: Theme JS-->
<!-- Toast Script -->
@stack('toast-script')
<!-- END: Toast Script -->
<!-- Profile Modal Script -->
@stack('profile-modal-script')
<!-- END: Profile Modal Script -->
<!-- Pricing Modal JS-->
@stack('pricing-script')
<!-- END: Pricing Modal JS-->
<!-- Create Project Modal JS-->
@stack('create-project-script')
<!-- END: Create Project Modal JS-->
<!-- Keep Projects Menu Always Open -->
<script>
(function() {
  'use strict';
  
  function keepProjectsMenuOpen() {
    // Find Projects menu item and keep it always open
    const projectsMenuItems = document.querySelectorAll('#layout-menu .menu-item');
    projectsMenuItems.forEach(function(menuItem) {
      const menuLink = menuItem.querySelector('.menu-link');
      if (menuLink && menuLink.textContent.trim().includes('Projects')) {
        const submenu = menuItem.querySelector('.menu-sub');
        if (submenu && submenu.children.length > 0) {
          // Always keep Projects menu open
          menuItem.classList.add('open');
          
          // Prevent toggle when clicking on Projects menu link
          menuLink.addEventListener('click', function(e) {
            // Check if click is on the + button - allow that to work
            const addButton = menuItem.querySelector('[data-bs-target="#createProjectModal"]');
            if (addButton && (addButton === e.target || addButton.contains(e.target))) {
              return; // Allow + button to work
            }
            
            // Prevent toggle for the menu link itself
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            // Ensure it stays open
            menuItem.classList.add('open');
            return false;
          }, true); // Use capture phase to intercept early
          
          // Override the menu toggle function for this specific item
          if (window.Helpers && window.Helpers.mainMenu) {
            const originalToggle = window.Helpers.mainMenu.toggle;
            window.Helpers.mainMenu.toggle = function(el, closeChildren) {
              const item = Menu._getItem(el, true);
              // If this is the Projects menu item, don't allow closing
              if (item === menuItem && item.classList.contains('open')) {
                return; // Don't toggle - keep it open
              }
              // Otherwise, use original toggle
              return originalToggle.call(this, el, closeChildren);
            };
          }
          
          // Monitor for any attempts to close and reopen immediately
          const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
              if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                if (!menuItem.classList.contains('open') && submenu.children.length > 0) {
                  // Use setTimeout to avoid infinite loop
                  setTimeout(function() {
                    menuItem.classList.add('open');
                  }, 0);
                }
              }
            });
          });
          
          observer.observe(menuItem, {
            attributes: true,
            attributeFilter: ['class']
          });
        }
      }
    });
  }
  
  // Run immediately and also after DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', keepProjectsMenuOpen);
  } else {
    keepProjectsMenuOpen();
  }
  
  // Also run after menu is initialized (menu.js runs after DOMContentLoaded)
  setTimeout(keepProjectsMenuOpen, 100);
  setTimeout(keepProjectsMenuOpen, 500);
})();
</script>
<!-- END: Keep Projects Menu Always Open -->
<!-- BEGIN: Page JS-->
@yield('page-script')
<!-- END: Page JS-->

<!-- app JS -->
@vite(['resources/js/app.js'])
<!-- END: app JS-->
