<?php
namespace obray\core;

use Psr\Log\LogLevel;

class Helpers
{
    const COLORS = [
        // text color
        "Black" =>              "\033[30m",
        "Red" =>                "\033[31m",
        "Green" =>              "\033[32m",
        "Yellow" =>             "\033[33m",
        "Blue" =>               "\033[34m",
        "Purple" =>             "\033[35m",
        "Cyan" =>               "\033[36m",
        "White" =>              "\033[37m",
        // text color bold
        "BlackBold" =>          "\033[30m",
        "RedBold" =>            "\033[1;31m",
        "GreenBold" =>          "\033[1;32m",
        "YellowBold" =>         "\033[1;33m",
        "BlueBold" =>           "\033[1;34m",
        "PurpleBold" =>         "\033[1;35m",
        "CyanBold" =>           "\033[1;36m",
        "WhiteBold" =>          "\033[1;37m",
        // text color muted
        "RedMuted" =>           "\033[2;31m",
        "GreenMuted" =>         "\033[2;32m",
        "YellowMuted" =>        "\033[2;33m",
        "BlueMuted" =>          "\033[2;34m",
        "PurpleMuted" =>        "\033[2;35m",
        "CyanMuted" =>          "\033[2;36m",
        "WhiteMuted" =>         "\033[2;37m",
        // text color underlined
        "BlackUnderline" =>     "\033[4;30m",
        "RedUnderline" =>       "\033[4;31m",
        "GreenUnderline" =>     "\033[4;32m",
        "YellowUnderline" =>    "\033[4;33m",
        "BlueUnderline" =>      "\033[4;34m",
        "PurpleUnderline" =>    "\033[4;35m",
        "CyanUnderline" =>      "\033[4;36m",
        "WhiteUnderline" =>     "\033[4;37m",
        // text color background
        "RedBackground" =>      "\033[7;31m",
        "GreenBackground" =>    "\033[7;32m",
        "YellowBackground" =>   "\033[7;33m",
        "BlueBackground" =>     "\033[7;34m",
        "PurpleBackground" =>   "\033[7;35m",
        "CyanBackground" =>     "\033[7;36m",
        "WhiteBackground" =>    "\033[7;37m",
        // reset - auto called after each of the above by default
        "Reset"=>               "\033[0m"
    ];

    static public function console(){

        $args = func_get_args();
        if( PHP_SAPI !== 'cli' && empty($args) ) return;

        if( is_array($args[0]) || is_object($args[0]) ) {
            print_r($args[0]);
        } else if( count($args) === 3 && $args[1] !== NULL && $args[2] !== NULL ){
            $color = self::COLORS[$args[2]];
            printf($color.array_shift($args)."\033[0m",array_shift($args) );
        } else {
            printf( array_shift($args),array_shift($args) );
        }

    }

    static public function getColor($level)
    {
        switch($level){
            case LogLevel::EMERGENCY:
                return "RedBold";
                break;
            case LogLevel::ALERT:
                return "RedBackground";
                break;
            case LogLevel::CRITICAL:
                return "Red";
                break;
            case LogLevel::ERROR:
                return "Red";
                break;
            case LogLevel::WARNING:
                return "Yellow";
                break;
            case LogLevel::NOTICE:
                return "Purple";
                break;
            case LogLevel::INFO:
                return "Blue";
                break;
            case LogLevel::DEBUG:
                return "White";
                break;
        }
        return "";
    }
}