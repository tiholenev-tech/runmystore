/**
 * voice-engine.js — S95.WIZARD.PART1_2 — Orchestrator over existing wizMic() flow.
 *
 * Закон №1: Пешо НЕ пише. Voice-first wizard.
 *
 * Architecture:
 *   - Existing wizMic(field) / _wizMicApply / _bgPrice / wizHighlightNext stay UNTOUCHED.
 *   - VoiceEngine = thin layer adding 4 capabilities:
 *       1. Continuous background SpeechRecognition for trigger words
 *          ("следващ", "напред", "назад", "запиши", "готово", "печатай",
 *           "пропусни", "стоп", "слушай")
 *       2. Trigger-word callbacks invoke existing flow (wizHighlightNext, ZAPISHI click, etc.)
 *       3. Conflict prevention: pause continuous listener while wizMic is recording
 *       4. voice_command_log INSERT for Tier 1 (Web Speech) commands. cost_eur = 0.
 *
 * Skeleton in Commit A: class definition + state, no SpeechRecognition wiring.
 * Continuous trigger listener wired in Commit B.
 * voice_command_log + Брой/Min mic wired in Commit C.
 */

(function () {
  'use strict';

  if (window.VoiceEngine) return; // idempotent

  var STATE = {
    IDLE: 'idle',
    LISTENING: 'listening',
    PAUSED: 'paused',
    SUSPENDED: 'suspended', // wizMic active — temporarily yield mic
  };

  // Trigger words → action keys. Action implementations land in Commit B.
  var TRIGGERS = {
    'следващ': 'next',
    'напред': 'next',
    'назад': 'prev',
    'запиши': 'save',
    'готово': 'save',
    'печатай': 'print',
    'принт': 'print',
    'пропусни': 'skip',
    'изтрий': 'clear_field',
    'изчисти': 'clear_field',
    'стоп': 'stop',
    'слушай': 'resume',
  };

  function VoiceEngine() {
    this.state = STATE.IDLE;
    this.recognition = null;       // background SpeechRecognition (Commit B)
    this.callbacks = {};           // action key → fn
    this.lastTranscript = '';
    this.startedAt = null;
  }

  VoiceEngine.prototype.start = function () {
    if (this.state === STATE.LISTENING) return;
    this.state = STATE.LISTENING;
    this.startedAt = Date.now();
    if (window.console && console.log) console.log('[VoiceEngine] start (skeleton — no listener wired yet)');
    // Commit B: instantiate continuous SpeechRecognition here
  };

  VoiceEngine.prototype.stop = function () {
    if (this.state === STATE.IDLE) return;
    if (this.recognition) {
      try { this.recognition.abort(); } catch (e) { /* noop */ }
      this.recognition = null;
    }
    this.state = STATE.IDLE;
    this.startedAt = null;
    if (window.console && console.log) console.log('[VoiceEngine] stop');
  };

  VoiceEngine.prototype.pause = function () {
    if (this.state !== STATE.LISTENING) return;
    if (this.recognition) {
      try { this.recognition.abort(); } catch (e) { /* noop */ }
    }
    this.state = STATE.PAUSED;
  };

  VoiceEngine.prototype.resume = function () {
    if (this.state !== STATE.PAUSED && this.state !== STATE.SUSPENDED) return;
    this.state = STATE.LISTENING;
    // Commit B: re-create recognition instance and start
  };

  // Suspend temporarily while wizMic is recording (avoid duplicate transcripts).
  VoiceEngine.prototype.suspend = function () {
    if (this.state === STATE.LISTENING) {
      if (this.recognition) { try { this.recognition.abort(); } catch (e) { /* noop */ } }
      this.state = STATE.SUSPENDED;
    }
  };

  VoiceEngine.prototype.on = function (action, fn) {
    if (typeof fn === 'function') this.callbacks[action] = fn;
  };

  // Test helper — Commit B replaces this with real onresult parsing.
  VoiceEngine.prototype._matchTrigger = function (text) {
    if (!text) return null;
    var t = text.trim().toLowerCase();
    var keys = Object.keys(TRIGGERS);
    for (var i = 0; i < keys.length; i++) {
      if (t.indexOf(keys[i]) !== -1) return TRIGGERS[keys[i]];
    }
    return null;
  };

  VoiceEngine.STATE = STATE;
  VoiceEngine.TRIGGERS = TRIGGERS;

  window.VoiceEngine = VoiceEngine;
  window._voiceEngine = new VoiceEngine();

  if (window.console && console.log) console.log('[VoiceEngine] loaded (skeleton, S95.PART1_2.A)');
})();
