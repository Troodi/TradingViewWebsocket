<?php
require dirname(__FILE__).'/vendor/autoload.php';

class TradingViewWebsocket {
  private $session;
  private $subscriptions;
  private $websocket;
  public $tickerData;
  private $sessionRegistered;
  private $class;
  private $login;
  private $password;

  public function __construct($class, $login = null, $password = null)
  {
      $this->class = $class;
	    $this->login = $login;
      $this->password = $password;
      $this->resetWebSocket();
  }

  private function generateSession() {
    return "qs_".$this->generateRandomString(12);
  }

  function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
      $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
  }

  private function sendMessage($func, $args){
    $this->websocket->send($this->createMessage($func, $args));
  }

  public function registerTicker($ticker){
    if(in_array($ticker, $this->subscriptions)) {
      return;
    }
    $this->subscriptions[] = $ticker;
    $this->websocket->send($this->createMessage("quote_add_symbols", [$this->session, $ticker, ['flags' => ["force_permission"]]]));
  }

  public function unregisterTicker($ticker){
    $index = array_search($ticker, $this->subscriptions);
    if($index === false){
      return;
    }
    unset($this->subscriptions[$index]);
    sort($this->subscriptions);
  }

  private function resetWebSocket(){
    $this->tickerData = [];
    $this->subscriptions = [];
    $this->session = $this->generateSession();
    $this->sessionRegistered = false;
    if($this->login and $this->password){
      $wss = "wss://prodata.tradingview.com/socket.io/websocket";
    } else {
      $wss = "wss://data.tradingview.com/socket.io/websocket";
    }
    $this->websocket = new WebSocket\Client($wss, [
      'timeout' => 60, // 1 minute time out
      'headers' => [
        'Origin' => 'https://data.tradingview.com',
      ],
    ]);
    while (true) {
      try {
        $string = $this->websocket->receive();
        $packets = $this->parseMessages($string);
        foreach($packets as $packet){
          if(is_array($packet) and $packet["~protocol~keepalive~"]){
            $this->sendRawMessage("~h~".$packet["~protocol~keepalive~"]);
          } elseif($packet->session_id) {
            if($this->login and $this->password) {
              $request_headers = [
                "accept: */*",
                "accept-encoding: gzip, deflate, br",
                "accept-language: ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7,lt;q=0.6",
                "cache-control: no-cache",
                "content-type: application/x-www-form-urlencoded",
                "origin: https://www.tradingview.com",
                "pragma: no-cache",
                "referer: no-cache",
                "sec-fetch-dest: empty",
                "sec-fetch-mode: cors",
                "sec-fetch-site: same-origin",
                "user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.125 Safari/537.36",
                "x-language: en",
                "x-requested-with: XMLHttpRequest"
              ];
              $ch = curl_init();
              curl_setopt($ch, CURLOPT_POST, true);
              curl_setopt($ch, CURLOPT_URL, "https://www.tradingview.com/accounts/signin/");
              curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
              curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
              curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . '/cookie.txt');
              curl_setopt($ch, CURLOPT_COOKIEJAR, __DIR__ . '/cookie.txt');
              curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
              curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
              curl_setopt($ch,CURLOPT_ENCODING , "gzip");
              curl_setopt($ch, CURLOPT_POSTFIELDS, "feature_source=Header&username=$this->login&password=$this->password&remember=on");
              $curl_exec = curl_exec($ch);
              curl_close($ch);
              $json = json_decode($curl_exec);
              $auth_token = $json->user->auth_token;
              $this->sendMessage("set_auth_token", [$auth_token]);
            } else {
              $this->sendMessage("set_auth_token", ["unauthorized_user_token"]);
            }
            $this->sendMessage("quote_create_session", [$this->session]);
            $this->sendMessage("quote_set_fields", [
              $this->session,
              "ch",
              "chp",
              "current_session",
              "description",
              "local_description",
              "language",
              "exchange",
              "fractional",
              "is_tradable",
              "lp",
              "minmov",
              "minmove2",
              "original_name",
              "pricescale",
              "pro_name",
              "short_name",
              "type",
              "update_mode",
              "volume",
              "ask",
              "bid",
              "fundamentals",
              "high_price",
              "is_tradable",
              "low_price",
              "open_price",
              "prev_close_price",
              "rch",
              "rchp",
              "rtc",
              "status",
              "basic_eps_net_income",
              "beta_1_year",
              "earnings_per_share_basic_ttm",
              "industry",
              "market_cap_basic",
              "price_earnings_ttm",
              "sector",
              "volume",
              "dividends_yield"
            ]);
            $this->sessionRegistered = true;
          } elseif ($packet->m && $packet->m === "qsd" && isset($packet->p) && $packet->p[0] === $this->session) {
            $tticker = $packet->p[1];
            $tickerName = $tticker->n;
            $tickerStatus = $tticker->s;
            $tickerUpdate = $tticker->v;
            foreach ($tickerUpdate as $key => $value)
              $this->tickerData[$tickerName][$key] = $value;
            }
          }
          call_user_func(array($this->class, 'readQuotes'), $this);
      } catch (\WebSocket\ConnectionException $e) {

      }
    }
  }

  private function sendRawMessage($message){
    $this->websocket->send($this->prependHeader($message));
  }

  // IO methods
  private function parseMessages($str){
    $packets = [];
    while(strlen($str) > 0){
      preg_match('/~m~(\d+)~m~/', $str, $x);
      $packet = $this->str_slice($str, strlen($x[0]), strlen($x[0])+$x[1]);
      if(substr($packet, 0, 3) != "~h~"){
        $packets[] = json_decode($packet);
      } else {
        $packets[] = ["~protocol~keepalive~" => substr($packet, 3)];
      }
      $str = $this->str_slice($str, strlen($x[0])+$x[1]);
    }
    return $packets;
  }

  private function prependHeader($str){
    return "~m~".strlen($str)."~m~".$str;
  }

  private function createMessage($func, $paramList){
    return $this->prependHeader($this->constructMessage($func, $paramList));
  }

  private function constructMessage($func, $paramList){
    return json_encode([
      'm' => $func,
      'p' => $paramList
    ]);
  }

  function str_slice() {
    $args = func_get_args();
    switch (count($args)) {
      case 1:
        return $args[0];
      case 2:
        $str = $args[0];
        $str_length = strlen($str);
        $start = $args[1];
        if ($start < 0) {
          if ($start >= -$str_length) {
            $start = $str_length - abs($start);
          } else {
            $start = 0;
          }
        }
        else if ($start >= $str_length) {
          $start = $str_length;
        }
        $length = $str_length - $start;
        return substr($str, $start, $length);
      case 3:
        $str = $args[0];
        $str_length = strlen($str);
        $start = $args[1];
        $end = $args[2];
        if ($start >= $str_length) {
          return "";
        }
        if ($start < 0) {
          if ($start < -$str_length) {
            $start = 0;
          } else {
            $start = $str_length - abs($start);
          }
        }
        if ($end <= $start) {
          return "";
        }
        if ($end > $str_length) {
          $end = $str_length;
        }
        $length = $end - $start;
        return substr($str, $start, $length);
    }
    return null;
  }
}
