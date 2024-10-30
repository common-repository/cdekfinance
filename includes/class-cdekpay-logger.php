<?php


class Cdekpay_Logger
{
    const CDEKPAY_MESSAGE_TYPE = 3;

    const CDEKPAY_LEVEL_INFO = 'info';
    const CDEKPAY_LEVEL_WARNING = 'warning';
    const CDEKPAY_LEVEL_ERROR = 'error';

    public static function info($message)
    {
        self::log(self::CDEKPAY_LEVEL_INFO, $message);
    }

    public static function error($message)
    {
        self::log(self::CDEKPAY_LEVEL_ERROR, $message);
    }

    public static function warning($message)
    {
        self::log(self::CDEKPAY_LEVEL_ERROR, $message);
    }

    public static function log($level, $message)
    {
        $filePath = WP_CONTENT_DIR.'/cdekpay-debug.log';
        $isDebugEnabled = get_option('cdekpay_debug_enabled');
        $isDebugEnabled = true;
        if ($isDebugEnabled) {
            if ( ! file_exists($filePath)) {
                global $wp_filesystem;
	            if(!$wp_filesystem){
		            require_once(ABSPATH . 'wp-admin/includes/file.php');
		            WP_Filesystem();
	            }
                $wp_filesystem->touch($filePath);
                $wp_filesystem->chmod($filePath, 0644);
            }

            $messageFormatted = self::formatMessage($level, $message)."\n";
            error_log($messageFormatted, self::CDEKPAY_MESSAGE_TYPE, $filePath);
        }
    }

    private static function formatMessage($level, $message)
    {
        $date = gmdate('Y-m-d H:i:s');

        return sprintf("[%s] [%s] %s \r\n", $date, $level, $message);
    }
}