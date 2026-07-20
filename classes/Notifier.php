<?php

/**
 * Notification hub — SMS / WhatsApp / in-app.
 *
 * Every message is logged to notification_log. Actual delivery happens
 * through a pluggable gateway: until an SMS provider is configured in
 * settings (sms_gateway_url + sms_api_key), messages stay 'queued'.
 * Swapping in a real gateway later means editing ONLY sendViaGateway().
 */
class Notifier
{
    public static function send(
        string $recipient, string $message,
        string $channel = 'sms', ?string $refTable = null, ?int $refId = null
    ): bool {
        $recipient = trim($recipient);
        if ($recipient === '' || trim($message) === '') return false;

        $id = (int)Database::insert(
            'INSERT INTO notification_log (channel, recipient, message, status, ref_table, ref_id)
             VALUES (?, ?, ?, "queued", ?, ?)',
            [$channel, $recipient, $message, $refTable, $refId]
        );

        $sent = self::sendViaGateway($recipient, $message, $channel);
        if ($sent !== null) {
            Database::execute(
                'UPDATE notification_log SET status = ? WHERE id = ?',
                [$sent ? 'sent' : 'failed', $id]
            );
        }
        return true;
    }

    /**
     * Returns true/false when a gateway is configured, null when not
     * (message stays queued for later dispatch).
     */
    private static function sendViaGateway(string $recipient, string $message, string $channel): ?bool
    {
        $url = Setting::get('sms_gateway_url', '');
        $key = Setting::get('sms_api_key', '');
        if ($url === '' || $key === '' || $channel !== 'sms') {
            return null; // no gateway configured yet
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_POSTFIELDS     => http_build_query([
                'api_key' => $key,
                'number'  => $recipient,
                'message' => $message,
            ]),
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $response !== false && $httpCode >= 200 && $httpCode < 300;
    }
}
