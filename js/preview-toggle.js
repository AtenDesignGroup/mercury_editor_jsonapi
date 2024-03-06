/**
 * @file
 * Preview toggle.
 *
 * Adds a control to switch between the Drupal and decoupled previews.
 */

(function (Drupal, once, drupalSettings) {
  Drupal.behaviors.mePreviewToggle = {
    attach: function (context, settings) {
      const toolbar = once('me-toolbar-preview-toggle', '#me-toolbar', context);
      toolbar.forEach(container => {
        const controls = container.querySelector('.me-toolbar__screen-controls');
        const iframe = document.getElementById('me-preview');

        if (!controls || !iframe) {
          return;
        }

        const url = iframe.src;
        const drupalUrl = new URL(url);
        const toggle = document.createElement('button');
        toggle.classList.add('me-button--secondary');
        toggle.innerHTML = 'Toggle Preview';
        toggle.addEventListener('click', () => {
          const uuid = drupalUrl.pathname.replace('/mercury-editor-preview', '').split('/').pop();
          const decoupledUrl = new URL(url);
          decoupledUrl.pathname = `/en/mercury-editor/node/page/${uuid}`;
          decoupledUrl.host = 'localhost:3000';

          const newUrl = iframe.src === drupalUrl.toString() ? decoupledUrl.toString() : drupalUrl.toString();
          iframe.src = newUrl;
        });
        // insert toggle before controls
        container.insertBefore(toggle, controls);
      });
    }
  };
})(Drupal, once, drupalSettings);