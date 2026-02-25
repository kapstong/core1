<?php
if (defined('PPC_SHOP_CHATBOT_INCLUDED')) {
    return;
}
define('PPC_SHOP_CHATBOT_INCLUDED', true);

$chatbotCssPath = __DIR__ . '/../assets/css/shop-chatbot.css';
$chatbotJsPath = __DIR__ . '/../assets/js/shop-chatbot.js';
$chatbotCssVersion = file_exists($chatbotCssPath) ? (string)filemtime($chatbotCssPath) : '1';
$chatbotJsVersion = file_exists($chatbotJsPath) ? (string)filemtime($chatbotJsPath) : '1';
?>
<link rel="stylesheet" href="assets/css/shop-chatbot.css?v=<?php echo rawurlencode($chatbotCssVersion); ?>">
<script src="assets/js/shop-chatbot.js?v=<?php echo rawurlencode($chatbotJsVersion); ?>" defer></script>
