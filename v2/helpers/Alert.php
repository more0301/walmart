<?php

declare(strict_types=1);

namespace WB\Helpers;

class Alert
{
    public static function sendTelegram(string $message)
    {
        $token = '850802223:AAH-4Hd1HFK4f9NsRYhkPvq4RtJzrzzILCU';
        //        $chat_id = '418590705';
        $chat_id
            = '-1001352719673'; // WM alerts, https://t.me/joinchat/GPMv8VCg3TmGtmJMtNr4qA

        $ch = curl_init();
        curl_setopt(
            $ch,
            CURLOPT_URL,
            'https://api.telegram.org/bot' . $token . '/sendMessage'
        );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            'chat_id=' . $chat_id . '&text=' . urlencode($message)
        );
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        curl_exec($ch);
        curl_close($ch);
    }

    public static function sendMail(
        string $to,
        string $subject,
        string $message
    ) {
        try {
            $m = mail($to, $subject, $message);
            if (true === $m) {
                Logger::log(
                    'Send mail. To: ' . $to . ' Subject: ' .
                    $subject . ' Message: ' . $message,
                    __METHOD__,
                    'info'
                );
            }
        } catch (\Throwable $e) {
            Logger::log($e->getMessage(), __METHOD__, 'error');
        }
    }
}
