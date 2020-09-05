<?php


namespace tasker;

class Console{
    /**
     * 输出头部信息
     **/
    public static function header(){
        $text=self::logo().PHP_EOL;
        $text.= "----------------------tasker----------------------".PHP_EOL;
        $text.= 'tasker version:' . Tasker::VERSION . "      PHP version:".PHP_VERSION.PHP_EOL;
        self::display($text,false);
    }

    /**
     * 输出指定信息
     * @param string $text 内容
     * @param bool $isClose 输出后是否退出
     */
    public static function display($text,$isClose=true){
        echo $text.PHP_EOL;
        $isClose==true && die;
    }
    public static function log($msg,$isClose=false){
        $text=date('[Y-m-d H:i:s]').$msg;
        self::display($text,$isClose);
    }
    public static function color($text, $status)
    {
        switch ($status) {
            case "SUCCESS":
            case "Green":
                $out = "[32m"; //Green
                break;
            case "FAILURE":
            case "Red":
                $out = "[31m"; //Red
                break;
            case "WARNING":
            case "Yellow":
                $out = "[33m"; //Yellow
                break;
            case "NOTE":
            case "Blue":
                $out = "[34m"; //Blue
                break;
            default:
                return $text;
        }
        return chr(27) . "$out" . "$text" . chr(27) . "[0m";
    }
    private static function logo(){
        return <<<logo

████████╗ █████╗ ███████╗██╗  ██╗███████╗██████╗ 
╚══██╔══╝██╔══██╗██╔════╝██║ ██╔╝██╔════╝██╔══██╗
   ██║   ███████║███████╗█████╔╝ █████╗  ██████╔╝
   ██║   ██╔══██║╚════██║██╔═██╗ ██╔══╝  ██╔══██╗
   ██║   ██║  ██║███████║██║  ██╗███████╗██║  ██║
   ╚═╝   ╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝                                              
logo;

    }
}