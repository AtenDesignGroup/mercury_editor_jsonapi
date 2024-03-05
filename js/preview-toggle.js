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
        const toggle = document.createElement('button');
        toggle.classList.add('me-toolbar__screen-control');
        toggle.innerHTML = 'Toggle Preview';
        toggle.addEventListener('click', () => {
          const drupalUrl = url;
          // const decoupledUrl = drupalSettings.mercury_editor_jsonapi.decoupledPreviewUrl;
          const decoupledUrl = url
            .replace('http://quadient-drupal.lndo.site/en/node/', 'http://localhost:3000/en/mercury-editor/node/page/')
            .replace('/mercury-editor-preview', '');
          const newUrl = iframe.src === drupalUrl ? decoupledUrl : drupalUrl;
          iframe.src = newUrl;
        });
        // insert toggle before controls
        container.insertBefore(toggle, controls);
      });
    }
  };
})(Drupal, once, drupalSettings);