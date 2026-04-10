<?php
/*
 * Include this file in the shared website footer or layout so the
 * floating Kwekwe Polytechnic AI widget appears on every page.
 *
 * Example:
 *   <?php
 *   $kwekweWidgetBaseUrl = 'https://chat.example.com';
 *   include __DIR__ . '/kwekwe-widget-include.php';
 *   ?>
 */

$kwekweWidgetBaseUrl = isset($kwekweWidgetBaseUrl)
    ? rtrim((string) $kwekweWidgetBaseUrl, '/')
    : 'https://kwekwepoly.ac.zw/kwekwe-poly-chatbot';
?>
<script
  src="<?= htmlspecialchars($kwekweWidgetBaseUrl, ENT_QUOTES) ?>/embed.js"
  data-api-url="<?= htmlspecialchars($kwekweWidgetBaseUrl, ENT_QUOTES) ?>"
  data-title="Kwekwe Poly AI"
  data-subtitle="Official Assistant"
  data-launcher-label="Chat with Kwekwe Poly"
  data-greeting="Hello! 👋 I'm your friendly AI assistant from Kwekwe Polytechnic. I'm here to help with any questions you might have about our programs, admissions, fees, or student life. What can I help you with today?"
  data-footer-text="Kwekwe Polytechnic Official AI Assistant  Powered by IT Unit &copy; 2025 Kwekwe Polytechnic"
  data-logo-url="<?= htmlspecialchars($kwekweWidgetBaseUrl, ENT_QUOTES) ?>/logo.png"
  data-kwekwe-widget
  defer
></script>
