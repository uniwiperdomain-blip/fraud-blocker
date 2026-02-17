(function() {
  // Tracking Pixel v2.0.0
  var PT_VERSION = '2.0.0';
  var PT_PIXEL_CODE = '{{ $pixelCode }}';
  var PT_API_BASE = '{{ $apiBase }}';
  var PT_COOKIE_NAME = 'pt_tid';
  var PT_DEBUG = typeof window !== 'undefined' && window.PixelTrackingDebug === true;

  // Utility functions
  function log(message, data) {
    if (!PT_DEBUG || !window.console) return;
    try {
      if (arguments.length > 1) {
        console.debug('[PixelTracking]', message, data || '');
      } else {
        console.debug('[PixelTracking]', message);
      }
    } catch (e) {}
  }

  function generateId() {
    return 'pt_' + Math.random().toString(36).substr(2, 9) + Date.now().toString(36);
  }

  function getCookie(name) {
    var value = "; " + document.cookie;
    var parts = value.split("; " + name + "=");
    if (parts.length === 2) {
      return parts.pop().split(";").shift();
    }
    try {
      var fallback = localStorage.getItem('pt_fallback_' + name);
      if (fallback) return fallback;
    } catch (e) {}
    return null;
  }

  function setCookie(name, value, days) {
    var expires = "";
    if (days) {
      var date = new Date();
      date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
      expires = "; expires=" + date.toUTCString();
    }
    var secure = window.location.protocol === 'https:' ? '; Secure' : '';
    var sameSite = '; SameSite=Lax';
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
      secure = '';
    }
    try {
      document.cookie = name + "=" + (value || "") + expires + "; path=/" + secure + sameSite;
      setTimeout(function() {
        if (!getCookie(name)) {
          try {
            localStorage.setItem('pt_fallback_' + name, value);
          } catch (e) {}
        }
      }, 100);
    } catch (e) {
      try {
        localStorage.setItem('pt_fallback_' + name, value);
      } catch (storageError) {}
    }
  }

  function getUrlParameter(name) {
    name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
    var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
    var results = regex.exec(window.location.search);
    return results === null ? null : decodeURIComponent(results[1].replace(/\+/g, ' '));
  }

  function getFacebookCookies() {
    return {
      fbp: getCookie('_fbp'),
      fbc: getCookie('_fbc')
    };
  }

  // Browser fingerprinting
  function generateFingerprint(callback) {
    var fingerprint = {};

    // Screen info
    fingerprint.screen = window.screen.width + 'x' + window.screen.height + 'x' + window.screen.colorDepth;

    // Timezone
    fingerprint.timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

    // Platform
    fingerprint.platform = navigator.platform;

    // Hardware concurrency
    if (navigator.hardwareConcurrency) {
      fingerprint.hardwareConcurrency = navigator.hardwareConcurrency;
    }

    // Device memory
    if (navigator.deviceMemory) {
      fingerprint.deviceMemory = navigator.deviceMemory;
    }

    // Canvas fingerprint
    try {
      var canvas = document.createElement('canvas');
      var ctx = canvas.getContext('2d');
      canvas.width = 200;
      canvas.height = 50;
      ctx.textBaseline = 'alphabetic';
      ctx.fillStyle = '#f60';
      ctx.fillRect(125, 1, 62, 20);
      ctx.fillStyle = '#069';
      ctx.font = '14px Arial';
      ctx.fillText('PixelTracking', 2, 15);
      ctx.fillStyle = 'rgba(102, 204, 0, 0.7)';
      ctx.font = '18px Times New Roman';
      ctx.fillText('Canvas FP', 4, 45);
      fingerprint.canvas = canvas.toDataURL().slice(-50);
    } catch (e) {}

    // WebGL fingerprint
    try {
      var canvas2 = document.createElement('canvas');
      var gl = canvas2.getContext('webgl') || canvas2.getContext('experimental-webgl');
      if (gl) {
        var debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
        if (debugInfo) {
          fingerprint.webgl = gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL) + '~' +
                             gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL);
        }
      }
    } catch (e) {}

    // Audio fingerprint
    try {
      var audioContext = window.OfflineAudioContext || window.webkitOfflineAudioContext;
      if (audioContext) {
        var context = new audioContext(1, 44100, 44100);
        var oscillator = context.createOscillator();
        oscillator.type = 'triangle';
        oscillator.frequency.setValueAtTime(10000, context.currentTime);
        var compressor = context.createDynamicsCompressor();
        oscillator.connect(compressor);
        compressor.connect(context.destination);
        oscillator.start(0);
        context.startRendering();
        context.oncomplete = function(event) {
          var sum = 0;
          var data = event.renderedBuffer.getChannelData(0);
          for (var i = 4500; i < 5000; i++) {
            sum += Math.abs(data[i]);
          }
          fingerprint.audio = sum.toString().slice(0, 10);
          callback(fingerprint);
        };
        return;
      }
    } catch (e) {}

    callback(fingerprint);
  }

  function sendRequest(endpoint, data, callback) {
    log('Sending request to:', PT_API_BASE + endpoint);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', PT_API_BASE + endpoint, true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.timeout = 30000;
    xhr.onreadystatechange = function() {
      if (xhr.readyState === 4) {
        if (callback) {
          if (xhr.status === 200) {
            try {
              callback(true, JSON.parse(xhr.responseText));
            } catch (e) {
              callback(false, { error: 'Invalid JSON' });
            }
          } else {
            callback(false, { error: 'HTTP ' + xhr.status });
          }
        }
      }
    };
    xhr.onerror = function() {
      if (callback) callback(false, { error: 'Network error' });
    };
    try {
      xhr.send(JSON.stringify(data));
    } catch (e) {
      if (callback) callback(false, { error: 'Send failed' });
    }
  }

  // Bot detection signals
  var botSignals = {};
  var mouseHasMoved = false;

  function collectBotSignals() {
    // WebDriver detection (Selenium, Puppeteer, Playwright)
    botSignals.webdriver = !!(navigator.webdriver);

    // Chrome-specific: real Chrome has window.chrome object
    var ua = navigator.userAgent || '';
    if (/Chrome/.test(ua) && !window.chrome) {
      botSignals.chrome_missing = true;
    }

    // Languages check (bots often have 0)
    botSignals.languages_count = (navigator.languages && navigator.languages.length) || 0;

    // Plugins check (real browsers have plugins)
    botSignals.plugins_count = (navigator.plugins && navigator.plugins.length) || 0;

    // Permission API presence
    botSignals.has_permissions = !!navigator.permissions;

    // Notification API presence
    botSignals.has_notification = !!window.Notification;

    // Mouse movement tracking
    var mouseHandler = function() {
      mouseHasMoved = true;
      botSignals.mouse_moved = true;
      document.removeEventListener('mousemove', mouseHandler);
    };
    document.addEventListener('mousemove', mouseHandler);

    // Honeypot field (invisible field that only bots fill)
    try {
      var honeypot = document.createElement('input');
      honeypot.type = 'text';
      honeypot.name = 'pt_hp_field';
      honeypot.id = 'pt_hp_field';
      honeypot.style.cssText = 'position:absolute;left:-9999px;top:-9999px;opacity:0;height:0;width:0;overflow:hidden;';
      honeypot.tabIndex = -1;
      honeypot.autocomplete = 'off';
      if (document.body) {
        document.body.appendChild(honeypot);
      }
    } catch (e) {}

    // JS challenge: requestAnimationFrame timing
    try {
      var challengeStart = performance.now();
      requestAnimationFrame(function() {
        var elapsed = performance.now() - challengeStart;
        botSignals.raf_timing = Math.round(elapsed);
        botSignals.js_challenge_passed = (elapsed > 1 && elapsed < 200);
      });
    } catch (e) {
      botSignals.js_challenge_passed = false;
    }
  }

  function finalizeBotSignals() {
    // Check honeypot at send time
    try {
      var hp = document.getElementById('pt_hp_field');
      if (hp && hp.value && hp.value.length > 0) {
        botSignals.honeypot_filled = true;
      } else {
        botSignals.honeypot_filled = false;
      }
    } catch (e) {}

    botSignals.mouse_moved = mouseHasMoved;
    return botSignals;
  }

  // Device detection
  var isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
  var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
  var isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);

  // Initialize tracking cookie
  var cookieId = getCookie(PT_COOKIE_NAME);
  if (!cookieId) {
    try {
      var localId = localStorage.getItem('pt_fallback_' + PT_COOKIE_NAME);
      if (localId) {
        cookieId = localId;
        setCookie(PT_COOKIE_NAME, cookieId, 365);
      } else {
        cookieId = generateId();
        setCookie(PT_COOKIE_NAME, cookieId, 365);
        try {
          localStorage.setItem('pt_fallback_' + PT_COOKIE_NAME, cookieId);
        } catch (e) {}
      }
    } catch (e) {
      cookieId = generateId();
      setCookie(PT_COOKIE_NAME, cookieId, 365);
    }
  }

  // Form tracking helpers
  function getFormIdentifier(form) {
    var actualForm = form;
    if (form.tagName !== 'FORM') {
      var realForm = form.querySelector('form');
      if (realForm) actualForm = realForm;
    }
    return actualForm.id ||
           actualForm.getAttribute('data-form-id') ||
           form.getAttribute('data-form-id') ||
           actualForm.name ||
           form.name ||
           'form-' + Date.now();
  }

  function extractFormFields(form) {
    var inputs = form.querySelectorAll('input, select, textarea');
    var fields = {};
    for (var i = 0; i < inputs.length; i++) {
      var input = inputs[i];
      var name = input.name || input.id || input.getAttribute('data-name') || input.getAttribute('aria-label');
      var value = input.value;
      var type = input.type || 'text';
      if (type === 'password' || type === 'hidden') continue;
      if (!name) continue;
      var lowerName = name.toLowerCase();
      var isContactField = (
        lowerName.includes('email') || lowerName.includes('phone') || lowerName.includes('tel') ||
        lowerName.includes('name') || lowerName.includes('company')
      );
      if (isContactField || (value && value.trim().length > 0)) {
        fields[name] = value ? value.substring(0, 100) : '';
      }
    }
    return fields;
  }

  var lastFormSubmission = {};

  function trackFormData(form, triggerType) {
    var formId = getFormIdentifier(form);
    var now = Date.now();
    var lastSubmit = lastFormSubmission[formId] || 0;
    if (now - lastSubmit < 2000) return;
    lastFormSubmission[formId] = now;

    var formData = {
      pixelCode: PT_PIXEL_CODE,
      cookieId: cookieId,
      url: window.location.href,
      formId: formId,
      formAction: form.action || window.location.href,
      triggerType: triggerType,
      fields: extractFormFields(form)
    };

    if (Object.keys(formData.fields).length === 0 && triggerType !== 'button_click') return;

    sendRequest('/form', formData, function(success, response) {
      log(success ? 'Form tracked' : 'Form tracking failed', response);
    });
  }

  // Page view tracking
  function trackPageView() {
    generateFingerprint(function(fingerprint) {
      setTimeout(function() {
        var fbCookies = getFacebookCookies();
        var pageData = {
          pixelCode: PT_PIXEL_CODE,
          cookieId: cookieId,
          url: window.location.href,
          urlPath: window.location.pathname,
          title: document.title,
          referrer: document.referrer,
          screenWidth: window.screen ? window.screen.width : null,
          screenHeight: window.screen ? window.screen.height : null,
          viewport: window.innerWidth + 'x' + window.innerHeight,
          isMobile: isMobile,
          isIOS: isIOS,
          isSafari: isSafari,
          utm_source: getUrlParameter('utm_source'),
          utm_medium: getUrlParameter('utm_medium'),
          utm_campaign: getUrlParameter('utm_campaign'),
          utm_content: getUrlParameter('utm_content'),
          utm_term: getUrlParameter('utm_term'),
          fbclid: getUrlParameter('fbclid'),
          gclid: getUrlParameter('gclid'),
          ttclid: getUrlParameter('ttclid'),
          fbp: fbCookies.fbp,
          fbc: fbCookies.fbc,
          campaign_id: getUrlParameter('campaign_id'),
          ad_id: getUrlParameter('ad_id'),
          h_ad_id: getUrlParameter('h_ad_id'),
          email: getUrlParameter('email') || getUrlParameter('contact_email'),
          phone: getUrlParameter('phone') || getUrlParameter('contact_phone'),
          fingerprint: fingerprint,
          botSignals: finalizeBotSignals()
        };

        sendRequest('/pageview', pageData, function(success, response) {
          log(success ? 'Page view tracked' : 'Page view failed', response);
        });
      }, 1500);
    });
  }

  // Click tracking
  function trackClicks() {
    document.addEventListener('click', function(event) {
      var element = event.target;
      if (!element) return;

      var isTrackable = element.tagName === 'BUTTON' ||
                       element.tagName === 'A' ||
                       element.type === 'submit' ||
                       (element.className && (element.className.includes('btn') || element.className.includes('cta')));

      var parentForm = element.closest('form, [data-form-id], .form-container');

      if (parentForm && (element.tagName === 'BUTTON' || element.type === 'submit' ||
          (element.className && element.className.includes('btn')))) {
        setTimeout(function() {
          trackFormData(parentForm, 'button_click');
        }, 50);
      }

      if (isTrackable) {
        var clickData = {
          pixelCode: PT_PIXEL_CODE,
          cookieId: cookieId,
          url: window.location.href,
          elementType: element.tagName.toLowerCase(),
          elementText: element.innerText || element.textContent || '',
          elementId: element.id || '',
          elementClass: element.className || '',
          elementHref: element.href || '',
          isFormButton: !!parentForm
        };

        sendRequest('/click', clickData, function(success, response) {
          log(success ? 'Click tracked' : 'Click failed', response);
        });
      }
    }, true);
  }

  // Form submission tracking
  function trackFormSubmissions() {
    document.addEventListener('submit', function(event) {
      var form = event.target;
      if (!form || form.tagName !== 'FORM') return;
      trackFormData(form, 'standard_submit');
    }, true);

    // Watch for dynamically added forms
    if (window.MutationObserver) {
      var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
          if (mutation.type === 'childList') {
            mutation.addedNodes.forEach(function(node) {
              if (node.nodeType === 1) {
                var forms = node.tagName === 'FORM' ? [node] : (node.querySelectorAll ? node.querySelectorAll('form') : []);
                forms.forEach(function(form) {
                  if (!form.getAttribute('data-pt-tracked')) {
                    form.setAttribute('data-pt-tracked', 'true');
                    var buttons = form.querySelectorAll('button, input[type="submit"]');
                    buttons.forEach(function(btn) {
                      btn.addEventListener('click', function() {
                        setTimeout(function() { trackFormData(form, 'button_click'); }, 50);
                      });
                    });
                  }
                });
              }
            });
          }
        });
      });
      observer.observe(document.body, { childList: true, subtree: true });
    }
  }

  // Engagement tracking
  var startTime = Date.now();
  var maxScrollDepth = 0;

  function calculateScrollDepth() {
    var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    var docHeight = document.documentElement.scrollHeight - window.innerHeight;
    if (docHeight > 0) {
      var depth = Math.round((scrollTop / docHeight) * 100);
      maxScrollDepth = Math.max(maxScrollDepth, Math.min(depth, 100));
    }
  }

  function sendEngagementData() {
    var data = {
      pixelCode: PT_PIXEL_CODE,
      cookieId: cookieId,
      url: window.location.href,
      timeOnPage: Math.round((Date.now() - startTime) / 1000),
      scrollDepth: maxScrollDepth,
      mouseMovement: mouseHasMoved
    };

    if (navigator.sendBeacon) {
      try {
        navigator.sendBeacon(PT_API_BASE + '/engagement', JSON.stringify(data));
      } catch (e) {}
    } else {
      var xhr = new XMLHttpRequest();
      xhr.open('POST', PT_API_BASE + '/engagement', false);
      xhr.setRequestHeader('Content-Type', 'application/json');
      try { xhr.send(JSON.stringify(data)); } catch (e) {}
    }
  }

  // Initialize
  function init() {
    try {
      collectBotSignals();
      trackPageView();
      trackClicks();
      trackFormSubmissions();

      window.addEventListener('scroll', calculateScrollDepth, { passive: true });
      window.addEventListener('beforeunload', sendEngagementData);
      window.addEventListener('pagehide', sendEngagementData);
      window.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden') sendEngagementData();
      });

      log('Tracking initialized');
    } catch (e) {
      log('Init error:', e.message);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Public API
  window.PixelTracking = {
    version: PT_VERSION,
    pixelCode: PT_PIXEL_CODE,
    cookieId: cookieId,
    trackEvent: function(eventName, eventData) {
      sendRequest('/event', {
        pixelCode: PT_PIXEL_CODE,
        cookieId: cookieId,
        url: window.location.href,
        eventName: eventName,
        eventData: eventData || {}
      }, function(success) {
        log(success ? 'Event tracked' : 'Event failed');
      });
    },
    identify: function(contactInfo, userData) {
      var email = typeof contactInfo === 'string' ? contactInfo : contactInfo.email;
      var phone = typeof contactInfo === 'object' ? contactInfo.phone : null;
      sendRequest('/identify', {
        pixelCode: PT_PIXEL_CODE,
        cookieId: cookieId,
        email: email || null,
        phone: phone || null,
        userData: userData || {}
      }, function(success) {
        log(success ? 'User identified' : 'Identify failed');
      });
    },
    trackForm: function(selector) {
      var form = document.querySelector(selector);
      if (form) {
        trackFormData(form, 'manual');
        return true;
      }
      return false;
    },
    getTrackingData: function() {
      return {
        pixelCode: PT_PIXEL_CODE,
        cookieId: cookieId,
        timeOnPage: Math.round((Date.now() - startTime) / 1000),
        scrollDepth: maxScrollDepth
      };
    }
  };
})();
