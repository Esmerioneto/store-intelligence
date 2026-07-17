/**
 * Esmerio Neto
 *
 * NOTICE OF LICENSE
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future.
 *
 * @category Egsn
 * @package Egsn_StoreIntelligence
 *
 * @copyright Copyright (c) 2026 Esmerio Neto.
 *
 * @author Esmerio Neto <esmerioneto@gmail.com>
 */
define(['mage/translate'], function ($t) {
    'use strict';

    return function (config, element) {
        var btn        = element.querySelector('#si-dismiss-btn');
        var dismissUrl = element.getAttribute('data-dismiss-url');
        var formKey    = element.getAttribute('data-form-key');

        if (!btn) return;

        btn.addEventListener('click', function () {
            if (btn.disabled) return;

            var id      = btn.getAttribute('data-id');
            btn.disabled    = true;
            btn.textContent = '…';

            fetch(dismissUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'form_key=' + encodeURIComponent(formKey) + '&id=' + encodeURIComponent(id)
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    btn.textContent   = $t('Dismissed');
                    btn.style.opacity = '0.5';
                } else {
                    btn.disabled    = false;
                    btn.textContent = $t('Dismiss Recommendation');
                }
            })
            .catch(function () {
                btn.disabled    = false;
                btn.textContent = $t('Dismiss Recommendation');
            });
        });
    };
});
