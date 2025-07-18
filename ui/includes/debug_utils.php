<?php
// /app.axialy.ai/includes/debug_utils.php
if (!function_exists('debugLog')) {
    function debugLog($message, $data = null) {
        $logMessage = date('Y-m-d H:i:s') . " - " . $message;
        if ($data !== null) {
            $logMessage .= " - Data: " . print_r($data, true);
        }
        error_log($logMessage);
    }
}
?>
