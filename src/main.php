<?php
ob_start();
//require our neccessary Class Store house
require 'daemonScrap.php';
//start class of BM121 mailer
class Main
{
    
    //defining our construtor
    public function __construct()
    {
        if (php_sapi_name() == 'cli') {
            $args = $_SERVER['argv'];
        } else {
            parse_str($_SERVER['QUERY_STRING'], $args);
        }

        $check_pthread = class_exists('Thread');
        $check_phpCompatibilty = PHP_ZTS;

        // we will be using commandline php for testing purpose
        
        
        if($check_pthread == $check_phpCompatibilty){ 
                    (isset($args[1])) ?
                     $scrap = new DaemonScrap($args[1]) : $scrap = new DaemonScrap;
                        $scrap->start() && $scrap->join();
                
            }else{
                echo "Your PHP version doesnt support pThreads";
            }
    }
}
return new Main;
