/**
 * voice-engine.js — S95.WIZARD.PART1_2 — Orchestrator over existing wizMic() flow.
 *
 * Закон №1: Пешо НЕ пише. Voice-first wizard.
 *
 * Architecture (Option Y, per Тихол decision):
 *   Existing wizMic / _wizMicApply / _bgPrice / wizHighlightNext / parseVoiceToFields /
 *   handleVoiceStep / openVoiceWizard / voiceForStep stay UNTOUCHED. VoiceEngine is a
 *   thin layer adding 4 capabilities:
 *     1. Continuous background SpeechRecognition listening for trigger words.
 *     2. Trigger-word callbacks dispatch to existing globals (wizHighlightNext,
 *        wizSave, etc.) — NEVER duplicate field-input transcription.
 *     3. Conflict-prevention: any per-field mic (wizMic / openVoice / wizMicAxis)
 *        suspends the continuous listener while it owns the microphone.
 *     4. voice_command_log INSERT for Tier 1 (cost_eur=0). Wired in Commit C.
 *
 * Triggers (Commit B):
 *   "следващ"/"напред" → wizHighlightNext()
 *   "назад"            → no-op (back-nav not in wizard yet)
 *   "запиши"/"готово"  → wizSave()
 *   "печатай"/"принт"  → no-op (mini-print overlay context — wired in Commit C)
 *   "пропусни"         → wizHighlightNext() (skip = advance)
 *   "изтрий"/"изчисти" → clear current .fg.wiz-active input
 *   "стоп"             → engine.pause()
 *   "слушай"           → engine.resume()
 */

(function () {
  'use strict';

  if (window.VoiceEngine) return; // idempotent

  var STATE = {
    IDLE: 'idle',
    LISTENING: 'listening',
    PAUSED: 'paused',
    SUSPENDED: 'suspended',
  };

  var TRIGGERS = {
    'следващ': 'next', 'напред': 'next',
    'назад': 'prev',
    'запиши': 'save', 'готово': 'save',
    'печатай': 'print', 'принт': 'print',
    'пропусни': 'skip',
    'изтрий': 'clear_field', 'изчисти': 'clear_field',
    'стоп': 'stop',
    'слушай': 'resume',
  };

  var DEFAULT_HANDLERS = {
    next: function () { if (typeof window.wizHighlightNext === 'function') window.wizHighlightNext(); },
    prev: function () { /* prev-field nav not implemented yet */ },
    save: function () { if (typeof window.wizSave === 'function') window.wizSave(); },
    print: function () { /* wired in Commit C with mini-print overlay context */ },
    skip: function () { if (typeof window.wizHighlightNext === 'function') window.wizHighlightNext(); },
    clear_field: function () {
      var fg = document.querySelector('.fg.wiz-active');
      if (!fg) return;
      var inp = fg.querySelector('input, select, textarea');
      if (inp) {
        inp.value = '';
        try { inp.dispatchEvent(new Event('input', { bubbles: true })); } catch (e) { /* legacy */ }
      }
    },
    stop: function () { if (window._voiceEngine) window._voiceEngine.pause(); },
    resume: function () { if (window._voiceEngine) window._voiceEngine.resume(); },
  };

  function _otherMicBusy() {
    if (document.querySelector('.wiz-mic.recording')) return true;
    var ov = document.getElementById('recOv');
    if (ov && ov.classList.contains('open')) return true;
    return false;
  }

  function VoiceEngine() {
    this.state = STATE.IDLE;
    this.recognition = null;
    this.callbacks = {};
    this.startedAt = null;
    this._lastFire = null;
    this._restartTimer = null;
  }

  VoiceEngine.prototype.start = function () {
    if (this.state === STATE.LISTENING) return;
    var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) {
      if (window.console) console.warn('[VoiceEngine] SpeechRecognition not supported');
      return;
    }
    if (_otherMicBusy()) {
      this.state = STATE.SUSPENDED;
      if (window.console) console.log('[VoiceEngine] start deferred — other mic busy');
      return;
    }
    this._initRecognition(SR);
    this.state = STATE.LISTENING;
    this.startedAt = Date.now();
    this._safeStart();
    if (window.console) console.log('[VoiceEngine] start (continuous trigger listener)');
  };

  VoiceEngine.prototype.stop = function () {
    if (this._restartTimer) { clearTimeout(this._restartTimer); this._restartTimer = null; }
    if (this.recognition) {
      try { this.recognition.abort(); } catch (e) { /* noop */ }
      this.recognition = null;
    }
    this.state = STATE.IDLE;
    this.startedAt = null;
    if (window.console) console.log('[VoiceEngine] stop');
  };

  VoiceEngine.prototype.pause = function () {
    if (this.state !== STATE.LISTENING) return;
    if (this.recognition) {
      try { this.recognition.abort(); } catch (e) { /* noop */ }
    }
    this.state = STATE.PAUSED;
    if (window.console) console.log('[VoiceEngine] paused');
  };

  VoiceEngine.prototype.resume = function () {
    if (this.state !== STATE.PAUSED && this.state !== STATE.SUSPENDED) return;
    var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) return;
    if (_otherMicBusy()) {
      this.state = STATE.SUSPENDED;
      return;
    }
    this._initRecognition(SR);
    this.state = STATE.LISTENING;
    this._safeStart();
    if (window.console) console.log('[VoiceEngine] resumed');
  };

  VoiceEngine.prototype.suspend = function () {
    if (this.state !== STATE.LISTENING) return;
    if (this.recognition) {
      try { this.recognition.abort(); } catch (e) { /* noop */ }
    }
    this.state = STATE.SUSPENDED;
  };

  VoiceEngine.prototype.on = function (action, fn) {
    if (typeof fn === 'function') this.callbacks[action] = fn;
  };

  VoiceEngine.prototype._matchTrigger = function (text) {
    if (!text) return null;
    var t = text.trim().toLowerCase();
    var keys = Object.keys(TRIGGERS);
    for (var i = 0; i < keys.length; i++) {
      if (t.indexOf(keys[i]) !== -1) return TRIGGERS[keys[i]];
    }
    return null;
  };

  VoiceEngine.prototype._dispatch = function (action, transcript) {
    var now = Date.now();
    if (this._lastFire && this._lastFire.action === action && now - this._lastFire.t < 1200) return;
    this._lastFire = { action: action, t: now };
    var fn = this.callbacks[action] || DEFAULT_HANDLERS[action];
    if (typeof fn === 'function') {
      try { fn(transcript); } catch (e) { if (window.console) console.error('[VoiceEngine] handler error', e); }
      if (window.console) console.log('[VoiceEngine] trigger:', action, '←', transcript);
    }
  };

  VoiceEngine.prototype._initRecognition = function (SR) {
    var self = this;
    var rec = new SR();
    rec.lang = 'bg-BG';
    rec.continuous = true;
    rec.interimResults = false;
    rec.onresult = function (e) {
      if (self.state !== STATE.LISTENING) return;
      for (var i = e.resultIndex; i < e.results.length; i++) {
        if (e.results[i].isFinal) {
          var transcript = e.results[i][0].transcript;
          var action = self._matchTrigger(transcript);
          if (action) self._dispatch(action, transcript);
        }
      }
    };
    rec.onend = function () {
      if (self.state === STATE.LISTENING) {
        self._restartTimer = setTimeout(function () { self._safeStart(); }, 250);
      }
    };
    rec.onerror = function (e) {
      var err = e && e.error;
      if (err === 'not-allowed' || err === 'service-not-allowed') {
        self.stop();
        if (window.console) console.warn('[VoiceEngine] mic permission denied');
        return;
      }
      if (self.state === STATE.LISTENING) {
        self._restartTimer = setTimeout(function () { self._safeStart(); }, 600);
      }
    };
    this.recognition = rec;
  };

  VoiceEngine.prototype._safeStart = function () {
    if (!this.recognition) return;
    try { this.recognition.start(); } catch (e) { /* already started */ }
  };

  // ─── Monkey-patch helpers ────────────────────────────────────────────
  function _watchUntilMicIdle() {
    var attempts = 0;
    var iv = setInterval(function () {
      attempts++;
      if (!_otherMicBusy() || attempts > 200) {
        clearInterval(iv);
        if (window._voiceEngine && window._voiceEngine.state === STATE.SUSPENDED) {
          window._voiceEngine.resume();
        }
      }
    }, 300);
  }

  function _wrapMicFn(name) {
    if (typeof window[name] !== 'function' || window[name].__veWrapped) return;
    var orig = window[name];
    window[name] = function () {
      if (window._voiceEngine) window._voiceEngine.suspend();
      var r;
      try { r = orig.apply(this, arguments); } finally { _watchUntilMicIdle(); }
      return r;
    };
    window[name].__veWrapped = true;
  }

  function _wrapOpenWizard(name) {
    if (typeof window[name] !== 'function' || window[name].__veWrapped) return;
    var orig = window[name];
    window[name] = function () {
      var r = orig.apply(this, arguments);
      if (window._voiceEngine) {
        setTimeout(function () { window._voiceEngine.start(); }, 800);
      }
      return r;
    };
    window[name].__veWrapped = true;
  }

  function _wrapCloseWizard() {
    if (typeof window.closeWizard !== 'function' || window.closeWizard.__veWrapped) return;
    var orig = window.closeWizard;
    window.closeWizard = function () {
      if (window._voiceEngine) window._voiceEngine.stop();
      return orig.apply(this, arguments);
    };
    window.closeWizard.__veWrapped = true;
  }

  function _initPatches() {
    ['wizMic', 'wizMicAxis', 'wizMicNewAxis', 'openVoice'].forEach(_wrapMicFn);
    ['openManualWizard', 'openVoiceWizard'].forEach(_wrapOpenWizard);
    _wrapCloseWizard();
    document.addEventListener('visibilitychange', function () {
      if (!window._voiceEngine) return;
      if (document.hidden) {
        if (window._voiceEngine.state === STATE.LISTENING) window._voiceEngine.pause();
      } else {
        if (window._voiceEngine.state === STATE.PAUSED) window._voiceEngine.resume();
      }
    });
  }

  VoiceEngine.STATE = STATE;
  VoiceEngine.TRIGGERS = TRIGGERS;
  window.VoiceEngine = VoiceEngine;
  window._voiceEngine = new VoiceEngine();

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', _initPatches);
  } else {
    _initPatches();
  }

  if (window.console) console.log('[VoiceEngine] loaded (S95.PART1_2.B — trigger listener active)');
})();
