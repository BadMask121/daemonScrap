<?php

 class DaemonScrap extends Thread
 {

     //file path for logging our lastMessageId
    private $filename = __DIR__ . "/con.dae";


    /**
     * @var our config database
     */
    private $config = [
        "host" => "mail.example.com",
        "port" => "800",
        "username"  => "team",
        "password"  => "password",
        "from" => 'mailer-daemon@example.com',
        "endpoint" => "http://10.0.0.103:2000/"
    ];

    
    private $info = [
        '-h' => ' For help',
        '-r' => ' Send Recent data from inbox',
        '-a' => ' Send all data from inbox',
        '-rl' => ' Send data from last Sent Message Id'
    ];

    //set timeout
    private $timeout = 1;


    //our startingpoint
    private $startPosStr =  "The mail system";


    //connection variable
    private $conn = null;

    //curl init
    private $curl = null;


    
    private $data = array();

    private $limit = 1;


    public function __construct($limit = 1){

        $host = '{' . $this->config['host'] . ':' . $this->config['port'] . '/imap/novalidate-cert' . '}INBOX';

        ($this->conn == null) ?
            $this->conn 
                = imap_open($host, $this->config['username'], $this->config['password'])
                    or die(imap_errors()) : null;

        imap_timeout(IMAP_OPENTIMEOUT, 5);

        $this->curl = curl_init();


        curl_setopt($this->curl , CURLOPT_URL , $this->config['endpoint']);
        curl_setopt($this->curl , CURLOPT_POST , 1);
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));

        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);

        $this->limit = $limit;
    }

      public function run(){

        $this->synchronized($this->scrap(),
            new stdClass());
      }
    
    /**
     * @method scrap
     * initiates scrapping for data
     * 
     * the logic behind this function will require you to run the script as soon
     * get a new message from daemon mailer
     */
    public function scrap(){

        $inbox =  imap_search($this->conn , 'FROM "'.$this->config['from'].'"');
        $recentInboxId = end($inbox); //gets recent id from inbox

        switch ($this->limit) {
            case '-a':

                     /**
                     * @method firstAllMessage
                     *
                     * if we dont have a last Message Id then we send all our inbox scrapped data
                     * to endpoint note: this will only be done once so as to increase efficiency
                     *
                     */
                    $this->sendAll($inbox);
                break;
            case '-r':


                    /**
                     *  @method send Recent Message
                     *
                     * Logic: if we have a last message then get the get info of the recent id
                     */
                    $this->sendRecent($inbox , $recentInboxId);    
                break;

            case is_numeric($this->limit):
                    $this->limit = $this->limit;
                    $this->sendAll($inbox);

                break;


            case '-h':
                    $this->print_help();
                break;

            case '-rl':

                    /**
                     * send data of all inbox after last Sent Message id
                     */
                    ($this->getLastMessageId() != -1) ?
                    $this->limit = $this->getLastMessageId() : $this->limit = 1;

                    $this->sendAll($inbox);
                break;

            default:
                    $this->print_help();
                break;
        }

        exit();
    }



    /**
     * @method send_data 
     *  sends data to remote endpoint
     */
    private function send_data($payload){

        if($this->curl == null)
            return false;

            

        $data = json_encode(
            [
                $payload
            ]
        );

        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $data);

        $result = curl_exec($this->curl) or die(curl_error($this->curl));

        $res = json_decode($result);
        if($res->code == 'CS200') 
            return true;

        return false;
    }

    /**
     * @method getInfo
     * 
     * scraps email and description from inbox
     */
    private function getInfo($id){

        $message = [];
        if($this->conn == null)
            return [];

        $body         = imap_body($this->conn , $id );

        $filterNeeded = substr(
            $body ,
            strripos($body , $this->startPosStr) ,
            strripos($body , "--")
        ); //will return strings after my starting points

        $filterNeededEnding =  substr(
            $filterNeeded ,
            stripos($filterNeeded , $filterNeeded) ,
            stripos($filterNeeded , '--')
        ); //will return string from the start position to the ending of --
        
        $body   = substr(
            $filterNeededEnding , 
            stripos($filterNeededEnding , ":") + 1 , 
            strlen($filterNeededEnding)
        ); //return body after the email
        
        preg_match('/[\._a-zA-Z0-9-]+@[\._a-zA-Z0-9-]+/i', 
            $filterNeededEnding, 
            $matches); //get Emails
        
            
            if(empty($matches)){                
                $message  = [
                    "email"     => $this->config['from'],
                    "description"   => trim($body)
                ];

            }else{
                $email  = $matches[0]; // return email from scrapped filters
                $message  = [
                    "email"     => $email,
                    "description"   => trim($body)
                ];
            }

        $this->data[] = $message;
        return $this->data;        
    }


    private function sendAll($inbox){

        $inboxlLength = sizeof($inbox) - 1;
        $counter_limit = 50;
        $counter = 0;

        echo "Started transportation ..." . "\n";

        for ($i = $this->limit; $i < $inboxlLength; $i += 2) {
        
            $r = $i;

            if ($r >= 1) {
                $r--;
            }

            $this->getInfo($inbox[$r]);
            $this->getInfo($inbox[$i]);

            if ($counter >= $counter_limit) {
                $counter = 0;

                $res = $this->send_data($this->data);
                if ($res != true) {
                    continue;
                }

                $this->setLastMessageId($i);
                echo "Data Sent to Server (IDs):" . $counter . "-" . $i . "\n";
            }

            if ($i >= $inboxlLength) {
                exit();
            }

            $counter += $i - $r;
        }

        echo "Daemon Transportation Ended" . "\n";

        $this->close();
    }

    private function sendRecent($inbox , $recentInboxId){

           if (($this->getLastMessageId() != -1) && ($this->getLastMessageId() <= $recentInboxId)) {

                if (!$this->send_data($this->getInfo($recentInboxId))) //send data scrapped to endpoint)
                {
                    throw new Exception("Data not sent to remote server");
                }

                echo "Data Sent to Recent inbox ID:" . $recentInboxId . "\n";
                $this->setLastMessageId(array_search($recentInboxId , $inbox));
                $this->close();
                exit();
        }

    }



    /**
     * @method setLastMessageId
     * 
     * save last Scrap Message idsetLastMessageId
     */
    private function setLastMessageId($id){
        $file = null;

        $file = @fopen($this->filename, 'w');
        @fwrite($file, $id);
        fclose($file);
    }


    //will return saved message id
    public function getLastMessageId() : int{

        if(!file_exists($this->filename))
                return -1;

        $file = fopen($this->filename ,  'r');

            if(filesize($this->filename) < 1)
                return -1;

        $id = fread($file , filesize($this->filename));

        fclose($file);
        return  ($file) ? (int) $id : -1;
    }

    private function print_help(){
        echo "Please follow the instructions below \n";
        foreach ($this->info as $key => $value) {
            echo $key . " => " . $value . "\n";
        }
    }

    //close curl
    private function close(){
        imap_close($this->conn);
        curl_close($this->curl);
    }

 }
 
?>