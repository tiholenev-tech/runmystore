<?php
$ai_placeholder = $ai_placeholder ?? 'Попитай AI';
?>
<style>
.ai-float-wrap{position:fixed;bottom:calc(var(--nav,56px) + 10px);left:50%;transform:translateX(-50%);z-index:90;pointer-events:none}
.ai-float-btn{pointer-events:auto;display:flex;align-items:center;gap:10px;padding:10px 22px;border-radius:20px;background:rgba(15,15,40,.85);border:1px solid rgba(99,102,241,.25);cursor:pointer;backdrop-filter:blur(14px);transition:all .25s;box-shadow:0 4px 20px rgba(99,102,241,.15);font-family:inherit}
.ai-float-btn:active{transform:scale(.96)}
.ai-float-btn span{font-size:13px;font-weight:600;color:#a5b4fc}
.ai-float-waves{display:flex;align-items:flex-end;gap:2px;height:16px}
.ai-float-bar{width:3px;border-radius:2px;background:currentColor;animation:aifwave 1s ease-in-out infinite}
@keyframes aifwave{0%,100%{transform:scaleY(.35)}50%{transform:scaleY(1)}}
</style>
<div class="ai-float-wrap">
  <button class="ai-float-btn" onclick="openChat()">
    <div class="ai-float-waves">
      <div class="ai-float-bar" style="color:#6366f1;height:16px;animation-delay:0s"></div>
      <div class="ai-float-bar" style="color:#818cf8;height:16px;animation-delay:.15s"></div>
      <div class="ai-float-bar" style="color:#a5b4fc;height:16px;animation-delay:.3s"></div>
      <div class="ai-float-bar" style="color:#818cf8;height:16px;animation-delay:.45s"></div>
      <div class="ai-float-bar" style="color:#6366f1;height:16px;animation-delay:.6s"></div>
    </div>
    <span><?= htmlspecialchars($ai_placeholder) ?></span>
  </button>
</div>
