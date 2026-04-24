/**
 * OFAC Bubble Popup
 *
 * Shows a small message above the chatbot trigger after a configurable delay.
 * Dismissable and persisted per-session via sessionStorage.
 *
 * @package Ocade_Fusion_AnythingLLM_Chatbot
 * @since 1.2.0
 */
(function () {
    'use strict';

    var cfg = window.ofacBubblePopup;
    if (!cfg || !cfg.storageKey) return;

    var storageKey = cfg.storageKey;
    var delay = Math.max(0, parseInt(cfg.delay, 10) || 0) * 1000;

    function init() {
        var popup = document.getElementById('ofac-bubble-popup');
        if (!popup) return;

        try {
            if (sessionStorage.getItem(storageKey)) return;
        } catch (e) {
            // sessionStorage unavailable (private mode, etc.) — show popup anyway
        }

        var timerId = null;

        var hide = function () {
            if (timerId) { clearTimeout(timerId); timerId = null; }
            popup.classList.remove('ofac-bubble-popup--visible');
            popup.setAttribute('hidden', '');
            try { sessionStorage.setItem(storageKey, '1'); } catch (e) {}
        };

        timerId = setTimeout(function () {
            timerId = null;
            popup.removeAttribute('hidden');
            requestAnimationFrame(function () {
                popup.classList.add('ofac-bubble-popup--visible');
            });
        }, delay);

        var closeBtn = popup.querySelector('.ofac-bubble-popup__close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                hide();
            }, { once: true });
        }

        var trigger = document.getElementById('ofac-trigger');
        if (trigger) {
            trigger.addEventListener('click', hide, { once: true });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
